# Realtime Chat & Notifikasi (Laravel Reverb)

Dokumen ini menjelaskan cara kerja dan cara menjalankan fitur realtime (chat instan & notifikasi bell) pada aplikasi ini. Ditulis supaya bisa dipahami baik oleh developer, DevOps, maupun yang belum familiar dengan websocket.

## Apa itu ini?

Sebelumnya, chat dan notifikasi hanya diperbarui lewat *polling* (cek ulang tiap beberapa detik). Sekarang keduanya realtime menggunakan **Laravel Reverb** — server websocket resmi dari Laravel yang jalan di server sendiri (tidak pakai layanan pihak ketiga).

Konsekuensinya: ada **satu proses tambahan** yang harus selalu hidup di server, terpisah dari proses web (Nginx/PHP-FPM) yang biasa.

## Pemisahan Channel

Supaya data chat dan notifikasi tidak tercampur, masing-masing punya jalur (channel) sendiri:

| Channel | Isi | Siapa yang bisa akses |
|---|---|---|
| `conversation.{id}` | Pesan di satu percakapan chat | Hanya 2 orang yang terlibat di percakapan itu |
| `chat.{userId}` | Notifikasi ringan "ada pesan baru masuk" untuk memperbarui daftar kontak | Hanya pemilik akun itu sendiri |
| `App.Models.User.{id}` | Notifikasi umum (bell icon) — sama sekali tidak berhubungan dengan chat | Hanya pemilik akun itu sendiri |

Aturan akses tiap channel didefinisikan di `routes/channels.php`.

## Menjalankan di Lokal / Staging

1. Pastikan `.env` sudah berisi konfigurasi berikut (sudah otomatis terisi oleh proses instalasi):
   ```
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=...
   REVERB_APP_KEY=...
   REVERB_APP_SECRET=...
   REVERB_HOST=localhost
   REVERB_PORT=8080
   REVERB_SCHEME=http
   ```
2. Compile ulang aset frontend (sekali saja, atau setiap kali ada perubahan JS):
   ```bash
   npm run build
   ```
3. Jalankan server websocket-nya (proses ini harus tetap terbuka di terminal/tab terpisah):
   ```bash
   php artisan reverb:start
   ```
4. Buka aplikasi seperti biasa (`php artisan serve` atau lewat Laragon). Chat dan notifikasi sekarang realtime.

Kalau langkah 3 tidak dijalankan, aplikasi tetap berfungsi normal — hanya saja chat/notifikasi kembali terasa "diam" sampai halaman di-refresh manual, karena tidak ada server yang mengirim update secara instan.

## Menjalankan di Production

Proses `php artisan reverb:start` **tidak boleh** dijalankan manual di terminal seperti di lokal — kalau terminal ditutup atau server restart, proses ikut mati dan realtime berhenti. Karena itu, di production proses ini harus di-supervisi supaya otomatis menyala kembali kalau mati.

### 1. Jalankan lewat Supervisor

Contoh konfigurasi `/etc/supervisor/conf.d/reverb.conf`:

```ini
[program:reverb]
command=php /path/ke/project/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/ke/project/storage/logs/reverb.log
```

Lalu:
```bash
supervisorctl reread
supervisorctl update
supervisorctl start reverb
```

### 2. Reverse proxy lewat Nginx

Port websocket (default `8080`) **tidak boleh** diakses langsung dari internet. Harus lewat Nginx dengan domain & SSL yang sama dengan aplikasi utama:

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

Pastikan port `8080` ditutup di firewall server dari akses luar — hanya boleh diakses oleh Nginx di server yang sama.

### 3. Kapan harus restart proses Reverb?

| Perubahan | Perlu restart Reverb? |
|---|---|
| Kode Livewire/Controller biasa | Tidak |
| `routes/channels.php` | Ya |
| `config/reverb.php` atau `.env` (`REVERB_*`, `BROADCAST_CONNECTION`) | Ya |
| Kalau proses crash | Otomatis oleh Supervisor, tidak perlu manual |

Jika proses direstart, user yang sedang online akan terputus sesaat lalu otomatis tersambung lagi dalam hitungan detik — tidak perlu refresh manual.

## Ringkasan Keamanan

- Semua channel privat divalidasi lewat sesi login (`routes/channels.php`) — tidak sembarang orang bisa mendengarkan percakapan orang lain.
- Trafik websocket dari luar tetap lewat HTTPS/SSL (via Nginx), bukan koneksi polos.
- Port asli Reverb tidak pernah diekspos langsung ke publik.

## Troubleshooting Singkat

- **Chat/notifikasi tidak realtime**: cek apakah proses `reverb:start` sedang berjalan (`supervisorctl status reverb`).
- **Error di browser console soal koneksi websocket gagal**: cek konfigurasi reverse proxy Nginx dan pastikan `VITE_REVERB_*` di `.env` sesuai domain yang dipakai, lalu `npm run build` ulang.
- **Setelah ganti `routes/channels.php` tidak berefek**: restart proses Reverb, karena file ini hanya dibaca sekali saat proses dimulai.
