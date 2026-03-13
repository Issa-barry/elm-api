<?php

namespace Database\Seeders;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Site;
use Illuminate\Database\Seeder;

class ProduitPackSeeder extends Seeder
{
    public function run(): void
    {
        // Produit global : usine_id = NULL, visible par toutes les usines
        $produit = Produit::withoutGlobalScopes()
            ->withTrashed()
            ->where('nom', 'Pack de 30')
            ->where('type', ProduitType::FABRICABLE)
            ->where('is_global', true)
            ->first();

        if (!$produit) {
            // Chercher l'ancien produit attaché à une usine (migration)
            $produit = Produit::withoutGlobalScopes()
                ->withTrashed()
                ->where('nom', 'Pack de 30')
                ->where('type', ProduitType::FABRICABLE)
                ->first();
        }

        if (!$produit) {
            $produit = new Produit([
                'nom'        => 'Pack de 30',
                'type'       => ProduitType::FABRICABLE,
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
        $produit->prix_usine  = 4500;
        $produit->prix_vente  = 5000;
        $produit->is_critique = true;
        $produit->statut      = ProduitStatut::ACTIF;

        // code_interne requis (NOT NULL) — backfill depuis code si absent
        if (empty($produit->code_interne)) {
            $produit->code_interne = $produit->code;
        }

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
                if ($existing->qte_stock < 1000) {
                    $existing->qte_stock = 1000;
                    $existing->saveQuietly();
                }
            }
        }
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
