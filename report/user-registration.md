# Redesign Alur Registrasi User — Smart ID Dashboard (Opsi B: Admin-Initiated Invite)

> Diputuskan: tidak ada form registrasi publik. Semua akun dibuat lewat undangan admin. Dokumen ini fokus ke desain itu — race condition, invalidasi sesi, resend invite, urutan implementasi, dan penyelarasan dengan fitur Fortify yang sudah aktif.

## 1. Kondisi Saat Ini (As-Is)

Berdasarkan review kode:

- Registrasi **self-service terbuka**: siapa pun bisa akses `/register` dan langsung punya akun aktif (`app/Actions/Fortify/CreateNewUser.php`).
- Tidak ada gate approval, tidak ada undangan, tidak ada penugasan role saat registrasi.
- Tabel `users` tidak punya kolom status (`is_active`, `approved_at`, dll) — hanya `email_verified_at` bawaan Fortify.
- Role & permission **sudah ada** (spatie/permission, trait `HasRoles` di `User` model) — tapi tidak dipakai saat registrasi.
- Ada `department_id` di tabel users — cocok dipakai langsung saat admin membuat invite.
- Infrastruktur notifikasi email sudah ada polanya (`app/Notifications/*`), tinggal ditambah notifikasi baru. `MAIL_MAILER` saat ini masih `log` (belum kirim email sungguhan).
- Fortify sudah mengaktifkan `registration()`, `emailVerification()`, dan `twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])`. `registration()` perlu **dimatikan**; dua fitur lain perlu diselaraskan dengan alur invite.

**Kesimpulan:** untuk aplikasi internal yang menyimpan data finance dan funnel investor, cara paling aman adalah **menghilangkan endpoint registrasi publik sepenuhnya** dan menggantinya dengan invite yang diinisiasi admin.

---

## 2. Kenapa Opsi B

Dengan admin-initiated invite, tidak ada form yang bisa diakses publik/anonim, sehingga hilang seluruh kelas risiko berikut tanpa perlu mitigasi tambahan:

- Bot/spam registrasi massal.
- Enumerasi domain email internal lewat form registrasi.
- Perlunya CAPTCHA, honeypot, rate limiting per-IP di endpoint pendaftaran.
- Duplicate/racing *registration* dari sisi user (karena tidak ada lagi input dari user yang belum diautentikasi).

Yang tersisa untuk didesain: bagaimana admin membuat invite dengan aman, bagaimana token dikelola, dan bagaimana status akun di-guard di seluruh siklus hidupnya.

---

## 3. Proposed Flow

```
┌─────────┐   admin buat invite   ┌────────────┐   set password    ┌─────────┐
│  Admin  │ ─────────────────────▶│  Invited   │ ─────────────────▶│  Active │
│ (isi    │  (nama+email+role+    │ (token     │  (klik link,      │ (bisa   │
│ form)   │   department)         │  terkirim) │   set password,   │  login) │
└─────────┘                       └────────────┘   opsional 2FA)   └─────────┘
                                        │
                                        │ resend invite (token lama invalid)
                                        ▼
                                  ┌────────────┐
                                  │  Invited   │ (token baru)
                                  └────────────┘

  Active ──suspend/revoke──▶ Suspended (semua sesi aktif diinvalidasi saat transisi ini)
```

Tidak ada state `pending` atau `rejected` — begitu admin submit form invite, user langsung berstatus `invited` dengan role & department sudah ditentukan. "Reject" tidak relevan lagi karena tidak ada permintaan dari user yang perlu ditolak; admin cukup tidak mengirim invite, atau memakai `revoke invite` kalau berubah pikiran setelah invite terkirim.

### Detail langkah

