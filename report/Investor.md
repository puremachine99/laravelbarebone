# Planning ERD & Migrasi — Departemen Funding (prefix `fo_`)

## 1. Konteks

Mengacu ke `smartboard_table.sql`, konvensi yang sudah dipakai di database:

- Tabel master **shared** (dipakai semua departemen): `users`, `employees`, `departments`, `positions`, `regions`, `regencies`, `provinces`, `roles`/`permissions` (spatie).
- Tabel spesifik departemen dikasih prefix (Partnership pakai `pmo_`).
- Departemen ditandai lewat `department_module` (`department_id` + `module_code`) — Funding cukup daftarkan `module_code = 'funding'`.
- `employees` sudah punya `region_id`, `pipeline_permissions` (jsonb), `target_okr`, `kpi_achieved_m1/m2` — tapi field ini generic lintas departemen, dan **tidak cukup** untuk kebutuhan target funding per-FO per-periode yang bisa diset Admin FO (lihat §4.11), jadi tetap perlu tabel baru.

Semua tabel baru pakai prefix **`fo_`**. Nama kolom pakai Bahasa Indonesia untuk field deskriptif (nama, catatan, jumlah, tanggal, dst), tapi kolom foreign key tetap ikut nama tabel yang direferensikan (`employee_id`, `region_id`, dst) supaya konvensi Eloquent (`belongsTo`) jalan tanpa konfigurasi tambahan. Kata **"pipeline" tidak diterjemahkan** — dipakai apa adanya sebagai nama tabel/kolom.

## 2. Keputusan desain (asumsi — koreksi kalau meleset)

| Keputusan | Alasan |
|---|---|
| `fo_pipelines.tahap` pakai kolom `varchar`, bukan tabel `fo_pipeline_stages` | Tahap Funding tetap (leads → ql → pitching → followup → won/lost), tidak butuh kanban yang bisa diatur admin seperti Partnership. |
| Log & follow-up **tetap tabel terpisah**, bukan kolom `json` | Konsisten dengan pola Action + transaction yang sudah kita rancang, dan gampang di-query untuk laporan. |
| 1 `fo_contacts` bisa punya banyak `fo_pipelines` (histori) | Kontak yang sempat lost bisa didekati lagi tanpa kehilangan histori deal sebelumnya. |
| Nilai tahap (`leads`, `ql`, `pitching`, `followup`, `won`, `lost`) **tidak** diterjemahkan | Sudah jadi istilah baku yang dipakai tim, diterjemahkan malah bikin bingung dokumentasi vs code. |
| **Baru**: tabel `fo_targets` untuk target per FO per periode, diisi oleh Admin FO | Kebutuhan baru yang kamu sebutkan — lihat §4.11. |

## 3. ERD ringkas (teks)

```
employees ──┬──< fo_contacts (employee_id, sbg pemilik kontak)
            ├──< fo_pipelines (employee_id, pemegang deal)
            ├──< fo_pipeline_pitching (employee_id, pembuat sesi)
            ├──< fo_pitching_participants (employee_id, peserta)
            ├──< fo_pipeline_followups (employee_id, pelaksana followup)
            ├──< fo_pipeline_logs (employee_id, pemindah tahap)
            └──< fo_targets (employee_id = target untuk siapa,
                              ditetapkan_oleh_employee_id = Admin FO yang set)

fo_contacts ──< fo_pipelines (contact_id)

fo_pipelines ──1:1── fo_pipeline_leads
             ──1:1── fo_pipeline_ql
             ──1:N── fo_pipeline_pitching ──1:N── fo_pipeline_followups
                                          └─N:M── fo_pitching_participants
             ──1:1── fo_pipeline_wons
             ──1:1── fo_pipeline_losts
             ──1:N── fo_pipeline_logs
```

## 4. Detail tabel baru

### 4.1 `fo_contact_positions` (opsional)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| nama | varchar(255) | tidak | | unique |
| created_at / updated_at | timestamp(0) | ya | | |

