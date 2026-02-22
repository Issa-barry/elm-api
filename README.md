
### 1) Deploiement initiale CONCEPTION
php artisan migrate:fresh --seed

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

---

## WORKFLOW SIMPLIFIÉ (recommandé)

Le workflow simplifié supprime la gestion des sorties véhicules. Tout se crée en une page.

### Flux simplifié

```
POST /v1/livraisons/one-shot                              → créer véhicule + propriétaire + livreur (one-shot)
POST /v1/livraisons/factures                              → créer une facture liée au véhicule
POST /v1/encaissements-livraisons                         → encaisser (contrôle dépassement)
POST /v1/livraisons/factures/{id}/deductions              → ajouter une déduction avant paiement commission
GET  /v1/livraisons/factures/{id}/commissions/calcul      → calculer la commission brut/net
POST /v1/livraisons/factures/{id}/commissions/paiement    → payer la commission (facture doit être "payee")
```

### One-shot : création véhicule + propriétaire + livreur

**POST** `/api/v1/livraisons/one-shot`
**Permission :** `vehicules.create`
**Content-Type :** `multipart/form-data`

Si le propriétaire ou le livreur existe déjà (même `phone`), l'enregistrement est réutilisé — pas de doublon.

**Payload :**
```
vehicule[nom_vehicule]             = "Camion Alpha"
vehicule[immatriculation]          = "GN-1234-A"
vehicule[type_vehicule]            = camion|moto|tricycle|pick_up|autre
vehicule[capacite_packs]           = 200
vehicule[mode_commission]          = forfait|pourcentage
vehicule[valeur_commission]        = 1000
vehicule[pourcentage_proprietaire] = 60
vehicule[pourcentage_livreur]      = 40
proprietaire[nom]                  = "DIALLO"
proprietaire[prenom]               = "Mamadou"
proprietaire[phone]                = "+224620000001"
proprietaire[pays]                 = "Guinée"
proprietaire[ville]                = "Conakry"
proprietaire[quartier]             = "Matam"
livreur[nom]                       = "BALDE"
livreur[prenom]                    = "Alpha"
livreur[phone]                     = "+224621000001"
photo                              = <fichier image jpg/jpeg/png/webp, max 3 Mo>
```

**Réponse 201 :**
```json
{
  "data": {
    "vehicule":     { "id": 1, "nom_vehicule": "Camion Alpha", "photo_url": "...", "proprietaire": {...}, "livreurPrincipal": {...} },
    "proprietaire": { "id": 1, "nom": "DIALLO", "prenom": "Mamadou", "phone": "+224620000001" },
    "livreur":      { "id": 1, "nom": "BALDE", "prenom": "Alpha", "phone": "+224621000001" }
  }
}
```

**Règles :**
- `pourcentage_proprietaire + pourcentage_livreur = 100`
- `immatriculation` unique par usine
- `phone` propriétaire/livreur normalisé avant recherche (suppression espaces, tirets, etc.)

### Facture de livraison (workflow simplifié)

**POST** `/api/v1/livraisons/factures`
**Permission :** `factures-livraisons.create`

Snapshots de commission capturés automatiquement depuis le véhicule à la création.

**Payload :**
```json
{
  "vehicule_id":  1,
  "packs_charges": 150,
  "montant_brut":  75000,
  "montant_net":   75000
}
```

**Réponse 201 :**
```json
{
  "data": {
    "id": 1,
    "reference": "FAC-LIV-20260221-0001",
    "vehicule_id": 1,
    "packs_charges": 150,
    "montant_brut": "75000.00",
    "montant_net": "75000.00",
    "statut_facture": "emise",
    "snapshot_mode_commission": "forfait",
    "snapshot_valeur_commission": "1000.00",
    "snapshot_pourcentage_proprietaire": "60.00",
    "snapshot_pourcentage_livreur": "40.00",
    "vehicule": { "id": 1, "nom_vehicule": "Camion Alpha" }
  }
}
```

### Encaissements (identique aux deux workflows)

**POST** `/api/v1/encaissements-livraisons`
**Permission :** `encaissements.create`

```json
{
  "facture_livraison_id": 1,
  "montant": 40000,
  "date_encaissement": "2026-02-21",
  "mode_paiement": "especes"
}
```

Statut facture mis à jour automatiquement : `emise` → `partiellement_payee` → `payee`

### Déductions de commission (par facture)

**POST** `/api/v1/livraisons/factures/{id}/deductions`
**Permission :** `commissions.create`

```json
{
  "cible":          "proprietaire",
  "type_deduction": "carburant",
  "montant":        5000,
  "commentaire":    "Plein aller-retour"
}
```

### Calcul commission (par facture)

**GET** `/api/v1/livraisons/factures/{id}/commissions/calcul`
**Permission :** `commissions.read`

Calcul depuis les snapshots de la facture (jamais depuis le véhicule actuel).

**Réponse 200 :**
```json
{
  "data": {
    "packs_charges":           150,
    "mode_commission":         "forfait",
    "commission_brute_totale": 150000,
    "part_proprietaire_brute": 90000,
    "part_livreur_brute":      60000,
    "deductions_proprietaire": 5000,
    "deductions_livreur":      0,
    "part_proprietaire_nette": 85000,
    "part_livreur_nette":      60000
  }
}
```

### Paiement commission (par facture)

**POST** `/api/v1/livraisons/factures/{id}/commissions/paiement`
**Permission :** `commissions.create`

**Règle :** la facture doit être au statut `payee` (422 sinon). Un seul paiement par facture (409 si doublon).

**Payload :**
```json
{ "date_paiement": "2026-02-21" }
```

**Réponse 201 :**
```json
{
  "data": {
    "statut":                  "paye",
    "commission_brute_totale": "150000.00",
    "part_proprietaire_nette": "85000.00",
    "part_livreur_nette":      "60000.00"
  }
}
```

### Routes simplifiées — tableau récapitulatif

| Méthode | Route | Permission | Description |
|---------|-------|-----------|-------------|
| POST | `/v1/livraisons/one-shot` | `vehicules.create` | Créer véhicule + propriétaire + livreur |
| GET | `/v1/livraisons/factures` | `factures-livraisons.read` | Liste des factures simplifiées |
| POST | `/v1/livraisons/factures` | `factures-livraisons.create` | Créer une facture |
| GET | `/v1/livraisons/factures/{id}` | `factures-livraisons.read` | Détail d'une facture |
| POST | `/v1/livraisons/factures/{id}/deductions` | `commissions.create` | Ajouter une déduction |
| GET | `/v1/livraisons/factures/{id}/commissions/calcul` | `commissions.read` | Calculer la commission |
| POST | `/v1/livraisons/factures/{id}/commissions/paiement` | `commissions.create` | Payer la commission |

### Règles métier — workflow simplifié

| Règle | Vérification |
|-------|-------------|
| `pourcentage_proprio + pourcentage_livreur = 100` | FormRequest one-shot |
| Immatriculation unique par usine | FormRequest one-shot |
| Photo obligatoire à la création | FormRequest one-shot |
| Réutilisation proprietaire/livreur par `phone` | Controller VehiculeOneShot |
| Snapshots commission figés à la création facture | Controller FactureSimplifieeStore |
| Encaissements cumulés ≤ `montant_net` | Controller EncaissementStore |
| Commission = facture `payee` seulement | Controller PaiementCommissionFactureStore |
| Un seul paiement commission par facture | Controller PaiementCommissionFactureStore (409) |
| Déductions bloquées si commission déjà payée | Controller DeductionFactureStore (409) |

---

## WORKFLOW CLASSIQUE (conservé pour rétrocompatibilité)

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