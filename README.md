
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