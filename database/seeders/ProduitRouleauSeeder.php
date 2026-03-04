<?php

namespace Database\Seeders;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Site;
use Illuminate\Database\Seeder;

class ProduitRouleauSeeder extends Seeder
{
    public function run(): void
    {
        // Produit global : usine_id = NULL, visible par toutes les usines
        $produit = Produit::withoutGlobalScopes()
            ->withTrashed()
            ->where('nom', 'Rouleau de packing')
            ->where('type', ProduitType::MATERIEL)
            ->where('is_global', true)
            ->first();

        if (!$produit) {
            // Chercher l'ancien produit attaché à une usine (migration)
            $produit = Produit::withoutGlobalScopes()
                ->withTrashed()
                ->where('nom', 'Rouleau de packing')
                ->where('type', ProduitType::MATERIEL)
                ->first();
        }

        if (!$produit) {
            $produit = new Produit([
                'nom'        => 'Rouleau de packing',
                'type'       => ProduitType::MATERIEL,
                'is_global' => true,
                'site_id'   => null,
            ]);
        } elseif ($produit->trashed()) {
            $produit->restore();
        }

        if (!$this->isValidNumericCode($produit->code ?? null)) {
            $produit->code = $this->generateNumericProductCode();
        }

        $produit->is_global  = true;
        $produit->site_id    = null;
        $produit->prix_achat  = $produit->prix_achat ?? 500;
        $produit->is_critique = true;
        $produit->statut      = ProduitStatut::ACTIF;
        $produit->save();

        // Créer une entrée stock pour chaque usine existante
        $sites = Site::withoutGlobalScopes()->get();
        foreach ($sites as $usine) {
            $existing = Stock::where('produit_id', $produit->id)
                ->where('site_id', $usine->id)
                ->first();

            if (!$existing) {
                Stock::create([
                    'produit_id' => $produit->id,
                    'site_id'   => $usine->id,
                    'qte_stock'  => 1000,
                ]);
            } else {
                // S'assurer qu'on a au moins 1000 en stock
                if ($existing->qte_stock < 1000) {
                    $existing->qte_stock = 1000;
                    $existing->saveQuietly();
                }
            }
        }

        Parametre::updateOrCreate(
            ['cle' => Parametre::CLE_PRODUIT_ROULEAU_ID],
            [
                'valeur'      => (string) $produit->id,
                'type'        => Parametre::TYPE_INTEGER,
                'groupe'      => Parametre::GROUPE_PACKING,
                'description' => 'ID du produit rouleau utilise pour le packing (gestion du stock)',
            ]
        );
    }

    private function isValidNumericCode(?string $code): bool
    {
        return is_string($code) && preg_match('/^\d{12}$/', $code) === 1;
    }

    private function generateNumericProductCode(): string
    {
        $prefix = now()->format('Ymd');

        $lastCode = Produit::withTrashed()
            ->where('code', 'like', $prefix . '%')
            ->whereRaw('LENGTH(code) = 12')
            ->whereRaw("code REGEXP '^[0-9]{12}$'")
            ->orderByDesc('code')
            ->value('code');

        $nextSequence = 1;
        if ($lastCode) {
            $nextSequence = ((int) substr($lastCode, -4)) + 1;
        }

        if ($nextSequence > 9999) {
            $nextSequence = 9999;
        }

        return $prefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
