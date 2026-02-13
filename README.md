
### Deploye
composer2 update


php artisan optimize:clear

php artisan optimize

## Deploy init  
php artisan migrate

php artisan db:seed --class=ParametreSeeder

php artisan db:seed --class=ProduitRouleauSeeder

php artisan db:seed --class=RoleAndPermissionSeeder



