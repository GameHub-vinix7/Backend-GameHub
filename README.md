// langkah langkah instalasi
1. git clone https://github.com/GameHub-vinix7/Backend-GameHub.git
2. cd GameHub
3. composer install
4. Copy-Item .env.example .env
5. php artisan key:generate
6. buat database baru dan sesuaikan di file .env
7. php artisan migrate --seed
8. php artisan serve

## Sinkronisasi data game asli

Seeder menyiapkan daftar game dan Steam App ID sebagai target sinkronisasi; data deskripsi/genre/tanggal/gambar di sana hanya nilai awal dan akan diganti saat Steam berhasil. Seeder tidak lagi membuat harga, riwayat, atau persyaratan sistem palsu. Setelah migrasi dan seed, sinkronkan **satu game per perintah** agar API eksternal tidak dipanggil secara massal:

```bash
php artisan games:sync-real-data --game=hades
```

Slug lain yang tersedia: `stardew-valley`, `cyberpunk-2077`, `control-ultimate-edition`, `the-witcher-3-wild-hunt`, `celeste`, `dead-cells`, dan `sifu`. Perintah mengambil metadata, harga regional Indonesia, serta persyaratan PC dari Steam, lalu menyimpannya ke MySQL. Integrasi saat ini memakai Steam sebagai sumber utama; API Epic tidak dipanggil langsung. Jika Steam tidak memberi harga, satu permintaan CheapShark untuk game tersebut dicoba; harga CheapShark disimpan dalam USD dan link diarahkan melalui halaman deal CheapShark.

Tidak ada mode `--all` atau sinkronisasi terjadwal CheapShark. Jangan menjalankan banyak perintah CheapShark sekaligus. Setelah tersimpan, frontend mengambil data melalui endpoint Laravel `/api/games`, `/api/games/{slug}`, dan `/api/games/{slug}/price-history`. Alamat kontak CheapShark bersifat opsional dan dapat diatur melalui `CHEAPSHARK_CONTACT_EMAIL`.

## Tambah data game
1. Daftarkan entri baru ke database\seeders\DatabaseSeeder.php
contoh = ['title' => 'Hollow Knight: Silksong','slug' => 'hollow-knight-silksong','steam_app_id' => 1030300,'description' => 'Sekuel Hollow Knight, platformer aksi dengan dunia yang luas dan penuh rahasia.','genre' => 'Action Platformer','release_date' => '2024-10-12','cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQQm9Z8XonoaF_CVq8qPZcHTSheLN6D50ZsV-8uW0ZfKA&s=10','popularity' => 0,],
2. Simpan dan jalankan php artisan db:seed --class=DatabaseSeeder
3. php artisan games:sync-real-data --game='game slug' ganti game slug sesuai dengan game yang ditabahkan
contoh : php artisan games:sync-real-data --game=hollow-knight-silksong
