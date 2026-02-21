
### 1) Deploiement initiale CONCEPTION

cd ~/domains/usine-eau-api.fr/public_html

composer2 update

php artisan key:generate

php artisan migrate:fresh --seed

php artisan storage:link

php artisan optimize:clear

php artisan optimize

### 2) Deploiement initiale (1er deployment PROD)
cd domains/usine-eau-api.fr/public_html
composer2 install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache

php artisan storage:link


### MEP (MISE EN PRODUCTION )
 

 # AUTRE /
 composer2 update
php artisan migrate
php artisan db:seed
php artisan optimize:clear
php artisan optimize

---

## API — Endpoints Users

### Vérification disponibilité téléphone

**POST** `/api/v1/users/check-phone`

Vérifie si un numéro de téléphone est déjà utilisé, **sans créer d'utilisateur**.
Le numéro est normalisé (suppression espaces, tirets, etc.) avant vérification — même logique que lors de la création.

**Authentification :** `Bearer <token>` (staff uniquement, permission `users.create`)
**Headers :** `X-Usine-Id: <id>` (si contexte usine requis)

**Corps de la requête :**
```json
{
  "phone": "+224 62-000-00-01",
  "code_phone_pays": "+224"
}
```

**Réponse 200 — disponible :**
```json
{
  "success": true,
  "message": "Ce numéro de téléphone est disponible.",
  "data": {
    "available": true,
    "normalized_phone": "+224620000001"
  }
}
```

**Réponse 200 — déjà utilisé :**
```json
{
  "success": true,
  "message": "Ce numéro de téléphone est déjà utilisé.",
  "data": {
    "available": false,
    "normalized_phone": "+224620000001"
  }
}
```