### 4.2 `fo_contacts`

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| employee_id | bigint FK → employees.id | tidak | | ON DELETE SET NULL — pemilik kontak, dasar validasi "kontak sendiri" |
| fo_contact_position_id | bigint FK → fo_contact_positions.id | ya | | ON DELETE SET NULL |
| nama | varchar(255) | tidak | | |
| no_telepon | varchar(30) | ya | | |
| email | varchar(255) | ya | | |
| profesi | varchar(255) | ya | | institusi/pekerjaan bebas teks |
| sumber | varchar(255) | tidak | | asal kontak (referral, event, cold call, dst) |
| region_id | bigint FK → regions.id | ya | | ON DELETE SET NULL |
| regency_id | bigint FK → regencies.id | ya | | ON DELETE SET NULL |
| catatan | text | ya | | |
| created_at / updated_at / deleted_at | timestamp(0) | ya | | soft delete |

**Index**: `employee_id`, `region_id`, `regency_id`.

### 4.3 `fo_pipelines` (kartu kanban / deal)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| contact_id | bigint FK → fo_contacts.id | tidak | | ON DELETE RESTRICT |
| employee_id | bigint FK → employees.id | tidak | | FO pemegang deal, ON DELETE SET NULL |
| tahap | varchar(20) | tidak | `'leads'` | leads / ql / pitching / followup / won / lost |
| tahun_fiskal | int4 | tidak | tahun berjalan | |
| created_at / updated_at / deleted_at | timestamp(0) | ya | | |

**Index**: `contact_id`, `employee_id`, `tahap`.
**Catatan**: satu `contact_id` boleh punya banyak baris di sini (histori re-engagement); "hanya 1 pipeline aktif per kontak" divalidasi di Action, bukan constraint DB.

### 4.4 `fo_pipeline_leads` (1:1 ke `fo_pipelines`)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| pipeline_id | bigint FK → fo_pipelines.id | tidak | | unique, ON DELETE CASCADE |
| proyeksi_jumlah | numeric(15,2) | tidak | | |
| proyeksi_tanggal_closing | date | tidak | | |
| catatan | text | ya | | |
| created_at / updated_at | timestamp(0) | ya | | |

### 4.5 `fo_pipeline_ql` (1:1 ke `fo_pipelines`)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| pipeline_id | bigint FK → fo_pipelines.id | tidak | | unique, ON DELETE CASCADE |
| ditandai_pada | timestamp(0) | tidak | now() | |
| catatan | text | ya | | |
| created_at / updated_at | timestamp(0) | ya | | |

### 4.6 `fo_pipeline_pitching` (1:N ke `fo_pipelines`)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| pipeline_id | bigint FK → fo_pipelines.id | tidak | | ON DELETE CASCADE |
| employee_id | bigint FK → employees.id | tidak | | pembuat sesi, ON DELETE SET NULL |
| tanggal_pitching | date | tidak | | |
| offline | bool | tidak | true | |
| metode_komunikasi | varchar(255) | ya | | |
| catatan | text | ya | | |
| created_at / updated_at | timestamp(0) | ya | | |

**Index**: `pipeline_id`.

### 4.7 `fo_pitching_participants` (pivot N:M)

| Kolom | Tipe | Null | Ket |
|---|---|---|---|
| pitching_id | bigint FK → fo_pipeline_pitching.id | tidak | ON DELETE CASCADE |
| employee_id | bigint FK → employees.id | tidak | ON DELETE CASCADE |

PK gabungan: (`pitching_id`, `employee_id`).

### 4.8 `fo_pipeline_followups` (1:N ke `fo_pipeline_pitching`)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| pitching_id | bigint FK → fo_pipeline_pitching.id | tidak | | ON DELETE CASCADE |
| employee_id | bigint FK → employees.id | tidak | | ON DELETE SET NULL |
| tanggal_followup | date | tidak | now() | |
| metode | varchar(100) | ya | | telpon/WA/meeting/email dst |
| hasil | text | tidak | | catatan kegiatan followup |
| tanggal_followup_berikutnya | date | ya | | opsional, buat reminder |
| created_at / updated_at | timestamp(0) | ya | | |

