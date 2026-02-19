
### 1) Deploiement initiale (1er deployment)
cd domains/usine-eau-api.fr/public_html

composer2 update
php artisan migrate
php artisan db:seed
php artisan optimize:clear
php artisan optimize

 

## 1TER Seeders individuels (si besoin)
php artisan db:seed --class=RoleAndPermissionSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=ParametreSeeder
php artisan db:seed --class=ProduitRouleauSeeder

ou 
## 2 Caching
php artisan optimize:clear
php artisan optimize


### MEP (MISE EN PRODUCTION )
composer2 update
php artisan optimize:clear 
php artisan optimize

php artisan optimize:clear
php artisan optimize