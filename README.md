// langkah langkah instalasi
1. git clone https://github.com/davidshdy17/GameHub.git
2. cd GameHub
3. composer install
4. Copy-Item .env.example .env
5. php artisan key:generate
6. buat database baru dan sesuaikan di file .env
7. php artisan migrate --seed
8. php artisan serve
