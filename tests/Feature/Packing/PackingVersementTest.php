<?php

namespace Tests\Feature\Packing;

use App\Enums\PackingStatut;
use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\SiteRole;
use App\Enums\SiteType;
use App\Models\Packing;
use App\Models\Parametre;
use App\Models\Prestataire;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Site;
use App\Models\User;
use App\Models\Versement;
use App\Services\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests des opérations sur versements via :
 *   GET    /api/v1/packings/{id}/versements
 *   POST   /api/v1/packings/{id}/versements
 *   DELETE /api/v1/packings/{id}/versements/{versementId}
 *
 * Couvre : création, recalcul du statut, validation (montant excessif,
 *          packing annulé), suppression, isolation.
 */
class PackingVersementTest extends TestCase
{
    use RefreshDatabase;

    private Site        $usine;
    private User        $staff;
    private Prestataire $prestataire;
    private Produit     $produitRouleau;
    private Stock       $stockRouleau;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        foreach (['packings.read', 'packings.create', 'versements.create', 'versements.delete'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->usine = Site::create([
            'nom'    => 'Site Packing Versement',
            'code'   => 'PCK-C',
            'type'   => SiteType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->usine->id,
        ]);

        $this->staff->sites()->attach($this->usine->id, [
            'role'       => SiteRole::MANAGER->value,
            'is_default' => true,
        ]);

        $this->staff->givePermissionTo(['packings.read', 'packings.create', 'versements.create', 'versements.delete']);

        app(SiteContext::class)->setCurrentSiteId($this->usine->id);

        $this->prestataire = Prestataire::create([
            'site_id'        => $this->usine->id,
            'nom'             => 'SOUMAH',
            'prenom'          => 'Alpha',
            'phone'           => '620111003',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'pays'            => 'Guinee',
        ]);

        $this->setupProduitRouleau();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function setupProduitRouleau(int $qteStock = 100): void
    {
        $this->produitRouleau = Produit::withoutGlobalScopes()->create([
            'site_id'    => $this->usine->id,
            'nom'         => 'Rouleau Versement Test',
            'code'        => 'ROUL-PCK-C',
            'type'        => ProduitType::MATERIEL->value,
            'statut'      => ProduitStatut::ACTIF->value,
            'prix_achat'  => 100,
            'is_global'   => false,
            'is_critique' => false,
        ]);

        Parametre::updateOrCreate(
            ['cle' => Parametre::CLE_PRODUIT_ROULEAU_ID],
            ['valeur' => (string) $this->produitRouleau->id, 'type' => 'integer']
        );

        Cache::forget('parametre_' . Parametre::CLE_PRODUIT_ROULEAU_ID);

        $this->stockRouleau = Stock::updateOrCreate(
            ['produit_id' => $this->produitRouleau->id, 'site_id' => $this->usine->id],
            ['qte_stock' => $qteStock, 'seuil_alerte_stock' => 5]
        );
    }

    /**
     * Crée un packing directement en base (sans décrement stock).
     * montant = nb_rouleaux × prix_par_rouleau est calculé automatiquement.
     */
    private function creerPacking(array $overrides = []): Packing
    {
        return Packing::create(array_merge([
            'site_id'         => $this->usine->id,
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 10,
            'prix_par_rouleau' => 500,
            'statut'           => PackingStatut::IMPAYEE->value,
        ], $overrides));
    }

    /** Crée un versement directement en base (sans recalcul du statut). */
    private function creerVersement(Packing $packing, int $montant): Versement
    {
        return Versement::create([
            'site_id'       => $this->usine->id,
            'packing_id'     => $packing->id,
            'montant'        => $montant,
            'date_versement' => today()->toDateString(),
            'mode_paiement'  => Versement::MODE_ESPECES,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /packings/{id}/versements
    // ──────────────────────────────────────────────────────────────────────

    public function test_index_versements_retourne_liste_et_packing(): void
    {
        $packing = $this->creerPacking();
        $this->creerVersement($packing, 1000);
        $this->creerVersement($packing, 2000);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson("/api/v1/packings/{$packing->id}/versements");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'packing'    => ['id', 'montant', 'statut'],
                    'versements' => [['id', 'montant', 'date_versement', 'mode_paiement']],
                ],
            ]);

        $this->assertCount(2, $response->json('data.versements'));
    }

    public function test_index_versements_retourne_404_si_packing_inexistant(): void
    {
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/packings/999999/versements')->assertNotFound();
    }

    // ──────────────────────────────────────────────────────────────────────
    // POST /packings/{id}/versements
    // ──────────────────────────────────────────────────────────────────────

    public function test_store_versement_partiel_change_statut_en_partielle(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant = 5000

        Sanctum::actingAs($this->staff);

        $response = $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 2000,
            'date_versement' => today()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.packing.statut', 'partielle')
            ->assertJsonPath('data.versement.montant', 2000);

        $this->assertDatabaseHas('versements', [
            'packing_id' => $packing->id,
            'montant'    => 2000,
        ]);
    }

    public function test_store_versement_total_change_statut_en_payee(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant = 5000

        Sanctum::actingAs($this->staff);

        $response = $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 5000,
            'date_versement' => today()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.packing.statut', 'payee');

        $this->assertEquals(PackingStatut::PAYEE, $packing->fresh()->statut);
    }

    public function test_store_versement_en_plusieurs_fois_jusqu_a_payee(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant = 5000

        Sanctum::actingAs($this->staff);

        $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 2000,
            'date_versement' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.packing.statut', 'partielle');

        $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 3000,
            'date_versement' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.packing.statut', 'payee');

        $this->assertEquals(2, $packing->versements()->count());
    }

    public function test_store_versement_avec_mode_paiement_virement(): void
    {
        $packing = $this->creerPacking();

        Sanctum::actingAs($this->staff);

        $response = $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 1000,
            'date_versement' => today()->toDateString(),
            'mode_paiement'  => 'virement',
            'notes'          => 'Virement du 01/03',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.versement.mode_paiement', 'virement');
    }

    public function test_store_versement_echoue_si_montant_depasse_restant(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant = 5000
        $this->creerVersement($packing, 4000);
        // restant = 1000

        Sanctum::actingAs($this->staff);

        $response = $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 2000,   // dépasse le restant de 1000
            'date_versement' => today()->toDateString(),
        ]);

        $response->assertStatus(422);

        // Aucun versement supplémentaire créé
        $this->assertEquals(1, $packing->versements()->count());
    }

