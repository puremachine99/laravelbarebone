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

## Dua Jalur Koneksi yang Berbeda

Ini bagian yang paling sering bikin bingung, jadi disebutkan eksplisit di sini:

- **Browser → Reverb**: lewat internet publik, harus lewat domain asli + HTTPS (`wss://domain-kalian/app/...`), diteruskan Nginx ke proses Reverb di `127.0.0.1:8080`.
- **Aplikasi Laravel (PHP) → Reverb**: ini terjadi *di dalam server yang sama*, jadi sebaiknya langsung ke `127.0.0.1:8080`, tidak perlu keluar-masuk lewat domain publik/Nginx/Cloudflare segala.

Karena itu ada 2 set variabel `.env` yang terpisah — lihat bagian **Setup Production** di bawah. Kalau cuma pakai satu set (nilai yang sama buat keduanya), aplikasi tetap bisa jalan, tapi jadi bergantung pada Nginx/Cloudflare yang harus selalu bisa "memutar balik" ke dirinya sendiri — lebih rapuh dan lebih lambat dibanding koneksi langsung.

## Setup Awal (Clone Baru)

Paket Reverb sudah tercantum di `composer.json` dan `package.json`, jadi `composer install` dan `npm install` standar sudah otomatis membawanya.

1. Salin `.env.example` ke `.env`.
2. Buat kunci koneksi websocket secara otomatis:
   ```bash
   php artisan reverb:install --no-interaction
   ```
   Ini menambahkan `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, dan `VITE_REVERB_*` ke `.env` dengan nilai acak yang aman dipakai langsung.

   > Kalau perintah ini dijalankan lebih dari sekali, kadang muncul baris duplikat. Buka `.env`, sisakan satu set saja per variabel.
3. Compile aset frontend supaya nilai `VITE_REVERB_*` ikut terbaca browser:
   ```bash
   npm run build
   ```
4. Jalankan migrasi seperti biasa — tabel chat (`conversations`, `messages`, dst.) sudah termasuk.
5. Jalankan dua proses bersamaan (dua tab terminal):
   ```bash
   php artisan serve
   php artisan reverb:start
   ```

Default `.env.example` (`REVERB_HOST=localhost`) sudah cukup untuk kebutuhan lokal ini — tidak perlu ubah apa-apa lagi.

## Setup Production / Staging (server publik)

Ini bagian yang beda dari lokal, karena browser mengakses lewat domain publik, bukan `localhost`.

### 1. Isi `.env` — dua set variabel, jangan disamakan

```env
# Dipakai BROWSER (lewat domain publik, HTTPS)
REVERB_HOST=domain-kalian.com
REVERB_PORT=443
REVERB_SCHEME=https

# Dipakai APLIKASI LARAVEL sendiri buat publish event (langsung ke proses Reverb, tanpa lewat Nginx)
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_SERVER_SCHEME=http
```

**Jangan pakai tanda kutip** di nilai-nilai ini (`REVERB_HOST=domain-kalian.com`, bukan `REVERB_HOST="domain-kalian.com"`). Satu karakter kutip yang tidak seimbang saja bisa membuat browser gagal connect dengan pesan error yang membingungkan.

Setelah mengubah `.env`, **wajib** build ulang — nilai `VITE_REVERB_*` di-"bakar" ke file JS saat build, bukan dibaca saat runtime:
```bash
npm run build
```

### 2. Jalankan proses Reverb, tetap hidup walau SSH ditutup

Kalau punya akses `sudo`, pakai **systemd** (bawaan OS, tidak perlu install apa-apa):

```bash
sudo nano /etc/systemd/system/reverb.service
```
```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
Type=simple
User=namauser
WorkingDirectory=/path/ke/project
ExecStart=/path/ke/php artisan reverb:start
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```
```bash
sudo systemctl daemon-reload
sudo systemctl enable reverb
sudo systemctl start reverb
sudo systemctl status reverb
```

**Kalau tidak punya akses `sudo`** (kasus umum di shared hosting/Virtualmin), pakai watchdog cron milik user sendiri — sudah terbukti jalan di server staging kami:

```bash
nano /path/ke/project/reverb-watchdog.sh
```
```bash
#!/bin/bash
cd /path/ke/project
if ! pgrep -f "artisan reverb:start" > /dev/null; then
    nohup /path/ke/php artisan reverb:start >> storage/logs/reverb.log 2>&1 &
