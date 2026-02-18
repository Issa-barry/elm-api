<?php

namespace App\Models;

use App\Enums\PrestataireType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Prestataire extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_MACHINISTE = PrestataireType::MACHINISTE->value;
    public const TYPE_MECANICIEN = PrestataireType::MECANICIEN->value;
    public const TYPE_CONSULTANT = PrestataireType::CONSULTANT->value;
    public const TYPE_FOURNISSEUR = PrestataireType::FOURNISSEUR->value;

    public const TYPES = PrestataireType::LABELS;

    protected $fillable = [
        'nom',
        'prenom',
        'raison_sociale',
        'phone',
        'email',
        'pays',
        'code_pays',
        'code_phone_pays',
        'ville',
        'quartier',
        'adresse',
        'specialite',
        'type',
        'tarif_horaire',
        'notes',
        'reference',
        'is_active',
    ];

    protected $appends = [
        'nom_complet',
        'type_label',
    ];

    protected function casts(): array
    {
        return [
            'type' => PrestataireType::class,
            'is_active' => 'boolean',
            'tarif_horaire' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Prestataire $prestataire) {
            $prestataire->prepareForPersistence(true);
        });

        static::updating(function (Prestataire $prestataire) {
            $prestataire->prepareForPersistence(false);
        });
    }

    protected function prepareForPersistence(bool $isCreating): void
    {
        if ($isCreating && empty($this->reference)) {
            $this->reference = self::generateReference();
        }

        if (empty($this->type)) {
            $this->type = PrestataireType::FOURNISSEUR;
        }

        $this->code_pays = self::normalizeIsoCountryCode($this->code_pays) ?? 'GN';
        $this->code_phone_pays = self::normalizeDialCode($this->code_phone_pays) ?? '+224';
        $this->phone = self::normalizePhoneE164($this->phone, $this->code_phone_pays);

        if (empty($this->pays)) {
            $this->pays = 'Guinee';
        }

        if ($this->tarif_horaire !== null) {
            $this->tarif_horaire = max(0, (int) $this->tarif_horaire);
        }
    }

    protected static function generateReference(): string
    {
        do {
            $reference = 'PREST-' . now()->format('Ymd') . '-' . Str::upper((string) Str::ulid());
        } while (self::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    public function setNomAttribute($value): void
    {
        $normalized = self::normalizeIdentity($value);
        $this->attributes['nom'] = $normalized !== null
            ? mb_strtoupper($normalized, 'UTF-8')
            : null;
    }

    public function setPrenomAttribute($value): void
    {
        $normalized = self::normalizeIdentity($value);
        $this->attributes['prenom'] = $normalized !== null
            ? mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8')
            : null;
    }

    public function setRaisonSocialeAttribute($value): void
    {
        $normalized = self::normalizeIdentity($value);
        $this->attributes['raison_sociale'] = $normalized !== null
            ? mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8')
            : null;
    }

    public function setPhoneAttribute($value): void
    {
        $dialCode = $this->attributes['code_phone_pays'] ?? $this->code_phone_pays ?? '+224';
        $this->attributes['phone'] = self::normalizePhoneE164($value, $dialCode);
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = self::normalizeEmail($value);
    }

    public function setPaysAttribute($value): void
    {
        $normalized = self::normalizeIdentity($value);
        $this->attributes['pays'] = $normalized !== null
            ? mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8')
            : null;
    }

    public function setCodePaysAttribute($value): void
    {
        $this->attributes['code_pays'] = self::normalizeIsoCountryCode($value);
    }

    public function setCodePhonePaysAttribute($value): void
    {
        $this->attributes['code_phone_pays'] = self::normalizeDialCode($value);
    }

    public function setVilleAttribute($value): void
    {
        $this->attributes['ville'] = self::normalizeLocation($value);
    }

    public function setQuartierAttribute($value): void
    {
        $this->attributes['quartier'] = self::normalizeLocation($value);
    }

    public function setAdresseAttribute($value): void
    {
        $this->attributes['adresse'] = self::normalizeText($value);
    }

    public function setSpecialiteAttribute($value): void
    {
        $this->attributes['specialite'] = self::normalizeText($value);
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = self::normalizeText($value);
    }

    public function setTarifHoraireAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['tarif_horaire'] = null;
            return;
        }

        $this->attributes['tarif_horaire'] = max(0, (int) $value);
    }

    public function setTypeAttribute($value): void
    {
        if ($value instanceof PrestataireType) {
            $this->attributes['type'] = $value->value;
            return;
        }

        if (is_string($value)) {
            $this->attributes['type'] = strtolower(trim($value));
            return;
        }

        $this->attributes['type'] = $value;
    }

    public function getNomCompletAttribute(): ?string
    {
        if (!empty($this->raison_sociale)) {
            return $this->raison_sociale;
        }

        $fullName = trim(implode(' ', array_filter([$this->prenom, $this->nom])));

        return $fullName !== '' ? $fullName : null;
    }

    public function getTypeLabelAttribute(): string
    {
        if ($this->type instanceof PrestataireType) {
            return $this->type->label();
        }

        $value = is_string($this->type) ? $this->type : null;
        if ($value) {
            $enum = PrestataireType::tryFrom($value);
            if ($enum) {
                return $enum->label();
            }
        }

        return '';
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeParSpecialite(Builder $query, string $specialite): Builder
    {
        return $query->where('specialite', 'like', "%{$specialite}%");
    }

    public function scopeParType(Builder $query, PrestataireType|string $type): Builder
    {
        $value = $type instanceof PrestataireType ? $type->value : $type;

        return $query->where('type', $value);
    }

    public function scopeMachinistes(Builder $query): Builder
    {
        return $query->where('type', PrestataireType::MACHINISTE->value);
    }

    public function packings(): HasMany
    {
        return $this->hasMany(Packing::class);
    }

    public function isPersonneMorale(): bool
    {
        return !empty($this->raison_sociale);
    }

    public function isPersonnePhysique(): bool
    {
        return !$this->isPersonneMorale();
    }

    public static function getTypes(): array
    {
        return PrestataireType::labels();
    }

    public static function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? strtolower($normalized) : null;
    }

    public static function normalizeIsoCountryCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/[^A-Z]/', '', $normalized) ?? '';

        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 2);
    }

    public static function normalizeDialCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
        }

        $digits = preg_replace('/\D/', '', $normalized) ?? '';
        if ($digits === '') {
            return null;
        }

        return '+' . substr($digits, 0, 4);
    }

    public static function normalizePhoneE164(mixed $value, mixed $dialCode = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $phone = trim((string) $value);
        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (!str_starts_with($phone, '+')) {
            $localDigits = preg_replace('/\D/', '', $phone) ?? '';
            $localDigits = ltrim($localDigits, '0');
            $countryCode = self::normalizeDialCode($dialCode) ?? '+224';

            if ($localDigits === '') {
                return null;
            }

            $phone = $countryCode . $localDigits;
        }

        $digits = preg_replace('/\D/', '', ltrim($phone, '+')) ?? '';
        if ($digits === '') {
            return null;
        }

        return '+' . $digits;
    }

    public static function normalizeLocation(mixed $value): ?string
    {
        $normalized = self::normalizeIdentity($value);

        return $normalized !== null
            ? mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8')
            : null;
    }

    public static function normalizeText(mixed $value): ?string
    {
        return self::normalizeIdentity($value);
    }

    private static function normalizeIdentity(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized !== '' ? $normalized : null;
    }
}
