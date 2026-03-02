<?php

namespace Tests\Feature\Packing;

use App\Enums\PackingStatut;
use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\UsineRole;
use App\Enums\UsineType;
use App\Models\Packing;
use App\Models\Parametre;
use App\Models\Prestataire;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Usine;
use App\Models\User;
use App\Services\UsineContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests CRUD de l'API /api/v1/packings.
 *
 * Couvre : index, show, store (+ décrement stock), update, destroy,
 *          filtres de liste, isolation multi-usine, permissions.
 */
class PackingCrudTest extends TestCase
{
    use RefreshDatabase;

    private Usine       $usine;
    private User        $staff;
    private Prestataire $prestataire;
    private Produit     $produitRouleau;
    private Stock       $stockRouleau;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        foreach (['packings.read', 'packings.create', 'packings.update', 'packings.delete'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->usine = Usine::create([
            'nom'    => 'Usine Packing CRUD',
            'code'   => 'PCK-A',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);

        $this->staff->usines()->attach($this->usine->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        $this->staff->givePermissionTo(['packings.read', 'packings.create', 'packings.update', 'packings.delete']);

        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $this->prestataire = Prestataire::create([
            'usine_id'        => $this->usine->id,
            'nom'             => 'DIALLO',
            'prenom'          => 'Mamadou',
            'phone'           => '620111001',
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
            'usine_id'    => $this->usine->id,
            'nom'         => 'Rouleau Test',
            'code'        => 'ROUL-PCK-A',
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
            ['produit_id' => $this->produitRouleau->id, 'usine_id' => $this->usine->id],
            ['qte_stock' => $qteStock, 'seuil_alerte_stock' => 5]
        );
    }

    /** Crée un packing directement en base sans passer par l'API (pas de décrement stock). */
    private function creerPacking(array $overrides = []): Packing
    {
        return Packing::create(array_merge([
            'usine_id'         => $this->usine->id,
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 5,
            'prix_par_rouleau' => 1000,
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────────────────
    // INDEX
    // ──────────────────────────────────────────────────────────────────────

    public function test_index_retourne_liste_des_packings_de_lusine(): void
    {
        $this->creerPacking();
        $this->creerPacking(['nb_rouleaux' => 3]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/packings');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'reference', 'nb_rouleaux', 'montant', 'statut', 'prestataire_nom']],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filtre_par_statut_impayee(): void
    {
        $this->creerPacking(['statut' => PackingStatut::IMPAYEE->value]);

        // Packing annulee (nb_rouleaux=0 pour éviter le check produit_rouleau)
        $this->creerPacking(['nb_rouleaux' => 0, 'statut' => PackingStatut::ANNULEE->value]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/packings?statut=impayee');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('impayee', $response->json('data.0.statut'));
    }

    public function test_index_filtre_par_prestataire(): void
    {
        $autrePrestataire = Prestataire::create([
            'usine_id'        => $this->usine->id,
            'nom'             => 'CAMARA',
            'prenom'          => 'Ibrahima',
            'phone'           => '620222002',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'pays'            => 'Guinee',
        ]);

        $this->creerPacking();
        $this->creerPacking(['prestataire_id' => $autrePrestataire->id]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson("/api/v1/packings?prestataire_id={$this->prestataire->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filtre_non_payes(): void
    {
        $this->creerPacking(['statut' => PackingStatut::IMPAYEE->value]);
        $this->creerPacking(['nb_rouleaux' => 0, 'statut' => PackingStatut::ANNULEE->value]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/packings?non_payes=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_isole_les_packings_des_autres_usines(): void
    {
        $autreUsine = Usine::create([
            'nom'    => 'Autre Usine',
            'code'   => 'PCK-AUTRE',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $autrePrestataire = Prestataire::create([
            'usine_id'        => $autreUsine->id,
            'nom'             => 'BARRY',
            'prenom'          => 'Sekou',
            'phone'           => '620333003',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'pays'            => 'Guinee',
        ]);

        // Packing de l'autre usine — insertion directe pour éviter la collision
        // de référence (generateReference() est scopé par usine via HasUsineScope).
        DB::table('packings')->insert([
            'usine_id'         => $autreUsine->id,
            'prestataire_id'   => $autrePrestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 0,
            'prix_par_rouleau' => 500,
            'montant'          => 0,
            'reference'        => 'PACK-AUTRE-0001',
            'statut'           => PackingStatut::IMPAYEE->value,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Packing de notre usine
        $this->creerPacking(['nb_rouleaux' => 0]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/packings');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->usine->id, $response->json('data.0.usine_id'));
    }

    public function test_index_necessite_permission_packings_read(): void
    {
        Permission::findOrCreate('packings.read', 'web');

        $userSansPerm = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);
        $userSansPerm->usines()->attach($this->usine->id, ['role' => UsineRole::STAFF->value, 'is_default' => true]);

        Sanctum::actingAs($userSansPerm);

        $this->getJson('/api/v1/packings')->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────
    // SHOW
    // ──────────────────────────────────────────────────────────────────────

    public function test_show_retourne_packing_avec_bons_champs(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 5, 'prix_par_rouleau' => 1000]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson("/api/v1/packings/{$packing->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $packing->id)
            ->assertJsonPath('data.nb_rouleaux', 5)
            ->assertJsonPath('data.montant', 5000)
            ->assertJsonPath('data.statut', 'impayee');
    }

    public function test_show_retourne_404_si_packing_inexistant(): void
    {
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/packings/999999')->assertNotFound();
    }

    // ──────────────────────────────────────────────────────────────────────
    // STORE
    // ──────────────────────────────────────────────────────────────────────

    public function test_store_cree_packing_et_decremente_le_stock(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 10,
            'prix_par_rouleau' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.packing.nb_rouleaux', 10)
            ->assertJsonPath('data.packing.montant', 5000)
            ->assertJsonPath('data.packing.statut', 'impayee');

        $this->assertEquals(90, $this->stockRouleau->fresh()->qte_stock);
        $this->assertDatabaseHas('packings', [
            'usine_id'       => $this->usine->id,
            'prestataire_id' => $this->prestataire->id,
            'nb_rouleaux'    => 10,
            'montant'        => 5000,
        ]);
    }

    public function test_store_retourne_stock_alert_dans_la_reponse(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 10,
            'prix_par_rouleau' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'packing',
                    'stock_alert' => ['stock_actuel', 'seuil_stock_faible', 'niveau', 'is_low_stock', 'is_out_of_stock'],
                ],
            ]);

        $this->assertEquals('in_stock', $response->json('data.stock_alert.niveau'));
        $this->assertEquals(90, $response->json('data.stock_alert.stock_actuel'));
    }

    public function test_store_retourne_alerte_low_stock_si_seuil_depasse(): void
    {
        // Seuil = 15, stock après création = 100 - 90 = 10 < 15
        Stock::where('id', $this->stockRouleau->id)->update(['seuil_alerte_stock' => 15]);

        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 90,
            'prix_par_rouleau' => 500,
        ]);

        $response->assertCreated();
        $this->assertEquals('low_stock', $response->json('data.stock_alert.niveau'));
    }

    public function test_store_echoue_422_si_stock_insuffisant(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 200,   // stock = 100
            'prix_par_rouleau' => 500,
        ]);

        $response->assertUnprocessable();

        // Stock non touché
        $this->assertEquals(100, $this->stockRouleau->fresh()->qte_stock);
        $this->assertDatabaseCount('packings', 0);
    }

    public function test_store_packing_annulee_ne_decremente_pas_le_stock(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 10,
            'prix_par_rouleau' => 500,
            'statut'           => 'annulee',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.packing.statut', 'annulee');

        // Aucun décrement
        $this->assertEquals(100, $this->stockRouleau->fresh()->qte_stock);
    }

    public function test_store_rejette_statut_partielle_et_payee(): void
    {
        Sanctum::actingAs($this->staff);

        $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 5,
            'prix_par_rouleau' => 500,
            'statut'           => 'partielle',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 5,
            'prix_par_rouleau' => 500,
            'statut'           => 'payee',
        ])->assertUnprocessable();
    }

    public function test_store_calcule_le_montant_automatiquement(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/packings', [
            'prestataire_id'   => $this->prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 7,
            'prix_par_rouleau' => 2000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.packing.montant', 14000);
    }

    public function test_store_genere_des_references_uniques_quand_on_change_dusine(): void
    {
        $autreUsine = Usine::create([
            'nom'    => 'Usine Secondaire',
            'code'   => 'PCK-B',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff->usines()->attach($autreUsine->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => false,
        ]);

        $autrePrestataire = Prestataire::create([
            'usine_id'        => $autreUsine->id,
            'nom'             => 'BAH',
            'prenom'          => 'Saliou',
            'phone'           => '620777777',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'pays'            => 'Guinee',
        ]);

        // Même produit rouleau, mais stock distinct par usine.
        Stock::updateOrCreate(
            ['produit_id' => $this->produitRouleau->id, 'usine_id' => $autreUsine->id],
            ['qte_stock' => 100, 'seuil_alerte_stock' => 5]
        );

        Sanctum::actingAs($this->staff);

        $responseUsineA = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/packings', [
                'prestataire_id'   => $this->prestataire->id,
                'date'             => today()->toDateString(),
                'nb_rouleaux'      => 1,
                'prix_par_rouleau' => 500,
            ]);

        $responseUsineB = $this->withHeader('X-Usine-Id', (string) $autreUsine->id)
            ->postJson('/api/v1/packings', [
                'prestataire_id'   => $autrePrestataire->id,
                'date'             => today()->toDateString(),
                'nb_rouleaux'      => 1,
                'prix_par_rouleau' => 500,
            ]);

        $responseUsineA->assertCreated();
        $responseUsineB->assertCreated();

        $this->assertNotSame(
            $responseUsineA->json('data.packing.reference'),
            $responseUsineB->json('data.packing.reference')
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────────────────────────

    public function test_update_modifie_un_packing_impayee(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 5, 'prix_par_rouleau' => 500]);

        Sanctum::actingAs($this->staff);

        $response = $this->putJson("/api/v1/packings/{$packing->id}", [
            'prix_par_rouleau' => 1000,
            'notes'            => 'Mise à jour test',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.prix_par_rouleau', 1000)
            ->assertJsonPath('data.montant', 5000);    // 5 * 1000
    }

    public function test_update_echoue_si_packing_est_annulee(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 0, 'statut' => PackingStatut::ANNULEE->value]);

        Sanctum::actingAs($this->staff);

        $this->putJson("/api/v1/packings/{$packing->id}", [
            'notes' => 'tentative',
        ])->assertUnprocessable();
    }

    public function test_update_rejette_le_champ_statut(): void
    {
        $packing = $this->creerPacking();

        Sanctum::actingAs($this->staff);

        $this->putJson("/api/v1/packings/{$packing->id}", [
            'statut' => 'annulee',
        ])->assertUnprocessable();
    }

    // ──────────────────────────────────────────────────────────────────────
    // DESTROY
    // ──────────────────────────────────────────────────────────────────────

    public function test_destroy_soft_delete_un_packing(): void
    {
        $packing = $this->creerPacking(['nb_rouleaux' => 0]);

        Sanctum::actingAs($this->staff);

        $this->deleteJson("/api/v1/packings/{$packing->id}")->assertOk();

        $this->assertSoftDeleted('packings', ['id' => $packing->id]);
    }

    public function test_destroy_retourne_404_si_packing_inexistant(): void
    {
        Sanctum::actingAs($this->staff);

        $this->deleteJson('/api/v1/packings/999999')->assertNotFound();
    }
}
