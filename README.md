# Smart ID Dashboard

Aplikasi dashboard pintar untuk manajemen internal data Master.

## Persiapan Database & Data Master

Aplikasi ini telah dilengkapi dengan data master bawaan (Region, Provinsi, Kabupaten/Kota, dan data awal lainnya). Untuk memulai, silakan jalankan perintah migrasi dan seeder berikut:

```bash
php artisan migrate --seed
```

Perintah di atas akan secara otomatis membuat seluruh struktur tabel yang diperlukan beserta pengisian data master awal ke dalam database Anda.