1. **Admin membuat invite** — form internal (bukan publik) berisi `name`, `email`, `role` (wajib), `department` (wajib/opsional sesuai kebutuhan). Halaman ini dibungkus policy/permission eksplisit (mis. `can:invite-users`), bukan cuma disembunyikan dari navigasi.
2. **Validasi email** — Wajib validasi domain email korporat di titik ini (mis. `@smartid.co.id`), dan cek belum ada user lain dengan email sama.
3. **Sistem membuat user** — Row `users` dibuat langsung dengan `status = invited`, role di-assign via spatie/permission, `password` diisi hash acak yang tidak pernah dipakai (unusable password) sampai user set password sendiri.
4. **Generate token & kirim email** — Token dibuat via mekanisme `PasswordBroker` bawaan Laravel (reuse tabel `password_reset_tokens`, bukan tabel baru), signed + expiring (mis. 3 hari), single-use. Email berisi link "Selamat, Anda diundang ke [App] — buat password Anda di sini".
5. **Resend invite** — Kalau user kehilangan email atau link expired, admin klik **"Kirim ulang undangan"** dari daftar user berstatus `invited`. Ini menginvalidasi token lama dan generate token baru. Rate-limit aksi ini per user (mis. maksimal beberapa kali per jam) untuk mencegah penyalahgunaan sebagai mekanisme spam email ke alamat pihak ketiga.
6. **Revoke invite** — Admin bisa membatalkan invite yang belum di-klaim (`status: invited → suspended` atau hapus user, tergantung kebutuhan audit — disarankan **soft-disable**, bukan hapus, supaya tetap ada jejak di activity log).
7. **User klik link** — Sistem validasi token (belum expired, belum dipakai, signature valid, `status` user masih `invited`). Tampilkan form set password (validasi kekuatan password via `PasswordValidationRules` yang sudah ada).
8. **Set password sukses** — `status → active`, token ditandai used, `email_verified_at` diisi otomatis (karena link dikirim & dibuka dari email yang sama = implicit verification, sehingga listener `emailVerification()` Fortify tidak perlu jalan terpisah). Jika 2FA diwajibkan untuk seluruh internal user, arahkan ke halaman enroll 2FA sebelum masuk dashboard.
9. **Login** — Fortify hanya izinkan `status = active`. Status `invited`/`suspended` diberi pesan sesuai kondisi (mis. "Akun Anda belum diaktifkan, cek email undangan" / "Akun Anda dinonaktifkan, hubungi admin").
10. **Suspend akun aktif** — Saat admin mengubah status user aktif menjadi `suspended`, sistem menghapus seluruh sesi aktif user tersebut agar akses terputus seketika, bukan menunggu sesi expire sendiri.

---

## 4. Risiko & Rekomendasi

| Isu | Risiko | Rekomendasi |
|---|---|---|
| Link set-password tidak expire / reusable | Account takeover | Signed URL Laravel, expiry 48–72 jam, single-use, disimpan hashed di DB (reuse `password_reset_tokens`) |
| Invite dibuat tanpa role/department | User `active` tapi tanpa akses, atau default ke role terlalu permissive | Form invite mewajibkan role & department diisi sebelum submit — bukan opsional |
| Status `invited` tidak eksplisit di-guard | User bisa login sebelum set password kalau ada bug | Kolom `status` + guard di semua auth flow (login Fortify), bukan cuma UI |
| Tidak ada audit trail | Tidak tahu siapa mengundang siapa, kapan, dengan role apa | Catat `invited_by`, `invited_at`; manfaatkan activity log yang sudah ada di project |
| Invite hilang/kadaluarsa | User kehilangan email atau link expired, tidak ada jalan masuk | Tombol **resend invite** yang menginvalidasi token lama + generate baru, rate-limited |
| Resend/invite disalahgunakan sebagai spam relay | Admin (atau akun admin yang dibobol) memakai fitur invite untuk mengirim email massal ke alamat sembarangan | Validasi domain email korporat wajib saat invite dibuat; rate-limit resend per user dan per admin |
| Racing invite untuk email sama | Dua admin membuat invite untuk email yang sama nyaris bersamaan → dua token aktif | Unique constraint pada `email` di tabel `users`; aksi create-invite dibungkus `DB::transaction` + cek existing sebelum insert |
| Sesi aktif saat suspend | User `active` yang sedang login lalu di-suspend admin — sesi lama tetap jalan sampai expire sendiri | Saat status berubah keluar dari `active`, invalidasi seluruh sesi user (hapus baris di tabel `sessions`, atau session-version check di middleware) |
| Halaman invite/manage-user tanpa authorization gate | Siapa pun yang tahu route bisa invite/suspend user kalau lupa dipasang guard | Wajib dibungkus policy/permission eksplisit (mis. `can:invite-users`, `can:manage-users`) |
| Konflik dengan fitur Fortify yang sudah aktif | `Features::registration()` masih ON berarti `/register` publik masih hidup berdampingan dengan invite flow; `emailVerification()` bisa memicu alur verifikasi kedua yang membingungkan | **Matikan `Features::registration()`** di `config/fortify.php` (hapus route publik sepenuhnya). Set `email_verified_at` otomatis saat set-password sukses supaya verifikasi Fortify tidak jalan dobel. Pertimbangkan **mewajibkan 2FA enrollment** setelah set password pertama kali, mengingat data finance & funnel investor |

**Best practice yang tetap berlaku:**

