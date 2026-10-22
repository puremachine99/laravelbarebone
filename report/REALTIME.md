# Realtime Chat & Notifikasi (Laravel Reverb)

Dokumen ini menjelaskan cara kerja dan cara menjalankan fitur realtime (chat instan & notifikasi bell) pada aplikasi ini.

Setup yang didokumentasikan di sini adalah yang **benar-benar terpasang dan berjalan** di server produksi `nakama.smartid.or.id` — bukan daftar opsi teoretis. Kalau kamu setup server baru, ikuti urutannya apa adanya.

## Ringkasan Arsitektur

```
Browser  ──wss://nakama.smartid.or.id/app──▶  Cloudflare ──▶ Nginx ──▶ Reverb :8080
Laravel (PHP) ──http://<bind>:8080/apps/...──────────────────────────▶ Reverb :8080
                                                                         ▲
                              cron @reboot ──▶ supervisord ──────────────┘
```

Ada **satu proses tambahan** yang harus selalu hidup di server, terpisah dari PHP-FPM: `php artisan reverb:start`. Yang menjaganya hidup adalah **supervisord milik user** (bukan systemd — kita tidak punya root), dan yang menghidupkan supervisord setelah reboot adalah **cron `@reboot`**.

Server produksi kita adalah shared server Virtualmin tanpa akses root, tanpa Docker. Itu bukan halangan: yang dibutuhkan Reverb cuma SSH, izin menjalankan proses long-running, dan reverse proxy yang sudah disiapkan admin.

## Pemisahan Channel

| Channel | Isi | Siapa yang bisa akses |
|---|---|---|
| `conversation.{id}` | Pesan di satu percakapan chat | Hanya peserta percakapan itu |
| `chat.{userId}` | Sinyal ringan "ada pesan baru" untuk memperbarui daftar kontak | Hanya pemilik akun |
| `App.Models.User.{id}` | Notifikasi umum (bell icon), tidak berhubungan dengan chat | Hanya pemilik akun |

Aturan otorisasinya di `routes/channels.php`.

Perlu diingat: otorisasi channel dilayani **aplikasi Laravel** lewat endpoint `/broadcasting/auth`, bukan oleh server Reverb. Jadi `APP_URL` yang benar itu penting, dan Reverb sendiri tidak pernah menyentuh database.

## Dua Jalur Koneksi yang Berbeda

Ini sumber kebingungan nomor satu, jadi disebut eksplisit:

- **Browser → Reverb** — lewat internet publik: domain asli + HTTPS (`wss://nakama.smartid.or.id/app/...`), diteruskan Cloudflare lalu Nginx ke proses Reverb.
- **Laravel (PHP) → Reverb** — terjadi *di dalam server yang sama*, lewat HTTP API Reverb (`/apps/{id}/events`).

Karena itu ada dua set variabel `.env` yang terpisah: `REVERB_*` (dipakai browser) dan `REVERB_SERVER_*` (dipakai PHP).

### ⚠️ Jebakan `REVERB_SERVER_HOST`

Satu variabel ini dipakai untuk **dua hal yang berbeda**:

| Dibaca di | Perannya |
|---|---|
| `config/reverb.php` | Alamat **bind** — interface mana yang didengarkan `reverb:start` |
| `config/broadcasting.php` | Alamat **tujuan** — ke mana PHP mengirim event |

Konsekuensinya, mempersempit nilai ini demi keamanan bisa memutus jalur Nginx tanpa peringatan.

**Di server kita nilainya `0.0.0.0` dan harus tetap begitu.** Pernah dicoba diganti `127.0.0.1` dengan alasan "lebih aman", hasilnya seluruh websocket mati dengan `502 Bad Gateway` — karena Nginx di server ini menghubungi Reverb lewat alamat privat, bukan loopback (perhatikan hostname servernya: `99-Virtualmin-Side-PrivIP`). Dengan `0.0.0.0` Reverb mendengar di semua interface, termasuk yang dipakai Nginx.

Kalau suatu saat mau tetap mempersempitnya, **jangan menebak** — cari dulu alamat upstream Nginx yang sebenarnya:

```bash
grep -rn "proxy_pass" /etc/nginx/ 2>/dev/null | grep -iE "8080|reverb"
```

Isi `REVERB_SERVER_HOST` dengan alamat persis dari situ. Kalau tujuannya menutup port 8080 dari internet, cara yang benar lewat firewall, bukan lewat bind address.

## Setup Lokal (Clone Baru)

Paket Reverb sudah ada di `composer.json` dan `package.json`, jadi `composer install` + `npm install` standar sudah membawanya.

