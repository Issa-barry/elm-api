<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendPackingReportJob;
use App\Services\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackingReportEmailController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, SiteContext $siteContext)
    {
        try {
            $validated = $request->validate([
                'email'          => ['required', 'email'],
                'date_from'      => ['nullable', 'date'],
                'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],
                'prestataire_id' => ['nullable', 'integer'],
                'statut'         => ['nullable', Rule::enum(PackingStatut::class)],
            ], [
                'email.required'         => "L'adresse email est obligatoire.",
                'email.email'            => "L'adresse email est invalide.",
                'date_to.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
                'statut.enum'            => 'Statut invalide. Valeurs : impayee, partielle, payee, annulee.',
            ]);

            $siteId = $siteContext->getCurrentSiteId();

            if (!$siteId) {
                return $this->errorResponse('Contexte usine non résolu. Envoyez le header X-Site-Id.', null, 422);
            }

            // Extraire les filtres (sans l'email) pour le job
            $filters = array_filter(
                array_diff_key($validated, ['email' => null]),
                fn ($v) => $v !== null
            );

            SendPackingReportJob::dispatch($validated['email'], $filters, $siteId);

            return $this->successResponse(
                ['email' => $validated['email']],
                'Rapport en cours de génération. Il sera envoyé à ' . $validated['email'] . ' dans quelques instants.',
                202
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les données fournies sont invalides.');
        } catch (\Exception $e) {
            return $this->errorResponse("Erreur lors de la mise en file du rapport.", $e->getMessage());
        }
    }
}
