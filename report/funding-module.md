# Module Funding (Investors)

Module untuk tim Funding Officer (FO) mengelola pipeline investor, dari prospek sampai akad, memantau pencapaian & konversi, serta mengatur target FO. Berada di bawah menu **Funding → Investors** pada sidebar, semua route di bawah prefix `/investors` dan dilindungi permission `access-investors`.

## Daftar Halaman

| Halaman | Route | Permission | Volt component |
|---|---|---|---|
| Dashboard | `/investors` | `investors-dashboard.view` | `investors.dashboard-index` |
| Monthly Achievement | `/investors/monthly` | `investors-monthly.view` | `investors.monthly-index` |
| Monthly Conversion | `/investors/conversion` | `investors-conversion.view` | `investors.conversion-index` |
| Funnel | `/investors/funnel` | `fo-funnel.view` | `investors.funnel-index` |
| Contacts | `/investors/contacts` | `fo-contacts.view` | `investors.contact-index` |
| Management FO | `/investors/management-fo` | `fo-targets.view` | `investors.management-fo` |
| Activity Log | `/investors/activity-log` | `investors-activity-log.view` | `investors.activity-log-index` |
| Master Document | `/investors/docs` | `investor-documents.view` | `investors.docs-index` |

## Fitur per Halaman

### 1. Dashboard (Daily Dashboard)
- Pilih tanggal untuk melihat realisasi harian.
- **Activity Tracker** — heatmap aktivitas bulanan.
- **Monthly Trend** — grafik tren bulan berjalan.
- **Realization per FO** — realisasi tiap FO pada tanggal terpilih.
- **FO Leaderboard** — peringkat FO bulan berjalan.

### 2. Monthly Achievement
- Pencapaian bulanan tim vs target.
- **Team Monthly Trend** — grafik tren bulanan tim.

### 3. Monthly Conversion
- Rasio konversi antar tahap funnel per bulan.
- **Year-to-Date Conversion Summary** — ringkasan konversi setahun berjalan.
- **Cumulative Funnel** — visualisasi funnel kumulatif.

### 4. Funnel (pipeline investor)
Tahap pipeline: **Leads → QL → Pitching → Follow Up → Won / Lost → Closing → Akad**.

- Dua mode tampilan: **Kanban** dan **Table** (toggle di toolbar).
- Filter: Funding Officer, Bulan, Tahun, serta pencarian kontak. Tombol Reset.
- **Add Lead** — tambah prospek dari daftar kontak, dengan tanggal, proyeksi nominal, proyeksi tanggal closing, dan sumber.
- Pindah tahap dengan form per tahap:
  - **Pitching** & **Follow Up** — catatan aktivitas (multi-entri per funnel). Follow up bisa ditambah, diedit, dihapus, dan dilihat yang sudah dihapus (*View Deleted*).
  - **Won** — nominal komitmen + tanggal.
  - **Closing** — nominal investasi final + tanggal.
  - **Akad** — nominal investasi, tanggal, dan **upload dokumen akad** (disimpan di S3 prefix `investors/funnel-akad/`).
  - **Lost** — wajib isi alasan lost.
- **History** per funnel (sidebar) berisi riwayat perpindahan tahap dan aktivitas.
- **Edit** data dasar & data tahap saat ini.
- Soft delete dan restore funnel.
- Kolom nominal/tanggal menyesuaikan tahap (data closing/won lebih diutamakan daripada proyeksi); tampilan follow up count dan link dokumen akad.

### 5. Contacts (FO Contacts)
- Daftar kontak calon investor, dengan statistik **Total Contacts** dan **Top PIC**.
- Tambah, edit, hapus kontak (dengan konfirmasi hapus).
- Kontak dikaitkan ke FO sebagai PIC.

### 6. Management FO
- Atur **target bulanan per FO** (disimpan lewat *Save Target*).
- Realisasi per FO per metrik: Leads, QL, Pitching, Follow Up, Won, Closing, Nilai Closing, dll.
- **Proyeksi** — estimasi tambahan dari funnel yang masih berjalan (Pitching/Follow Up) dengan target tanggal closing di periode terpilih; tidak dihitung sebagai closing pasti.
- **Ranking FO** (hanya terlihat oleh admin/Direksi).
- FO biasa hanya melihat data dirinya sendiri; Investor Admin, Super Admin, dan Direksi melihat semua FO (Direksi read-only).

### 7. Activity Log
- Jejak aktivitas seluruh perubahan di module (siapa melakukan apa).
- Filter: Actor, Bulan, Tahun, rentang tanggal From–To.
- Hanya tampil untuk role Investor Admin, Super Admin, dan Funding Officer.

### 8. Master Document
- Repositori materi pembekalan FO untuk pitching (brosur, company profile, power point, dll).
- Upload ke S3 (`investors/investor-docs/{environment}/...`), judul & deskripsi, soft delete + restore (*View Deleted*).
- **Preview** untuk tipe file yang didukung, serta **Download**.
- Upload/edit/hapus hanya untuk Investor Admin/Super Admin; Funding Officer dan Direksi read-only (lihat, preview, download).

## Notifikasi
- `FundingDealClosedNotification` — deal closing, dikirim ke admin dan owner FO.
- `FundingDealAkadNotification` — akad tercatat, dikirim ke owner FO.
- `FundingDealLostNotification` — deal lost, dikirim ke owner FO.

## Role & Permission
Resource permission didefinisikan di `config/resource_permissions.php` (grup `investors`):

| Resource | Aksi |
|---|---|
| `investors-dashboard`, `investors-monthly`, `investors-conversion`, `investors-activity-log` | view |
| `fo-funnel` | view, create, update, delete, restore |
| `fo-contacts` | view, create, update, delete |
| `fo-targets` | view, update |
| `investor-documents` | view, create, update, delete, restore |

Role yang terlibat: **Investor Admin**, **Super Admin**, **Funding Officer**, **Direksi** (read-only).

## Integrasi Lain
- **KPI Funding** di halaman profile user (`/profile`, `/users/{user}`) berupa heatmap target vs realisasi, hanya tampil jika departemen user punya module investors (`EmployeeTargetHeatmap`, `FoTargetMetrics`).

## Catatan
- Dokumen ini disusun dari route, sidebar, config permission, dan komponen Volt di `resources/views/livewire/investors/`. Detail kecil per fitur (misal isi kolom export) bisa berbeda; cek komponen terkait untuk akurasinya.
