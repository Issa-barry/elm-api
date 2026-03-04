<?php

namespace App\Jobs;

use App\Mail\PackingReportMail;
use App\Models\Packing;
use App\Services\SiteContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPackingReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly array  $filters,
        public readonly int    $siteId,
    ) {}

    public function handle(SiteContext $siteContext): void
    {
        // Reconstituer le contexte usine dans le worker (pas de requête HTTP)
        $siteContext->setCurrentSiteId($this->usineId);

        $packings = Packing::with(['prestataire', 'versements'])
            ->when($this->filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($this->filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($this->filters['prestataire_id'] ?? null, fn ($q, $v) => $q->where('prestataire_id', $v))
            ->when($this->filters['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->orderBy('date', 'desc')
            ->get();

        $summary = [
            'total_packings' => $packings->count(),
            'total_rouleaux' => $packings->sum('nb_rouleaux'),
            'total_montant'  => $packings->sum('montant'),
            'total_verse'    => $packings->sum('montant_verse'),
            'total_restant'  => $packings->sum('montant_restant'),
        ];

        // Générer le PDF en mémoire — pas de fichier temporaire
        $pdfContent = Pdf::loadView('pdf.packings.report', [
            'packings' => $packings,
            'summary'  => $summary,
            'filters'  => $this->filters,
        ])->setPaper('a4', 'landscape')->output();

        Mail::to($this->recipientEmail)
            ->send(new PackingReportMail($pdfContent, $this->filters, $summary));
    }
}
