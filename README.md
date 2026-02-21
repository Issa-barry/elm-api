
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