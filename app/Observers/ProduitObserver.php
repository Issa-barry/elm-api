<?php

namespace App\Observers;

use App\Enums\ProduitStatut;
use App\Enums\UserType;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\User;
use App\Notifications\ProduitRuptureStockNotification;
use Illuminate\Support\Facades\Notification;

class ProduitObserver
{
    public function updated(Produit $produit): void
    {
        // 1. Uniquement pour les produits critiques
        if (!$produit->is_critique) {
            return;
        }

        // 2. Vérifier si le produit vient de passer en rupture
        $stockVientDeZero = $produit->wasChanged('qte_stock')
            && $produit->qte_stock <= 0
            && $produit->getOriginal('qte_stock') > 0;

        $statutVientDeRupture = $produit->wasChanged('statut')
            && $produit->statut === ProduitStatut::RUPTURE_STOCK
            && $produit->getOriginal('statut') !== ProduitStatut::RUPTURE_STOCK->value;

        if (!$stockVientDeZero && !$statutVientDeRupture) {
            return;
        }

        // 3. Toggle global
        if (!Parametre::isNotificationsStockActives()) {
            return;
        }

        // 4. Anti-spam cooldown
        $cooldown = Parametre::getNotificationsStockCooldownMinutes();
        if ($produit->last_stockout_notified_at !== null
            && $produit->last_stockout_notified_at->diffInMinutes(now()) < $cooldown
        ) {
            return;
        }

        // 5. Destinataires : staff avec rôle admin ou manager
        $destinataires = User::where('type', UserType::STAFF->value)
            ->role(['admin', 'manager'])
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        // 6. Envoi de la notification
        Notification::send($destinataires, new ProduitRuptureStockNotification($produit));

        // 7. Mettre à jour last_stockout_notified_at sans déclencher l'observer à nouveau
        $produit->updateQuietly(['last_stockout_notified_at' => now()]);
    }
}
