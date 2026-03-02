<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Packings</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a2e; }

        .header { padding: 16px 0 12px; border-bottom: 2px solid #1d4ed8; margin-bottom: 16px; }
        .header h1 { font-size: 16px; color: #1d4ed8; font-weight: bold; }
        .header .meta { color: #6b7280; margin-top: 4px; font-size: 9px; }

        .filters { margin-bottom: 14px; background: #f0f4ff; padding: 7px 10px; border-left: 3px solid #1d4ed8; font-size: 9px; color: #374151; }
        .filters strong { color: #1d4ed8; }

        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 16px; }
        .kpis td { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 12px; text-align: center; }
        .kpis .lbl { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
        .kpis .val { font-size: 14px; font-weight: bold; margin-top: 3px; }
        .kpis .val-blue  { color: #1d4ed8; }
        .kpis .val-green { color: #16a34a; }
        .kpis .val-red   { color: #dc2626; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data thead th {
            background: #1d4ed8; color: #fff;
            padding: 6px 8px; text-align: left;
            font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px;
        }
        table.data tbody tr:nth-child(even) { background: #f8fafc; }
        table.data tbody td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }

        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-impayee  { background: #fee2e2; color: #dc2626; }
        .badge-partielle{ background: #fef3c7; color: #d97706; }
        .badge-payee    { background: #dcfce7; color: #16a34a; }
        .badge-annulee  { background: #f1f5f9; color: #64748b; }

        .footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 8px; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <h1>Rapport des Packings</h1>
    <div class="meta">Généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

@php
    $hasFilters = !empty($filters['date_from']) || !empty($filters['date_to'])
                || !empty($filters['statut']) || !empty($filters['prestataire_id']);
@endphp

@if($hasFilters)
<div class="filters">
    <strong>Filtres appliqués :</strong>
    @if(!empty($filters['date_from'])) &nbsp;Du <strong>{{ $filters['date_from'] }}</strong> @endif
    @if(!empty($filters['date_to'])) &nbsp;au <strong>{{ $filters['date_to'] }}</strong> @endif
    @if(!empty($filters['statut'])) &nbsp;— Statut : <strong>{{ ucfirst($filters['statut']) }}</strong> @endif
</div>
@endif

<table class="kpis">
    <tr>
        <td>
            <div class="lbl">Packings</div>
            <div class="val val-blue">{{ $summary['total_packings'] }}</div>
        </td>
        <td>
            <div class="lbl">Rouleaux</div>
            <div class="val val-blue">{{ number_format($summary['total_rouleaux']) }}</div>
        </td>
        <td>
            <div class="lbl">Montant total</div>
            <div class="val val-blue">{{ number_format($summary['total_montant']) }} GNF</div>
        </td>
        <td>
            <div class="lbl">Versé</div>
            <div class="val val-green">{{ number_format($summary['total_verse']) }} GNF</div>
        </td>
        <td>
            <div class="lbl">Restant</div>
            <div class="val val-red">{{ number_format($summary['total_restant']) }} GNF</div>
        </td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Réf.</th>
            <th>Prestataire</th>
            <th>Téléphone</th>
            <th>Date</th>
            <th>Rouleaux</th>
            <th>Montant</th>
            <th>Versé</th>
            <th>Restant</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse($packings as $packing)
        @php
            $statutVal = $packing->statut instanceof \App\Enums\PackingStatut
                ? $packing->statut->value
                : (string) $packing->statut;
        @endphp
        <tr>
            <td>{{ $packing->reference ?? '—' }}</td>
            <td>{{ $packing->prestataire?->nom_complet ?? '—' }}</td>
            <td>{{ $packing->prestataire?->phone ?? '—' }}</td>
            <td>{{ \Carbon\Carbon::parse($packing->date)->format('d/m/Y') }}</td>
            <td style="text-align:center;">{{ $packing->nb_rouleaux }}</td>
            <td style="text-align:right;">{{ number_format($packing->montant) }} GNF</td>
            <td style="text-align:right; color:#16a34a;">{{ number_format($packing->montant_verse) }} GNF</td>
            <td style="text-align:right; color:#dc2626;">{{ number_format($packing->montant_restant) }} GNF</td>
            <td>
                <span class="badge badge-{{ $statutVal }}">{{ $packing->statut_label }}</span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9" style="text-align:center; color:#9ca3af; padding:20px;">
                Aucun packing trouvé pour ces filtres.
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    elm-api &mdash; Document généré automatiquement &mdash; {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
