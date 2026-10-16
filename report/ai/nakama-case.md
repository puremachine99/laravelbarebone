# Panduan & Test Case API Pemetaan Desk Nakama

API ini menjalankan chatbot pendampingan pemetaan desk: pegawai memilih unit kerja, lalu dipandu 13 langkah tanya-jawab untuk memetakan output kerja, tahapan, kebutuhan data, titik tersulit, dan kondisi ideal. Jawaban dipahami dengan pattern matching; LLM hanya dipakai sebagai cadangan bila pola tidak dikenali.

Dokumen ini berisi cara menerapkan API di frontend dan skenario uji yang bisa dijalankan dengan curl, Postman, atau Swagger UI (`/docs`).

## Daftar isi

1. [Persiapan](#persiapan)
2. [Daftar endpoint](#daftar-endpoint)
3. [Alur 13 langkah](#alur-13-langkah)
4. [Membaca pertanyaan dan mengirim jawaban](#membaca-pertanyaan-dan-mengirim-jawaban)
5. [Pola integrasi frontend](#pola-integrasi-frontend)
6. [Test case](#test-case)
7. [Kode error](#kode-error)
8. [Penyimpanan data](#penyimpanan-data)

---

## Persiapan

```bash
export BASE_URL="http://localhost:4000"
export API_KEY="nk-key-budi"
```

Jalankan server di port 4000 dengan `PORT_URL=4000` di `.env`, atau `uvicorn main:app --port 4000`.

### Autentikasi

Semua endpoint `/v1/mapping/...` memakai API key yang sama dengan API lain. Kirim salah satu header:

```
X-API-Key: nk-key-budi
Authorization: Bearer nk-key-budi
```

- Data dipisah per pemilik API key. Key lain tidak bisa membaca sesi milik key ini (hasilnya `404`).
- Pada `ENVIRONMENT=development` dengan `API_KEYS={}`, semua request diterima tanpa key sebagai pengguna `local`.
- Membuat sesi dan mengirim jawaban dibatasi `RATE_LIMIT_MAPPING_PER_MINUTE` (bawaan 60 per menit per key).

### Daftar unit

Unit kerja dibaca dari `assets/mapping/skills_mapping.json`. Kunci unit memakai `nama_departemen`, nama tampilan memakai `Deskripsi`:

| Kunci (`unit`) | Nama |
|---|---|
| `corporate_secretariat` | Corporate Secretariat |
| `people_and_culture` | People and Culture |
| `finance` | Finance |
| `revenue_and_growth` | Revenue and Growth |
| `product_and_rdi` | Product and RDI |
| `digital_transformation` | Digital Transformation |

Menambah unit cukup dengan menambah entri di file JSON tersebut lalu me-restart server.

---

## Daftar endpoint

| Method | Path | Fungsi |
|---|---|---|
| `GET` | `/v1/mapping/units` | Daftar unit beserta warna, ikon, dan contoh jawaban |
| `POST` | `/v1/mapping/sessions` | Mulai sesi baru, mengembalikan sapaan dan pertanyaan langkah 1 |
| `GET` | `/v1/mapping/sessions` | Daftar sesi, filter `user_id`, `unit`, `status` |
| `GET` | `/v1/mapping/sessions/{session_id}` | Detail sesi dan seluruh riwayat chat (untuk melanjutkan sesi) |
| `GET` | `/v1/mapping/sessions/{session_id}/messages` | Riwayat chat saja |
| `POST` | `/v1/mapping/sessions/{session_id}/messages` | Kirim jawaban, mengembalikan balasan dan pertanyaan berikutnya |
| `POST` | `/v1/mapping/sessions/{session_id}/pause` | Jeda sesi dan simpan transkrip |
| `GET` | `/v1/mapping/sessions/{session_id}/export` | Unduh ringkasan sesi, `format=markdown` atau `json` |
| `GET` | `/v1/mapping/summaries` | Daftar ringkasan probis per pegawai, filter `unit`, `status`, `q`, `user_id` |
| `GET` | `/v1/mapping/summaries/{session_id}` | Ringkasan probis satu pegawai |
| `GET` | `/v1/mapping/units/{unit_key}/summary` | Statistik agregat satu unit |
| `GET` | `/v1/mapping/units/{unit_key}/export` | Ekspor seluruh jawaban unit, `format=csv` atau `json` |

Semua daftar memakai `limit` (1–200, bawaan 30) dan `offset`.

---

## Alur 13 langkah

| Langkah | Pertanyaan | `ui.type` | Data yang disimpan (`field`) |
|---|---|---|---|
| 1 | Output kerja apa saja? | `list` | `outputs` |
| 2 | Senang atau tidak dengan pekerjaan? | `scale` | `sentiment` (skor 1–5) |
| 3 | Output mana paling sulit? | `choice` | `hardest_output` |
| 4 | Berapa lama output dibuat? | `duration` | `duration` (angka + satuan) |
| 5 | Jelaskan tahapannya | `stages` | `stages` |
| 6 | Tampilkan peta tahapan | `stage_cards` (tanpa input) | — |
| 7 | Yakin tahapan lengkap? | `confirm` | `stages_confirmed` |
| 8 | Data input tiap tahap | `list` + `context` | `stage_inputs` |
| 9 | Data output tiap tahap | `list` + `context` | `stage_outputs` |
| 10 | Tahap mana paling sulit? | `choice` (dengan alasan) | `hardest_stage` |
| 11 | Kondisi ideal | `text` | `ideal` (+ tag otomatis) |
| 12 | Ada output lain? | `confirm` | `more_outputs` |
| 13 | Penutup | `summary` (tanpa input) | `session_completed` |

Dua titik putar ulang:

- Langkah 7 → 5 bila pengguna memilih **Masih ada yang kurang**. Tahapan lama dikirim kembali di `ui.prefill`.
- Langkah 12 → 1 bila pengguna memilih **Ada, petakan output lain**. `progress.output_no` bertambah.

Langkah 8 dan 9 diulang untuk setiap tahap, satu tahap per waktu (8 → 9 → tahap berikutnya → … → 10).

Server juga dapat mengirim pertanyaan di luar urutan:

| `kind` | Kapan muncul |
|---|---|
| `followup` | Jawaban terlalu pendek atau terlalu umum, misalnya hanya “data” di langkah 8, atau alasan di langkah 10 kurang dari 4 kata |
| `faq` | Pengguna bertanya soal privasi, tujuan, sisa langkah, atau minta contoh. Pertanyaan semula diulang setelahnya |
| `info` | Konfirmasi jeda, atau sesi sudah selesai |

---

## Membaca pertanyaan dan mengirim jawaban

### Struktur pesan

Setiap pesan dari server memiliki bentuk berikut:

```json
{
  "id": "msg_054e9b0b6ed04aa4a1e0c4994087532f",
  "role": "assistant",
  "kind": "question",
  "text": "Sebelum masuk detail, jujur-jujuran saja: kamu senang nggak dengan pekerjaanmu sekarang?",
  "step": 2,
  "ui": { "type": "scale", "...": "..." },
  "context": null,
  "method": "pattern",
  "created_at": "2026-10-15T05:58:54.238928Z"
}
```

| Field | Arti |
|---|---|
| `kind` | `greeting`, `question`, `followup`, `ack`, `faq`, `info`, `stage_cards`, `summary`, atau `answer` (pesan pengguna) |
| `text` | Teks siap tampil |
| `step` | Langkah ke berapa pesan ini |
| `ui` | Spesifikasi input yang diharapkan. `null` untuk pesan biasa |
| `context` | Chip konteks tahap pada langkah 8–9: `{"stage", "stage_no", "stage_total"}` |
| `method` | `pattern`, `form`, `llm`, atau `auto`. `llm` berarti jawaban dibantu LLM cadangan |

Input yang harus ditampilkan adalah pesan **terakhir** yang `ui.type`-nya salah satu dari `list`, `scale`, `choice`, `duration`, `stages`, `confirm`, `text`. `stage_cards` dan `summary` hanya untuk ditampilkan.

### Payload jawaban

Body `POST /v1/mapping/sessions/{id}/messages` punya tiga field, minimal salah satu terisi:

| Field | Tipe | Batas |
|---|---|---|
| `text` | string | maks 4000 karakter |
| `items` | array string | maks 30 item, tiap item maks 300 karakter |
| `value` | string | maks 32 karakter |

Cara mengisi per `ui.type`:

| `ui.type` | Payload | Contoh |
|---|---|---|
| `list` | `items` berisi isian. Bila ada `ui.skip_label` dan pengguna tidak punya isian: `value: "skip"` | `{"items": ["Laporan arus kas", "Rekonsiliasi bank"]}` |
| `scale` | `value` = `ui.options[].value` (`"1"`–`"5"`), `text` opsional | `{"value": "3", "text": "kadang stres karena input manual"}` |
| `choice` | `value` = `ui.options[].value`. Bila `ui.reason` true, isi alasan di `text` | `{"value": "1", "text": "nunggu balasan unit lama"}` |
| `duration` | `text` berisi angka + satuan (+ frekuensi) | `{"text": "2 hari per minggu", "value": "duration"}` |
| `stages` | `items` berurutan, satu item per tahap | `{"items": ["Unduh mutasi bank", "Cocokkan di excel"]}` |
| `confirm` | `value` = `ui.options[].value` | `{"value": "yes"}` |
| `text` | `text` | `{"text": "Idealnya ada notifikasi otomatis ke PIC unit"}` |

Jawaban teks bebas juga diterima di semua langkah dan dipahami dengan pattern matching, misalnya “yang ketiga”, “sudah lengkap”, “2-3 hari kerja per minggu”, “cukup menikmati tapi kadang stres”, atau “masih ada”.

### Respons jawaban

```json
{
  "session": { "id": "map_...", "status": "active", "progress": { "...": "..." }, "prompt": [ "..." ], "summary": { "...": "..." } },
  "user_message": { "role": "user", "kind": "answer", "text": "• Laporan arus kas\n• Rekonsiliasi bank", "...": "..." },
  "messages": [
    { "kind": "ack", "text": "Oke, tercatat rapi. 📌 Output-output itu sudah masuk daftar.", "ui": null },
    { "kind": "question", "step": 2, "text": "Sebelum masuk detail, ...", "ui": { "type": "scale", "...": "..." } }
  ]
}
```

`session.progress` dipakai untuk indikator progres:

```json
{
  "step": 8,
  "total_steps": 13,
  "title": "Data input",
  "percent": 54,
  "output_no": 1,
  "output_name": "Rekonsiliasi bank",
  "outputs_total": 2,
  "outputs_mapped": 0,
  "stage_no": 1,
  "stage_total": 2,
  "stage_name": "Unduh mutasi bank"
}
```

`session.status` bernilai `active`, `paused`, atau `completed`.

---

## Pola integrasi frontend

```js
const BASE_URL = "http://localhost:4000";
const headers = { "Content-Type": "application/json", "X-API-Key": API_KEY };

async function api(path, options = {}) {
  const response = await fetch(BASE_URL + path, { ...options, headers });
  const body = await response.json();
  if (!response.ok) throw Object.assign(new Error(body.error.message), { code: body.error.code, status: response.status });
  return body;
}

function activePrompt(messages) {
  const inputs = ["list", "scale", "choice", "duration", "stages", "confirm", "text"];
  return [...messages].reverse().find((m) => m.ui && inputs.includes(m.ui.type)) || null;
}

const session = await api("/v1/mapping/sessions", {
  method: "POST",
  body: JSON.stringify({ unit: "finance", name: "Dimas", anonymous: false, user_id: clientId }),
});
localStorage.setItem("pemetaan-session", session.id);
render(session.messages);
showInput(activePrompt(session.messages));

async function answer(payload) {
  const turn = await api(`/v1/mapping/sessions/${session.id}/messages`, { method: "POST", body: JSON.stringify(payload) });
  render(turn.messages);
  updateProgress(turn.session.progress);
  if (turn.session.status === "completed") return showClosing(turn.session.summary);
  showInput(activePrompt(turn.messages) || activePrompt(turn.session.prompt));
}
```

Hal yang perlu diperhatikan:

1. **Tampilkan pesan satu per satu.** `messages` bisa berisi beberapa pesan (ack → kartu tahapan → pertanyaan). Beri jeda singkat antar pesan agar terasa seperti chat.
2. **Melanjutkan sesi.** Simpan `session.id` di `localStorage`. Saat halaman dibuka lagi, panggil `GET /v1/mapping/sessions/{id}`, render `messages`, lalu tampilkan input dari `prompt`. Sesi `paused` otomatis aktif lagi saat jawaban berikutnya dikirim.
3. **Satu jawaban dalam satu waktu.** Kunci tombol kirim selama request berjalan. Request paralel ke sesi yang sama ditolak dengan `409 mapping_busy`.
4. **Identitas unit.** Pakai `unit.color`, `unit.soft_color`, `unit.icon`, `unit.emoji`, dan `unit.floating` agar tampilan tiap unit konsisten sepanjang sesi.
5. **Layar penutup.** Saat `status` = `completed`, data ringkasan ada di `session.summary` (atau `GET /v1/mapping/summaries/{id}`).
6. **`user_id`** opsional. Isi dengan ID unik per perangkat atau pegawai agar sesi bisa difilter (`GET /v1/mapping/sessions?user_id=...`).

Contoh implementasi lengkap tersedia di `prototype-govai-frontend/public/desk-nakama.html`.

---

## Test case

Ganti `SESSION_ID` dengan nilai `id` dari TC-M02.

### TC-M01 Mengambil daftar unit

```bash
curl "$BASE_URL/v1/mapping/units" -H "X-API-Key: $API_KEY"
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`
- [ ] `items` berisi 6 unit sesuai `skills_mapping.json`
- [ ] Setiap unit memiliki `key`, `name`, `color`, `icon`, `emoji`, `floating`, `sample_outputs`, `sample_stages`

### TC-M02 Memulai sesi

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"unit": "finance", "name": "Dimas", "anonymous": false, "user_id": "web-123"}'
```

**Hasil yang diharapkan:**

- [ ] HTTP `201`
- [ ] `id` diawali `map_`, catat sebagai `SESSION_ID`
- [ ] `status` = `active`, `progress.step` = `1`
- [ ] `messages[0].kind` = `greeting`, berisi nama dan unit
- [ ] `messages[1].ui.type` = `list`, `ui.examples` berisi contoh output khas Finance
- [ ] Dengan `"anonymous": true`, `display_name` = `Rekan Anonim (Finance)` dan sapaan memakai “Rekan”
- [ ] Dengan `"unit": "keuangan"` → HTTP `422`, `error.code` = `unknown_unit`

### TC-M03 Langkah 1–4: output, perasaan, output tersulit, durasi

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"items": ["Laporan arus kas", "Rekonsiliasi bank"]}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"value": "3", "text": "cukup menikmati, tapi sering japri WA satu-satu"}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "yang rekonsiliasi"}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "2-3 hari kerja per minggu"}'
```

**Hasil yang diharapkan:**

- [ ] Setelah jawaban 1: pertanyaan berikutnya `ui.type` = `scale`
- [ ] Setelah jawaban 2: `ack` memuat empati kebutuhan (“Bolak-balik konfirmasi lewat chat…”), pertanyaan berikutnya `ui.type` = `choice` berisi 2 output
- [ ] Setelah jawaban 3: `progress.output_name` = `Rekonsiliasi bank`, pertanyaan berikutnya `ui.type` = `duration`
- [ ] Setelah jawaban 4: `ack` berisi “2–3 hari per minggu”, pertanyaan berikutnya `ui.type` = `stages`
- [ ] Semua balasan `method` = `pattern` (tanpa LLM)

### TC-M04 Langkah 5–7: tahapan dan putar ulang revisi

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "pertama unduh mutasi dari bank, lalu cocokkan di excel, kemudian konfirmasi selisih via WA"}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"value": "revise"}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"items": ["Unduh mutasi dari bank", "Cocokkan di excel", "Konfirmasi selisih via WA", "Susun ringkasan saldo"]}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "sudah lengkap"}'
```

**Hasil yang diharapkan:**

- [ ] Jawaban teks dipecah menjadi 3 tahap (kata “pertama”, “lalu”, “kemudian” dibuang)
- [ ] Respons berisi pesan `stage_cards` (langkah 6) dengan `ui.stages[]` ber-`icon` dan `emoji`, lalu pertanyaan `confirm` (langkah 7)
- [ ] Setelah `revise`: `progress.step` = `5`, `ui.prefill` berisi 3 tahap sebelumnya
- [ ] Setelah 4 tahap dan “sudah lengkap”: `progress.step` = `8`, `context.stage` = `Unduh mutasi dari bank`, `context.stage_total` = `4`

### TC-M05 Langkah 8–9: data input dan output per tahap

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "data"}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"items": ["Akses internet banking", "Token bank"]}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"value": "skip"}'
```

Ulangi pasangan input/output untuk tahap 2–4.

**Hasil yang diharapkan:**

- [ ] Jawaban “data” memicu `kind` = `followup` (““Data” yang seperti apa?”), langkah tetap 8
- [ ] Setelah jawaban rinci: lanjut ke langkah 9 untuk tahap yang sama
- [ ] `value: "skip"` diterima sebagai “tidak ada”, lanjut ke langkah 8 tahap berikutnya dengan `context.stage_no` = `2`
- [ ] Setelah tahap terakhir: `progress.step` = `10`

### TC-M06 Langkah 10–13: tahap tersulit, ideal, dan selesai

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"value": "2", "text": "nunggu balasan unit lama banget, bisa 2 hari"}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "Idealnya ada portal konfirmasi otomatis dengan notifikasi ke PIC unit"}'

curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"value": "done"}'
```

**Hasil yang diharapkan:**

- [ ] Tahap tersulit = tahap ke-3 (`value` dihitung dari 0)
- [ ] Alasan kurang dari 4 kata memicu `followup` “Kenapa tahap … terasa paling sulit?”
- [ ] Ideal kurang dari 5 kata memicu `followup` “Boleh dijelaskan lebih?”
- [ ] Setelah `done`: `session.status` = `completed`, `progress.percent` = `100`, pesan terakhir `kind` = `summary`
- [ ] `session.summary.totals.outputs_mapped` = `1`, `summary.outputs[0].ideal_tags` memuat `Otomasi` dan `Notifikasi`
- [ ] `session.transcript_file_id` terisi (transkrip tersimpan di MinIO)
- [ ] Mengirim jawaban lagi ke sesi ini dibalas `info` “Sesi ini sudah selesai…”

### TC-M07 Putar ulang ke output berikutnya

Ulangi TC-M02 sampai langkah 12, lalu kirim:

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "masih ada"}'
```

