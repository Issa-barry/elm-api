<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organisation_id' => $this->organisation_id,
            'user_id'         => $this->user_id,
            'event_type'      => $this->event_type,
            'unit_price'      => $this->unit_price,
            'quantity'        => $this->quantity,
            'amount'          => $this->amount,
            'status'          => $this->status?->value,
            'status_label'    => $this->status?->label(),
            'occurred_at'     => $this->occurred_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),
            'organisation'    => $this->whenLoaded('organisation', fn () => [
                'id'      => $this->organisation->id,
                'nom'     => $this->organisation->nom,
                'code'    => $this->organisation->code,
                'forfait' => $this->organisation->relationLoaded('forfait') && $this->organisation->forfait
                    ? ['id' => $this->organisation->forfait->id, 'slug' => $this->organisation->forfait->slug, 'nom' => $this->organisation->forfait->nom]
                    : null,
            ]),
            'user'            => $this->whenLoaded('user', fn () => [
                'id'         => $this->user->id,
                'nom_complet' => $this->user->nom_complet,
                'reference'  => $this->user->reference,
            ]),
        ];
    }
}
