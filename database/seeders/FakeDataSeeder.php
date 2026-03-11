<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Génère des données volumineuses pour stress-test de la base.
 *
 * VOLUME : 60 000 commandes/jour × JOURS jours par site.
 *
 * Stratégie :
 *  - DB::table() uniquement (zéro event Eloquent, zéro validation)
 *  - Bulk INSERT par chunks de CHUNK_SIZE lignes
 *  - Statuts pré-calculés → aucun UPDATE individuel
 *  - Astuce MAX(id) pour récupérer la plage d'IDs après chaque bulk INSERT
 *  - SET FOREIGN_KEY_CHECKS=0 pendant le seeding pour la vitesse
 *
 * Usage :
 *   php artisan db:seed --class=FakeDataSeeder
 */
class FakeDataSeeder extends Seeder
{
    // ─── Volume ────────────────────────────────────────────────────────────────
    // Commandes : volume massif sur fenêtre courte (stress-test)
    private const JOURS_COMMANDES    = 30;      // 30 j × 60k = 1,8M commandes/site
    private const COMMANDES_PAR_JOUR = 60_000;

    // Packings : fenêtre longue → couvre TOUS les filtres date du frontend :
    //   aujourd'hui / hier / cette semaine / semaine dernière /
    //   ce mois / mois dernier / cette année / année dernière
    private const JOURS_PACKINGS     = 400;     // ~13 mois en arrière (couvre année N-1)
    private const PACKINGS_PAR_JOUR  = 25;      // 25/j × 400j = 10 000 packings/site

    private const CLIENTS_TOTAL      = 10_000;  // clients par site
    private const CHUNK_SIZE         = 500;     // lignes par INSERT (optimal MySQL)

    // ─── Données de référence ──────────────────────────────────────────────────
    private const NOMS = [
        'DIALLO','BALDE','BARRY','CAMARA','SYLLA','CONDE','TOURE','SOW',
        'KEITA','BAH','TRAORE','KOUYATE','SOUMAH','BANGOURA','GUILAVOGUI',
        'KOIVOGUI','DOUMBOUYA','FOFANA','KOUROUMA','MAGASSOUBA',
    ];
    private const PRENOMS = [
        'Mamadou','Fatoumata','Alpha','Aissatou','Ibrahim','Kadiatou',
        'Thierno','Mariama','Ousmane','Abdoulaye','Aminata','Saran',
        'Elhadj','Seydou','Mohamed','Ibrahima','Oumar','Sekou','Aboubacar',
    ];
    private const QUARTIERS = [
        'Matoto','Kaloum','Dixinn','Ratoma','Matam','Bambeto',
        'Tombolia','Hafia','Kagbelen','Sonfonia','Lambanyi','Kaporo',
        'Madina','Cosa','Koloma','Enta','Cameroun','Dar-Es-Salam',
    ];
    private const MODES = ['especes','mobile_money','virement','cheque'];

    // ─── État interne ──────────────────────────────────────────────────────────
    private int    $seq   = 0;
    private string $runId = '';  // préfixe unique par run pour éviter conflits de références

    // =========================================================================
    //  POINT D'ENTRÉE
    // =========================================================================

    public function run(): void
    {
        ini_set('memory_limit', '1G');
        set_time_limit(0);

        $this->runId = date('ymdHis');

        $this->command->info('Stress-test seeder — 60 000 commandes/jour');
        $this->command->line('Run ID : ' . $this->runId);
        $this->command->newLine();

        $sites = DB::table('sites')->whereNull('deleted_at')->get();
        if ($sites->isEmpty()) {
            $this->command->error('Aucun site trouvé. Lancez d\'abord : php artisan db:seed');
            return;
        }

        $adminUser = DB::table('users')->whereNull('deleted_at')->first();
        if (! $adminUser) {
            $this->command->error('Aucun utilisateur trouvé.');
            return;
        }

        $this->disableConstraints();

        try {
            foreach ($sites as $site) {
                $this->command->info("─── Site : {$site->nom} ({$site->code}) ───");
                $this->seedSite($site, $adminUser->id);
                $this->command->newLine();
            }
        } finally {
            $this->enableConstraints();
        }

        $this->printSummary();
    }