**Hasil yang diharapkan:**

- [ ] `progress.step` = `1`, `progress.output_no` = `2`
- [ ] Pertanyaan menyebut output yang belum dibedah dan `ui.skip_label` = `Lanjut dengan daftar ini`
- [ ] Mengirim `{"value": "skip"}` atau “lanjut” melanjutkan ke langkah 2 tanpa menambah output baru
- [ ] Bila tinggal satu output yang belum dibedah, langkah 3 dilewati otomatis

### TC-M08 Pertanyaan FAQ di tengah sesi

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/messages" \
  -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"text": "atasan saya bisa lihat jawaban ini gak?"}'
```

**Hasil yang diharapkan:**

- [ ] `messages[0].kind` = `faq` berisi penjelasan privasi
- [ ] `messages[1]` mengulang pertanyaan langkah yang sama, `progress.step` tidak berubah
- [ ] Contoh lain: “untuk apa sih ini?” (tujuan), “masih lama gak?” (sisa langkah), “contohnya gimana?” (contoh khas unit)

### TC-M09 Jeda dan lanjutkan sesi

```bash
curl -X POST "$BASE_URL/v1/mapping/sessions/$SESSION_ID/pause" -H "X-API-Key: $API_KEY"

curl "$BASE_URL/v1/mapping/sessions/$SESSION_ID" -H "X-API-Key: $API_KEY"
```

**Hasil yang diharapkan:**

- [ ] Respons pause: `status` = `paused`, `transcript_file_id` terisi
- [ ] Detail sesi: `messages` berisi seluruh riwayat, `prompt` berisi pertanyaan terakhir beserta `ui`
- [ ] Mengirim teks “jeda dulu ya” lewat endpoint messages juga menjeda sesi
- [ ] Jawaban berikutnya membuat `status` kembali `active`

### TC-M10 Daftar ringkasan probis per pegawai

```bash
curl "$BASE_URL/v1/mapping/summaries?unit=finance&status=completed&limit=20" -H "X-API-Key: $API_KEY"

