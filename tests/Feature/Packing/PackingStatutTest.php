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
 * Tests des transitions de statut via PATCH /api/v1/packings/{id}/statut
 * et des méthodes métier mettreAJourStatut(), annuler(), reactiver().
 */
class PackingStatutTest extends TestCase
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

        foreach (['packings.read', 'packings.create', 'packings.update'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->usine = Site::create([
            'nom'    => 'Site Packing Statut',
            'code'   => 'PCK-B',
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

        $this->staff->givePermissionTo(['packings.read', 'packings.create', 'packings.update']);

        app(SiteContext::class)->setCurrentSiteId($this->usine->id);

        $this->prestataire = Prestataire::create([
            'site_id'        => $this->usine->id,
            'nom'             => 'BALDE',
            'prenom'          => 'Oumar',
            'phone'           => '620111002',
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
            'nom'         => 'Rouleau Statut Test',
            'code'        => 'ROUL-PCK-B',
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

    /** Crée un packing via l'API (décrémente le stock). */
    private function creerPackingViaApi(int $nbRouleaux = 10): array
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => $nbRouleaux,
            'prix_par_rouleau' => 500,
        ]);

        $response->assertCreated();

        return [
            'packing' => Packing::find($response->json('data.packing.id')),
            'id'      => $response->json('data.packing.id'),
        ];
    }

    /** Crée un packing directement en base (sans décrement stock). */
    private function creerPackingDirect(array $overrides = []): Packing
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

    // ──────────────────────────────────────────────────────────────────────
    // Transitions via API PATCH /{id}/statut
    // ──────────────────────────────────────────────────────────────────────

    public function test_annuler_packing_restaure_le_stock(): void
    {
        ['id' => $id] = $this->creerPackingViaApi(10);

        // Après création → stock = 90
        $this->assertEquals(90, $this->stockRouleau->fresh()->qte_stock);

        $response = $this->patchJson("/api/v1/packings/{$id}/statut", ['statut' => 'annulee']);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'annulee');

        // Stock restauré
        $this->assertEquals(100, $this->stockRouleau->fresh()->qte_stock);
    }

    public function test_annuler_packing_deja_annulee_est_idempotent(): void
    {
        $packing = $this->creerPackingDirect(['nb_rouleaux' => 0, 'statut' => PackingStatut::ANNULEE->value]);

        Sanctum::actingAs($this->staff);

        $response = $this->patchJson("/api/v1/packings/{$packing->id}/statut", ['statut' => 'annulee']);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'annulee');

        // Stock inchangé
        $this->assertEquals(100, $this->stockRouleau->fresh()->qte_stock);
    }

    public function test_reactiver_packing_annulee_decremente_le_stock(): void
    {
        // Créer un packing annulé via API → stock non décrémenté
        Sanctum::actingAs($this->staff);

        $storeResponse = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 10,
            'prix_par_rouleau' => 500,
            'statut'           => 'annulee',
        ]);
        $storeResponse->assertCreated();
        $id = $storeResponse->json('data.packing.id');

        // Stock toujours à 100
        $this->assertEquals(100, $this->stockRouleau->fresh()->qte_stock);

        // Réactiver → doit décrémenter
        $response = $this->patchJson("/api/v1/packings/{$id}/statut", ['statut' => 'impayee']);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'impayee');

        $this->assertEquals(90, $this->stockRouleau->fresh()->qte_stock);
    }

    public function test_reactiver_echoue_si_stock_insuffisant(): void
    {
        // Packing annulé pour 15 rouleaux mais stock = 5
        Stock::where('id', $this->stockRouleau->id)->update(['qte_stock' => 5]);

        $packing = $this->creerPackingDirect([
            'nb_rouleaux' => 15,
            'statut'      => PackingStatut::ANNULEE->value,
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->patchJson("/api/v1/packings/{$packing->id}/statut", ['statut' => 'impayee']);

        // Doit échouer (stock insuffisant)
        $response->assertStatus(422);

        // Statut inchangé
        $this->assertEquals(PackingStatut::ANNULEE->value, $packing->fresh()->statut->value);
    }

    public function test_changer_statut_en_partielle_est_refuse(): void
    {
        $packing = $this->creerPackingDirect();

        Sanctum::actingAs($this->staff);

        $this->patchJson("/api/v1/packings/{$packing->id}/statut", ['statut' => 'partielle'])
            ->assertStatus(422);
    }

    public function test_changer_statut_en_payee_est_refuse(): void
    {
        $packing = $this->creerPackingDirect();

        Sanctum::actingAs($this->staff);

        $this->patchJson("/api/v1/packings/{$packing->id}/statut", ['statut' => 'payee'])
            ->assertStatus(422);
    }

    public function test_changer_statut_packing_inexistant_retourne_404(): void
    {
        Sanctum::actingAs($this->staff);

        $this->patchJson('/api/v1/packings/999999/statut', ['statut' => 'annulee'])
            ->assertNotFound();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tests unitaires de mettreAJourStatut()
    // ──────────────────────────────────────────────────────────────────────

    public function test_mettre_a_jour_statut_impayee_si_aucun_versement(): void
    {
        $packing = $this->creerPackingDirect(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);

        $result = $packing->mettreAJourStatut();

        $this->assertTrue($result);
        $this->assertEquals(PackingStatut::IMPAYEE, $packing->fresh()->statut);
    }

    public function test_mettre_a_jour_statut_partielle_si_versement_partiel(): void
    {
        $packing = $this->creerPackingDirect(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant total = 5000

        Versement::create([
            'site_id'       => $this->usine->id,
            'packing_id'     => $packing->id,
            'montant'        => 2000,
            'date_versement' => today()->toDateString(),
            'mode_paiement'  => 'especes',
        ]);

        $result = $packing->mettreAJourStatut();

        $this->assertTrue($result);
        $this->assertEquals(PackingStatut::PARTIELLE, $packing->fresh()->statut);
    }

    public function test_mettre_a_jour_statut_payee_si_versement_total(): void
    {
        $packing = $this->creerPackingDirect(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant total = 5000

        Versement::create([
            'site_id'       => $this->usine->id,
            'packing_id'     => $packing->id,
            'montant'        => 5000,
            'date_versement' => today()->toDateString(),
            'mode_paiement'  => 'especes',
        ]);

        $packing->mettreAJourStatut();

        $this->assertEquals(PackingStatut::PAYEE, $packing->fresh()->statut);
    }

    public function test_mettre_a_jour_statut_retourne_false_si_annulee(): void
    {
        $packing = $this->creerPackingDirect([
            'nb_rouleaux' => 0,
            'statut'      => PackingStatut::ANNULEE->value,
        ]);

        $result = $packing->mettreAJourStatut();

        $this->assertFalse($result);
        $this->assertEquals(PackingStatut::ANNULEE, $packing->fresh()->statut);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Accesseurs calculés
    // ──────────────────────────────────────────────────────────────────────

    public function test_montant_verse_et_restant_sont_calcules_correctement(): void
    {
        $packing = $this->creerPackingDirect(['nb_rouleaux' => 10, 'prix_par_rouleau' => 500]);
        // montant = 5000

        Versement::create([
            'site_id'       => $this->usine->id,
            'packing_id'     => $packing->id,
            'montant'        => 1500,
            'date_versement' => today()->toDateString(),
        ]);

        $packing->refresh();

        $this->assertEquals(1500, $packing->montant_verse);
        $this->assertEquals(3500, $packing->montant_restant);
    }

    public function test_montant_restant_est_zero_apres_paiement_complet(): void
    {
        $packing = $this->creerPackingDirect(['nb_rouleaux' => 5, 'prix_par_rouleau' => 1000]);
        // montant = 5000

        Versement::create([
            'site_id'       => $this->usine->id,
            'packing_id'     => $packing->id,
            'montant'        => 5000,
            'date_versement' => today()->toDateString(),
        ]);

        $packing->refresh();

        $this->assertEquals(5000, $packing->montant_verse);
        $this->assertEquals(0, $packing->montant_restant);
    }
}