**Index**: `pitching_id`, `employee_id`.

### 4.9 `fo_pipeline_wons` (1:1 ke `fo_pipelines`)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| pipeline_id | bigint FK → fo_pipelines.id | tidak | | unique, ON DELETE CASCADE |
| jumlah_investasi | numeric(15,2) | tidak | | |
| jangka_waktu | varchar(100) | ya | | tenor investasi |
| bagi_hasil | varchar(255) | ya | | |
| tanggal_won | date | tidak | | |
| path_dokumen | varchar(255) | ya | | path s3 dokumen kesepakatan |
| catatan | text | ya | | |
| created_at / updated_at | timestamp(0) | ya | | |

### 4.10 `fo_pipeline_losts` (1:1 ke `fo_pipelines`)

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| pipeline_id | bigint FK → fo_pipelines.id | tidak | | unique, ON DELETE CASCADE |
| alasan | text | tidak | | |
| tanggal_lost | date | tidak | | |
| catatan | text | ya | | |
| created_at / updated_at | timestamp(0) | ya | | |

### 4.11 `fo_targets` — **baru**: target per FO, diset Admin FO

| Kolom | Tipe | Null | Default | Ket |
|---|---|---|---|---|
| id | bigint PK | | | |
| employee_id | bigint FK → employees.id | tidak | | target ini untuk FO siapa, ON DELETE CASCADE |
| ditetapkan_oleh_employee_id | bigint FK → employees.id | tidak | | Admin FO yang menetapkan, ON DELETE SET NULL |
| tahun | int4 | tidak | | |
| bulan | int4 | ya | | null = target tahunan, terisi = target bulanan |
| jumlah_target | numeric(15,2) | tidak | | target nominal investasi (won) |
| catatan | text | ya | | |
| created_at / updated_at | timestamp(0) | ya | | |

**Unique**: (`employee_id`, `tahun`, `bulan`) — satu FO cuma boleh punya satu target aktif per periode; edit ya update baris ini, bukan bikin baris baru (biar histori revisi target juga ketahuan lewat `updated_at`, atau kalau butuh histori revisi lengkap, tambahkan `fo_target_logs` seperti pola `fo_pipeline_logs`).
**Pencapaian (achievement)** tidak disimpan sebagai kolom di sini — dihitung on-the-fly dari `SUM(fo_pipeline_wons.jumlah_investasi)` yang `employee_id`-nya sama dan `tanggal_won` masuk periode target, supaya angkanya selalu real-time dan tidak perlu disinkronkan manual.

## 5. Urutan migration (supaya FK tidak error)

1. `fo_contact_positions`
2. `fo_contacts`
3. `fo_targets` *(cukup FK ke employees, bisa dibuat kapan saja setelah employees ada)*
4. `fo_pipelines` *(FK ke fo_contacts, employees)*
5. `fo_pipeline_leads`
6. `fo_pipeline_ql`
7. `fo_pipeline_pitching`
8. `fo_pitching_participants`
9. `fo_pipeline_followups`
10. `fo_pipeline_wons`
11. `fo_pipeline_losts`
12. `fo_pipeline_logs`

## 6. Yang masih perlu kamu putuskan

- Target di `fo_targets` — cukup 1 angka nominal (`jumlah_target`), atau perlu jenis target lain juga (misal target jumlah kontak baru, jumlah pitching)? Kalau iya, tambah kolom `jenis_target` + generalisasi `jumlah_target` jadi lebih fleksibel.
- Target bulanan, tahunan, atau dua-duanya harus bisa jalan bersamaan?
- Apakah revisi target perlu tercatat historinya (siapa ubah, kapan, dari berapa ke berapa), atau cukup `updated_at` biasa?
- `fo_contact_positions` tetap dipakai atau cukup kolom bebas `profesi`?

---
*Setelah poin di atas fix, tahap selanjutnya: generate file migration Laravel (`php artisan make:migration`) sesuai urutan §5, lalu model + relationship Eloquent-nya.*