    public function test_store_versement_echoue_si_packing_annulee(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 0, 'statut' => PackingStatut::ANNULEE->value]);

        Sanctum::actingAs($this->staff);

        $response = $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 500,
            'date_versement' => today()->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertEquals(0, $packing->versements()->count());
    }

    public function test_store_versement_echoue_si_packing_deja_integralement_paye(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant = 5000
        $this->creerVersement($packing, 5000);
        $packing->mettreAJourStatut();  // statut = payee

        Sanctum::actingAs($this->staff);

        $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 100,
            'date_versement' => today()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_store_versement_necessite_permission_versements_create(): void
    {
        Permission::findOrCreate('versements.create', 'web');

        $userSansPerm = User::factory()->create([
            'type'             => 'staff',
            'default_site_id' => $this->usine->id,
        ]);
        $userSansPerm->sites()->attach($this->usine->id, ['role' => SiteRole::STAFF->value, 'is_default' => true]);

        $packing = $this->creerPacking();

        Sanctum::actingAs($userSansPerm);

        $this->postJson("/api/v1/packings/{$packing->id}/versements", [
            'montant'        => 500,
            'date_versement' => today()->toDateString(),
        ])->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────
    // DELETE /packings/{id}/versements/{versementId}
    // ──────────────────────────────────────────────────────────────────────

    public function test_destroy_versement_soft_delete_et_recalcule_statut(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant = 5000

        $v1 = $this->creerVersement($packing, 3000);
        $v2 = $this->creerVersement($packing, 2000);
        $packing->mettreAJourStatut(); // payee
        $this->assertEquals(PackingStatut::PAYEE, $packing->fresh()->statut);

        Sanctum::actingAs($this->staff);

        $response = $this->deleteJson("/api/v1/packings/{$packing->id}/versements/{$v2->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['packing']])
            ->assertJsonPath('data.packing.statut', 'partielle');

        $this->assertSoftDeleted('versements', ['id' => $v2->id]);
        $this->assertEquals(PackingStatut::PARTIELLE, $packing->fresh()->statut);
    }

    public function test_destroy_tous_versements_repasse_statut_en_impayee(): void
    {
        $packing   = $this->creerPacking(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        $versement = $this->creerVersement($packing, 2000);
        $packing->mettreAJourStatut(); // partielle

        Sanctum::actingAs($this->staff);

        $this->deleteJson("/api/v1/packings/{$packing->id}/versements/{$versement->id}")
            ->assertOk()
            ->assertJsonPath('data.packing.statut', 'impayee');

        $this->assertEquals(PackingStatut::IMPAYEE, $packing->fresh()->statut);
    }

    public function test_destroy_versement_retourne_404_si_versement_inexistant(): void
    {
        $packing = $this->creerPacking();

        Sanctum::actingAs($this->staff);

        $this->deleteJson("/api/v1/packings/{$packing->id}/versements/999999")
            ->assertNotFound();
    }

    public function test_destroy_versement_retourne_404_si_versement_appartient_a_un_autre_packing(): void
    {
        $packingA  = $this->creerPacking();
        $packingB  = $this->creerPacking();
        $versement = $this->creerVersement($packingB, 1000);

        Sanctum::actingAs($this->staff);

        // Tentative de supprimer le versement de B en passant par A
        $this->deleteJson("/api/v1/packings/{$packingA->id}/versements/{$versement->id}")
            ->assertNotFound();
    }
}
