# Smart ID Dashboard

Aplikasi dashboard pintar untuk manajemen internal data Master.

## Prasyarat System

*   PHP >= 8.4
*   Composer
*   Node.js & NPM
*   Database (PostgreSQL / MySQL)

## Langkah Instalasi

Ikuti langkah-langkah di bawah ini untuk menjalankan aplikasi di lingkungan lokal Anda:

### 1. Unduh Dependensi PHP
Pasang seluruh paket pustaka PHP yang diperlukan menggunakan Composer:
```bash
composer install
```

### 2. Konfigurasi Environment
Salin file konfigurasi lingkungan `.env.example` ke `.env`:
```bash
cp .env.example .env
```
Setelah disalin, silakan buka file `.env` tersebut dan sesuaikan konfigurasi koneksi database Anda (seperti `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`).

### 3. Generate Application Key
Jalankan perintah berikut untuk menggenerasi kunci aplikasi Laravel:
```bash
php artisan key:generate
```

### 4. Migrasi & Seed Data Master
Inisialisasi struktur database beserta seluruh data master bawaan (Region, Provinsi, Kabupaten/Kota, dan data awal lainnya):
```bash
php artisan migrate --seed
```

### 5. Pasang & Kompilasi Aset Frontend
Pasang modul Node.js dan jalankan server development Vite:
```bash
npm install
npm run dev
```

### 6. Jalankan Server Aplikasi
Jalankan server lokal Laravel:
```bash
php artisan serve
```
Aplikasi Anda kini siap diakses melalui tautan [http://127.0.0.1:8000](http://127.0.0.1:8000).
