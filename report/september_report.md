# Legend

Format commit/entry ngikutin gaya [Conventional Commits](https://www.conventionalcommits.org/), disesuaikan buat dev log harian:

| Prefix      | Arti |
|-------------|------|
| `feat`      | fitur baru |
| `fix`       | perbaikan bug — format: `[masalah/gejala] -> penyebab & solusi` |
| `refactor`  | rapihin/susun ulang kode, gak ada perubahan behavior |
| `test`      | nambah/nyesuain test |
| `docs`      | dokumentasi |
| `chore`     | beres-beres, gak ngaruh ke behavior aplikasi |
| `infra`     | konfigurasi server/deployment/dependency |
| `style`     | perubahan tampilan/CSS doang, gak ngaruh ke logic |
| `verify`    | ngecek ulang behavior existing, ternyata udah benar/gak perlu diubah |
| `note`      | catatan tambahan, bukan perubahan kode |
| `pending`   | belum dikerjain / nunggu keputusan |

Checklist status: `-[x]` = selesai, `-[ ]` = belum/pending.

---

## September

### 01/09/2026 — Username & Secondary Email, Perbaikan UI Navbar
-[x] feat : kolom `username` & `secondary_email` di tabel `users` (migration baru) + `ProfileValidationRules` — user sekarang bisa login pakai username selain email
-[x] feat : `LoginRequest` & `FortifyServiceProvider` disesuaikan biar `authenticateUsing` nerima username atau email, bukan email doang
-[x] fix  : dropdown avatar navbar (mobile & desktop) — lebar dibatasi `max-w-xs` bikin nama panjang kepotong -> diganti `max-w-sm` + `wrap-break-word` biar nama panjang turun baris, bukan ke-truncate
-[x] fix  : `resources/js/echo.js` sempat coba join channel presence `online` walau user belum login (guest) -> ditambah guard cek meta tag `authenticated` sebelum `Echo.join('online')`

### 02/09/2026 — Root Cause: Reset Password "Gak Bisa Submit, Gak Ada Error"
-[x] fix : [`/reset-password` diklik Submit keliatan gak ngapa-ngapain, gak ada pesan error muncul sama sekali — padahal di lokal jalan normal] server sebenarnya udah proses & redirect balik (`302` ke halaman yang sama) tiap validasi gagal (token invalid/expired/udah pernah dipakai), tapi komponen `<x-input>` (Mary UI) cuma bisa nampilin error kalau field-nya punya `wire:model` atau prop `errorField` eksplisit — form ini plain HTML (bukan Livewire), input cuma pakai `name=` biasa, jadi `$errors->has(null)` selalu `false` -> pesan error dari Fortify (`This password reset token is invalid.`, dst) gak pernah nongol di UI -> ditambah `error-field="email"` & `error-field="password"` di kedua input, plus `old('email', request('email'))` biar email yang diketik user gak ilang pas validasi gagal
-[x] test : 2 test baru di `PasswordResetTest` — token invalid dapet session error yang bener (`assertSessionHasErrors`), & view beneran nampilin teks errornya (bukan cuma kecek di session, tapi di HTML)

### 02/09/2026 (Sesi 2) — Peek Button & Password Strength Guide di Reset Password
-[x] feat : field password di `/reset-password` diganti `<x-input>` -> `<x-password>` (Mary UI) — dapet tombol peek (icon mata) buat show/hide isi password, gak perlu JS custom karena udah bawaan komponen
-[x] feat : checklist kekuatan password realtime pas ngetik (Alpine.js, murni client-side tanpa round-trip Livewire) — 4 kriteria: minimal 12 karakter, huruf besar & kecil, ada angka, ada simbol; tiap kriteria ada ikon centang (aman)/silang (belum), plus bar & label **Weak / Medium / Strong** yang otomatis ganti warna (merah/kuning/hijau) ngikutin skor
-[x] note : kriteria checklist ngikutin aturan password production di `AppServiceProvider::configureDefaults()` (min 12 karakter, mixed case, angka, simbol) — checklist ini murni panduan visual di client, validasi asli tetap dipegang penuh sama server

### 02/09/2026 (Sesi 3) — Link Reset Password Sekali Pakai & Reusable Component
-[x] verify : [khawatir link reset password bisa dipakai berkali-kali] ternyata link otomatis invalid setelah sukses dipakai — bawaan Laravel/Fortify (`PasswordBroker::reset()` langsung hapus token dari tabel `password_reset_tokens` begitu password berhasil diganti), gak perlu kode tambahan -> dikunci pakai test regresi `reset password link cannot be reused after a successful reset` biar gak sengaja kebobol di masa depan
-[x] refactor : peek button + checklist kekuatan password ditarik jadi satu komponen reusable `<x-password-strength-field>` (`resources/views/components/password-strength-field.blade.php`) — semua attribute (`wire:model`, `name`, `label`, `error-field`, dst) tinggal diteruskan lewat `{{ $attributes }}`, gak ada logic Alpine yang ditulis ulang tiap dipakai di halaman lain
-[x] feat : `/profile/security` field "New password" sekarang pakai komponen yang sama (`<x-password-strength-field wire:model="password" .../>`) — checklist & peek button konsisten sama halaman reset password
-[x] feat : `/profile/security` field "Current password" & "Confirm password" juga dinaikin ke `<x-password>` (dari `<x-input type="password">` biasa) biar peek button ada di semua field password, gak cuma yang baru
-[x] test : full suite `PasswordResetTest` (7 test) + `ProfileSecurityTest` (6 test) tetap hijau setelah refactor, Pint bersih
