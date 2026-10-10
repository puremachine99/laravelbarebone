<x-layouts::app.sidebar title="API Documentation">
    <div x-data="{ activeSection: 'overview' }" class="space-y-4">

        {{-- Page Header --}}
        <div>
            <h1 class="text-xl font-bold text-base-content">OPD Lookup API Documentation</h1>
            <p class="text-sm text-base-content/60 mt-0.5">
                Dokumentasi teknis untuk developer yang ingin mengintegrasikan dropdown Provinsi &rarr; Kabupaten/Kota
                &rarr; OPD dari aplikasi lain, dan untuk layanan AI yang membaca data Nakama lewat
                <a href="#ai-data" class="link">AI Data API</a>.
            </p>
        </div>

        <div class="flex flex-col lg:flex-row gap-4 items-start">

            {{-- LEFT: Table of contents --}}
            <div class="lg:w-56 w-full shrink-0 lg:sticky lg:top-4">
                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-3 gap-1">
                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider px-2 pb-1">
                            Daftar Isi</p>
                        <a href="#overview" class="btn btn-sm btn-ghost justify-start">Overview</a>
                        <a href="#alur" class="btn btn-sm btn-ghost justify-start">Alur Cascading Dropdown</a>
                        <a href="#cara-pakai" class="btn btn-sm btn-ghost justify-start">Cara Pakai (Contoh Kode)</a>
                        <a href="#provinces" class="btn btn-sm btn-ghost justify-start">GET /api/provinces</a>
                        <a href="#regencies" class="btn btn-sm btn-ghost justify-start">GET /api/regencies</a>
                        <a href="#opds" class="btn btn-sm btn-ghost justify-start">GET /api/opds</a>
                        <a href="#opds-search" class="btn btn-sm btn-ghost justify-start">GET /api/opds/search</a>
                        <a href="#catatan" class="btn btn-sm btn-ghost justify-start">Catatan Implementasi</a>
                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider px-2 pt-3 pb-1">
                            AI Data API</p>
                        <a href="#ai-data" class="btn btn-sm btn-ghost justify-start">Overview AI Data</a>
                        <a href="#ai-auth" class="btn btn-sm btn-ghost justify-start">Autentikasi</a>
                        <a href="#ai-datasets" class="btn btn-sm btn-ghost justify-start">GET /api/ai/datasets</a>
                        <a href="#ai-query" class="btn btn-sm btn-ghost justify-start">POST /api/ai/query</a>
                        <a href="#ai-testing" class="btn btn-sm btn-ghost justify-start">Cara Testing</a>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Content --}}
            <div class="flex-1 min-w-0 space-y-4 w-full">

                {{-- Overview --}}
                <div id="overview" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Overview</h2>
                        <p class="text-sm text-base-content/70">
                            Empat endpoint publik, read-only. Tiga pertama untuk mengisi dropdown cascading
                            <span class="font-semibold">Provinsi &rarr; Kabupaten/Kota &rarr; OPD</span>
                            (response tidak dipaginasi), dan
                            <code class="text-xs">/api/opds/search</code> untuk pencarian OPD berdasarkan apa saja
                            (dipaginasi).
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>Base URL: {{ config('app.url') }}/api</code></pre>
                        </div>
                    </div>
                </div>

                {{-- Alur --}}
                <div id="alur" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Alur Cascading Dropdown</h2>
                        <ol class="list-decimal list-inside text-sm text-base-content/70 space-y-1.5">
                            <li>Panggil <code class="text-xs">GET /api/provinces</code> untuk populate dropdown
                                provinsi.</li>
                            <li>Setelah user memilih provinsi, panggil
                                <code class="text-xs">GET /api/regencies?provinsi=&lt;nama provinsi&gt;</code>
                                untuk populate dropdown kabupaten/kota.
                            </li>
                            <li>Setelah user memilih kabupaten/kota (opsional), panggil
                                <code class="text-xs">GET /api/opds?provinsi=&lt;nama provinsi&gt;&amp;kabupaten=&lt;nama
                                    kabupaten&gt;</code>
                                untuk populate dropdown OPD.
                            </li>
                        </ol>
                        <div class="alert alert-info text-xs mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Semua pencarian nama menggunakan <strong>partial match</strong>
                                (<code>LIKE %keyword%</code>), jadi tidak perlu mengirim nama yang persis sama.</span>
                        </div>
                    </div>
                </div>

                {{-- Cara Pakai --}}
                <div id="cara-pakai" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Cara Pakai (Contoh Kode)</h2>

                        <p class="text-sm text-base-content/70">1. Populate dropdown <strong>provinsi</strong> saat
                            halaman dibuka.</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>const res = await fetch('{{ config('app.url') }}/api/provinces');