curl "$BASE_URL/v1/mapping/summaries?q=dimas" -H "X-API-Key: $API_KEY"

curl "$BASE_URL/v1/mapping/summaries/$SESSION_ID" -H "X-API-Key: $API_KEY"
```

**Hasil yang diharapkan:**

- [ ] HTTP `200`, `items` diurutkan dari yang terakhir diperbarui, `total` sesuai filter
- [ ] Setiap item memuat `employee_name`, `unit_name`, `status`, `percent`, `totals`, `sentiment_average`, `needs`, dan `outputs[]`
- [ ] `outputs[].stages[]` memuat `inputs`, `outputs`, dan `hardest`
- [ ] Sesi anonim tampil sebagai `employee_name` = `Anonim` dan tidak ikut hasil pencarian `q`
- [ ] `unit=xxx` → HTTP `422`, ID tidak dikenal → HTTP `404`

Contoh satu item:

```json
{
  "session_id": "map_...",
  "employee_name": "Dimas",
  "unit_name": "Finance",
  "status": "completed",
  "percent": 100,
  "sentiment_average": 3.0,
  "totals": { "outputs_listed": 2, "outputs_mapped": 1, "stages": 4, "inputs": 2, "results": 3, "bottlenecks": 1 },
  "needs": [{ "key": "coordination", "label": "Notifikasi & pelacakan", "count": 2 }],
  "outputs": [
    {
      "output_no": 1,
      "name": "Rekonsiliasi bank",
      "sentiment_label": "Campur aduk",
      "duration_text": "2–3 hari per minggu",
      "stages": [{ "no": 1, "name": "Unduh mutasi dari bank", "inputs": ["Akses internet banking"], "outputs": [], "hardest": false }],
      "hardest_stage": { "index": 2, "name": "Konfirmasi selisih via WA", "reason": "nunggu balasan unit lama banget, bisa 2 hari" },
      "ideal": "Idealnya ada portal konfirmasi otomatis dengan notifikasi ke PIC unit",
      "ideal_tags": ["Otomasi", "Notifikasi", "Sistem terintegrasi"]
    }
  ]
}
```

### TC-M11 Ringkasan dan ekspor per unit

```bash
curl "$BASE_URL/v1/mapping/units/finance/summary" -H "X-API-Key: $API_KEY"

