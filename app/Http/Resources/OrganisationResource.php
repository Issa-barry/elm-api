<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'nom'          => $this->nom,
            'code'         => $this->code,
            'statut'       => $this->statut?->value,
            'statut_label' => $this->statut?->label(),
            'email'        => $this->email,
            'phone'        => $this->phone,
            'pays'         => $this->pays,
            'ville'        => $this->ville,
            'quartier'     => $this->quartier,
            'adresse'      => $this->adresse,
            'description'  => $this->description,
            'sites_count'  => $this->whenCounted('sites'),
            'users_count'  => $this->whenCounted('users'),
            'sites'        => $this->whenLoaded('sites', fn () => $this->sites->map(fn ($s) => [
                'id'     => $s->id,
                'nom'    => $s->nom,
                'code'   => $s->code,
                'type'   => $s->type?->value,
                'statut' => $s->statut?->value,
            ])),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