const { data: provinces } = await res.json();

// data: [{ id: 1, name: "Jawa Barat" }, { id: 2, name: "Jawa Timur" }, ...]
provinces.forEach(p => provinsiSelect.add(new Option(p.name, p.name)));</code></pre>
                        </div>

                        <p class="text-sm text-base-content/70 mt-2">2. Saat user memilih provinsi, populate dropdown
                            <strong>kabupaten/kota</strong>.
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>provinsiSelect.addEventListener('change', async (e) => {
    const provinsi = e.target.value; // nama provinsi dari option yang dipilih

    const res = await fetch(`{{ config('app.url') }}/api/regencies?provinsi=${encodeURIComponent(provinsi)}`);
    const { data: regencies } = await res.json();

    kabupatenSelect.innerHTML = '';
    regencies.forEach(r => kabupatenSelect.add(new Option(r.name, r.name)));
});</code></pre>
                        </div>

                        <p class="text-sm text-base-content/70 mt-2">3. Saat user memilih kabupaten/kota, populate
                            dropdown <strong>OPD</strong>.</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>kabupatenSelect.addEventListener('change', async (e) => {
    const provinsi = provinsiSelect.value;
    const kabupaten = e.target.value;

    const params = new URLSearchParams({ provinsi, kabupaten });
    const res = await fetch(`{{ config('app.url') }}/api/opds?${params}`);
    const { data: opds } = await res.json();

    opdSelect.innerHTML = '';
    opds.forEach(o => opdSelect.add(new Option(o.name, o.id)));
});</code></pre>
                        </div>

                        <div class="alert alert-info text-xs mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Jika endpoint dipanggil dari domain berbeda (aplikasi lain), pastikan CORS
                                mengizinkan origin tersebut, atau panggil dari server-side (backend-to-backend) untuk
                                menghindari isu CORS di browser.</span>
                        </div>
                    </div>
                </div>

                {{-- /api/provinces --}}
                <div id="provinces" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/provinces</h2>
                        </div>
                        <p class="text-sm text-base-content/70">List provinsi, opsional difilter dengan keyword nama.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Query
                            Parameters</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Wajib</th>
                                        <th>Tipe</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">keyword</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Filter nama provinsi, partial match, case-insensitive.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh
                            Request</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl "{{ config('app.url') }}/api/provinces?keyword=jawa"</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "data": [
    { "id": 1, "name": "Jawa Barat" },
    { "id": 2, "name": "Jawa Timur" }
  ]
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- /api/regencies --}}
                <div id="regencies" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/regencies</h2>
                        </div>
                        <p class="text-sm text-base-content/70">List kabupaten/kota dalam suatu provinsi.
                            <span class="font-semibold">provinsi</span> wajib diisi.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Query
                            Parameters</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Wajib</th>
                                        <th>Tipe</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">provinsi</td>
                                        <td><span class="badge badge-error badge-sm text-white">Ya</span></td>
                                        <td>string</td>
                                        <td>Nama provinsi, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">keyword</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Filter tambahan nama kabupaten/kota, partial match.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh
                            Request</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl "{{ config('app.url') }}/api/regencies?provinsi=Jawa Timur&keyword=Malang"</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "data": [
    { "id": 12, "name": "Kabupaten Malang", "type": "Kabupaten", "provinsi": "Jawa Timur" },
    { "id": 13, "name": "Kota Malang", "type": "Kota", "provinsi": "Jawa Timur" }
  ]
}</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-error badge-sm text-white">422 Unprocessable Content</span>
                            &mdash; jika <code class="text-xs">provinsi</code> tidak dikirim
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "message": "The provinsi field is required.",
  "errors": {
    "provinsi": ["The provinsi field is required."]
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- /api/opds --}}
                <div id="opds" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/opds</h2>
                        </div>
                        <p class="text-sm text-base-content/70">List OPD (Organisasi Perangkat Daerah).
                            <span class="font-semibold">provinsi</span> wajib diisi,
                            <span class="font-semibold">kabupaten</span> dan
                            <span class="font-semibold">keyword</span> opsional untuk mempersempit hasil.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Query
                            Parameters</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Wajib</th>
                                        <th>Tipe</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">provinsi</td>
                                        <td><span class="badge badge-error badge-sm text-white">Ya</span></td>
                                        <td>string</td>
                                        <td>Nama provinsi, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">kabupaten</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Filter nama kabupaten/kota, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">keyword</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Filter nama OPD, partial match.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh
                            Request</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl "{{ config('app.url') }}/api/opds?provinsi=Jawa Timur&kabupaten=Malang&keyword=Pendidikan"</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "data": [
    { "id": 45, "name": "Dinas Pendidikan", "regency": "Kabupaten Malang", "provinsi": "Jawa Timur" }
  ]
}</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-error badge-sm text-white">422 Unprocessable Content</span>
                            &mdash; jika <code class="text-xs">provinsi</code> tidak dikirim
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "message": "The provinsi field is required.",
  "errors": {
    "provinsi": ["The provinsi field is required."]
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- /api/opds/search --}}
                <div id="opds-search" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/opds/search</h2>
                        </div>
                        <p class="text-sm text-base-content/70">
                            Pencarian OPD berdasarkan apa saja. Parameter
                            <span class="font-semibold">q</span> dicocokkan ke semua kolom sekaligus: nama instansi,
                            jenis, kabupaten/kota, provinsi, region, serta nama dan kode bidang urusan. Tidak ada
                            parameter yang wajib; tanpa parameter, endpoint mengembalikan semua OPD per halaman.
                        </p>
                        <p class="text-sm text-base-content/70">
                            Kata kunci dipisah spasi dan <span class="font-semibold">semuanya harus cocok</span>,
                            tapi boleh di kolom yang berbeda. Contoh:
                            <code class="text-xs">q=dinas malang</code> menemukan "Dinas Pendidikan" di Kabupaten
                            Malang. Pencocokan partial dan tidak membedakan huruf besar/kecil.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Query
                            Parameters</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Wajib</th>
                                        <th>Tipe</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">q</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Pencarian bebas ke semua kolom (lihat di atas).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">jenis</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Jenis instansi, harus persis sama: <code class="text-xs">OPD</code>,
                                            <code class="text-xs">Kementrian</code>, atau
                                            <code class="text-xs">Unit Kesehatan</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">provinsi</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Nama provinsi, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">kabupaten</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Nama kabupaten/kota, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">region</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Nama region, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">urusan</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Nama bidang urusan (partial match) atau kode urusan (persis).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">sort</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td><code class="text-xs">name</code> (default) atau
                                            <code class="text-xs">jenis</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">direction</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td><code class="text-xs">asc</code> (default) atau
                                            <code class="text-xs">desc</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">per_page</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>integer</td>
                                        <td>Jumlah data per halaman, 1&ndash;100 (default 25).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">page</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>integer</td>
                                        <td>Nomor halaman (default 1).</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-base-content/60">
                            Semua parameter bisa digabung; hasilnya adalah irisan semua kondisi.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh
                            Request</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl "{{ config('app.url') }}/api/opds/search?q=dinas pendidikan malang"</code></pre>
                            <pre data-prefix="$"><code>curl "{{ config('app.url') }}/api/opds/search?jenis=Kementrian&per_page=50"</code></pre>
                            <pre data-prefix="$"><code>curl "{{ config('app.url') }}/api/opds/search?provinsi=Jawa Timur&urusan=pendidikan&sort=name&direction=desc"</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "data": [
    {
      "id": 45,
      "name": "Dinas Pendidikan",
      "jenis": "OPD",
      "regency": "Kabupaten Malang",
      "provinsi": "Jawa Timur",
      "region": "Wilayah 3",
      "urusans": ["Pendidikan"]
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 25, "total": 1 }
}</code></pre>
                        </div>
                        <p class="text-xs text-base-content/60">
                            <code class="text-xs">region</code> bernilai <code class="text-xs">null</code> jika
                            kabupaten/kota belum dipetakan ke region. <code class="text-xs">urusans</code> adalah
                            array kosong jika OPD belum punya bidang urusan.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-error badge-sm text-white">422 Unprocessable Content</span>
                            &mdash; jika parameter tidak valid, misalnya <code class="text-xs">per_page=500</code>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "message": "The per page field must not be greater than 100.",
  "errors": {
    "per_page": ["The per page field must not be greater than 100."]
  }
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- Catatan --}}
                <div id="catatan" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Catatan Implementasi</h2>
                        <ul class="list-disc list-inside text-sm text-base-content/70 space-y-1.5">
                            <li>Response <code class="text-xs">/api/provinces</code>,
                                <code class="text-xs">/api/regencies</code>, dan
                                <code class="text-xs">/api/opds</code> tidak dipaginasi &mdash; cocok untuk populate
                                dropdown langsung, tapi hindari memanggil <code class="text-xs">/api/opds</code> tanpa
                                filter yang cukup spesifik jika jumlah OPD sangat besar. Untuk pencarian umum gunakan
                                <code class="text-xs">/api/opds/search</code> yang dipaginasi.</li>
                            <li>Pencarian <code class="text-xs">keyword</code> pada
                                <code class="text-xs">/api/provinces</code> case-insensitive secara eksplisit
                                (menggunakan <code class="text-xs">LOWER()</code> di query). Pencarian pada
                                <code class="text-xs">/api/regencies</code> dan <code class="text-xs">/api/opds</code>
                                menggunakan <code class="text-xs">LIKE</code> biasa &mdash; case-insensitive selama
                                collation database default.
                            </li>
                            <li>Relasi data: <code class="text-xs">Opd belongsTo Regency</code>,
                                <code class="text-xs">Regency belongsTo Province</code>.
                            </li>
                            <li>Source code: <code class="text-xs">app/Http/Controllers/Api/</code>, resource:
                                <code class="text-xs">app/Http/Resources/</code>, route:
                                <code class="text-xs">routes/api.php</code>.
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- AI Data API: overview --}}
                <div id="ai-data" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold">AI Data API</h2>
                            <span class="badge badge-warning badge-sm">Internal</span>
                        </div>
                        <p class="text-sm text-base-content/70">
                            API untuk layanan AI (<code class="text-xs">nakama-ai</code>) yang menjawab pertanyaan user
                            di AI Center tentang data Nakama. Layanan AI mengklasifikasi pertanyaan ke skill/departemen,
                            lalu memanggil API ini sebagai <strong>function call / tool</strong>. Bukan untuk aplikasi
                            lain dan bukan untuk browser.
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="1"><code>User bertanya di AI Center</code></pre>
                            <pre data-prefix="2"><code>Laravel → layanan AI: pesan + user_context (departemen, role, tools, assertion)</code></pre>
                            <pre data-prefix="3"><code>Layanan AI: klasifikasi skill → pilih dataset + filter</code></pre>
                            <pre data-prefix="4"><code>Layanan AI → POST /api/ai/query (Bearer token + X-AI-User-Assertion)</code></pre>
                            <pre data-prefix="5"><code>Laravel: cek token, assertion, user aktif, izin dataset, scope departemen → data</code></pre>
                            <pre data-prefix="6"><code>Gemini merangkum hasilnya → jawaban ke user</code></pre>
                        </div>
                        <ul class="list-disc list-inside text-sm text-base-content/70 space-y-1.5">
                            <li>Data hanya bisa diminta dari <strong>katalog dataset</strong>
                                (<code class="text-xs">config/ai_datasets.php</code>). Tidak ada SQL dari AI, tidak ada
                                endpoint tulis.</li>
                            <li>Yang boleh keluar: nama, NIS, departemen, posisi, KPI/capaian. Yang <strong>tidak
                                    pernah</strong> keluar: NIK (KTP), NPWP, status kepegawaian, status pernikahan,
                                tanggal lahir, gender, telepon, email, alamat, gaji, rekening.</li>
                            <li>Izin selalu dicek dari database Nakama untuk user di assertion. Departemen/role yang
                                dikirim di <code class="text-xs">user_context</code> hanya untuk routing, tidak pernah
                                dipakai sebagai dasar izin.</li>
                            <li>Setiap permintaan, termasuk yang ditolak, dicatat di tabel
                                <code class="text-xs">ai_data_query_logs</code>.</li>
                        </ul>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>Base URL: {{ config('app.url') }}/api/ai</code></pre>
                        </div>
                    </div>
                </div>

                {{-- AI Data API: auth --}}
                <div id="ai-auth" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Autentikasi</h2>
                        <p class="text-sm text-base-content/70">Setiap request wajib membawa <strong>dua</strong>
                            header. Salah satu kurang atau salah, request ditolak.</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Header</th>
                                        <th>Isi</th>
                                        <th>Membuktikan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">Authorization</td>
                                        <td><code class="text-xs">Bearer &lt;AI_DATA_API_TOKEN&gt;</code></td>
                                        <td>Pemanggilnya layanan AI yang dipercaya. Satu token, disimpan di
                                            <code class="text-xs">.env</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">X-AI-User-Assertion</td>
                                        <td><code class="text-xs">user_context.assertion</code> apa adanya</td>
                                        <td>Untuk user siapa data diminta.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Assertion
                        </p>
                        <p class="text-sm text-base-content/70">
                            Diterbitkan Laravel untuk user yang sedang login, dikirim ke layanan AI di
                            <code class="text-xs">user_context</code>, berlaku 5 menit. Bentuknya
                            <code class="text-xs">&lt;isi&gt;.&lt;tanda tangan&gt;</code>: isi berupa base64url dari
                            <code class="text-xs">{"uid": 12, "cid": 345, "exp": 1791624600}</code> (user, percakapan
                            AI Center, kedaluwarsa), tanda tangan berupa HMAC-SHA256 dengan secret yang hanya ada di
                            server Nakama. Isinya bisa dibaca tetapi tidak bisa diubah.
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>"user_context": {
  "department": "Departemen Finance",
  "roles": ["Investor Admin"],
  "available_tools": ["fo_achievement", "ai_usage"],
  "assertion": "eyJ1aWQiOjEyLCJjaWQiOjM0NSwiZXhwIjoxNzkxNjI0NjAwfQ.k3Jx9Qe1v7wq..."
}</code></pre>
                        </div>
                        <div class="alert alert-warning text-xs mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            <span>Kode layanan AI harus menyisipkan assertion ke header sendiri. Assertion
                                <strong>tidak boleh</strong> dimasukkan ke prompt, argumen tool, atau apa pun yang
                                dilihat model, supaya tidak bisa dicuri lewat prompt injection.</span>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Kode Status
                        </p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Penyebab</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge badge-error badge-sm text-white">401</span></td>
                                        <td>Token kosong/salah, assertion tidak ada/tidak valid/kedaluwarsa, atau API belum
                                            dikonfigurasi (token/secret kosong).</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-error badge-sm text-white">403</span></td>
                                        <td>User tidak ada lagi, tidak aktif, bukan pemilik percakapan, atau tidak punya
                                            izin ke dataset.</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-error badge-sm text-white">422</span></td>
                                        <td>Permintaan di luar katalog (dataset, dimensi, metrik, filter, parameter tidak
                                            dikenal) atau periode tidak valid.</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-warning badge-sm">429</span></td>
                                        <td>Batas query per user tercapai (default 30 per jam untuk
                                            <code class="text-xs">/query</code>).</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- AI Data API: datasets --}}
                <div id="ai-datasets" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/ai/datasets</h2>
                        </div>
                        <p class="text-sm text-base-content/70">
                            Daftar dataset (tool) yang boleh dipakai user di assertion, lengkap dengan
                            <code class="text-xs">skill</code> dan <code class="text-xs">department</code> untuk routing.
                            Dataset yang tidak boleh dipakai user tidak muncul sama sekali. Ini "data master" untuk
                            layanan AI: tidak perlu mengetahui nama tabel database.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Dataset
                            tersedia</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Dataset</th>
                                        <th>Skill</th>
                                        <th>Berbasis waktu</th>
                                        <th>Isi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">fo_achievement</td>
                                        <td>funding</td>
                                        <td>Ya</td>
                                        <td>Capaian Funding Officer per orang: leads, QL, pitching, won, closing, nilai
                                            closing, target, capaian %.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">ai_usage</td>
                                        <td>ai_center</td>
                                        <td>Ya</td>
                                        <td>Pertanyaan ke AI Center dan pengguna aktif, per bulan/departemen.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">employees</td>
                                        <td>master_data</td>
                                        <td>Tidak</td>
                                        <td>Karyawan aktif: nama, NIS, departemen, posisi, region. Dibatasi ke departemen
                                            user kecuali punya akses semua departemen.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">departments</td>
                                        <td>master_data</td>
                                        <td>Tidak</td>
                                        <td>Departemen, kode, jumlah karyawan aktif.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">roles</td>
                                        <td>master_data</td>
                                        <td>Tidak</td>
                                        <td>Role aplikasi dan jumlah pengguna (bisa per departemen), tanpa nama.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">regions</td>
                                        <td>master_data</td>
                                        <td>Tidak</td>
                                        <td>Region dengan jumlah provinsi, kabupaten/kota, dan OPD.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">opds</td>
                                        <td>master_data</td>
                                        <td>Tidak</td>
                                        <td>OPD/instansi dengan jenis, kabupaten/kota, provinsi, region, bidang urusan.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">urusans</td>
                                        <td>master_data</td>
                                        <td>Tidak</td>
                                        <td>Bidang urusan, kode, jumlah OPD.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">products</td>
                                        <td>master_data</td>
                                        <td>Tidak</td>
                                        <td>Produk: nama, kode, kategori, service center, divisi, pelaksana, status.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-base-content/60">Dataset dengan <code class="text-xs">"time_based":
                                false</code> adalah data master: jangan kirim parameter periode atau
                            <code class="text-xs">compare_previous</code>; response-nya berisi
                            <code class="text-xs">"period": null</code> dan, untuk data referensi bersama,
                            <code class="text-xs">"scope": "global"</code>.</p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "data": [
    {
      "skill": "funding",
      "department": "Departemen Finance",
      "dataset": "fo_achievement",
      "label": "Capaian Funding Officer (modul Funding)",
      "description": "Realisasi funnel per Funding Officer dan capaian nilai closing ...",
      "dimensions": { "employee": "Nama Funding Officer", "department": "Nama departemen", "month": "Bulan (YYYY-MM)" },
      "metrics": {
        "leads": "Jumlah leads baru",
        "ql": "Jumlah qualified lead",
        "pitching": "Jumlah funnel yang di-pitching",
        "won": "Jumlah funnel won",
        "closing": "Jumlah closing (deal)",
        "nilai_closing": "Total nominal investasi closing (Rp)",
        "target_nilai_closing": "Target nilai closing untuk periode (Rp)",
        "achievement_pct": "Capaian nilai closing terhadap target (%)"
      },
      "filters": ["department"]
    }
  ]
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- AI Data API: query --}}
                <div id="ai-query" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-info font-mono font-bold">POST</span>
                            <h2 class="text-lg font-bold font-mono">/api/ai/query</h2>
                        </div>
                        <p class="text-sm text-base-content/70">
                            Satu permintaan data, body JSON. Semua parameter selain
                            <code class="text-xs">dataset</code> opsional. Nilai harus berasal dari
                            <code class="text-xs">/api/ai/datasets</code>; parameter lain ditolak (422), termasuk
                            mencoba memilih user lain lewat body.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Body</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Wajib</th>
                                        <th>Tipe</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">dataset</td>
                                        <td><span class="badge badge-error badge-sm text-white">Ya</span></td>
                                        <td>string</td>
                                        <td>Nama dataset, mis. <code class="text-xs">fo_achievement</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">group_by</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string[]</td>
                                        <td>Dimensi pengelompokan. Tanpa ini hasilnya satu baris total.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">metrics</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string[]</td>
                                        <td>Metrik yang dikembalikan (default semua).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">filters</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>object</td>
                                        <td>Mis. <code class="text-xs">{"department": "Departemen Finance"}</code>. Hanya
                                            bisa mempersempit scope user, tidak memperluas.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">from, to</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>YYYY-MM</td>
                                        <td>Periode per bulan. Cukup <code class="text-xs">from</code> untuk satu bulan.
                                            Maks. 24 bulan.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">from_date, to_date</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>YYYY-MM-DD</td>
                                        <td>Periode per tanggal, mis. 30 hari terakhir. Tanpa
                                            <code class="text-xs">to_date</code> = sampai hari ini. Tidak bisa digabung
                                            dengan <code class="text-xs">from</code>/<code class="text-xs">to</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">all_time</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>boolean</td>
                                        <td>Dari data pertama sampai bulan ini. Tidak bisa digabung dengan periode lain
                                            atau <code class="text-xs">compare_previous</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">compare_previous</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>boolean</td>
                                        <td>Bandingkan dengan periode sebelumnya yang sama panjang. Menambah kolom
                                            <code class="text-xs">_previous</code>, <code class="text-xs">_delta</code>,
                                            <code class="text-xs">_delta_pct</code> per metrik (dihitung server). Tidak
                                            bisa digabung dengan <code class="text-xs">group_by: ["month"]</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">sort, direction</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>string</td>
                                        <td>Urutkan berdasarkan metrik (termasuk kolom perbandingan);
                                            <code class="text-xs">desc</code> (default) atau
                                            <code class="text-xs">asc</code>. Diurutkan sebelum
                                            <code class="text-xs">limit</code>, jadi cocok untuk ranking.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">limit</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>integer</td>
                                        <td>1&ndash;200 (default 200).</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh
                            Request</p>
                        <p class="text-sm text-base-content/70">"Siapa FO dengan capaian tertinggi sepanjang waktu?"
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{ "dataset": "fo_achievement", "group_by": ["employee"], "all_time": true,
  "metrics": ["nilai_closing", "closing", "achievement_pct"], "sort": "nilai_closing", "limit": 10 }</code></pre>
                        </div>
                        <p class="text-sm text-base-content/70">"Bagaimana closing 30 hari terakhir dibanding 30 hari
                            sebelumnya?"</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{ "dataset": "fo_achievement", "group_by": ["employee"], "metrics": ["nilai_closing"],
  "from_date": "2026-09-11", "to_date": "2026-10-10", "compare_previous": true, "sort": "nilai_closing_delta" }</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "dataset": "fo_achievement",
  "label": "Capaian Funding Officer (modul Funding)",
  "period": { "from": "2026-09-11", "to": "2026-10-10" },
  "compared_to": { "from": "2026-08-12", "to": "2026-09-10" },
  "scope": "all_departments",
  "group_by": ["employee"],
  "metrics": ["nilai_closing"],
  "rows": [
    { "employee": "Budi", "nilai_closing": 50000000.0, "nilai_closing_previous": 99000000.0,
      "nilai_closing_delta": -49000000.0, "nilai_closing_delta_pct": -49.5 }
  ],
  "suppressed_rows": 0,
  "truncated": false,
  "notes": []
}</code></pre>
                        </div>
                        <ul class="list-disc list-inside text-xs text-base-content/60 space-y-1">
                            <li><code class="text-xs">scope</code>: <code class="text-xs">all_departments</code> atau
                                <code class="text-xs">own_department</code> (user hanya boleh melihat departemennya).
                            </li>
                            <li><code class="text-xs">suppressed_rows</code>: baris yang disembunyikan karena dibentuk
                                dari kurang dari 5 orang (dataset agregat), demi privasi.</li>
                            <li>Metrik berakhiran <code class="text-xs">_pct</code> bernilai
                                <code class="text-xs">null</code> bila tidak bisa dihitung (mis. target belum diisi).
                            </li>
                        </ul>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-error badge-sm text-white">422 Unprocessable Content</span>
                            &mdash; mis. meminta kolom yang tidak ada di katalog
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "message": "The selected group_by.0 is invalid.",
  "errors": { "group_by.0": ["The selected group_by.0 is invalid."] }
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- AI Data API: testing --}}
                <div id="ai-testing" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Cara Testing</h2>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">1. Test otomatis
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>php artisan test --compact tests/Feature/AiData tests/Feature/AiCenter tests/Feature/Api</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">2. Tanpa
                            token, langsung sebagai user</p>
                        <p class="text-sm text-base-content/70">Menjalankan permintaan persis seperti API, dengan izin
                            dan scope user tersebut, dan tercatat di audit log.</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>php artisan ai-data:query email@smartid.co.id --catalog</code></pre>
                            <pre data-prefix="$"><code>php artisan ai-data:query email@smartid.co.id '{"dataset":"fo_achievement","group_by":["employee"],"all_time":true,"sort":"nilai_closing"}'</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">3. Lewat
                            HTTP (curl) &mdash; persiapan</p>
                        <ol class="list-decimal list-inside text-sm text-base-content/70 space-y-1">
                            <li>Isi <code class="text-xs">AI_DATA_API_TOKEN</code> dan
                                <code class="text-xs">AI_DATA_ASSERTION_SECRET</code> di <code class="text-xs">.env</code>
                                dengan string acak panjang, lalu jalankan
                                <code class="text-xs">php artisan config:clear</code>.</li>
                            <li>Pastikan user yang dipakai pernah chat di <code class="text-xs">/ai-center</code>
                                (assertion terikat ke salah satu percakapannya).</li>
                            <li>Assertion berlaku <strong>5 menit</strong>. Kalau mulai mendapat
                                <code class="text-xs">HTTP 401</code>, buat ulang.</li>
                        </ol>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">PowerShell +
                            curl.exe</p>
                        <p class="text-sm text-base-content/70">Body JSON ditulis ke file sementara lalu dikirim dengan
                            <code class="text-xs">--data-binary "@file"</code>, supaya tanda kutip tidak dirusak
                            PowerShell (error <code class="text-xs">bad range specification</code>). Jalankan dari folder
                            project:</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=">"><code>$url   = "{{ config('app.url') }}/api/ai"</code></pre>
                            <pre data-prefix=">"><code>$token = (Select-String -Path .env -Pattern '^AI_DATA_API_TOKEN=(.+)$').Matches[0].Groups[1].Value</code></pre>
                            <pre data-prefix=">"><code>function New-Assertion {
  (php artisan tinker --execute '$u = App\Models\User::firstWhere("email", "email@smartid.co.id"); echo app(App\Services\AiData\UserAssertion::class)->issue($u, $u->aiConversations()->latest("id")->value("id"));' | Select-Object -Last 1).Trim()
}</code></pre>
                            <pre data-prefix=">"><code>function Ask([string]$json) {
  $file = New-TemporaryFile
  Set-Content -Path $file -Value $json -NoNewline -Encoding utf8NoBOM
  curl.exe -s -X POST "$url/query" `
    -H "Authorization: Bearer $token" `
    -H "X-AI-User-Assertion: $assertion" `
    -H "Content-Type: application/json" `
    --data-binary "@$file" `
    -w "`nHTTP %{http_code}`n"
  Remove-Item $file
}</code></pre>
                            <pre data-prefix=">"><code>$assertion = New-Assertion</code></pre>
                            <pre data-prefix=">"><code>curl.exe -s "$url/datasets" -H "Authorization: Bearer $token" -H "X-AI-User-Assertion: $assertion" -w "`nHTTP %{http_code}`n"</code></pre>
                            <pre data-prefix=">"><code>Ask '{"dataset":"employees","group_by":["department"]}'</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Git Bash /
                            Linux / macOS</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>URL={{ config('app.url') }}/api/ai</code></pre>
                            <pre data-prefix="$"><code>TOKEN=$(grep '^AI_DATA_API_TOKEN=' .env | cut -d= -f2-)</code></pre>
                            <pre data-prefix="$"><code>ASSERTION=$(php artisan tinker --execute '$u = App\Models\User::firstWhere("email", "email@smartid.co.id"); echo app(App\Services\AiData\UserAssertion::class)->issue($u, $u->aiConversations()->latest("id")->value("id"));' | tail -1)</code></pre>
                            <pre data-prefix="$"><code>curl -s "$URL/datasets" -H "Authorization: Bearer $TOKEN" -H "X-AI-User-Assertion: $ASSERTION" -w "\nHTTP %{http_code}\n"</code></pre>
                            <pre data-prefix="$"><code>curl -s -X POST "$URL/query" -H "Authorization: Bearer $TOKEN" -H "X-AI-User-Assertion: $ASSERTION" -H "Content-Type: application/json" -w "\nHTTP %{http_code}\n" -d '{"dataset":"employees","group_by":["department"]}'</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh body
                            (dipakai dengan <code class="text-xs normal-case">Ask '...'</code> atau
                            <code class="text-xs normal-case">-d '...'</code>) &rarr; 200</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Pertanyaan</th>
                                        <th>Body</th>
                                    </tr>
                                </thead>
                                <tbody class="font-mono text-xs">
                                    <tr>
                                        <td class="font-sans">Daftar karyawan tim Digital Transformation</td>
                                        <td>{"dataset":"employees","group_by":["employee","nis","position_struktural"],"filters":{"department":"digital"}}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">Jumlah karyawan per departemen</td>
                                        <td>{"dataset":"employees","group_by":["department"]}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">Daftar departemen</td>
                                        <td>{"dataset":"departments","group_by":["department","code"]}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">Jumlah pengguna per role</td>
                                        <td>{"dataset":"roles","group_by":["role"]}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">Daftar region</td>
                                        <td>{"dataset":"regions","group_by":["region"]}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">10 provinsi dengan OPD urusan pendidikan terbanyak</td>
                                        <td>{"dataset":"opds","group_by":["province"],"filters":{"urusan":"pendidikan"},"sort":"opds","limit":10}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">Jumlah OPD per jenis</td>
                                        <td>{"dataset":"opds","group_by":["jenis"]}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">10 urusan dengan OPD terbanyak</td>
                                        <td>{"dataset":"urusans","group_by":["urusan","code"],"sort":"opds","limit":10}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">Produk per kategori dan status</td>
                                        <td>{"dataset":"products","group_by":["category","status"]}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">5 FO dengan nilai closing tertinggi sepanjang waktu</td>
                                        <td>{"dataset":"fo_achievement","group_by":["employee"],"all_time":true,"metrics":["nilai_closing","closing"],"sort":"nilai_closing","limit":5}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-sans">Closing 30 hari terakhir vs 30 hari sebelumnya</td>
                                        <td>{"dataset":"fo_achievement","group_by":["employee"],"from_date":"2026-09-10","to_date":"2026-10-10","compare_previous":true,"sort":"nilai_closing"}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Yang harus
                            ditolak (PowerShell)</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="422"><code>Ask '{"dataset":"employees","group_by":["nik"]}'</code></pre>
                            <pre data-prefix="422"><code>Ask '{"dataset":"departments","from":"2026-09"}'</code></pre>
                            <pre data-prefix="401"><code>curl.exe -s "$url/datasets" -H "Authorization: Bearer $token" -w "`nHTTP %{http_code}`n"</code></pre>
                            <pre data-prefix="401"><code>curl.exe -s "$url/datasets" -H "Authorization: Bearer salah" -H "X-AI-User-Assertion: $assertion" -w "`nHTTP %{http_code}`n"</code></pre>
                            <pre data-prefix="401"><code>curl.exe -s "$url/datasets" -H "Authorization: Bearer $token" -H "X-AI-User-Assertion: x$assertion" -w "`nHTTP %{http_code}`n"</code></pre>
                        </div>
                        <p class="text-sm text-base-content/70">Juga ditolak: user tanpa izin dataset (403), user
                            suspended/dihapus (403), batas query per jam tercapai (429).</p>

                        <div class="alert alert-info text-xs mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Assertion kosong berarti <code>AI_DATA_ASSERTION_SECRET</code> belum diisi (jalankan
                                <code>php artisan config:clear</code> setelah mengubah <code>.env</code>) atau user
                                belum pernah chat di AI Center. Ganti <code>email@smartid.co.id</code> dengan akun yang
                                punya izin dataset (Super Admin otomatis punya semua). Source code:
                                <code>app/Services/AiData/</code>,
                                <code>app/Http/Controllers/Api/AiDataController.php</code>, katalog
                                <code>config/ai_datasets.php</code>.</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layouts::app.sidebar>
