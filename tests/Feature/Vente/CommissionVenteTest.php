<?php

namespace Tests\Feature\Vente;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\SiteType;
use App\Enums\StatutCommissionVente;
use App\Enums\StatutVersementCommission;
use App\Models\CommissionVente;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\Proprietaire;
use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use App\Models\VersementCommission;
use App\Models\Vehicule;
use App\Services\CommissionVenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests de la règle métier :
 * la commission est créée UNIQUEMENT quand la facture passe à "payee".
 */
class CommissionVenteTest extends TestCase
{
    use RefreshDatabase;

    private User  $staff;
    private Site  $site;
    private int   $vehiculeAvecCommId;    // commission_active = true, taux = 60, prix_vente > prix_usine
    private int   $vehiculeSansCommId;    // commission_active = false
    private int   $produitMargeId;        // prix_usine=1000, prix_vente=3000  → marge=2000
    private int   $produitSansMargeId;    // prix_usine=2000, prix_vente=2000  → marge=0
    private Livreur      $livreur;
    private Proprietaire $proprietaire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach ([
            'commandes.create', 'commandes.read',
            'encaissements.create', 'encaissements.read',
            'factures-livraisons.read',
        ] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->site = Site::create([
            'nom'    => 'Site Commission Test',
            'code'   => 'CMT-TEST',
            'type'   => SiteType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->site->id,
        ]);
        $this->staff->sites()->attach($this->site->id, ['role' => 'manager', 'is_default' => true]);
        $this->staff->givePermissionTo([
            'commandes.create', 'commandes.read',
            'encaissements.create', 'encaissements.read',
            'factures-livraisons.read',
        ]);

        $this->proprietaire = Proprietaire::factory()->create(['site_id' => $this->site->id]);
        $this->livreur      = Livreur::factory()->create(['site_id' => $this->site->id]);

        // Véhicule AVEC commission active (taux livreur 60 %)
        $vehiculeAvecComm = Vehicule::withoutGlobalScopes()->create([
            'site_id'                  => $this->site->id,
            'nom_vehicule'             => 'Camion Comm',
            'immatriculation'          => 'CMT-001',
            'type_vehicule'            => 'camion',
            'capacite_packs'           => 300,
            'proprietaire_id'          => $this->proprietaire->id,
            'livreur_principal_id'     => $this->livreur->id,
            'pris_en_charge_par_usine' => false,
            'commission_active'        => true,
            'taux_commission_livreur'  => 60.00,
            'is_active'                => true,
        ]);
        $this->vehiculeAvecCommId = $vehiculeAvecComm->id;

        // Véhicule SANS commission
        $vehiculeSansComm = Vehicule::withoutGlobalScopes()->create([
            'site_id'                  => $this->site->id,
            'nom_vehicule'             => 'Camion Sans Comm',
            'immatriculation'          => 'CMT-002',
            'type_vehicule'            => 'camion',
            'capacite_packs'           => 300,
            'proprietaire_id'          => $this->proprietaire->id,
            'livreur_principal_id'     => $this->livreur->id,
            'pris_en_charge_par_usine' => false,
            'commission_active'        => false,
            'taux_commission_livreur'  => 0.00,
            'is_active'                => true,
        ]);
        $this->vehiculeSansCommId = $vehiculeSansComm->id;

        // Produit avec marge (prix_vente > prix_usine)
        $produitMarge = Produit::withoutGlobalScopes()->create([
            'site_id'    => $this->site->id,
            'nom'        => 'Produit Marge',
            'code'       => 'MARGE-01',
            'type'       => ProduitType::FABRICABLE->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_usine' => 1000,
            'prix_vente' => 3000,
        ]);
        Stock::create(['produit_id' => $produitMarge->id, 'site_id' => $this->site->id, 'qte_stock' => 1000]);
        $this->produitMargeId = $produitMarge->id;

        // Produit SANS marge (prix_usine = prix_vente)
        $produitSansMarge = Produit::withoutGlobalScopes()->create([
            'site_id'    => $this->site->id,
            'nom'        => 'Produit Sans Marge',
            'code'       => 'SMARGE-01',
            'type'       => ProduitType::FABRICABLE->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_usine' => 2000,
            'prix_vente' => 2000,
        ]);
        Stock::create(['produit_id' => $produitSansMarge->id, 'site_id' => $this->site->id, 'qte_stock' => 1000]);
        $this->produitSansMargeId = $produitSansMarge->id;
    }

    private function h(): array
    {
        return ['X-Site-Id' => (string) $this->site->id];
    }

    private function creerCommande(int $vehiculeId, int $produitId, int $qte = 5): array
    {
        $resp = $this->withHeaders($this->h())
            ->postJson('/api/v1/ventes/commandes', [
                'vehicule_id' => $vehiculeId,
                'lignes'      => [['produit_id' => $produitId, 'qte' => $qte]],
            ]);

        $resp->assertCreated();

        return [
            'commande_id' => $resp->json('data.id'),
            'facture_id'  => $resp->json('data.facture.id'),
            'montant'     => (float) $resp->json('data.facture.montant_net'),
        ];
    }

