<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Parametre extends Model
{
    /* =========================
       GROUPES
       ========================= */

    public const GROUPE_GENERAL = 'general';
    public const GROUPE_PACKING = 'packing';
    public const GROUPE_PAIEMENT = 'paiement';

    public const GROUPES = [
        self::GROUPE_GENERAL => 'Général',
        self::GROUPE_PACKING => 'Packing',
        self::GROUPE_PAIEMENT => 'Paiement',
    ];

    /* =========================
       TYPES
       ========================= */

    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_JSON = 'json';

    /* =========================
       CLÉS PRÉDÉFINIES
       ========================= */

    public const CLE_PRIX_ROULEAU_DEFAUT = 'prix_rouleau_defaut';
    public const CLE_PRODUIT_ROULEAU_ID = 'produit_rouleau_id';

    protected $table = 'parametres';

    protected $fillable = [
        'cle',
        'valeur',
        'type',
        'groupe',
        'description',
    ];

    /* =========================
       MÉTHODES STATIQUES
       ========================= */

    /**
     * Récupérer une valeur de paramètre avec cache
     */
    public static function get(string $cle, mixed $default = null): mixed
    {
        $cacheKey = "parametre_{$cle}";

        return Cache::remember($cacheKey, 3600, function () use ($cle, $default) {
            $parametre = self::where('cle', $cle)->first();

            if (!$parametre) {
                return $default;
            }

            return self::castValue($parametre->valeur, $parametre->type);
        });
    }

    /**
     * Définir une valeur de paramètre
     */
    public static function set(string $cle, mixed $valeur): bool
    {
        $parametre = self::where('cle', $cle)->first();

        if (!$parametre) {
            return false;
        }

        // Encoder la valeur si nécessaire
        if ($parametre->type === self::TYPE_JSON && is_array($valeur)) {
            $valeur = json_encode($valeur);
        } elseif ($parametre->type === self::TYPE_BOOLEAN) {
            $valeur = $valeur ? '1' : '0';
        }

        $parametre->valeur = (string) $valeur;
        $result = $parametre->save();

        // Invalider le cache
        Cache::forget("parametre_{$cle}");

        return $result;
    }

    /**
     * Caster la valeur selon le type
     */
    protected static function castValue(?string $valeur, string $type): mixed
    {
        if ($valeur === null) {
            return null;
        }

        return match ($type) {
            self::TYPE_INTEGER => (int) $valeur,
            self::TYPE_BOOLEAN => $valeur === '1' || $valeur === 'true',
            self::TYPE_JSON => json_decode($valeur, true),
            default => $valeur,
        };
    }

    /* =========================
       RACCOURCIS
       ========================= */

    /**
     * Récupérer le prix par rouleau par défaut
     */
    public static function getPrixRouleauDefaut(): int
    {
        return (int) self::get(self::CLE_PRIX_ROULEAU_DEFAUT, 500);
    }

    public static function getProduitRouleauId(): ?int
    {
        $id = self::get(self::CLE_PRODUIT_ROULEAU_ID);
        return $id ? (int) $id : null;
    }

    public static function getProduitRouleau(): ?Produit
    {
        $id = self::getProduitRouleauId();
        return $id ? Produit::find($id) : null;
    }

    /**
     * Récupérer tous les paramètres d'un groupe
     */
    public static function getByGroupe(string $groupe): array
    {
        $parametres = self::where('groupe', $groupe)->get();
        $result = [];

        foreach ($parametres as $parametre) {
            $result[$parametre->cle] = [
                'valeur' => self::castValue($parametre->valeur, $parametre->type),
                'type' => $parametre->type,
                'description' => $parametre->description,
            ];
        }

        return $result;
    }

    /**
     * Vider le cache de tous les paramètres
     */
    public static function clearCache(): void
    {
        $parametres = self::all();
        foreach ($parametres as $parametre) {
            Cache::forget("parametre_{$parametre->cle}");
        }
    }

    /* =========================
       ACCESSEURS
       ========================= */

    public function getValeurCasteeAttribute(): mixed
    {
        return self::castValue($this->valeur, $this->type);
    }

    public function getGroupeLabelAttribute(): string
    {
        return self::GROUPES[$this->groupe] ?? $this->groupe;
    }
}
