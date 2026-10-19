// langkah langkah instalasi
1. git clone https://github.com/davidshdy17/GameHub.git
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

Slug lain yang tersedia: `stardew-valley`, `cyberpunk-2077`, `control-ultimate-edition`, `the-witcher-3-wild-hunt`, `celeste`, `dead-cells`, dan `sifu`. Perintah mengambil metadata, harga regional Indonesia, serta persyaratan PC dari Steam, lalu menyimpannya ke MySQL. Integrasi saat ini memakai Steam sebagai sumber utama. Jika Steam tidak memberi harga, satu permintaan CheapShark untuk game tersebut dicoba; harga CheapShark disimpan dalam USD dan link diarahkan melalui halaman deal CheapShark.

## Sinkronisasi offer Epic Games

Epic GraphQL menolak permintaan server-side dari fingerprint TLS non-browser (Cloudflare 403), sehingga adapter Epic membaca katalog publik egdata.app (`/search/v2/search`) yang memuat harga regional Epic dalam IDR. Hanya kecocokan judul persis atau slug produk kanonik yang diterima; hasil seperti "Hades II", pre-purchase, bundle, dan harga non-IDR ditolak.

Sinkronkan satu game:

```bash
php artisan games:sync-epic --game=hades
```

Atau seluruh katalog (satu permintaan per game dengan jeda 750 ms):

```bash
php artisan games:sync-epic
```

Perintah ini membuat/memperbarui offer `Epic Games` (`source=epic`) berisi harga, diskon, dan URL produk Epic, lalu mencatat riwayat harga nyata. Game yang tidak tersedia di katalog Epic regional Indonesia akan dilewati tanpa menghapus offer lama. Frontend menampilkan harga/diskon Epic dari respons `/api/games` dan `/api/games/{slug}`; bila offer Epic belum ada, kartu Epic tetap memakai identitas game dari API dengan keterangan "Harga belum tersedia".

Tidak ada mode `--all` untuk Steam/CheapShark. Jangan menjalankan banyak perintah CheapShark sekaligus. Setelah tersimpan, frontend mengambil data melalui endpoint Laravel `/api/games`, `/api/games/{slug}`, dan `/api/games/{slug}/price-history`. Alamat kontak CheapShark bersifat opsional dan dapat diatur melalui `CHEAPSHARK_CONTACT_EMAIL`.