    // =========================================================================
    //  COORDINATION PAR SITE
    // =========================================================================

    private function seedSite(object $site, int $userId): void
    {
        $siteId = $site->id;

        // ── Charger les références une seule fois ─────────────────────────────
        $vehicules = DB::table('vehicules')
            ->where('site_id', $siteId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->get();

        $produitIds = DB::table('produits')
            ->where(function ($q) use ($siteId) {
                $q->whereNull('site_id')->orWhere('site_id', $siteId);
            })
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        $prestataireIds = DB::table('prestataires')
            ->where('site_id', $siteId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        $livreur      = DB::table('livreurs')->where('site_id', $siteId)->whereNull('deleted_at')->first();
        $proprietaire = DB::table('proprietaires')->where('site_id', $siteId)->whereNull('deleted_at')->first();

        if ($vehicules->isEmpty() || empty($produitIds)) {
            $this->command->warn("   Pas de véhicules/produits — site ignoré.");
            return;
        }

        $vehiculeArr = $vehicules->toArray();

        // ── 1. Clients ────────────────────────────────────────────────────────
        $this->command->line('  [1/3] Clients...');
        $this->bulkCreateClients($siteId);

        // ── 2. Packings ───────────────────────────────────────────────────────
        if (! empty($prestataireIds)) {
            $this->command->line('  [2/3] Packings...');
            $this->ensureStockRouleaux($siteId);
            $this->bulkCreatePackings($siteId, $prestataireIds);
        }

        // ── 3. Commandes (le gros) ────────────────────────────────────────────
        $this->command->line('  [3/3] Commandes (' . number_format(self::COMMANDES_PAR_JOUR) . '/jour × ' . self::JOURS_COMMANDES . ' jours)...');
        $this->bulkCreateCommandes($siteId, $userId, $vehiculeArr, $produitIds, $livreur, $proprietaire);
    }

    // =========================================================================
    //  CLIENTS
    // =========================================================================

    private function bulkCreateClients(int $siteId): void
    {
        $count      = self::CLIENTS_TOTAL;
        $phoneBase  = 630_000_000 + ($siteId * $count);
        $noms       = self::NOMS;
        $prenoms    = self::PRENOMS;
        $quartiers  = self::QUARTIERS;
        $nbNoms     = count($noms);
        $nbPrenoms  = count($prenoms);
        $nbQuartiers = count($quartiers);
        $now        = now()->toDateTimeString();
        $runId      = $this->runId;

        $bar = $this->command->getOutput()->createProgressBar($count);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');

        for ($i = 0; $i < $count; $i += self::CHUNK_SIZE) {
            $rows      = [];
            $batchSize = min(self::CHUNK_SIZE, $count - $i);

            for ($j = 0; $j < $batchSize; $j++) {
                $idx  = $i + $j;
                $rows[] = [
                    'site_id'         => $siteId,
                    'nom'             => $noms[$idx % $nbNoms],
                    'prenom'          => $prenoms[$idx % $nbPrenoms],
                    'phone'           => '+224' . ($phoneBase + $idx),
                    'pays'            => 'Guinee',
                    'code_pays'       => 'GN',
                    'code_phone_pays' => '+224',
                    'ville'           => 'Conakry',
                    'quartier'        => $quartiers[$idx % $nbQuartiers],
                    'reference'       => 'CLI-' . $runId . '-S' . $siteId . '-' . str_pad($idx, 7, '0', STR_PAD_LEFT),
                    'is_active'       => 1,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            DB::table('clients')->insert($rows);
            $bar->advance($batchSize);
        }

        $bar->finish();
        $this->command->newLine();
    }

    // =========================================================================
    //  PACKINGS + VERSEMENTS
    // =========================================================================

    private function ensureStockRouleaux(int $siteId): void
    {
        $produitRouleauId = (int) DB::table('parametres')
            ->where('cle', 'PRODUIT_ROULEAU_ID')
            ->value('valeur');

        if (! $produitRouleauId) {
            return;
        }

        // Assez pour absorber tous les packings (jusqu'à 40 rouleaux chacun)
        $reserve = self::JOURS_PACKINGS * self::PACKINGS_PAR_JOUR * 45;
        DB::table('stocks')
            ->where('produit_id', $produitRouleauId)
            ->where('site_id', $siteId)
            ->update(['qte_stock' => $reserve]);
    }

    private function bulkCreatePackings(int $siteId, array $prestataireIds): void
    {
        $total  = self::JOURS_PACKINGS * self::PACKINGS_PAR_JOUR;
        $modes  = self::MODES;
        $nbModes = count($modes);
        $nbPrest = count($prestataireIds);
        $produitRouleauId = (int) DB::table('parametres')
            ->where('cle', 'PRODUIT_ROULEAU_ID')
            ->value('valeur');

        $bar = $this->command->getOutput()->createProgressBar(self::JOURS_PACKINGS);
        $bar->setFormat(' Jour %current%/%max% [%bar%] %percent:3s%%');

        for ($day = 0; $day < self::JOURS_PACKINGS; $day++) {
            $date    = Carbon::now()->subDays($day);
            $dateStr = $date->toDateTimeString();
            $dateDay = $date->toDateString();

            $packings   = [];
            $versements = [];
            $totalRouleaux = 0;

            for ($j = 0; $j < self::PACKINGS_PAR_JOUR; $j++) {
                $this->seq++;
                $nbRouleaux    = ($this->seq % 36) + 5; // 5–40
                $montant       = $nbRouleaux * 500;
                $totalRouleaux += $nbRouleaux;

                // Statut : 40 % payé / 30 % partiel / 30 % impayé (modulo déterministe)
                $statut = match ($this->seq % 10) {
                    0, 1, 2, 3 => 'payee',
                    4, 5, 6    => 'partielle',
                    default    => 'impayee',
                };

                $packings[] = [
                    'site_id'          => $siteId,
                    'prestataire_id'   => $prestataireIds[$this->seq % $nbPrest],
                    'reference'        => 'PACK-' . $this->runId . '-' . str_pad($this->seq, 10, '0', STR_PAD_LEFT),
                    'date'             => $dateDay,
                    'nb_rouleaux'      => $nbRouleaux,
                    'prix_par_rouleau' => 500,
                    'montant'          => $montant,
                    'statut'           => $statut,
                    'created_at'       => $dateStr,
                    'updated_at'       => $dateStr,
                ];
            }

            // INSERT packings → récupérer plage IDs
            $maxBefore = DB::table('packings')->max('id') ?? 0;
            DB::table('packings')->insert($packings);
            $maxAfter = DB::table('packings')->max('id') ?? 0;

            // Construire les versements à partir des IDs
            $packIdx = 0;
            for ($id = $maxBefore + 1; $id <= $maxAfter; $id++) {
                $pk     = $packings[$packIdx++];
                $statut = $pk['statut'];

                if ($statut === 'payee') {
                    $versements[] = [
                        'site_id'        => $siteId,
                        'packing_id'     => $id,
                        'reference'      => 'VERS-' . $this->runId . '-' . str_pad($id, 10, '0', STR_PAD_LEFT),
                        'montant'        => $pk['montant'],
                        'date_versement' => $dateDay,
                        'mode_paiement'  => $modes[$id % $nbModes],
                        'created_at'     => $dateStr,
                        'updated_at'     => $dateStr,
                    ];
                } elseif ($statut === 'partielle') {
                    $versements[] = [
                        'site_id'        => $siteId,
                        'packing_id'     => $id,
                        'reference'      => 'VERS-' . $this->runId . '-' . str_pad($id, 10, '0', STR_PAD_LEFT),
                        'montant'        => (int) ($pk['montant'] * 0.5),
                        'date_versement' => $dateDay,
                        'mode_paiement'  => $modes[$id % $nbModes],
                        'created_at'     => $dateStr,
                        'updated_at'     => $dateStr,
                    ];
                }
            }

            // INSERT versements (en chunks car potentiellement large)
            foreach (array_chunk($versements, 1000) as $chunk) {
                DB::table('versements')->insert($chunk);
            }
            $versements = [];

            // Décrémenter stock rouleaux en une seule requête
            if ($produitRouleauId && $totalRouleaux > 0) {
                DB::table('stocks')
                    ->where('produit_id', $produitRouleauId)
                    ->where('site_id', $siteId)
                    ->decrement('qte_stock', $totalRouleaux);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
    }

    // =========================================================================
    //  COMMANDES + LIGNES + FACTURES + ENCAISSEMENTS + COMMISSIONS + VERSEMENTS
    // =========================================================================

    private function bulkCreateCommandes(
        int     $siteId,
        int     $userId,
        array   $vehiculeArr,
        array   $produitIds,
        ?object $livreur,
        ?object $proprietaire
    ): void {
        $nbVehicules = count($vehiculeArr);
        $nbProduits  = count($produitIds);
        $modes       = self::MODES;
        $nbModes     = count($modes);
        $hasProprio  = $livreur && $proprietaire;

        // Précalculer vehicule → commission_active pour éviter l'accès objet en boucle
        $vehiculeCommission = [];
        $vehiculeTaux       = [];
        foreach ($vehiculeArr as $v) {
            $vehiculeCommission[$v->id] = (bool) ($v->commission_active ?? false);
            $vehiculeTaux[$v->id]       = (float) ($v->taux_commission_livreur ?? 60.0);
        }
        $vehiculeIds = array_column($vehiculeArr, 'id');

        $bar = $this->command->getOutput()->createProgressBar(self::JOURS_COMMANDES);
        $bar->setFormat(' Jour %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s% écoulé');

        for ($day = 0; $day < self::JOURS_COMMANDES; $day++) {
            $date        = Carbon::now()->subDays($day);
            $dateStr     = $date->toDateTimeString();
            $dateDay     = $date->toDateString();
            $remaining   = self::COMMANDES_PAR_JOUR;

            while ($remaining > 0) {
                $batchSize = min(self::CHUNK_SIZE, $remaining);
                $remaining -= $batchSize;

                // ── Phase 1 : Préparer les métadonnées du batch ───────────────
                $meta = []; // index → données partagées entre tables

                for ($j = 0; $j < $batchSize; $j++) {
                    $this->seq++;
                    $vId = $vehiculeIds[$this->seq % $nbVehicules];

                    // Nombre de lignes (1–3, rotation)
                    $nbLignes      = ($this->seq % 3) + 1;
                    $totalCommande = 0;
                    $lignesMeta    = [];

                    for ($l = 0; $l < $nbLignes; $l++) {
                        $pId       = $produitIds[($this->seq + $l * 7) % $nbProduits];
                        $qte       = ($this->seq % 90) + 10;          // 10–99
                        $prixUsine = (($this->seq % 35) + 25) * 100;  // 2 500–6 000
                        $prixVente = (int) ($prixUsine * 1.2);
                        $total     = $qte * $prixVente;
                        $totalCommande += $total;

                        $lignesMeta[] = [
                            'produit_id'          => $pId,
                            'qte'                 => $qte,
                            'prix_usine_snapshot' => $prixUsine,
                            'prix_vente_snapshot' => $prixVente,
                            'total_ligne'         => $total,
                        ];
                    }

                    // Statuts pré-calculés (aucun UPDATE individuel ensuite)
                    $factureStatut = match ($this->seq % 10) {
                        0, 1, 2, 3 => 'payee',
                        4, 5, 6    => 'partiel',
                        default    => 'impayee',
                    };

                    $hasComm = $hasProprio && $vehiculeCommission[$vId];

                    $commStatut = 'impayee';
                    if ($hasComm) {
                        $commStatut = match ($this->seq % 5) {
                            0, 1    => 'payee',
                            2       => 'partielle',
                            default => 'impayee',
                        };
                    }

                    $commandeStatut = ($factureStatut === 'payee' && (! $hasComm || $commStatut === 'payee'))
                        ? 'cloturee'
                        : 'active';

                    $meta[$j] = [
                        'vehicule_id'      => $vId,
                        'total'            => $totalCommande,
                        'lignes'           => $lignesMeta,
                        'commande_statut'  => $commandeStatut,
                        'facture_statut'   => $factureStatut,
                        'has_comm'         => $hasComm,
                        'comm_statut'      => $commStatut,
                        'taux'             => $vehiculeTaux[$vId],
                        'ref_seq'          => $this->seq,
                    ];
                }

                // ── Phase 2 : INSERT commandes_ventes ─────────────────────────
                $commandeRows = [];
                foreach ($meta as $m) {
                    $commandeRows[] = [
                        'site_id'        => $siteId,
                        'vehicule_id'    => $m['vehicule_id'],
                        'reference'      => 'VNT-' . $this->runId . '-' . str_pad($m['ref_seq'], 10, '0', STR_PAD_LEFT),
                        'total_commande' => $m['total'],
                        'statut'         => $m['commande_statut'],
                        'created_by'     => $userId,
                        'updated_by'     => $userId,
                        'created_at'     => $dateStr,
                        'updated_at'     => $dateStr,
                    ];
                }

                $cmdBefore = DB::table('commandes_ventes')->max('id') ?? 0;
                DB::table('commandes_ventes')->insert($commandeRows);
                // IDs alloués : $cmdBefore+1 … $cmdBefore+$batchSize

                // ── Phase 3 : INSERT factures_ventes ──────────────────────────
                $factureRows = [];
                foreach ($meta as $j => $m) {
                    $cmdId = $cmdBefore + $j + 1;
                    $factureRows[] = [
                        'site_id'           => $siteId,
                        'vehicule_id'       => $m['vehicule_id'],
                        'commande_vente_id' => $cmdId,
                        'reference'         => 'FAC-VNT-' . $this->runId . '-' . str_pad($m['ref_seq'], 10, '0', STR_PAD_LEFT),
                        'montant_brut'      => $m['total'],
                        'montant_net'       => $m['total'],
                        'statut_facture'    => $m['facture_statut'],
                        'created_at'        => $dateStr,
                        'updated_at'        => $dateStr,
                    ];
                }

                $facBefore = DB::table('factures_ventes')->max('id') ?? 0;
                DB::table('factures_ventes')->insert($factureRows);
                // IDs factures : $facBefore+1 … $facBefore+$batchSize

                // ── Phase 4 : INSERT lignes + encaissements ───────────────────
                $ligneRows       = [];
                $encaissRows     = [];

                foreach ($meta as $j => $m) {
                    $cmdId = $cmdBefore + $j + 1;
                    $facId = $facBefore + $j + 1;

                    foreach ($m['lignes'] as $ligne) {
                        $ligneRows[] = [
                            'commande_vente_id'   => $cmdId,
                            'produit_id'          => $ligne['produit_id'],
                            'qte'                 => $ligne['qte'],
                            'prix_usine_snapshot' => $ligne['prix_usine_snapshot'],
                            'prix_vente_snapshot' => $ligne['prix_vente_snapshot'],
                            'total_ligne'         => $ligne['total_ligne'],
                            'created_at'          => $dateStr,
                            'updated_at'          => $dateStr,
                        ];
                    }

                    if ($m['facture_statut'] === 'payee') {
                        $encaissRows[] = [
                            'facture_vente_id'  => $facId,
                            'montant'           => $m['total'],
                            'date_encaissement' => $dateDay,
                            'mode_paiement'     => $modes[$m['ref_seq'] % $nbModes],
                            'created_at'        => $dateStr,
                            'updated_at'        => $dateStr,
                        ];
                    } elseif ($m['facture_statut'] === 'partiel') {
                        $encaissRows[] = [
                            'facture_vente_id'  => $facId,
                            'montant'           => (int) ($m['total'] * 0.5),
                            'date_encaissement' => $dateDay,
                            'mode_paiement'     => $modes[$m['ref_seq'] % $nbModes],
                            'created_at'        => $dateStr,
                            'updated_at'        => $dateStr,
                        ];
                    }
                }

                // Lignes en sous-chunks (peuvent être 3× batchSize)
                foreach (array_chunk($ligneRows, 1000) as $chunk) {
                    DB::table('commande_vente_lignes')->insert($chunk);
                }

                if (! empty($encaissRows)) {
                    DB::table('encaissements_ventes')->insert($encaissRows);
                }

                // ── Phase 5 : INSERT commissions ──────────────────────────────
                $commRows  = [];
                $commMeta  = []; // pour les versements ensuite

                foreach ($meta as $j => $m) {
                    if (! $m['has_comm']) {
                        continue;
                    }

                    $cmdId             = $cmdBefore + $j + 1;
                    $montantComm       = (int) round($m['total'] * 0.05);
                    $partLivreur       = (int) round($montantComm * $m['taux'] / 100);
                    $partProprio       = $montantComm - $partLivreur;

                    $commRows[] = [
                        'site_id'                  => $siteId,
                        'commande_vente_id'        => $cmdId,
                        'vehicule_id'              => $m['vehicule_id'],
                        'livreur_id'               => $livreur->id,
                        'proprietaire_id'          => $proprietaire->id,
                        'taux_livreur_snapshot'    => $m['taux'],
                        'montant_commission_total' => $montantComm,
                        'part_livreur'             => $partLivreur,
                        'part_proprietaire'        => $partProprio,
                        'statut'                   => $m['comm_statut'],
                        'eligible_at'              => null,
                        'created_at'               => $dateStr,
                        'updated_at'               => $dateStr,
                    ];

                    $commMeta[] = [
                        'statut'       => $m['comm_statut'],
                        'part_livreur' => $partLivreur,
                        'part_proprio' => $partProprio,
                    ];
                }

                if (! empty($commRows)) {
                    $commBefore = DB::table('commission_ventes')->max('id') ?? 0;
                    DB::table('commission_ventes')->insert($commRows);

                    // ── Phase 6 : INSERT versements_commission ────────────────
                    $versCommRows = [];
                    foreach ($commMeta as $i => $cm) {
                        if ($cm['statut'] !== 'payee') {
                            continue;
                        }
                        $commId = $commBefore + $i + 1;
                        $versCommRows[] = [
                            'site_id'             => $siteId,
                            'commission_vente_id' => $commId,
                            'beneficiaire_type'   => 'livreur',
                            'beneficiaire_id'     => $livreur->id,
                            'montant_attendu'     => $cm['part_livreur'],
                            'montant_verse'       => $cm['part_livreur'],
                            'statut'              => 'effectue',
                            'verse_par'           => $userId,
                            'verse_at'            => $dateStr,
                            'created_at'          => $dateStr,
                            'updated_at'          => $dateStr,
                        ];
                        $versCommRows[] = [
                            'site_id'             => $siteId,
                            'commission_vente_id' => $commId,
                            'beneficiaire_type'   => 'proprietaire',
                            'beneficiaire_id'     => $proprietaire->id,
                            'montant_attendu'     => $cm['part_proprio'],
                            'montant_verse'       => $cm['part_proprio'],
                            'statut'              => 'effectue',
                            'verse_par'           => $userId,
                            'verse_at'            => $dateStr,
                            'created_at'          => $dateStr,
                            'updated_at'          => $dateStr,
                        ];
                    }

                    if (! empty($versCommRows)) {
                        foreach (array_chunk($versCommRows, 1000) as $chunk) {
                            DB::table('versements_commission')->insert($chunk);
                        }
                    }
                }
            } // end while chunks

            $bar->advance();
        } // end for days

        $bar->finish();
        $this->command->newLine();
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function disableConstraints(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET UNIQUE_CHECKS=0');
        }
    }

    private function enableConstraints(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET UNIQUE_CHECKS=1');
        }
    }

    private function printSummary(): void
    {
        $this->command->newLine();
        $this->command->info('=== Résumé base de données ===');
        $this->command->table(
            ['Table', 'Lignes totales'],
            [
                ['clients',               number_format(DB::table('clients')->count())],
                ['prestataires',          number_format(DB::table('prestataires')->count())],
                ['packings',              number_format(DB::table('packings')->count())],
                ['versements',            number_format(DB::table('versements')->count())],
                ['commandes_ventes',      number_format(DB::table('commandes_ventes')->count())],
                ['commande_vente_lignes', number_format(DB::table('commande_vente_lignes')->count())],
                ['factures_ventes',       number_format(DB::table('factures_ventes')->count())],
                ['encaissements_ventes',  number_format(DB::table('encaissements_ventes')->count())],
                ['commission_ventes',     number_format(DB::table('commission_ventes')->count())],
                ['versements_commission', number_format(DB::table('versements_commission')->count())],
            ]
        );
    }
}
