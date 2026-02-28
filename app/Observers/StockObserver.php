<?php

namespace App\Observers;

use App\Enums\UserType;
use App\Models\Parametre;
use App\Models\Stock;
use App\Models\User;
use App\Notifications\ProduitRuptureStockNotification;
use Illuminate\Support\Facades\Notification;

class StockObserver
{
    public function updated(Stock $stock): void
    {
        // 1. Le stock doit avoir changé
        if (!$stock->wasChanged('qte_stock')) {
            return;
        }

        // 2. Charger le produit associé
        $produit = $stock->produit;

        // 3. Uniquement pour les produits critiques
        if (!$produit || !$produit->is_critique) {
            return;
        }

        $ancienStock   = (int) $stock->getOriginal('qte_stock');
        $nouveauStock  = $stock->qte_stock;
        $seuilEffectif = $stock->low_stock_threshold;

        // 4. Seuil franchi à la baisse
        $seuilFranchi = $ancienStock > $seuilEffectif && $nouveauStock <= $seuilEffectif;

        if (!$seuilFranchi) {
            return;
        }

        // 5. Toggle global
        if (!Parametre::isNotificationsStockActives()) {
            return;
        }

        // 6. Anti-spam cooldown (tracké sur le produit)
        $cooldown = Parametre::getNotificationsStockCooldownMinutes();
        if ($produit->last_stockout_notified_at !== null
            && $produit->last_stockout_notified_at->diffInMinutes(now()) < $cooldown
        ) {
            return;
        }

        // 7. Destinataires : staff avec rôle admin ou manager
        $destinataires = User::where('type', UserType::STAFF->value)
            ->role(['admin', 'manager'])
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        // 8. Type d'alerte selon le stock final
        $alertType = $nouveauStock <= 0 ? 'rupture_stock' : 'low_stock';

        // 9. Envoi de la notification
        Notification::send(
            $destinataires,
            new ProduitRuptureStockNotification($produit, $alertType, $stock)
        );

        // 10. Mettre à jour last_stockout_notified_at sans déclencher l'observer
        $produit->updateQuietly(['last_stockout_notified_at' => now()]);
    }
}
