<?php

namespace App\Observers;

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

        // 2. Le stock doit avoir changé
        if (!$produit->wasChanged('qte_stock')) {
            return;
        }

        $ancienStock    = (int) $produit->getOriginal('qte_stock');
        $nouveauStock   = $produit->qte_stock;
        $seuilEffectif  = $produit->low_stock_threshold;

        // 3. Déterminer si le seuil est franchi à la baisse
        //    - Si seuil = 0 : alerte uniquement à rupture (nouveau <= 0, ancien > 0)
        //    - Si seuil > 0 : alerte dès que le stock passe de > seuil à <= seuil
        $seuilFranchi = $ancienStock > $seuilEffectif && $nouveauStock <= $seuilEffectif;

        if (!$seuilFranchi) {
            return;
        }

        // 4. Toggle global
        if (!Parametre::isNotificationsStockActives()) {
            return;
        }

        // 5. Anti-spam cooldown
        $cooldown = Parametre::getNotificationsStockCooldownMinutes();
        if ($produit->last_stockout_notified_at !== null
            && $produit->last_stockout_notified_at->diffInMinutes(now()) < $cooldown
        ) {
            return;
        }

        // 6. Destinataires : staff avec rôle admin ou manager
        $destinataires = User::where('type', UserType::STAFF->value)
            ->role(['admin', 'manager'])
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        // 7. Type d'alerte selon le stock final
        $alertType = $nouveauStock <= 0 ? 'rupture_stock' : 'low_stock';

        // 8. Envoi de la notification
        Notification::send($destinataires, new ProduitRuptureStockNotification($produit, $alertType));

        // 9. Mettre à jour last_stockout_notified_at sans déclencher l'observer à nouveau
        $produit->updateQuietly(['last_stockout_notified_at' => now()]);
    }
}
