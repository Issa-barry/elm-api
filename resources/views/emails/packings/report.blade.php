<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 20px;">
        <h2 style="color: #1d4ed8; margin: 0;">Rapport des Packings</h2>
        <p style="color: #6b7280; font-size: 13px; margin: 4px 0 0;">
            Généré le {{ now()->format('d/m/Y à H:i') }}
        </p>
    </div>

    <p>Bonjour,</p>
    <p>Veuillez trouver en pièce jointe le rapport des packings avec le récapitulatif suivant :</p>

    <table style="width:100%; border-collapse:collapse; margin: 20px 0;">
        <tr style="background:#f0f4ff;">
            <td style="padding:10px 14px; border:1px solid #dbeafe; color:#374151;">Total packings</td>
            <td style="padding:10px 14px; border:1px solid #dbeafe; font-weight:bold; color:#1d4ed8;">
                {{ $summary['total_packings'] }}
            </td>
        </tr>
        <tr>
            <td style="padding:10px 14px; border:1px solid #e5e7eb; color:#374151;">Rouleaux</td>
            <td style="padding:10px 14px; border:1px solid #e5e7eb; font-weight:bold; color:#1d4ed8;">
                {{ number_format($summary['total_rouleaux']) }}
            </td>
        </tr>
        <tr style="background:#f0f4ff;">
            <td style="padding:10px 14px; border:1px solid #dbeafe; color:#374151;">Montant total</td>
            <td style="padding:10px 14px; border:1px solid #dbeafe; font-weight:bold; color:#1d4ed8;">
                {{ number_format($summary['total_montant']) }} GNF
            </td>
        </tr>
        <tr>
            <td style="padding:10px 14px; border:1px solid #e5e7eb; color:#374151;">Versé</td>
            <td style="padding:10px 14px; border:1px solid #e5e7eb; font-weight:bold; color:#16a34a;">
                {{ number_format($summary['total_verse']) }} GNF
            </td>
        </tr>
        <tr style="background:#f0f4ff;">
            <td style="padding:10px 14px; border:1px solid #dbeafe; color:#374151;">Restant dû</td>
            <td style="padding:10px 14px; border:1px solid #dbeafe; font-weight:bold; color:#dc2626;">
                {{ number_format($summary['total_restant']) }} GNF
            </td>
        </tr>
    </table>

    @php
        $hasFilters = !empty($filters['date_from']) || !empty($filters['date_to'])
                    || !empty($filters['statut']) || !empty($filters['prestataire_id']);
    @endphp

    @if($hasFilters)
    <p style="font-size:12px; color:#6b7280; background:#f9fafb; padding:8px 12px; border-left:3px solid #1d4ed8;">
        <strong>Filtres appliqués :</strong>
        @if(!empty($filters['date_from'])) Du {{ $filters['date_from'] }} @endif
        @if(!empty($filters['date_to'])) au {{ $filters['date_to'] }} @endif
        @if(!empty($filters['statut'])) — Statut : {{ ucfirst($filters['statut']) }} @endif
    </p>
    @endif

    <p style="color:#6b7280; font-size:12px; margin-top:24px; border-top:1px solid #e5e7eb; padding-top:12px;">
        Ce message est généré automatiquement par elm-api. Merci de ne pas y répondre.
    </p>

</body>
</html>