curl -o finance.csv "$BASE_URL/v1/mapping/units/finance/export?format=csv" -H "X-API-Key: $API_KEY"

curl -o ringkasan.md "$BASE_URL/v1/mapping/sessions/$SESSION_ID/export?format=markdown" -H "X-API-Key: $API_KEY"
```

**Hasil yang diharapkan:**

- [ ] Summary memuat `sessions`, `completed`, `completion_rate`, `participants`, `sentiment_average`, `hardest_outputs`, `hardest_stages`, `needs`, `ideal_tags`, `pattern_turns`, `llm_turns`
- [ ] CSV terbuka rapi di Excel, satu baris per jawaban dengan kolom unit, pengguna, output ke-, langkah ke-, field, dan nilai
- [ ] Hanya jawaban terbaru yang diekspor; jawaban yang direvisi (langkah 5–7) tidak terhitung dua kali
- [ ] Markdown berisi tabel tahapan, data input/output, tahap tersulit, kondisi ideal, dan transkrip

### TC-M12 Validasi dan keamanan

| Skenario | Request | Hasil yang diharapkan |
|---|---|---|
| Tanpa API key (key aktif) | `GET /v1/mapping/summaries` tanpa header | `401 unauthorized` |
| API key salah | header `X-API-Key: salah` | `401 unauthorized` |
| Jawaban kosong | body `{}` | `422 empty_answer` |
| Data sensitif | `{"text": "NIK 3201010101900001"}` | `422 sensitive_data_detected`, nilai tersamar `3201********0001` |
| Kirim paralel | dua jawaban bersamaan ke sesi yang sama | salah satu `409 mapping_busy` |
| Sesi milik key lain | `GET /v1/mapping/sessions/{id}` dengan key berbeda | `404 mapping_session_not_found` |
| Terlalu sering | lebih dari 60 jawaban per menit | `429 rate_limited` dengan header `Retry-After` |

---

## Kode error

Format error sama dengan API lain:

```json
{"error": {"code": "empty_answer", "message": "Jawaban tidak boleh kosong."}}
```

| HTTP | Kode | Penyebab | Saran tampilan |
|---|---|---|---|
| 401 | `unauthorized` | API key tidak dikirim atau salah | Minta kode akses |
| 404 | `mapping_session_not_found` | Sesi tidak ada atau milik key lain | Hapus ID sesi tersimpan, mulai sesi baru |
| 409 | `mapping_busy` | Jawaban sebelumnya masih diproses | Tunggu lalu kirim ulang |
| 422 | `unknown_unit` | Kunci unit tidak dikenal (`details.allowed` berisi daftar valid) | Muat ulang daftar unit |
| 422 | `empty_answer` | `text`, `items`, dan `value` kosong | Minta pengguna mengisi jawaban |
| 422 | `sensitive_data_detected` | Jawaban memuat NIK, NPWP, nomor rekening, kartu, atau kredensial | Tampilkan `message`, minta samarkan data |
| 422 | `validation_error` | Field melebihi batas panjang | Batasi input di frontend |
| 429 | `rate_limited` | Melebihi batas per menit | Tunggu sesuai `Retry-After` |

---

## Penyimpanan data

| Data | Lokasi |
|---|---|
| Status sesi dan state alur | PostgreSQL, tabel `mapping_sessions` |
| Jawaban ter-tag (unit, pengguna, output ke-, langkah ke-, tahap ke-) | PostgreSQL, tabel `mapping_responses` |
| Riwayat chat | PostgreSQL, tabel `conversations` dan `messages` (provider `pattern`) |
| Cache status sesi dan riwayat chat | Redis, TTL `MAPPING_CACHE_TTL_SECONDS` (bawaan 24 jam) |
| Cache hasil LLM cadangan | Redis, TTL `MAPPING_LLM_CACHE_SECONDS` (bawaan 7 hari) |
| Transkrip JSON per sesi | MinIO, disimpan setiap satu output selesai, saat dijeda, dan saat sesi selesai |

Tabel dibuat otomatis saat server start.

### Penggunaan LLM

LLM hanya dipanggil bila pattern matching gagal: perasaan yang tidak bisa disimpulkan, durasi tanpa angka yang jelas, pilihan yang ambigu, atau pertanyaan di luar FAQ. Pengaturannya:

| Variabel | Bawaan | Fungsi |
|---|---|---|
| `MAPPING_LLM_ENABLED` | `true` | `false` untuk pattern matching saja |
| `MAPPING_LLM_MAX_CALLS_PER_SESSION` | `20` | Batas panggilan LLM per sesi |
| `MAPPING_LLM_TIMEOUT_SECONDS` | `20` | Batas waktu satu panggilan |

Pantau rasionya lewat `pattern_turns` dan `llm_turns` di detail sesi atau di `GET /v1/mapping/units/{unit_key}/summary`.
