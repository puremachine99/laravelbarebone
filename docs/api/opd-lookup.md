# OPD Lookup API

Endpoints untuk mengisi dropdown cascading **Provinsi → Kabupaten/Kota → OPD** dari aplikasi lain. Semua endpoint bersifat publik (belum ada middleware auth), read-only, dan tidak dipaginasi — response berisi seluruh hasil yang cocok dengan filter.

Base URL: `{APP_URL}/api`

## Alur penggunaan (cascading dropdown)

1. Panggil `GET /api/provinces` untuk populate dropdown provinsi.
2. Setelah user memilih provinsi, panggil `GET /api/regencies?provinsi=<nama provinsi>` untuk populate dropdown kabupaten/kota.
3. Setelah user memilih kabupaten/kota (opsional), panggil `GET /api/opds?provinsi=<nama provinsi>&kabupaten=<nama kabupaten>` untuk populate dropdown OPD.

Semua pencarian nama menggunakan **partial match** (`LIKE %keyword%`), jadi tidak perlu mengirim nama yang persis sama — cukup id/nama yang ditampilkan pada dropdown sebelumnya.

---

## `GET /api/provinces`

List provinsi, opsional difilter dengan keyword nama.

### Query Parameters

| Parameter | Wajib | Tipe   | Keterangan                                                    |
|-----------|-------|--------|-----------------------------------------------------------------|
| `keyword` | Tidak | string | Filter nama provinsi, partial match, **case-insensitive**.     |

### Contoh Request

```
GET /api/provinces
GET /api/provinces?keyword=jawa
```

### Contoh Response `200 OK`

```json
{
  "data": [
    { "id": 1, "name": "Jawa Barat" },
    { "id": 2, "name": "Jawa Timur" }
  ]
}
```

---

## `GET /api/regencies`

List kabupaten/kota dalam suatu provinsi. `provinsi` wajib diisi.

### Query Parameters

| Parameter   | Wajib | Tipe   | Keterangan                                                          |
|-------------|-------|--------|------------------------------------------------------------------------|
| `provinsi`  | Ya    | string | Nama provinsi, partial match.                                          |
| `keyword`   | Tidak | string | Filter tambahan nama kabupaten/kota, partial match.                     |

### Contoh Request

```
GET /api/regencies?provinsi=Jawa Timur
GET /api/regencies?provinsi=Jawa Timur&keyword=Malang
```

### Contoh Response `200 OK`

```json
{
  "data": [
    { "id": 12, "name": "Kabupaten Malang", "type": "Kabupaten", "provinsi": "Jawa Timur" },
    { "id": 13, "name": "Kota Malang", "type": "Kota", "provinsi": "Jawa Timur" }
  ]
}
```

### Response `422 Unprocessable Content` (jika `provinsi` tidak dikirim)

```json
{
  "message": "The provinsi field is required.",
  "errors": {
    "provinsi": ["The provinsi field is required."]
  }
}
```

---

## `GET /api/opds`

List OPD (Organisasi Perangkat Daerah). `provinsi` wajib diisi, `kabupaten` dan `keyword` opsional untuk mempersempit hasil.

### Query Parameters

| Parameter    | Wajib | Tipe   | Keterangan                                                    |
|--------------|-------|--------|-------------------------------------------------------------------|
| `provinsi`   | Ya    | string | Nama provinsi, partial match.                                      |
| `kabupaten`  | Tidak | string | Filter nama kabupaten/kota, partial match.                         |
| `keyword`    | Tidak | string | Filter nama OPD, partial match.                                    |

### Contoh Request

```
GET /api/opds?provinsi=Jawa Timur
GET /api/opds?provinsi=Jawa Timur&kabupaten=Malang
GET /api/opds?provinsi=Jawa Timur&kabupaten=Malang&keyword=Pendidikan
```

### Contoh Response `200 OK`

```json
{
  "data": [
    { "id": 45, "name": "Dinas Pendidikan", "regency": "Kabupaten Malang", "provinsi": "Jawa Timur" }
  ]
}
```

### Response `422 Unprocessable Content` (jika `provinsi` tidak dikirim)

```json
{
  "message": "The provinsi field is required.",
  "errors": {
    "provinsi": ["The provinsi field is required."]
  }
}
```

---

## Catatan Implementasi

- Response tidak dipaginasi — cocok untuk populate dropdown langsung, tapi hindari memanggil `/api/opds` tanpa filter yang cukup spesifik jika jumlah OPD sangat besar.
- Pencarian `keyword` pada `/api/provinces` case-insensitive secara eksplisit (menggunakan `LOWER()` di query, tidak bergantung pada collation database). Pencarian pada `/api/regencies` dan `/api/opds` menggunakan `LIKE` biasa — case-insensitive selama collation database default (utf8mb4_unicode_ci/general_ci di MySQL, atau SQLite untuk testing).
- Relasi data: `Opd belongsTo Regency`, `Regency belongsTo Province`. Endpoint OPD dan Regency melakukan join tidak langsung (`whereHas`) melalui relasi ini.
- Source code: `app/Http/Controllers/Api/{ProvinceController,RegencyController,OpdController}.php`, resource: `app/Http/Resources/{ProvinceResource,RegencyResource,OpdResource}.php`, route: `routes/api.php`.
