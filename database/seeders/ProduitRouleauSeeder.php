<?php

namespace Database\Seeders;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Usine;
use Illuminate\Database\Seeder;

class ProduitRouleauSeeder extends Seeder
{
    public function run(): void
    {
        // Dans un seeder il n'y a pas de contexte HTTP : HasUsineScope ne peut pas
        // auto-remplir usine_id. On rattache le produit à l'usine opérationnelle
        // de référence (ELM-USN-01), créée par la backfill migration 200004.
        $usine = Usine::where('code', 'ELM-USN-01')->first()
            ?? Usine::where('type', 'usine')->first();

        if (!$usine) {
            $this->command->warn('ProduitRouleauSeeder : aucune usine opérationnelle trouvée, seeder ignoré.');
            return;
        }

        $produit = Produit::withoutGlobalScopes()
            ->withTrashed()
            ->where('nom', 'Rouleau de packing')
            ->where('type', ProduitType::MATERIEL)
            ->where('usine_id', $usine->id)
            ->first();

        if (!$produit) {
            $produit = new Produit([
                'nom'      => 'Rouleau de packing',
                'type'     => ProduitType::MATERIEL,
                'qte_stock' => 0,
                'usine_id' => $usine->id,
            ]);
        } elseif ($produit->trashed()) {
            $produit->restore();
        }

        if (!$this->isValidNumericCode($produit->code ?? null)) {
            $produit->code = $this->generateNumericProductCode();
        }

        $produit->prix_achat  = $produit->prix_achat ?? 500;
        $produit->is_critique = true;
        $produit->statut      = $produit->qte_stock > 0 ? ProduitStatut::ACTIF : ProduitStatut::RUPTURE_STOCK;
        $produit->save();

        Parametre::updateOrCreate(
            ['cle' => Parametre::CLE_PRODUIT_ROULEAU_ID],
            [
                'valeur' => (string) $produit->id,
                'type' => Parametre::TYPE_INTEGER,
                'groupe' => Parametre::GROUPE_PACKING,
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
