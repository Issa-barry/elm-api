<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingEventStatus;
use App\Enums\UserType;
use App\Models\Organisation;
use App\Models\OrganisationBillingEvent;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests Feature — Facturation par organisation.
 *
 * Couverture :
 *  - Création user => event billing créé automatiquement
 *  - Idempotence : pas de doublon event pour même user_created
 *  - Marquage paid fonctionne
 *  - Marquage paid sur event déjà paid => 422
 *  - Marquage paid sur event cancelled => 422
 *  - Listing events avec filtre organisation_id
 *  - Listing events avec filtre status
 *  - Accès interdit aux non super_admin
 */
class BillingEventTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $adminEntreprise;
    private Organisation $organisation;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->organisation = Organisation::factory()->create(['nom' => 'Org Billing Test', 'code' => 'BLG-001']);

        $this->site = Site::factory()->create(['code' => 'BLG-SITE', 'nom' => 'Site Billing Test']);

        $this->superAdmin = User::factory()->create([
            'type'            => UserType::STAFF->value,
            'organisation_id' => $this->organisation->id,
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->adminEntreprise = User::factory()->create([
            'type'            => UserType::STAFF->value,
            'organisation_id' => $this->organisation->id,
        ]);
        $this->adminEntreprise->assignRole('admin_entreprise');
        $this->adminEntreprise->sites()->attach($this->site->id, ['role' => 'owner_siege', 'is_default' => true]);
        $this->adminEntreprise->update(['default_site_id' => $this->site->id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function validUserPayload(array $overrides = []): array
    {
        return array_merge([
            'nom'             => 'DUPONT',
            'prenom'          => 'Jean',
            'phone'           => '+22462000' . rand(1000, 9999),
            'type'            => 'staff',
            'role'            => 'employe',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaloum',
            'password'        => 'secret1234',
            'organisation_id' => $this->organisation->id,
        ], $overrides);
    }

    // ── Création user => event créé ───────────────────────────────────────

    public function test_creating_user_generates_billing_event(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/users', $this->validUserPayload())
            ->assertCreated();

        $this->assertDatabaseCount('organisation_billing_events', 1);

        $event = OrganisationBillingEvent::first();
        $this->assertEquals('user_created', $event->event_type);
        $this->assertEquals($this->organisation->id, $event->organisation_id);
        $this->assertEquals(BillingEventStatus::PENDING, $event->status);
        $this->assertEquals(1, $event->quantity);
    }

    public function test_billing_event_amount_equals_unit_price_times_quantity(): void
    {
        config(['billing.user_account_price' => 5000]);

        $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/users', $this->validUserPayload())
            ->assertCreated();

        $event = OrganisationBillingEvent::first();
        $this->assertEquals('5000.00', $event->amount);
        $this->assertEquals('5000.00', $event->unit_price);
    }

    public function test_no_billing_event_created_when_user_has_no_organisation(): void
    {
        // Créer un user sans organisation_id (super_admin sans org ne devrait pas générer d'event)
        $adminNoOrg = User::factory()->create([
            'type'            => UserType::STAFF->value,
            'organisation_id' => null,
        ]);
        $adminNoOrg->assignRole('super_admin');

        $payload = $this->validUserPayload();
        unset($payload['organisation_id']);

        $this->actingAs($adminNoOrg)
            ->postJson('/api/v1/users', $payload)
            ->assertCreated();

        $this->assertDatabaseCount('organisation_billing_events', 0);
    }

    // ── Idempotence — pas de doublon ──────────────────────────────────────

    public function test_unique_constraint_prevents_duplicate_billing_event(): void
    {
        $user = User::factory()->create(['organisation_id' => $this->organisation->id]);

        // Créer manuellement un premier event
        OrganisationBillingEvent::create([
            'organisation_id' => $this->organisation->id,
            'user_id'         => $user->id,
            'event_type'      => 'user_created',
            'unit_price'      => 0,
            'quantity'        => 1,
            'amount'          => 0,
            'status'          => BillingEventStatus::PENDING,
            'occurred_at'     => now(),
        ]);

        // firstOrCreate avec même (event_type, user_id) ne doit pas créer de doublon
        OrganisationBillingEvent::firstOrCreate(
            ['event_type' => 'user_created', 'user_id' => $user->id],
            [
                'organisation_id' => $this->organisation->id,
                'unit_price'      => 0,
                'quantity'        => 1,
                'amount'          => 0,
                'status'          => BillingEventStatus::PENDING,
                'occurred_at'     => now(),
            ]
        );

        $this->assertDatabaseCount('organisation_billing_events', 1);
    }

    // ── Marquage paid ─────────────────────────────────────────────────────

    public function test_super_admin_can_mark_event_as_paid(): void
    {
        $user = User::factory()->create(['organisation_id' => $this->organisation->id]);
        $event = OrganisationBillingEvent::create([
            'organisation_id' => $this->organisation->id,
            'user_id'         => $user->id,
            'event_type'      => 'user_created',
            'unit_price'      => 1000,
            'quantity'        => 1,
            'amount'          => 1000,
            'status'          => BillingEventStatus::PENDING,
            'occurred_at'     => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patchJson("/api/v1/billing/events/{$event->id}/paid");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid');

        $this->assertEquals(BillingEventStatus::PAID, $event->fresh()->status);
    }

    public function test_marking_already_paid_event_returns_422(): void
    {
        $user = User::factory()->create(['organisation_id' => $this->organisation->id]);
        $event = OrganisationBillingEvent::create([
            'organisation_id' => $this->organisation->id,
            'user_id'         => $user->id,
            'event_type'      => 'user_created',
            'unit_price'      => 0,
            'quantity'        => 1,
            'amount'          => 0,
            'status'          => BillingEventStatus::PAID,
            'occurred_at'     => now(),
        ]);

        $this->actingAs($this->superAdmin)
            ->patchJson("/api/v1/billing/events/{$event->id}/paid")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_marking_cancelled_event_as_paid_returns_422(): void
    {
        $user = User::factory()->create(['organisation_id' => $this->organisation->id]);
        $event = OrganisationBillingEvent::create([
            'organisation_id' => $this->organisation->id,
            'user_id'         => $user->id,
            'event_type'      => 'user_created',
            'unit_price'      => 0,
            'quantity'        => 1,
            'amount'          => 0,
            'status'          => BillingEventStatus::CANCELLED,
            'occurred_at'     => now(),
        ]);

        $this->actingAs($this->superAdmin)
            ->patchJson("/api/v1/billing/events/{$event->id}/paid")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    // ── Listing avec filtres ──────────────────────────────────────────────

    public function test_super_admin_can_list_all_billing_events(): void
    {
        $user1 = User::factory()->create(['organisation_id' => $this->organisation->id]);
        $user2 = User::factory()->create(['organisation_id' => $this->organisation->id]);

        OrganisationBillingEvent::create(['organisation_id' => $this->organisation->id, 'user_id' => $user1->id, 'event_type' => 'user_created', 'unit_price' => 0, 'quantity' => 1, 'amount' => 0, 'status' => BillingEventStatus::PENDING, 'occurred_at' => now()]);
        OrganisationBillingEvent::create(['organisation_id' => $this->organisation->id, 'user_id' => $user2->id, 'event_type' => 'user_created', 'unit_price' => 0, 'quantity' => 1, 'amount' => 0, 'status' => BillingEventStatus::PAID, 'occurred_at' => now()]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/billing/events');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_billing_events_by_organisation_id(): void
    {
        $otherOrg  = Organisation::factory()->create(['code' => 'BLG-002']);
        $user1     = User::factory()->create(['organisation_id' => $this->organisation->id]);
        $user2     = User::factory()->create(['organisation_id' => $otherOrg->id]);

        OrganisationBillingEvent::create(['organisation_id' => $this->organisation->id, 'user_id' => $user1->id, 'event_type' => 'user_created', 'unit_price' => 0, 'quantity' => 1, 'amount' => 0, 'status' => BillingEventStatus::PENDING, 'occurred_at' => now()]);
        OrganisationBillingEvent::create(['organisation_id' => $otherOrg->id,           'user_id' => $user2->id, 'event_type' => 'user_created', 'unit_price' => 0, 'quantity' => 1, 'amount' => 0, 'status' => BillingEventStatus::PENDING, 'occurred_at' => now()]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/billing/events?organisation_id=' . $this->organisation->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.organisation_id', $this->organisation->id);
    }

    public function test_can_filter_billing_events_by_status(): void
    {
        $user1 = User::factory()->create(['organisation_id' => $this->organisation->id]);
        $user2 = User::factory()->create(['organisation_id' => $this->organisation->id]);

        OrganisationBillingEvent::create(['organisation_id' => $this->organisation->id, 'user_id' => $user1->id, 'event_type' => 'user_created', 'unit_price' => 0, 'quantity' => 1, 'amount' => 0, 'status' => BillingEventStatus::PENDING, 'occurred_at' => now()]);
        OrganisationBillingEvent::create(['organisation_id' => $this->organisation->id, 'user_id' => $user2->id, 'event_type' => 'user_created', 'unit_price' => 0, 'quantity' => 1, 'amount' => 0, 'status' => BillingEventStatus::PAID,    'occurred_at' => now()]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/billing/events?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    // ── Accès interdit ────────────────────────────────────────────────────

    public function test_admin_entreprise_cannot_list_billing_events(): void
    {
        $this->actingAs($this->adminEntreprise)
            ->getJson('/api/v1/billing/events')
            ->assertForbidden();
    }

    public function test_admin_entreprise_cannot_mark_event_as_paid(): void
    {
        $user  = User::factory()->create(['organisation_id' => $this->organisation->id]);
        $event = OrganisationBillingEvent::create([
            'organisation_id' => $this->organisation->id,
            'user_id'         => $user->id,
            'event_type'      => 'user_created',
            'unit_price'      => 0,
            'quantity'        => 1,
            'amount'          => 0,
            'status'          => BillingEventStatus::PENDING,
            'occurred_at'     => now(),
        ]);

        $this->actingAs($this->adminEntreprise)
            ->patchJson("/api/v1/billing/events/{$event->id}/paid")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/billing/events')->assertUnauthorized();
    }
}
