# Test Case API Nakama LLM

Skenario pengujian end-to-end melalui endpoint API. Setiap skenario dapat dijalankan dengan curl, Postman, atau Swagger UI (`/docs`).

## Persiapan

Set variabel berikut di terminal sebelum menjalankan perintah:

```bash
export BASE_URL="http://localhost:8000"
export API_KEY="nk-key-budi"
```

Siapkan file uji di folder kerja:

| File                              | Dipakai di | Keterangan                                                         |
| --------------------------------- | ---------- | ------------------------------------------------------------------ |
| `laporan.pdf` atau `laporan.docx` | TC-04      | Dokumen berisi teks dan tabel, lebih baik ada grafik atau foto     |
| `foto.jpg`                        | TC-05      | Foto atau tangkapan layar, misalnya struk, papan nama, atau grafik |

Pastikan server sehat:

```bash
curl "$BASE_URL/health"
```

Hasil yang diharapkan: `"status": "ok"`, `"database": "ok"`, dan `providers` berisi `gemini` dan/atau `anthropic`.

Semua contoh di bawah memakai `"stream": false` agar hasil mudah diperiksa. Untuk menguji streaming, ganti dengan `"stream": true` dan tambahkan opsi `-N` pada curl.

---

## TC-01 Memulai percakapan dengan chat teks

**Tujuan:** model menjawab pertanyaan teks dan percakapan tersimpan di riwayat.

**Langkah 1.** Kirim pesan pertama tanpa `conversation_id`:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Apa itu Renstra dan apa bedanya dengan Renja?",
    "user_id": "pegawai-1024",
    "session_id": "qa-tc01",
    "stream": false
  }'
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`
- [ ] `conversation_id` terisi, catat sebagai `CONV_ID`
- [ ] `message.role` = `assistant`, `message.model` = `gemini-3.7-flash` (atau `gemini-3.6-flash` bila terjadi fallback)
- [ ] `message.markdown` berisi jawaban berformat Markdown
- [ ] Bila model mencari di web: teks memuat penanda `[1]`, `[2]`, dan `message.markdown` diakhiri daftar **Sumber:**
- [ ] `message.tokens.total_tokens` lebih dari 0
- [ ] `title` berisi judul singkat percakapan

**Langkah 2.** Lanjutkan percakapan yang sama:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "conversation_id": "CONV_ID",
    "message": "Ringkas jawabanmu tadi menjadi 3 poin.",
    "stream": false
  }'
```

**Hasil yang diharapkan:**

- [ ] HTTP `200` dengan `conversation_id` yang sama
- [ ] Jawaban merujuk konteks pesan sebelumnya
- [ ] `message.user_id` = `pegawai-1024` dan `message.session_id` = `qa-tc01` (diwarisi dari percakapan)

**Langkah 3.** Periksa riwayat:

```bash
curl "$BASE_URL/v1/conversations/CONV_ID" -H "X-API-Key: $API_KEY"
```

**Hasil yang diharapkan:**

- [ ] `messages` berisi 4 pesan berurutan: user, assistant, user, assistant
- [ ] `tokens.total_tokens` percakapan sama dengan jumlah token kedua jawaban

---

## TC-02 Memulai percakapan dengan chat pembuatan gambar

**Tujuan:** model memanggil alat `generate_image` dan gambar dapat diunduh.

**Langkah 1.** Kirim permintaan gambar:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Buatkan poster ucapan Selamat Hari Kemerdekaan RI ke-81 dengan nuansa merah putih, rasio 16:9.",
    "user_id": "pegawai-1024",
    "session_id": "qa-tc02",
    "stream": false
  }'
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`
- [ ] `message.content` memuat blok `tool_call` dengan `name` = `generate_image` dan `output.status` = `success`
- [ ] `message.content` memuat blok `image` dengan `file_id` dan `url`
- [ ] `message.markdown` memuat sintaks gambar `![...](/v1/files/.../content?exp=...&sig=...)`

**Langkah 2.** Unduh gambar dari `url` pada blok `image` (URL bertanda tangan, tidak butuh API key):

```bash
curl -o poster.png "$BASE_URL/v1/files/FILE_ID/content?exp=EXP&sig=SIG"
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`, atau `307` lalu `200` bila penyimpanan memakai MinIO (tambahkan `-L` pada curl)
- [ ] File terbuka sebagai gambar dengan rasio 16:9

**Langkah 3 (opsional).** Minta revisi pada percakapan yang sama:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": "CONV_ID", "message": "Tambahkan tulisan Dirgahayu Indonesia di bagian atas.", "stream": false}'
```