1. **Least privilege by default** — role & department ditentukan admin saat invite dibuat, bukan default kosong/permissive.
2. **Password dibuat oleh user, bukan admin** — admin tidak pernah tahu/set password user.
3. **Link sekali pakai + expired** — jangan andalkan signature saja tanpa expiry & single-use tracking.
4. **Audit semua aksi admin** — invite, resend, revoke, suspend harus tercatat di activity log.
5. **Login harus reject akun non-`active`** — validasi di layer auth, bukan hanya middleware UI.

---

## 5. Perubahan Skema Data

- `users.status` (enum: `invited`, `active`, `suspended`) — default `invited` saat dibuat via invite.
- `users.invited_by` (FK ke `users.id`, nullable).
- `users.invited_at` (timestamp, nullable).
- **Tidak perlu tabel baru** untuk token — reuse tabel `password_reset_tokens` bawaan Laravel/Fortify, dibedakan dari reset password biasa lewat pengecekan `status` user saat token divalidasi.
- Role & department sudah bisa langsung diisi saat insert (via spatie/permission dan kolom `department_id` yang sudah ada) — tidak perlu kolom tambahan untuk itu.

## 6. Komponen yang Perlu Dibuat/Diubah

- **Matikan** `Features::registration()` di `config/fortify.php`; hapus/redirect route `register` publik.
- Hapus atau nonaktifkan `app/Actions/Fortify/CreateNewUser.php` dari alur publik (tetap bisa dipakai secara internal sebagai action yang dipanggil dari form invite admin, dengan parameter role/department).
- Livewire/Volt component **"Undang User Baru"** (dibungkus `can:invite-users`): form nama, email, role, department → submit → create user + kirim invite email.
- Livewire/Volt component **daftar user invited/active/suspended** dengan aksi **resend invite**, **revoke**, **suspend** (dibungkus `can:manage-users`).
- `Notification`: `UserInvitedNotification` (ke user, berisi link set password).
- Route + halaman **set password via token** (reuse `PasswordBroker`).
- Custom check di Fortify login pipeline untuk blokir status selain `active`.
- **Session invalidation hook**: listener pada perubahan status user (observer di model `User` atau di action suspend) yang menghapus baris sesi terkait saat status berubah keluar dari `active`.
- Opsional: halaman/step **paksa enroll 2FA** setelah set password pertama kali.
- Aktifkan `MAIL_MAILER` produksi + `MAIL_FROM_ADDRESS` domain perusahaan.

---

## 7. Urutan Implementasi yang Disarankan

1. **Konfigurasi `MAIL_MAILER` produksi** — blocker keras sebelum email invite bisa diuji di lingkungan nyata.
2. **Migrasi skema**: kolom `status`, `invited_by`, `invited_at` di `users`.
3. **Matikan `Features::registration()`** di Fortify + hapus akses ke `/register` publik — lakukan ini **sebelum** flow invite baru live, supaya tidak ada periode di mana dua jalur pembuatan akun (publik + invite) berjalan bersamaan.
4. **Guard login Fortify** (blokir status selain `active`).
5. **Halaman "Undang User Baru"** (create invite + assign role/department) dengan policy gate dan transaction/unique-check untuk mencegah race condition.
6. **Notifikasi** `UserInvitedNotification`.
7. **Halaman set-password via token** (reuse `PasswordBroker`) + auto-set `email_verified_at`.
8. **Halaman manage-user**: resend invite, revoke, suspend + session invalidation on suspend.
9. Opsional: langkah enroll 2FA wajib setelah set password.
10. QA end-to-end: racing invite untuk email sama, resend invite, login dengan status non-active, suspend memutus sesi aktif.

---

## 8. Ringkasan

- Tidak ada lagi form registrasi publik — semua akun dibuat lewat invite admin (`status: invited → active → suspended`).
- Admin menentukan role & department saat invite dibuat, sehingga tidak ada window "active tapi tanpa akses".
- Token set-password reuse mekanisme `PasswordBroker`/`password_reset_tokens` bawaan Laravel — tidak perlu tabel baru.
- Race condition (invite ganda untuk email sama) dan invalidasi sesi saat suspend perlu ditangani eksplisit di level kode, bukan diasumsikan aman oleh Eloquent/Fortify default.
- `Features::registration()` Fortify wajib dimatikan sebagai bagian dari implementasi, bukan opsional — kalau tidak, endpoint lama tetap jadi celah paralel.
- Urutan implementasi: matikan registrasi publik & pasang guard login lebih dulu, baru bangun flow invite di atasnya.