    private function encaisser(int $factureId, float $montant): void
    {
        $this->withHeaders($this->h())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => $montant,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'especes',
        ])->assertCreated();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  1. Création commande → aucune commission créée
    // ─────────────────────────────────────────────────────────────────────────
    public function test_creation_commande_ne_cree_pas_de_commission(): void
    {
        Sanctum::actingAs($this->staff);

        $this->creerCommande($this->vehiculeAvecCommId, $this->produitMargeId);

        $this->assertEquals(0, CommissionVente::withoutGlobalScopes()->count());
        $this->assertEquals(0, VersementCommission::withoutGlobalScopes()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  2. Encaissement partiel → aucune commission créée
    // ─────────────────────────────────────────────────────────────────────────
    public function test_encaissement_partiel_ne_cree_pas_commission(): void
    {
        Sanctum::actingAs($this->staff);

        // 5 × (3000-1000) = 10 000 de commission possible ; montant facture = 5 × 3000 = 15 000
        $data = $this->creerCommande($this->vehiculeAvecCommId, $this->produitMargeId, 5);

        $this->encaisser($data['facture_id'], $data['montant'] / 2); // paiement partiel

        $this->assertEquals(0, CommissionVente::withoutGlobalScopes()->count());
        $this->assertEquals(0, VersementCommission::withoutGlobalScopes()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  3. Paiement complet → commission eligible + versements en_attente
    // ─────────────────────────────────────────────────────────────────────────
    public function test_paiement_complet_cree_commission_eligible_avec_versements(): void
    {
        Sanctum::actingAs($this->staff);

        // 5 unités × 3000 = 15 000 de CA ; marge = 5 × (3000-1000) = 10 000
        // Part livreur (60%) = 6 000 ; part propriétaire (40%) = 4 000
        $data = $this->creerCommande($this->vehiculeAvecCommId, $this->produitMargeId, 5);
        $this->encaisser($data['facture_id'], $data['montant']); // paiement complet

        $commission = CommissionVente::withoutGlobalScopes()
            ->where('commande_vente_id', $data['commande_id'])
            ->first();

        $this->assertNotNull($commission);
        $this->assertEquals(StatutCommissionVente::IMPAYEE, $commission->statut);
        $this->assertNotNull($commission->eligible_at);
        $this->assertEquals(10000.0, (float) $commission->montant_commission_total);
        $this->assertEquals(6000.0, (float) $commission->part_livreur);
        $this->assertEquals(4000.0, (float) $commission->part_proprietaire);

        $versements = VersementCommission::withoutGlobalScopes()
            ->where('commission_vente_id', $commission->id)
            ->get();

        $this->assertCount(2, $versements);
        $this->assertTrue($versements->where('beneficiaire_type', 'livreur')->isNotEmpty());
        $this->assertTrue($versements->where('beneficiaire_type', 'proprietaire')->isNotEmpty());
        $versements->each(fn ($v) => $this->assertEquals(StatutVersementCommission::EN_ATTENTE, $v->statut));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  4. Appel idempotent : second appel au service après paiement → pas de doublon
    // ─────────────────────────────────────────────────────────────────────────
    public function test_second_appel_service_ne_duplique_pas_la_commission(): void
    {
        Sanctum::actingAs($this->staff);

        $data = $this->creerCommande($this->vehiculeAvecCommId, $this->produitMargeId, 5);
        $this->encaisser($data['facture_id'], $data['montant']);

        // Premier appel déjà effectué par encaissement ; on rappelle manuellement le service
        $facture = \App\Models\FactureVente::withoutGlobalScopes()->find($data['facture_id']);
        app(CommissionVenteService::class)->creerSiEligible($facture);
        app(CommissionVenteService::class)->creerSiEligible($facture);

        $this->assertEquals(1, CommissionVente::withoutGlobalScopes()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  5. Marge nulle → commission créée avec statut "payee", sans versements
    //     + commande clôturée
    // ─────────────────────────────────────────────────────────────────────────
    public function test_commission_nulle_statut_payee_sans_versements_et_commande_cloturee(): void
    {
        Sanctum::actingAs($this->staff);

        // prix_usine = prix_vente = 2000 → marge = 0
        $data = $this->creerCommande($this->vehiculeAvecCommId, $this->produitSansMargeId, 5);
        $this->encaisser($data['facture_id'], $data['montant']);

        $commission = CommissionVente::withoutGlobalScopes()
            ->where('commande_vente_id', $data['commande_id'])
            ->first();

        $this->assertNotNull($commission);
        $this->assertEquals(StatutCommissionVente::PAYEE, $commission->statut);
        $this->assertEquals(0.0, (float) $commission->montant_commission_total);
        $this->assertEquals(0, VersementCommission::withoutGlobalScopes()->count());

        $commande = \App\Models\CommandeVente::withoutGlobalScopes()->find($data['commande_id']);
        $this->assertEquals(\App\Enums\StatutCommandeVente::CLOTUREE, $commande->statut);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  6. Véhicule sans commission active → aucune commission créée
    // ─────────────────────────────────────────────────────────────────────────
    public function test_vehicule_sans_commission_active_ne_cree_pas_commission(): void
    {
        Sanctum::actingAs($this->staff);

        $data = $this->creerCommande($this->vehiculeSansCommId, $this->produitMargeId, 5);
        $this->encaisser($data['facture_id'], $data['montant']);

        $this->assertEquals(0, CommissionVente::withoutGlobalScopes()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  7. Commission déjà existante en "impayee" → service idempotent, pas de doublon
    // ─────────────────────────────────────────────────────────────────────────
    public function test_commission_existante_impayee_service_idempotent(): void
    {
        Sanctum::actingAs($this->staff);

        $data = $this->creerCommande($this->vehiculeAvecCommId, $this->produitMargeId, 5);

        // Insérer manuellement une commission impayee (sans eligible_at)
        \App\Models\CommissionVente::create([
            'commande_vente_id'        => $data['commande_id'],
            'vehicule_id'              => $this->vehiculeAvecCommId,
            'livreur_id'               => $this->livreur->id,
            'proprietaire_id'          => $this->proprietaire->id,
            'taux_livreur_snapshot'    => 60,
            'montant_commission_total' => 10000,
            'part_livreur'             => 6000,
            'part_proprietaire'        => 4000,
            'statut'                   => \App\Enums\StatutCommissionVente::IMPAYEE->value,
            'eligible_at'              => null,
        ]);

        // Payer la facture → le service doit renseigner eligible_at sans dupliquer
        $this->encaisser($data['facture_id'], $data['montant']);

        $commission = \App\Models\CommissionVente::withoutGlobalScopes()
            ->where('commande_vente_id', $data['commande_id'])
            ->first();

        $this->assertEquals(StatutCommissionVente::IMPAYEE, $commission->statut);
        $this->assertNotNull($commission->eligible_at);
        $this->assertEquals(1, \App\Models\CommissionVente::withoutGlobalScopes()->count()); // pas de doublon
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  8. Paiement en deux fois (partiel puis solde) → commission créée une seule fois
    // ─────────────────────────────────────────────────────────────────────────
    public function test_paiement_en_deux_fois_cree_une_seule_commission(): void
    {
        Sanctum::actingAs($this->staff);

        $data = $this->creerCommande($this->vehiculeAvecCommId, $this->produitMargeId, 5);

        $this->encaisser($data['facture_id'], $data['montant'] / 2); // partiel
        $this->assertEquals(0, CommissionVente::withoutGlobalScopes()->count());

        $this->encaisser($data['facture_id'], $data['montant'] / 2); // solde
        $this->assertEquals(1, CommissionVente::withoutGlobalScopes()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  9. Véhicule sans commission + facture payée → commande clôturée
    // ─────────────────────────────────────────────────────────────────────────
    public function test_vehicule_sans_commission_facture_payee_commande_cloturee(): void
    {
        Sanctum::actingAs($this->staff);

        $data = $this->creerCommande($this->vehiculeSansCommId, $this->produitMargeId, 5);
        $this->encaisser($data['facture_id'], $data['montant']);

        $this->assertEquals(0, CommissionVente::withoutGlobalScopes()->count());

        $commande = \App\Models\CommandeVente::withoutGlobalScopes()->find($data['commande_id']);
        $this->assertEquals(\App\Enums\StatutCommandeVente::CLOTUREE, $commande->statut);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. Commission versée complètement → commande clôturée
    // ─────────────────────────────────────────────────────────────────────────
    public function test_commission_payee_commande_cloturee(): void
    {
        Sanctum::actingAs($this->staff);

        // 5 × marge 2000 = 10 000 commission ; livreur 60% = 6 000, propriétaire 40% = 4 000
        $data = $this->creerCommande($this->vehiculeAvecCommId, $this->produitMargeId, 5);
        $this->encaisser($data['facture_id'], $data['montant']);

        $commission = CommissionVente::withoutGlobalScopes()
            ->where('commande_vente_id', $data['commande_id'])
            ->first();

        $this->assertNotNull($commission);
        $this->assertEquals(StatutCommissionVente::IMPAYEE, $commission->statut);

        // Commande encore active tant que la commission n'est pas versée
        $commande = \App\Models\CommandeVente::withoutGlobalScopes()->find($data['commande_id']);
        $this->assertEquals(\App\Enums\StatutCommandeVente::ACTIVE, $commande->statut);

        // Verser la commission livreur
        $this->withHeaders($this->h())->postJson(
            "/api/v1/ventes/commissions/{$commission->id}/versements/livreur",
            ['montant' => 6000, 'date_paiement' => now()->toDateString()]
        )->assertCreated();

        // Verser la commission propriétaire
        $this->withHeaders($this->h())->postJson(
            "/api/v1/ventes/commissions/{$commission->id}/versements/proprietaire",
            ['montant' => 4000, 'date_paiement' => now()->toDateString()]
        )->assertCreated();

        $commission->refresh();
        $this->assertEquals(StatutCommissionVente::PAYEE, $commission->statut);

        $commande->refresh();
        $this->assertEquals(\App\Enums\StatutCommandeVente::CLOTUREE, $commande->statut);
    }
}