- [ ] Gambar baru dibuat dengan `reference_file_ids` berisi gambar sebelumnya

---

## TC-03 Memulai percakapan dengan chat pembuatan dokumen

**Tujuan:** model memanggil alat `create_document` dan file Excel/Word/PowerPoint dapat diunduh.

**Langkah 1.** Minta file Excel:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Buatkan file Excel anggaran rapat koordinasi: kolom No, Uraian, Volume, Satuan, Harga Satuan, Jumlah. Isi 5 baris contoh, kolom Jumlah memakai rumus, dan tambahkan baris Total.",
    "user_id": "pegawai-1024",
    "session_id": "qa-tc03",
    "stream": false
  }'
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`
- [ ] `message.content` memuat blok `tool_call` dengan `name` = `create_document` dan `output.status` = `success`
- [ ] `message.content` memuat blok `document` dengan `format` = `xlsx`, `filename` berakhiran `.xlsx`, dan `url`
- [ ] `message.markdown` memuat tautan `[nama-file.xlsx](/v1/files/...)`

**Langkah 2.** Unduh dan buka file:

```bash
curl -L -o anggaran.xlsx "URL_DARI_BLOK_DOCUMENT"
```

**Hasil yang diharapkan:**

- [ ] File terbuka di Excel tanpa peringatan rusak
- [ ] Header tebal dengan latar berwarna, baris header dibekukan, filter aktif
- [ ] Sel kolom Jumlah dan baris Total berisi rumus (misalnya `=C2*E2`, `=SUM(F2:F6)`), bukan angka statis

**Variasi yang perlu diuji:**

| Permintaan                                                                                                        | Hasil yang diharapkan                                                                       |
| ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| "Buatkan notulen rapat dalam format Word"                                                                         | `format` = `docx`, berisi judul, heading, dan daftar                                        |
| "Buatkan 5 slide presentasi tentang program kerja 2027"                                                           | `format` = `pptx`, 5 slide atau lebih                                                       |
| Unggah `template.xlsx` (TC-04 langkah 1), lalu "Isi data pegawai mengikuti format template ini" dengan `file_ids` | Blok `tool_call` memuat `template_file_id`, file hasil mempertahankan kop dan gaya template |

---

## TC-04 Memulai percakapan dengan chat analisis dokumen

**Tujuan:** model membaca isi dokumen, termasuk tabel dan gambar di dalamnya.

**Langkah 1.** Unggah dokumen:

```bash
curl -X POST "$BASE_URL/v1/files" \
  -H "X-API-Key: $API_KEY" \
  -F "files=@laporan.pdf"
```

**Hasil yang diharapkan:**

- [ ] HTTP `201`
- [ ] `files[0].id` terisi, catat sebagai `DOC_ID`
- [ ] `files[0].category` = `pdf` (atau `document` untuk docx/xlsx/pptx)
- [ ] Untuk docx/pptx/xlsx yang berisi gambar: `files[0].meta.embedded_image_ids` berisi daftar ID gambar

**Langkah 2.** Minta analisis:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Ringkas dokumen ini dalam 5 poin, sebutkan angka-angka penting, dan jelaskan isi grafik atau gambar di dalamnya.",
    "file_ids": ["DOC_ID"],
    "user_id": "pegawai-1024",
    "session_id": "qa-tc04",
    "stream": false
  }'
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`
- [ ] Ringkasan sesuai isi dokumen, angka yang disebut ada di dokumen
- [ ] Bila dokumen berisi grafik/foto, jawaban menjelaskan isi visual tersebut
- [ ] `user_message.markdown` memuat tautan lampiran `[laporan.pdf](...)`

**Langkah 3 (opsional).** Lihat teks hasil ekstraksi (untuk dokumen Office):

```bash
curl "$BASE_URL/v1/files/DOC_ID/text" -H "X-API-Key: $API_KEY"
```

