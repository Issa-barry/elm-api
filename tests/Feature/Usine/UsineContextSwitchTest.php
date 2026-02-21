<?php

namespace Tests\Feature\Usine;

use App\Enums\UsineRole;
use App\Enums\UsineType;
use App\Models\Prestataire;
use App\Models\Usine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Vérifie que le siège peut basculer de contexte usine via X-Usine-Id.
 * Et que usine_id est auto-rempli à la création.
 */
class UsineContextSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Usine $siege;
    private Usine $usineA;
    private Usine $usineB;
    private User  $siegeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('prestataires.read', 'web');
        Permission::findOrCreate('prestataires.create', 'web');

        // Codes différents des codes du backfill (ELM-SIEGE, ELM-USN-01)
        $this->siege = Usine::create([
            'nom'    => 'ELM Siège Test',
            'code'   => 'CTX-SIEGE',
            'type'   => UsineType::SIEGE,
            'statut' => 'active',
        ]);

        $this->usineA = Usine::create([
            'nom'       => 'Usine Alpha',
            'code'      => 'CTX-A',
            'type'      => UsineType::USINE,
            'statut'    => 'active',
            'parent_id' => $this->siege->id,
        ]);

        $this->usineB = Usine::create([
            'nom'       => 'Usine Beta',
            'code'      => 'CTX-B',
            'type'      => UsineType::USINE,
            'statut'    => 'active',
            'parent_id' => $this->siege->id,
        ]);

        $this->siegeUser = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usineA->id,
        ]);

        $this->siegeUser->usines()->attach($this->siege->id, [
            'role'       => UsineRole::OWNER_SIEGE->value,
            'is_default' => false,
        ]);

        $this->siegeUser->usines()->attach($this->usineA->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        $this->siegeUser->givePermissionTo(['prestataires.read', 'prestataires.create']);
    }

    /** Siège peut basculer vers usine B via X-Usine-Id */
    public function test_siege_peut_changer_contexte_via_header(): void
    {
        $prestataireA = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->usineA, '+224601000001'));
        $prestataireB = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->usineB, '+224601000002'));

        Sanctum::actingAs($this->siegeUser);

        // Contexte usine A
        $responseA = $this->withHeader('X-Usine-Id', (string) $this->usineA->id)
            ->getJson('/api/v1/prestataires');

        $responseA->assertOk();
        $idsA = collect($responseA->json('data'))->pluck('id');
        $this->assertTrue($idsA->contains($prestataireA->id));
        $this->assertFalse($idsA->contains($prestataireB->id));

        // Basculer vers usine B
        $responseB = $this->withHeader('X-Usine-Id', (string) $this->usineB->id)
            ->getJson('/api/v1/prestataires');

        $responseB->assertOk();
        $idsB = collect($responseB->json('data'))->pluck('id');
        $this->assertFalse($idsB->contains($prestataireA->id));
        $this->assertTrue($idsB->contains($prestataireB->id));
    }

    /** Sans X-Usine-Id et sans default_usine_id, le siège voit tout (vue consolidée) */
    public function test_siege_sans_header_voit_toutes_les_donnees(): void
    {
        $prestataireA = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->usineA, '+224601000003'));
        $prestataireB = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->usineB, '+224601000004'));

        Sanctum::actingAs($this->siegeUser);

        // Pour tester la vue consolidée, pas de default_usine_id
        $this->siegeUser->update(['default_usine_id' => null]);

        $response = $this->getJson('/api/v1/prestataires');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($prestataireA->id), 'Prestataire A devrait être visible en vue consolidée');
        $this->assertTrue($ids->contains($prestataireB->id), 'Prestataire B devrait être visible en vue consolidée');
    }

    /** usine_id est auto-rempli à la création selon X-Usine-Id */
    public function test_usine_id_auto_rempli_a_la_creation(): void
    {
        Sanctum::actingAs($this->siegeUser);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usineB->id)
            ->postJson('/api/v1/prestataires', [
                'nom'             => 'Diallo',
                'prenom'          => 'Mamadou',
                'phone'           => '+224620000010',
                'type'            => 'fournisseur',
                'pays'            => 'Guinee',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
            ]);

        $response->assertCreated();
        $this->assertEquals($this->usineB->id, $response->json('data.usine_id'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function prestatairePayload(Usine $usine, string $phone): array
    {
        return [
            'usine_id'        => $usine->id,
            'nom'             => 'TEST',
            'prenom'          => 'User',
            'phone'           => $phone,
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'reference'       => 'PREST-' . $usine->code . '-' . rand(1000, 9999),
        ];
    }
}
