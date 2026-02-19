<?php

namespace App\Notifications;

use App\Models\Produit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProduitRuptureStockNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Produit $produit) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'rupture_stock',
            'produit_id' => $this->produit->id,
            'nom'        => $this->produit->nom,
            'code'       => $this->produit->code,
            'qte_stock'  => $this->produit->qte_stock,
            'statut'     => $this->produit->statut?->value,
            'message'    => "Rupture de stock : {$this->produit->nom} (réf. {$this->produit->code}) — stock actuel : {$this->produit->qte_stock}",
            'date'       => now()->toISOString(),
        ];
    }

    /**
     * Optionnel : activer mail en ajoutant 'mail' dans via() et en décommentant.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject("⚠️ Rupture de stock — {$this->produit->nom}")
    //         ->line("Le produit **{$this->produit->nom}** (réf. {$this->produit->code}) est en rupture de stock.")
    //         ->line("Stock actuel : {$this->produit->qte_stock}")
    //         ->action('Voir le produit', url("/produits/{$this->produit->id}"));
    // }
}