- [ ] `text` berisi isi dokumen dalam Markdown, termasuk tabel

---

## TC-05 Memulai percakapan dengan chat analisis gambar

**Tujuan:** model membaca dan menjelaskan isi gambar.

**Langkah 1.** Unggah gambar:

```bash
curl -X POST "$BASE_URL/v1/files" \
  -H "X-API-Key: $API_KEY" \
  -F "files=@foto.jpg"
```

**Hasil yang diharapkan:**

- [ ] HTTP `201`
- [ ] `files[0].category` = `image`, catat `files[0].id` sebagai `IMG_ID`
- [ ] Bila sisi terpanjang gambar lebih dari 2576 px atau ukurannya lebih dari 3,75 MB, gambar diperkecil otomatis dan `meta.resized` = `true`

**Langkah 2.** Minta analisis:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Jelaskan isi gambar ini dan salin semua teks yang terlihat.",
    "file_ids": ["IMG_ID"],
    "user_id": "pegawai-1024",
    "session_id": "qa-tc05",
    "stream": false
  }'
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`
- [ ] Deskripsi sesuai isi gambar dan teks yang disalin cocok dengan yang terlihat
- [ ] `user_message.markdown` memuat pratinjau gambar `![foto.jpg](...)`

**Langkah 3 (opsional).** Edit gambar yang sama:

```bash
curl -X POST "$BASE_URL/v1/chat" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": "CONV_ID", "message": "Ubah latar belakang gambar ini menjadi putih polos.", "stream": false}'
```

- [ ] Blok `tool_call` `generate_image` memuat `reference_file_ids` berisi `IMG_ID` dan blok `image` baru tersedia

---

## TC-06 Menghapus percakapan

**Tujuan:** percakapan dan seluruh pesannya terhapus dan tidak dapat diakses lagi.

**Langkah 1.** Cari percakapan uji:

```bash
curl "$BASE_URL/v1/conversations?user_id=pegawai-1024" -H "X-API-Key: $API_KEY"
```

- [ ] `items` memuat percakapan dari TC-01 sampai TC-05

**Langkah 2.** Hapus percakapan:

```bash
curl -i -X DELETE "$BASE_URL/v1/conversations/CONV_ID" -H "X-API-Key: $API_KEY"
```

**Hasil yang diharapkan:**

- [ ] HTTP `204 No Content`

**Langkah 3.** Pastikan percakapan sudah tidak ada:

```bash
curl -i "$BASE_URL/v1/conversations/CONV_ID" -H "X-API-Key: $API_KEY"
```

**Hasil yang diharapkan:**

- [ ] HTTP `404` dengan `error.code` = `conversation_not_found`
- [ ] Percakapan tidak muncul lagi di `GET /v1/conversations`
- [ ] Menghapus ulang `CONV_ID` yang sama menghasilkan `404`

File yang diunggah atau dihasilkan tidak ikut terhapus bersama percakapan. Hapus terpisah bila perlu:

```bash
curl -i -X DELETE "$BASE_URL/v1/files/FILE_ID" -H "X-API-Key: $API_KEY"
```

---

## Uji negatif singkat

| Skenario                                          | Perintah                                                                                       | Hasil yang diharapkan                                   |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| Tanpa API key (butuh `API_KEYS` terisi di `.env`) | `curl -X POST "$BASE_URL/v1/chat" -H "Content-Type: application/json" -d '{"message":"halo"}'` | `401`, `error.code` = `unauthorized`                    |
| Pesan kosong                                      | `-d '{"message":""}'`                                                                          | `422`, `error.code` = `validation_error`                |
| Model tidak dikenal                               | `-d '{"message":"halo","model":"gpt-4"}'`                                                      | `422`, `error.code` = `unknown_model`                   |
| Percakapan milik API key lain                     | `GET /v1/conversations/CONV_ID` dengan API key berbeda                                         | `404`                                                   |
| Format Office lama                                | Unggah `dokumen.doc`                                                                           | `415`, `error.code` = `legacy_office_format`            |
| Kirim pesan saat jawaban masih berjalan           | Dua `POST /v1/chat` beruntun dengan `conversation_id` sama dan `stream: true`                  | Request kedua `409`, `error.code` = `conversation_busy` |
