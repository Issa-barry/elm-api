<?php

namespace Database\Seeders;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Models\Produit;
use App\Models\Usine;
use Illuminate\Database\Seeder;

class ProduitPackSeeder extends Seeder
{
    public function run(): void
    {
        $usine = Usine::where('nom', 'Usine de kaka')->first()
            ?? Usine::where('type', 'usine')->first();

        if (!$usine) {
            $this->command->warn('ProduitPackSeeder : aucune usine opérationnelle trouvée, seeder ignoré.');
            return;
        }

        $produit = Produit::withoutGlobalScopes()
            ->withTrashed()
            ->where('nom', 'Pack de 30')
            ->where('type', ProduitType::FABRICABLE)
            ->where('usine_id', $usine->id)
            ->first();

        if (!$produit) {
            $produit = new Produit([
                'nom'      => 'Pack de 30',
                'type'     => ProduitType::FABRICABLE,
                'usine_id' => $usine->id,
            ]);
        } elseif ($produit->trashed()) {
            $produit->restore();
        }

        if (!$this->isValidNumericCode($produit->code ?? null)) {
            $produit->code = $this->generateNumericProductCode();
        }

        $produit->prix_usine  = 4500;
        $produit->prix_vente  = 5000;
        $produit->qte_stock   = 1000;
        $produit->is_critique = true;
        $produit->statut      = ProduitStatut::ACTIF;
        $produit->save();
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
