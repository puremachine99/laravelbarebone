<x-layouts::app.sidebar title="API Documentation">
    <div x-data="{ activeSection: 'overview' }" class="space-y-4">

        {{-- Page Header --}}
        <div>
            <h1 class="text-xl font-bold text-base-content">OPD Lookup API Documentation</h1>
            <p class="text-sm text-base-content/60 mt-0.5">
                Dokumentasi teknis untuk developer yang ingin mengintegrasikan dropdown Provinsi &rarr; Kabupaten/Kota
                &rarr; OPD dari aplikasi lain.
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
                        <a href="#catatan" class="btn btn-sm btn-ghost justify-start">Catatan Implementasi</a>
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
                            Tiga endpoint publik, read-only, untuk mengisi dropdown cascading
                            <span class="font-semibold">Provinsi &rarr; Kabupaten/Kota &rarr; OPD</span>.
                            Response tidak dipaginasi &mdash; berisi seluruh hasil yang cocok dengan filter yang
                            dikirim.
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

                {{-- Catatan --}}
                <div id="catatan" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Catatan Implementasi</h2>
                        <ul class="list-disc list-inside text-sm text-base-content/70 space-y-1.5">
                            <li>Response tidak dipaginasi &mdash; cocok untuk populate dropdown langsung, tapi hindari
                                memanggil <code class="text-xs">/api/opds</code> tanpa filter yang cukup spesifik jika
                                jumlah OPD sangat besar.</li>
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

            </div>
        </div>
    </div>
</x-layouts::app.sidebar>