1. Salin `.env.example` ke `.env`, jalankan `php artisan key:generate`.
2. Buat kunci websocket:
   ```bash
   php artisan reverb:install --no-interaction
   ```
   > Kalau dijalankan lebih dari sekali kadang muncul baris duplikat di `.env`. Sisakan satu set saja per variabel.
3. Jalankan migrasi seperti biasa — tabel chat sudah termasuk.
4. Jalankan tiga proses (tiga tab terminal):
   ```bash
   php artisan serve
   php artisan reverb:start
   npm run dev
   ```

Default `.env.example` (`REVERB_HOST=localhost`) sudah cukup untuk lokal.

### Alternatif: numpang Reverb server

Kalau tidak mau menjalankan Reverb sendiri di laptop, arahkan saja ke instance server:

```env
REVERB_HOST=nakama.smartid.or.id
REVERB_PORT=443
REVERB_SCHEME=https
```

dan **jangan set `REVERB_SERVER_*` sama sekali** — kalau diset, PHP lokalmu akan mencoba publish ke localhost sendiri yang tidak ada Reverb-nya. `allowed_origins` di server permisif, jadi origin `http://localhost:8000` diterima.

> **Peringatan:** saat ini dev dan produksi memakai `REVERB_APP_ID` yang sama pada instance yang sama. Artinya pesan yang kamu kirim dari lokal ke `conversation.5` **akan muncul di layar user produksi** yang sedang membuka percakapan itu, dan sebaliknya. Sampai ada app kedua khusus dev (lihat [Pekerjaan yang Belum Selesai](#pekerjaan-yang-belum-selesai)), jangan tes di channel yang sedang dipakai user sungguhan.

## Setup Production — Langkah demi Langkah

### 1. `.env` — dua set variabel, jangan disamakan

```env
APP_ENV=production
APP_URL=https://nakama.smartid.or.id

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<dari reverb:install>
REVERB_APP_KEY=<dari reverb:install>
REVERB_APP_SECRET=<dari reverb:install>

# Dipakai BROWSER — lewat domain publik, HTTPS
REVERB_HOST=nakama.smartid.or.id
REVERB_PORT=443
REVERB_SCHEME=https

# Dipakai PHP + alamat bind — baca peringatan di atas sebelum mengubah
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_SERVER_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Jangan pakai tanda kutip** pada nilai `REVERB_HOST`. Satu kutip yang nyelip akan ikut terbawa ke URL websocket dan menghasilkan error yang membingungkan.

`REVERB_APP_SECRET` di aplikasi harus **identik** dengan yang ada di config Reverb server — dipakai untuk menandatangani request publish (HMAC). Kalau beda, publish ditolak `401 Authentication signature invalid`.

### 2. Reverse proxy Nginx (butuh akses admin server)

Tanpa ini browser tidak akan pernah menembus ke Reverb. Ini satu-satunya bagian yang butuh root — minta ke admin server kalau kamu tidak punya:

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
    proxy_read_timeout 3600s;
}

location /apps/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
}
```

Dua lokasi, bukan satu — `/app/` untuk koneksi websocket browser, `/apps/` untuk HTTP publish API. `proxy_read_timeout` yang panjang mencegah koneksi idle diputus Nginx.

Cloudflare mendukung WebSocket secara default, biasanya tidak perlu setting tambahan (di domain kita sudah terbukti jalan dengan orange cloud aktif).

### 3. Supervisord — menjaga proses Reverb tetap hidup

Kita tidak punya root, jadi systemd bukan opsi. Yang dipakai: **supervisord milik user**.

Config di `/home/nakama/etc/supervisord.conf`, dengan program bernama `laravel-reverb`:

```ini
[program:laravel-reverb]
command=php /home/nakama/smartboard/artisan reverb:start
directory=/home/nakama/smartboard
autostart=true
autorestart=true
startretries=10
stdout_logfile=/home/nakama/smartboard/storage/logs/reverb.log
redirect_stderr=true
```

Jalankan supervisord:

```bash
supervisord -c /home/nakama/etc/supervisord.conf
```

### 4. Cron `@reboot` — menjaga supervisord tetap hidup

Supervisord menjaga Reverb, tapi tidak ada yang menjaga supervisord setelah server reboot. Ini penambalnya — `crontab -e`:

```cron
@reboot supervisord -c /home/nakama/etc/supervisord.conf
```

Tanpa baris ini seluruh rantai mati setelah reboot dan harus dinyalakan manual. Ini lubang yang paling gampang terlewat, karena semuanya kelihatan normal sampai server benar-benar reboot.

### 5. Build aset — di server, bukan di laptop

```bash
npm run build
php artisan config:cache
supervisorctl -c ~/etc/supervisord.conf restart laravel-reverb
```

`npm run build` **wajib dijalankan di server dengan `.env` server**, karena nilai `VITE_REVERB_*` di-*bake* ke file JS saat build, bukan dibaca saat runtime. Kalau bundle-nya hasil build di laptop dengan `.env` lokal, browser user akan mencoba konek ke `localhost:8080`.

## Operasional Harian

Bikin alias supaya tidak mengetik `-c` terus:

```bash
echo "alias sctl='supervisorctl -c /home/nakama/etc/supervisord.conf'" >> ~/.bashrc
```

```bash
sctl status                    # cek kondisi
sctl restart laravel-reverb    # restart
sctl tail -f laravel-reverb    # lihat log berjalan
```

**Jangan pernah `kill` PID Reverb.** Supervisord akan langsung menghidupkannya lagi, jadi kamu cuma bikin bingung diri sendiri saat mengira sudah mematikannya.

### Queue worker tidak dibutuhkan — tapi ada syaratnya

Server ini **tidak menjalankan queue worker**. Supaya realtime tetap jalan, semua siaran harus sengaja dibuat sinkron. Ada dua mekanisme berbeda, dan keduanya gampang terlewat:

**1. Event chat** — `App\Events\Chat\MessageSent` memakai `ShouldBroadcastNow`, bukan `ShouldBroadcast`. Event dikirim di request yang sama, tidak lewat queue.

**2. Notifikasi** — ini jebakannya. Kelas notifikasi kita tidak mengantre (tidak `implements ShouldQueue`), **tapi itu tidak cukup**. Saat channel `broadcast` dipakai, Laravel melempar `Illuminate\Notifications\Events\BroadcastNotificationCreated` — dan event bawaan itu `implements ShouldBroadcast`, jadi **masuk antrian**. Tanpa worker, job-nya mengendap di tabel `jobs` selamanya.

Gejalanya menipu: notifikasi tetap tersimpan dan muncul begitu halaman di-refresh (channel `database` jalan sinkron), tapi lonceng tidak pernah bergerak sendiri. Kelihatan seperti masalah frontend, padahal bukan.

Solusinya, setiap `toBroadcast()` memaksa koneksi `sync`:

```php
public function toBroadcast(object $notifiable): BroadcastMessage
{
    return (new BroadcastMessage($this->toDatabase($notifiable)))
        ->onConnection('sync');
}
```

**Setiap notifikasi baru yang memakai channel `broadcast` wajib ikut pola ini.** Ada test penjaga di `tests/Feature/NotificationTest.php` yang gagal kalau ada yang lupa.

Repo ini juga belum punya scheduled task, jadi cron `schedule:run` belum perlu. Artinya cuma **satu** proses long-running yang harus dijaga.

Kalau nanti volume notifikasi naik atau ada yang diubah ke `ShouldQueue`, barulah queue worker jadi wajib — tambahkan sebagai program supervisord kedua, lalu `->onConnection('sync')` boleh dihapus. Ingat: worker harus ikut di-restart setiap deploy, kalau tidak dia menjalankan kode lama.

### Kapan harus restart Reverb?

| Perubahan | Restart Reverb? |
|---|---|
| Kode Livewire/Controller biasa | Tidak |
| `routes/channels.php` | Ya |
| `config/reverb.php`, `config/broadcasting.php`, `.env` (`REVERB_*`, `BROADCAST_CONNECTION`) | Ya |
| Yang menyangkut `VITE_REVERB_*` | Ya + `npm run build` |
| Proses crash | Otomatis (supervisord) |

Urutan yang benar saat deploy dengan perubahan `.env`:

```bash
php artisan config:clear
npm run build
php artisan config:cache
sctl restart laravel-reverb    # paling akhir
```

Restart Reverb harus **paling akhir** — proses Reverb membaca config sekali saat boot, jadi selama belum di-restart dia masih memakai nilai lama.

User yang sedang online cuma terputus sesaat lalu tersambung otomatis, tidak perlu refresh manual.

## Verifikasi

### Cek cepat dari server

```bash
sctl status                     # harus RUNNING
ss -lntp | grep 8080            # harus LISTEN 0.0.0.0:8080
```

### Cek jalur browser (bisa dari mana saja)

Ini menguji rantai lengkap Cloudflare → Nginx → Reverb tanpa perlu buka browser:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -N \
  -H "Connection: Upgrade" -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  "https://nakama.smartid.or.id/app/<REVERB_APP_KEY>?protocol=7&client=js&version=8.6.0"
```

| Hasil | Artinya |
|---|---|
| `101` | Sehat — rantai lengkap jalan |
| `502` | Nginx tidak bisa menjangkau Reverb — proses mati, atau bind address salah |
| `4001` (di body) | `REVERB_APP_KEY` tidak dikenali server |
| timeout | Cloudflare/Nginx tidak meneruskan upgrade WebSocket |

Tambahkan `-H "Origin: https://domain-mu"` untuk sekalian menguji `allowed_origins`.

### Cek jalur PHP → Reverb

```bash
php artisan tinker --execute 'Illuminate\Support\Facades\Broadcast::connection("reverb")->broadcast(["konek-test"], "ping", ["ok" => true]); echo "PUBLISH OK\n";'
```

`PUBLISH OK` berarti signature HMAC cocok dan Reverb menerima. Kalau `401`, berarti `REVERB_APP_SECRET` aplikasi beda dengan punya server.

Dua tes ini menguji **jalur yang berbeda** dan bisa gagal sendiri-sendiri — publish bisa sukses sementara browser gagal, atau sebaliknya. Selalu jalankan keduanya saat mendiagnosis.

### Tes fungsional

1. Buka aplikasi di dua browser berbeda (atau satu Incognito), login dengan **2 akun berbeda**.
2. Buka `/chat` di keduanya, mulai percakapan.
3. Kirim pesan — harus langsung muncul di sisi lain **tanpa refresh**.
4. Untuk notifikasi: picu aksi yang menghasilkan notifikasi (mis. closing deal), lalu lihat ikon lonceng di akun penerima — badge harus bertambah **tanpa refresh**. Kalau badge baru muncul setelah di-refresh, itu gejala klasik siaran yang tersangkut di queue — baca [Queue worker tidak dibutuhkan — tapi ada syaratnya](#queue-worker-tidak-dibutuhkan--tapi-ada-syaratnya).
5. Cek Console (F12) — tidak boleh ada `WebSocket connection ... failed`.

## Troubleshooting

Diagnosis paling efisien: jalankan dua tes curl di atas dulu, karena hasilnya langsung memisahkan masalah server dari masalah browser.

- **Semua `502`, termasuk curl** — Reverb tidak terjangkau Nginx. Cek `sctl status` dan `ss -lntp | grep 8080`. Kalau Reverb `RUNNING` tapi bind-nya bukan `0.0.0.0`, itu penyebabnya — kembalikan `REVERB_SERVER_HOST=0.0.0.0`, `config:cache`, restart.
- **curl `101` tapi browser gagal** — barulah ini masalah sisi klien: `allowed_origins`, cache browser, atau bundle JS hasil build dengan `.env` yang salah. Bandingkan hash file `app-*.js` di error console dengan output `npm run build` terakhir.
- **`WebSocket connection to 'wss://localhost:8080/...' failed`** — `.env` server masih `REVERB_HOST=localhost`. Ganti, lalu `npm run build` ulang **di server**.
- **URL error ada tanda kutip nyelip** (mis. `domain.com"/app/...`) — kutip tidak seimbang di `REVERB_HOST`. Nilainya harus polos.
- **Sudah ubah `.env` tapi browser tetap pakai nilai lama** — lupa `npm run build`, atau cache browser (Ctrl+Shift+R). Ingat: `config:clear` tidak berpengaruh apa pun pada nilai `VITE_*` yang sudah ter-bake.
- **`401 Authentication signature invalid`** saat publish — `REVERB_APP_SECRET` aplikasi tidak sama dengan punya server Reverb.
- **Ganti `routes/channels.php` tidak berefek** — restart Reverb.
- **Chat mati mendadak dan `sctl status` bilang `FATAL`/`BACKOFF`** — Reverb gagal boot berulang kali. Baca `storage/logs/reverb.log`, jangan cuma restart.
- **Semua normal tapi mati total setelah server reboot** — cek `crontab -l`, kemungkinan baris `@reboot supervisord` hilang.

## Ringkasan Keamanan

- Semua channel privat divalidasi lewat sesi login (`routes/channels.php`) — orang lain tidak bisa mendengarkan percakapan yang bukan miliknya.
- Trafik websocket dari browser lewat HTTPS/WSS (Cloudflare + Nginx), bukan koneksi polos.
- Port 8080 tidak diekspos ke publik lewat Nginx. Catatan: karena Reverb bind ke `0.0.0.0`, port itu secara teknis terbuka di interface server — kalau perlu ditutup rapat, pakai firewall, **bukan** dengan mempersempit bind address (lihat peringatan di awal dokumen).
- `REVERB_APP_SECRET` adalah kredensial. Jangan commit, jangan kirim lewat chat.

## Pekerjaan yang Belum Selesai

**App terpisah untuk dev.** Dev dan produksi masih berbagi `REVERB_APP_ID` yang sama pada satu instance Reverb, sehingga event bocor lintas environment. Perbaikannya: tambah entry kedua di array `apps.apps` pada `config/reverb.php` server dengan `app_id`/`key`/`secret` berbeda. Satu proses Reverb bisa melayani banyak app, jadi tidak perlu server tambahan — hanya perlu `sctl restart laravel-reverb` setelahnya.
