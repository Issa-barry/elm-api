<?php

namespace Database\Factories;

use App\Enums\PackingStatut;
use App\Enums\UsineType;
use App\Models\Packing;
use App\Models\Prestataire;
use App\Models\Usine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Packing>
 *
 * nb_rouleaux vaut 0 par défaut pour éviter la validation du produit_rouleau_id.
 * Utiliser ->avecRouleaux(n) dans les tests qui ont configuré le paramètre.
 */
class PackingFactory extends Factory
{
    protected $model = Packing::class;

    public function definition(): array
    {
        $usine = Usine::withoutGlobalScopes()->firstOrCreate(
            ['code' => 'TEST-DEFAULT'],
            ['nom' => 'Usine Test Default', 'type' => UsineType::USINE->value, 'statut' => 'active']
        );

        $prestataire = Prestataire::withoutGlobalScopes()->firstOrCreate(
            ['phone' => '+22460000099', 'usine_id' => $usine->id],
            [
                'nom'             => 'PRESTATAIRE TEST',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
                'pays'            => 'Guinee',
            ]
        );

        return [
            'usine_id'         => $usine->id,
            'prestataire_id'   => $prestataire->id,
            'date'             => today()->toDateString(),
            'nb_rouleaux'      => 0,
            'prix_par_rouleau' => 500,
            'statut'           => PackingStatut::IMPAYEE->value,
        ];
    }

    /** Packing avec nb_rouleaux > 0 (nécessite le paramètre produit_rouleau_id configuré). */
    public function avecRouleaux(int $nb = 10): static
    {
        return $this->state(['nb_rouleaux' => $nb]);
    }

    /** Packing annulé (stock non décrémenté). */
    public function annulee(): static
    {
        return $this->state(['statut' => PackingStatut::ANNULEE->value, 'nb_rouleaux' => 0]);
    }
}
