<?php

namespace App\Notifications;

use App\Models\Produit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProduitRuptureStockNotification extends Notification
{
    use Queueable;

    /**
     * @param Produit $produit
     * @param string  $alertType  'rupture_stock' | 'low_stock'
     */
    public function __construct(
        private readonly Produit $produit,
        private readonly string $alertType = 'rupture_stock',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl     = rtrim(env('FRONTEND_URL', 'http://localhost:4200'), '/');
        $produitUrl      = "{$frontendUrl}/#/produits/produits-edit/{$this->produit->id}";
        $seuilEffectif   = $this->produit->low_stock_threshold;
        $isRupture       = $this->alertType === 'rupture_stock';

        $subject = $isRupture
            ? "Rupture de stock — {$this->produit->nom}"
            : "Stock faible — {$this->produit->nom}";

        $ligne = $isRupture
            ? "Le produit **{$this->produit->nom}** (réf. {$this->produit->code}) est en **rupture de stock**."
            : "Le produit **{$this->produit->nom}** (réf. {$this->produit->code}) a atteint le seuil d'alerte stock (seuil : **{$seuilEffectif}**).";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line($ligne)
            ->line("Stock actuel : **{$this->produit->qte_stock}**")
            ->action('Voir le produit', $produitUrl)
            ->line('Veuillez réapprovisionner ce produit dès que possible.')
            ->salutation('— ' . config('app.name'));
    }

    public function toArray(mixed $notifiable): array
    {
        $seuilEffectif = $this->produit->low_stock_threshold;
        $isRupture     = $this->alertType === 'rupture_stock';

        $message = $isRupture
            ? "Rupture de stock : {$this->produit->nom} (réf. {$this->produit->code}) — stock actuel : {$this->produit->qte_stock}"
            : "Stock faible : {$this->produit->nom} (réf. {$this->produit->code}) — stock actuel : {$this->produit->qte_stock} / seuil : {$seuilEffectif}";

        return [
            'type'           => $this->alertType,
            'produit_id'     => $this->produit->id,
            'nom'            => $this->produit->nom,
            'code'           => $this->produit->code,
            'qte_stock'      => $this->produit->qte_stock,
            'seuil_effectif' => $seuilEffectif,
            'statut'         => $this->produit->statut?->value,
            'message'        => $message,
            'date'           => now()->toISOString(),
        ];
    }
}