fi
```
```bash
chmod +x /path/ke/project/reverb-watchdog.sh
/path/ke/project/reverb-watchdog.sh
crontab -e
```
Tambahkan baris ini di crontab:
```
* * * * * /path/ke/project/reverb-watchdog.sh
```
Cron mengecek tiap 1 menit — kalau proses Reverb mati (crash/server reboot), otomatis dihidupkan lagi. Delay pemulihan maksimal 1 menit, cukup aman untuk kebutuhan internal seperti ini.

### 3. Reverse proxy Nginx — wajib, butuh akses admin server

Tanpa ini, browser tidak akan pernah bisa menembus ke proses Reverb, walau `.env` dan proses Reverb sudah benar. **Ini satu-satunya bagian yang butuh akses root/admin** — kalau kalian developer tanpa akses `sudo`, minta DevOps menambahkan blok ini ke server block domain kalian:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```
Lalu reload Nginx (`sudo systemctl reload nginx`). Port `8080` tidak boleh dibuka langsung ke internet — hanya diakses Nginx di server yang sama.

Kalau domain kalian di belakang Cloudflare (proxy/"orange cloud"), pastikan WebSocket tidak diblokir — pada dasarnya Cloudflare mendukung WebSocket secara default, biasanya tidak perlu setting tambahan.

## Cara Menguji Apakah Sudah Realtime

1. Buka aplikasi di dua browser berbeda (atau satu Incognito), login dengan **2 akun berbeda**.
2. Buka `/chat` di kedua browser, mulai percakapan antar 2 akun itu.
3. Kirim pesan dari satu akun — harus langsung muncul di browser satunya **tanpa refresh**.
4. Untuk notifikasi: picu aksi yang menghasilkan notifikasi, cek ikon lonceng di penerima — badge harus bertambah **tanpa refresh**.
5. Buka Console browser (F12) — kalau ada baris merah `WebSocket connection ... failed`, berarti ada yang belum benar (lihat Troubleshooting).

## Kapan Harus Restart Proses Reverb?

| Perubahan | Perlu restart Reverb? |
|---|---|
| Kode Livewire/Controller biasa | Tidak |
| `routes/channels.php` | Ya |
| `config/reverb.php`, `config/broadcasting.php`, atau `.env` (`REVERB_*`, `BROADCAST_CONNECTION`) | Ya |
| Ubah `.env` lalu perlu `npm run build` juga (kalau menyangkut `VITE_REVERB_*`) | Ya + build ulang |
| Proses crash | Otomatis (systemd/cron watchdog) |

Kalau proses direstart, user yang sedang online cuma terputus sesaat lalu otomatis tersambung lagi — tidak perlu refresh manual.

## Ringkasan Keamanan

- Semua channel privat divalidasi lewat sesi login (`routes/channels.php`) — tidak sembarang orang bisa mendengarkan percakapan orang lain.
- Trafik websocket dari browser tetap lewat HTTPS/SSL (via Nginx), bukan koneksi polos.
- Port asli Reverb (`8080`) tidak pernah diekspos langsung ke publik.

## Troubleshooting

- **Console browser: `WebSocket connection to 'wss://localhost:8080/...' failed`** — `.env` di server masih pakai `REVERB_HOST=localhost` (nilai default lokal). Ganti ke domain publik, lalu `npm run build` ulang.
- **URL di error ada karakter aneh/tanda kutip nyelip** (mis. `domain.com"/app/...`) — ada tanda kutip tidak seimbang di nilai `REVERB_HOST` pada `.env`. Pastikan nilainya polos tanpa kutip sama sekali.
- **URL sudah bersih tapi tetap `WebSocket connection failed`** — hampir pasti Nginx belum diberi blok reverse proxy untuk path `/app/` (lihat bagian Setup Production #3). Ini butuh akses admin server.
- **Sudah ubah `.env` tapi browser masih connect ke nilai lama** — lupa `npm run build`, atau browser masih memakai file JS lama dari cache (hard refresh: Ctrl+Shift+R).
- **Ganti `routes/channels.php` tidak berefek** — restart proses Reverb, file ini hanya dibaca sekali saat proses dimulai.
- **Chat/notifikasi tiba-tiba diam lagi** — cek proses Reverb masih hidup: `ps aux | grep reverb` (kalau pakai cron watchdog) atau `sudo systemctl status reverb` (kalau pakai systemd).