**Réponse 422 — validation KO :**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "phone": ["Le numéro de téléphone est obligatoire."]
  }
}
```

---

## API — Module Livraison

> Middleware commun : `auth:sanctum` + `user.type:staff` + `usine.context`
> Permission `photo` : upload via `multipart/form-data`

### Flux complet (départ → facture → encaissement → commission)

```
POST /v1/vehicules                        → créer un véhicule (avec photo)
POST /v1/sorties-vehicules                → créer un départ (snapshot commission)
PATCH /v1/sorties-vehicules/{id}/retour   → enregistrer le retour
PATCH /v1/sorties-vehicules/{id}/cloture  → clôturer la sortie
POST /v1/factures-livraisons              → créer la facture (montant_brut)
POST /v1/encaissements-livraisons         → encaisser (contrôle dépassement)
GET  /v1/commissions/{sortieId}/calcul    → calculer brut/net avec déductions
POST /v1/commissions/{sortieId}/paiement  → valider et enregistrer le paiement
```

### Propriétaires

| Méthode | Route | Permission |
|---------|-------|-----------|
| GET | `/v1/proprietaires` | `proprietaires.read` |
| POST | `/v1/proprietaires` | `proprietaires.create` |
| GET | `/v1/proprietaires/{id}` | `proprietaires.read` |
| PUT | `/v1/proprietaires/{id}` | `proprietaires.update` |
| DELETE | `/v1/proprietaires/{id}` | `proprietaires.delete` |

### Livreurs

| Méthode | Route | Permission |
|---------|-------|-----------|
| GET | `/v1/livreurs` | `livreurs.read` |
| POST | `/v1/livreurs` | `livreurs.create` |
| GET | `/v1/livreurs/{id}` | `livreurs.read` |
| PUT | `/v1/livreurs/{id}` | `livreurs.update` |
| DELETE | `/v1/livreurs/{id}` | `livreurs.delete` |

### Véhicules (avec photo)

| Méthode | Route | Permission |
|---------|-------|-----------|
| GET | `/v1/vehicules` | `vehicules.read` |
| POST | `/v1/vehicules` | `vehicules.create` |
| GET | `/v1/vehicules/{id}` | `vehicules.read` |
| POST | `/v1/vehicules/{id}` | `vehicules.update` (multipart) |
| DELETE | `/v1/vehicules/{id}` | `vehicules.delete` |

**Création véhicule — payload `multipart/form-data` :**
```
nom_vehicule, immatriculation, type_vehicule (camion|moto|tricycle|pick_up|autre),
capacite_packs, proprietaire_id, livreur_principal_id (nullable),
pris_en_charge_par_usine (bool), mode_commission (forfait|pourcentage),
valeur_commission, pourcentage_proprietaire, pourcentage_livreur,
photo (jpg|jpeg|png|webp, max 3 Mo) — OBLIGATOIRE
```

**Règles :** `pourcentage_proprietaire + pourcentage_livreur = 100` (sauf `pris_en_charge_par_usine = true`).
`immatriculation` unique par usine.

**Réponse :**
```json
{
  "data": {
    "id": 1,
    "nom_vehicule": "Camion Alpha",
    "immatriculation": "GN-1234-A",
    "photo_path": "vehicules/abc123.jpg",
    "photo_url": "http://localhost/storage/vehicules/abc123.jpg",
    "proprietaire": { "id": 1, "nom": "DIALLO", "prenom": "Mamadou" }
  }
}
```

### Sorties véhicules

| Méthode | Route | Permission |
|---------|-------|-----------|
| GET | `/v1/sorties-vehicules` | `sorties.read` |
| POST | `/v1/sorties-vehicules` | `sorties.create` |
| GET | `/v1/sorties-vehicules/{id}` | `sorties.read` |
| PATCH | `/v1/sorties-vehicules/{id}/retour` | `sorties.update` |
| PATCH | `/v1/sorties-vehicules/{id}/cloture` | `sorties.update` |

**Création départ :**
```json
{ "vehicule_id": 1, "livreur_id_effectif": 2, "packs_charges": 100 }
```
**Règles :** `packs_charges <= capacite_packs`. Un seul départ `en_cours` par véhicule. Snapshots commission capturés automatiquement.

**Enregistrement retour :**
```json
{ "packs_retour": 10 }
```

### Factures de livraison

| Méthode | Route | Permission |
|---------|-------|-----------|
| GET | `/v1/factures-livraisons` | `factures-livraisons.read` |
| POST | `/v1/factures-livraisons` | `factures-livraisons.create` |
| GET | `/v1/factures-livraisons/{id}` | `factures-livraisons.read` |

**Création :**
```json
{ "sortie_vehicule_id": 1, "montant_brut": 50000, "montant_net": 50000 }
```
Référence auto-générée (`FAC-LIV-YYYYMMDD-NNNN`). Statut auto : `emise` → `partiellement_payee` → `payee`.

### Encaissements

| Méthode | Route | Permission |
|---------|-------|-----------|
| GET | `/v1/encaissements-livraisons` | `encaissements.read` |
| POST | `/v1/encaissements-livraisons` | `encaissements.create` |

```json
{
  "facture_livraison_id": 1,
  "montant": 25000,
  "date_encaissement": "2026-02-21",
  "mode_paiement": "especes"
}
```
**Règle :** cumul des encaissements ≤ `montant_net` facture.

### Déductions de commission

| Méthode | Route | Permission |
|---------|-------|-----------|
| POST | `/v1/deductions-commissions` | `commissions.create` |

```json
{
  "sortie_vehicule_id": 1,
  "cible": "proprietaire",
  "type_deduction": "carburant",
  "montant": 5000,
  "commentaire": "Plein carburant aller-retour"
}
```

### Commissions

| Méthode | Route | Permission |
|---------|-------|-----------|
| GET | `/v1/commissions/{sortieId}/calcul` | `commissions.read` |
| POST | `/v1/commissions/{sortieId}/paiement` | `commissions.create` |

**Calcul (GET) — réponse :**
```json
{
  "data": {
    "packs_livres": 90,
    "mode_commission": "forfait",
    "commission_brute_totale": 90000,
    "part_proprietaire_brute": 54000,
    "part_livreur_brute": 36000,
    "deductions_proprietaire": 5000,
    "deductions_livreur": 2000,
    "part_proprietaire_nette": 49000,
    "part_livreur_nette": 34000
  }
}
```
Paiement possible uniquement sur sortie `cloture`. Un seul paiement par sortie.

### Règles métier principales

| Règle | Vérification |
|-------|-------------|
| `packs_charges <= capacite_packs` | Controller SortieVehicule |
| Un seul départ `en_cours` par véhicule | Controller SortieVehicule |
| `pourcentage_proprio + pourcentage_livreur = 100` | FormRequest Vehicule |
| Encaissements cumulés ≤ `montant_net` | Controller Encaissement |
| Paiement commission = sortie clôturée uniquement | Controller PaiementCommission |
| Immatriculation unique par usine | Migration + FormRequest |
| Photo obligatoire à la création | FormRequest Vehicule |
| Ancien fichier photo supprimé à la mise à jour | Controller VehiculeUpdate |