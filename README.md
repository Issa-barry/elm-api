
### Deploiement initiale (1er deployment)
php artisan migrate
php artisan db:seed

## Seeders individuels (si besoin)
php artisan db:seed --class=RoleAndPermissionSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=ParametreSeeder
php artisan db:seed --class=ProduitRouleauSeeder


### MEP (MISE EN PRODUCTION )
composer2 update

php artisan optimize:clear

php artisan optimize