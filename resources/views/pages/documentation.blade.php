@php
    $groups = [
        [
            'label' => 'OPD Lookup API',
            'badge' => null,
            'tabs' => [
                'overview' => 'Overview',
                'alur' => 'Alur Cascading Dropdown',
                'cara-pakai' => 'Cara Pakai (Contoh Kode)',
                'playground' => 'Coba API (Playground)',
                'regions' => 'GET /api/regions',
                'provinces' => 'GET /api/provinces',
                'regencies' => 'GET /api/regencies',
                'opds' => 'GET /api/opds',
                'opds-search' => 'GET /api/opds/search',
                'catatan' => 'Catatan Implementasi',
            ],
        ],
        [
            'label' => 'AI Data API',
            'badge' => 'Internal',
            'tabs' => [
                'ai-data' => 'Overview',
                'ai-auth' => 'Autentikasi',
                'ai-datasets' => 'GET /api/ai/datasets',
                'ai-query' => 'POST /api/ai/query',
                'ai-testing' => 'Cara Testing',
            ],
        ],
        [
            'label' => 'Employee API',
            'badge' => 'Internal',
            'tabs' => [
                'employees' => 'GET /api/employees',
                'employee-detail' => 'GET /api/employees/{id}',
            ],
        ],
        [
            'label' => 'SSO (Login with SmartID)',
            'badge' => null,
            'tabs' => [
                'sso-overview' => 'Overview & Alur',
                'sso-laravel' => 'Pasang di Laravel / Filament',
                'sso-api' => 'GET /api/me',
                'sso-troubleshooting' => 'Troubleshooting',
            ],
        ],
    ];

    $tabIds = collect($groups)->flatMap(fn (array $group) => array_keys($group['tabs']))->values()->all();

    $titles = collect($groups)
        ->flatMap(fn (array $group) => collect($group['tabs'])->map(fn (string $tab) => ['group' => $group['label'], 'tab' => $tab]))
        ->all();
@endphp

<x-layouts::app.sidebar title="Dokumentasi Teknis">
    {{-- Vertical tabs: one section at a time. The URL hash (#ai-auth, ...) opens a tab directly and stays shareable. --}}
    <div x-data="{
        tabs: @js($tabIds),
        titles: @js($titles),
        active: @js($tabIds[0]),
        get previous() {
            return this.tabs[this.tabs.indexOf(this.active) - 1] ?? null;
        },
        get next() {
            return this.tabs[this.tabs.indexOf(this.active) + 1] ?? null;
        },
        query: '',
        index: [],
        init() {
            this.fromHash();
            window.addEventListener('hashchange', () => this.fromHash());

            // Search index: every tab's own text (hidden tabs still have their textContent).
            this.index = this.tabs.map((id) => ({
                id,
                text: [this.titles[id].group, this.titles[id].tab, document.getElementById(id)?.textContent ?? '']
                    .join(' ')
                    .replace(/\s+/g, ' ')
                    .trim(),
            }));

            // Press / anywhere (outside a field) to jump to the search box.
            window.addEventListener('keydown', (event) => {
                if (event.key === '/' && ! ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                    event.preventDefault();
                    this.$refs.search.focus();
                }
            });
        },
        get searching() {
            return this.query.trim().length >= 2;
        },
        get results() {
            const needle = this.query.trim().toLowerCase();

            if (! this.searching) {
                return [];
            }

            return this.index
                .filter((entry) => entry.text.toLowerCase().includes(needle))
                .map((entry) => {
                    const at = entry.text.toLowerCase().indexOf(needle);
                    const start = Math.max(0, at - 40);

                    return {
                        id: entry.id,
                        snippet: (start > 0 ? '…' : '') + entry.text.slice(start, at + needle.length + 70) + '…',
                    };
                });
        },
        matches(id) {
            return ! this.searching || this.results.some((result) => result.id === id);
        },
        pick(id) {
            this.open(id);
            this.query = '';
        },
        fromHash() {
            const id = window.location.hash.slice(1);

            if (this.tabs.includes(id)) {
                this.active = id;
            }
        },
        open(id) {
            this.active = id;
            history.replaceState(null, '', '#' + id);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    }" class="space-y-4">

        {{-- Page Header: title left, search top right --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm">
        <div class="card-body p-5 flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-base-content">Dokumentasi Teknis Nakama</h1>
                <p class="text-sm text-base-content/60 mt-0.5">
                    Referensi teknis aplikasi Nakama untuk developer: daftar API, cara autentikasi, contoh request
                    dan response, serta cara testing. Pilih bagian di menu kiri atau cari topik di kotak pencarian.
                </p>
            </div>

            <div class="relative w-full sm:w-80 shrink-0" x-on:click.outside="query = ''"
                x-on:keydown.escape="query = ''; $refs.search.blur()">
                <label class="input input-sm input-bordered flex items-center gap-2 w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0 text-base-content/50" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                    </svg>
                    <input type="search" x-ref="search" x-model="query" class="grow"
                        placeholder="Cari di dokumentasi…" aria-label="Cari di dokumentasi"
                        x-on:keydown.enter.prevent="results.length && pick(results[0].id)" />
                    <kbd class="kbd kbd-xs hidden sm:inline-flex" x-show="! query">/</kbd>
                </label>

                {{-- Results --}}
                <div x-show="searching" style="display: none"
                    class="absolute right-0 z-30 mt-1 w-full sm:w-md max-h-96 overflow-y-auto rounded-xl border border-base-300 bg-base-100 shadow-lg">
                    <template x-for="result in results" :key="result.id">
                        <button type="button" x-on:click="pick(result.id)"
                            class="block w-full text-left px-3 py-2 border-b border-base-200 last:border-b-0 hover:bg-base-200 focus:bg-base-200 focus:outline-none">
                            <p class="text-xs text-base-content/50">
                                <span x-text="titles[result.id].group"></span> &rsaquo;
                                <span class="font-semibold text-base-content" x-text="titles[result.id].tab"></span>
                            </p>
                            <p class="text-xs text-base-content/70 truncate" x-text="result.snippet"></p>
                        </button>
                    </template>
                    <p x-show="results.length === 0" class="px-3 py-3 text-sm text-base-content/50">
                        Tidak ada hasil untuk &ldquo;<span x-text="query.trim()"></span>&rdquo;.
                    </p>
                </div>
            </div>
        </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-4 items-start">

            {{-- LEFT: vertical tabs. Only the active tab's card is shown on the right. --}}
            <nav class="lg:w-64 w-full shrink-0 lg:sticky lg:top-4" aria-label="Bagian dokumentasi">
                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-3 gap-3">
                        @foreach ($groups as $group)
                            <div class="{{ ! $loop->first ? 'border-t border-base-300 pt-3' : '' }}">
                                <p class="flex items-center gap-2 text-xs font-bold text-base-content uppercase tracking-wider rounded-lg bg-base-200 px-3 py-1.5 mb-1.5">
                                    {{ $group['label'] }}
                                    @if ($group['badge'])
                                        <span class="badge badge-warning badge-xs normal-case tracking-normal">{{ $group['badge'] }}</span>
                                    @endif
                                </p>
                                <div class="flex flex-col gap-0.5" role="tablist" aria-orientation="vertical">
                                    @foreach ($group['tabs'] as $id => $label)
                                        <a href="#{{ $id }}" role="tab" aria-controls="{{ $id }}"
                                            x-on:click.prevent="open('{{ $id }}')"
                                            :class="! matches('{{ $id }}') && 'opacity-40'"
                                            aria-selected="{{ $id === $tabIds[0] ? 'true' : 'false' }}"
                                            :aria-selected="(active === '{{ $id }}').toString()"
                                            class="block rounded-r-lg border-l-2 border-transparent px-3 py-1.5 ml-2 text-sm text-base-content transition-colors hover:bg-base-200 aria-selected:border-primary aria-selected:bg-primary aria-selected:font-semibold aria-selected:text-primary-content">{{ $label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </nav>

            {{-- RIGHT: Content --}}
            <div class="flex-1 min-w-0 space-y-4 w-full">

                {{-- Where am I: group › tab --}}
                <p class="text-xs text-base-content/50">
                    <span x-text="titles[active].group">{{ $titles[$tabIds[0]]['group'] }}</span>
                    <span aria-hidden="true">&rsaquo;</span>
                    <span class="font-semibold text-base-content/80" x-text="titles[active].tab">{{ $titles[$tabIds[0]]['tab'] }}</span>
                </p>

                {{-- Overview --}}
                <div id="overview" role="tabpanel" x-show="active === 'overview'" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                <div id="alur" role="tabpanel" x-show="active === 'alur'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                <div id="cara-pakai" role="tabpanel" x-show="active === 'cara-pakai'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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

                {{-- Playground: editable GET commands run from the browser against this app's own /api --}}
                <div id="playground" role="tabpanel" x-show="active === 'playground'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3" x-data="{
                        rows: [],
                        selected: null,
                        shell: 'powershell',
                        runningAll: false,
                        origin: window.location.origin,
                        init() {
                            this.rows = @js(\App\Support\ApiPlaygroundPresets::all()).map((row, index) => ({ ...row, id: index, original: row.path, result: null, running: false }));
                        },
                        normalize(input) {
                            const raw = (input || '').trim();
                            const quoted = raw.match(/[\x22']([^\x22']*\/api\/[^\x22']*)[\x22']/);
                            let path = quoted ? quoted[1] : raw;
                            const at = path.indexOf('/api/');

                            if (at === -1) {
                                return null;
                            }

                            path = path.slice(at);

                            if (! quoted && /^curl/i.test(raw)) {
                                path = path.split(/\s/)[0];
                            }

                            return path;
                        },
                        urlFor(row) {
                            const path = this.normalize(row.path);

                            return path ? new URL(path, this.origin).href : null;
                        },
                        curlFor(row) {
                            const url = this.urlFor(row);
                            const dq = String.fromCharCode(34);

                            return url ? (this.shell === 'powershell' ? 'curl.exe' : 'curl') + ' -s -H ' + dq + 'Accept: application/json' + dq + ' ' + dq + url + dq : '';
                        },
                        expected(row) {
                            return row.path === row.original ? row.expect : null;
                        },
                        passed(row) {
                            return row.result?.status !== undefined && this.expected(row) !== null && row.result.status === this.expected(row);
                        },
                        failed(row) {
                            return !! row.result && this.expected(row) !== null && ! this.passed(row);
                        },
                        get summary() {
                            const checked = this.rows.filter((row) => row.result && this.expected(row) !== null);

                            return { checked: checked.length, passed: checked.filter((row) => this.passed(row)).length };
                        },
                        async run(row) {
                            this.selected = row.id;
                            const url = this.urlFor(row);

                            if (! url) {
                                row.result = { error: 'Command harus berisi path /api/... (contoh: /api/opds?provinsi=Jawa Timur).' };

                                return;
                            }

                            row.running = true;
                            const started = performance.now();

                            try {
                                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                                const text = await response.text();
                                let body = text;
                                let count = null;
                                let total = null;

                                try {
                                    const json = JSON.parse(text);
                                    body = JSON.stringify(json, null, 2);
                                    count = Array.isArray(json.data) ? json.data.length : null;
                                    total = json.meta?.total ?? null;
                                } catch (error) {}

                                row.result = {
                                    url,
                                    status: response.status,
                                    ms: Math.round(performance.now() - started),
                                    limit: response.headers.get('X-RateLimit-Limit'),
                                    remaining: response.headers.get('X-RateLimit-Remaining'),
                                    count,
                                    total,
                                    body: body.length > 100000 ? body.slice(0, 100000) + ' … (dipotong, response terlalu besar)' : body,
                                };
                            } catch (error) {
                                row.result = { error: 'Request gagal: ' + error.message };
                            }

                            row.running = false;
                        },
                        async runAll() {
                            this.runningAll = true;

                            for (const row of this.rows) {
                                await this.run(row);
                            }

                            this.selected = null;
                            this.runningAll = false;
                        },
                        add() {
                            const id = Math.max(-1, ...this.rows.map((row) => row.id)) + 1;
                            this.rows.push({ id, group: 'Command custom', label: 'Command baru', path: '/api/', original: null, expect: null, result: null, running: false, custom: true });
                            this.selected = id;
                            this.$nextTick(() => document.getElementById('playground-input-' + id)?.focus());
                        },
                        remove(row) {
                            this.rows = this.rows.filter((other) => other.id !== row.id);
                        },
                        reset(row) {
                            row.path = row.original;
                            row.result = null;
                        },
                        copy(text) {
                            navigator.clipboard?.writeText(text);
                        },
                    }">
                        <h2 class="text-lg font-bold">Coba API (Playground)</h2>
                        {{-- Cascading dropdown simulation: Region → Provinsi → Kabupaten/Kota → OPD, every step calls the real API --}}
                        @php
                            $cascadeExample = <<<'JS'
// Ganti dengan URL SmartID.
const API = 'https://smartdashboard.example.com/api';

const get = async (path) => {
    const response = await fetch(API + path, { headers: { Accept: 'application/json' } });

    return (await response.json()).data;
};

const fill = (select, items, label = (item) => item.name) => {
    select.innerHTML = '<option value="">-- pilih --</option>'
        + items.map((item) => `<option value="${item.id}">${label(item)}</option>`).join('');
};

// 1. Halaman dibuka: isi dropdown region.
fill(regionSelect, await get('/regions'));

// 2. Region dipilih: provinsi & kabupaten/kota di region itu.
regionSelect.addEventListener('change', async () => {
    fill(provinceSelect, await get(`/provinces?region_id=${regionSelect.value}`));
    fill(regencySelect, await get(`/regencies?region_id=${regionSelect.value}`), (r) => `${r.name} (${r.provinsi})`);
    fill(opdSelect, []);
});

// 3. Provinsi dipilih (opsional): saring kabupaten/kota ke provinsi itu.
provinceSelect.addEventListener('change', async () => {
    const province = provinceSelect.value ? `&province_id=${provinceSelect.value}` : '';

    fill(regencySelect, await get(`/regencies?region_id=${regionSelect.value}${province}`));
    fill(opdSelect, []);
});

// 4. Kabupaten/kota dipilih: daftar OPD-nya.
regencySelect.addEventListener('change', async () => {
    fill(opdSelect, await get(`/opds?regency_id=${regencySelect.value}`));
});
JS;
                        @endphp
                        <div class="rounded-box border border-primary/30 bg-primary/5 p-4 space-y-4" x-data="{
                            regions: [],
                            provinces: [],
                            regencies: [],
                            opds: [],
                            regionId: '',
                            provinceId: '',
                            regencyId: '',
                            opdSearch: '',
                            loading: { region: false, provinsi: false, kabupaten: false, opd: false },
                            calls: [],
                            openCall: null,
                            async call(step, path) {
                                const started = performance.now();
                                const entry = { id: Math.random().toString(36).slice(2), step, path, status: null, ms: 0, count: null, body: '' };
                                this.loading[step] = true;

                                try {
                                    const response = await fetch(new URL(path, window.location.origin), { headers: { Accept: 'application/json' } });
                                    const json = await response.json().catch(() => null);
                                    entry.status = response.status;
                                    entry.count = Array.isArray(json?.data) ? json.data.length : null;
                                    entry.body = JSON.stringify(json, null, 2);

                                    return response.ok ? (json?.data ?? []) : [];
                                } catch (error) {
                                    entry.status = 'ERR';
                                    entry.body = error.message;

                                    return [];
                                } finally {
                                    entry.ms = Math.round(performance.now() - started);
                                    this.calls = [entry, ...this.calls].slice(0, 10);
                                    this.loading[step] = false;
                                }
                            },
                            async init() {
                                this.regions = await this.call('region', '/api/regions');
                            },
                            async pickRegion() {
                                const regionId = this.regionId;
                                this.provinceId = '';
                                this.regencyId = '';
                                this.provinces = [];
                                this.regencies = [];
                                this.opds = [];

                                if (! regionId) {
                                    return;
                                }

                                const [provinces, regencies] = await Promise.all([
                                    this.call('provinsi', '/api/provinces?region_id=' + regionId),
                                    this.call('kabupaten', '/api/regencies?region_id=' + regionId),
                                ]);

                                if (regionId === this.regionId) {
                                    this.provinces = provinces;
                                    this.regencies = regencies;
                                }
                            },
                            async pickProvince() {
                                const key = this.regionId + ':' + this.provinceId;
                                this.regencyId = '';
                                this.opds = [];

                                const regencies = await this.call('kabupaten', '/api/regencies?region_id=' + this.regionId + (this.provinceId ? '&province_id=' + this.provinceId : ''));

                                if (key === this.regionId + ':' + this.provinceId) {
                                    this.regencies = regencies;
                                }
                            },
                            async pickRegency() {
                                const regencyId = this.regencyId;
                                this.opds = [];
                                this.opdSearch = '';

                                if (! regencyId) {
                                    return;
                                }

                                const opds = await this.call('opd', '/api/opds?regency_id=' + regencyId);

                                if (regencyId === this.regencyId) {
                                    this.opds = opds;
                                }
                            },
                            get filteredOpds() {
                                const needle = this.opdSearch.trim().toLowerCase();

                                return needle ? this.opds.filter((opd) => opd.name.toLowerCase().includes(needle)) : this.opds;
                            },
                        }">
                            <div>
                                <h3 class="font-bold">Simulasi dropdown Region &rarr; Provinsi &rarr; Kabupaten/Kota &rarr; OPD</h3>
                                <p class="text-sm text-base-content/70">
                                    Pilih dari kiri ke kanan. Setiap pilihan memanggil API sungguhan; request-nya tampil
                                    di log di bawah, persis seperti yang perlu dilakukan aplikasi Anda.
                                </p>
                            </div>

                            <div class="grid sm:grid-cols-3 gap-3">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold">1. Region</span>
                                    <select x-model="regionId" x-on:change="pickRegion()" class="select select-sm select-bordered w-full" aria-label="Region">
                                        <option value="">-- pilih region --</option>
                                        <template x-for="region in regions" :key="region.id">
                                            <option :value="region.id" x-text="region.name + ' (' + region.regencies_count + ' kab/kota)'"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold">2. Provinsi <span class="font-normal text-base-content/60">(opsional, menyaring kab/kota)</span></span>
                                    <select x-model="provinceId" x-on:change="pickProvince()" x-bind:disabled="! regionId" class="select select-sm select-bordered w-full" aria-label="Provinsi">
                                        <option value="" x-text="regionId ? '-- semua provinsi (' + provinces.length + ') --' : '-- pilih region dulu --'"></option>
                                        <template x-for="province in provinces" :key="province.id">
                                            <option :value="province.id" x-text="province.name"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold">3. Kabupaten / Kota</span>
                                    <select x-model="regencyId" x-on:change="pickRegency()" x-bind:disabled="! regionId" class="select select-sm select-bordered w-full" aria-label="Kabupaten / Kota">
                                        <option value="" x-text="regionId ? '-- pilih dari ' + regencies.length + ' kab/kota --' : '-- pilih region dulu --'"></option>
                                        <template x-for="regency in regencies" :key="regency.id">
                                            <option :value="regency.id" x-text="provinceId ? regency.name : regency.name + ' — ' + regency.provinsi"></option>
                                        </template>
                                    </select>
                                </label>
                            </div>

                            <div class="flex items-center gap-2 text-xs text-base-content/60" x-show="Object.values(loading).some(Boolean)" style="display: none">
                                <span class="loading loading-spinner loading-xs"></span> Memanggil API&hellip;
                            </div>

                            {{-- 4. OPD list --}}
                            <div x-show="regencyId" style="display: none" class="space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-semibold">4. OPD di kabupaten/kota terpilih
                                        <span class="badge badge-sm badge-ghost ml-1" x-text="opds.length + ' OPD'"></span>
                                    </p>
                                    <input type="search" x-model="opdSearch" placeholder="Saring nama OPD…" aria-label="Saring nama OPD"
                                        class="input input-xs input-bordered w-full sm:w-56" />
                                </div>
                                <div class="max-h-72 overflow-y-auto rounded-box border border-base-300 bg-base-100">
                                    <table class="table table-xs">
                                        <thead class="sticky top-0 bg-base-100">
                                            <tr>
                                                <th>ID</th>
                                                <th>Nama OPD</th>
                                                <th>Jenis</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="opd in filteredOpds" :key="opd.id">
                                                <tr>
                                                    <td class="font-mono" x-text="opd.id"></td>
                                                    <td x-text="opd.name"></td>
                                                    <td x-text="opd.jenis ?? '-'"></td>
                                                </tr>
                                            </template>
                                            <tr x-show="! loading.opd && filteredOpds.length === 0">
                                                <td colspan="3" class="text-center text-base-content/60">Tidak ada OPD.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- API calls made by the simulation --}}
                            <div class="space-y-1">
                                <p class="text-xs font-semibold">Request API yang dipanggil <span class="font-normal text-base-content/60">(terbaru di atas, klik untuk lihat response)</span></p>
                                <div class="rounded-box bg-[#0d1117] p-2 font-mono text-xs text-[#c9d1d9] space-y-0.5">
                                    <template x-for="entry in calls" :key="entry.id">
                                        <div>
                                            <button type="button" x-on:click="openCall = openCall === entry.id ? null : entry.id" class="flex w-full flex-wrap items-center gap-x-2 text-left hover:bg-white/5 rounded px-1">
                                                <span class="text-[#d2a8ff] w-16 shrink-0" x-text="entry.step"></span>
                                                <span class="text-[#3fb950]">GET</span>
                                                <span class="grow break-all" x-text="entry.path"></span>
                                                <span x-bind:class="entry.status === 200 ? 'text-[#3fb950]' : 'text-[#f85149]'" x-text="entry.status"></span>
                                                <span class="text-[#8b949e]" x-text="entry.ms + 'ms'"></span>
                                                <span class="text-[#79c0ff]" x-show="entry.count !== null" x-text="entry.count + ' item'"></span>
                                            </button>
                                            <pre x-show="openCall === entry.id" class="mt-1 max-h-64 overflow-auto rounded bg-black/30 p-2" x-text="entry.body"></pre>
                                        </div>
                                    </template>
                                    <p x-show="calls.length === 0" class="text-[#8b949e] px-1">$ belum ada request</p>
                                </div>
                            </div>

                            {{-- How to build the same dropdowns --}}
                            <div x-data="{ guide: false, copied: false }" class="rounded-box border border-base-300 bg-base-100">
                                <button type="button" x-on:click="guide = ! guide" class="flex w-full items-center justify-between px-3 py-2 text-sm font-semibold">
                                    Cara pakai di aplikasi Anda
                                    <span class="text-xs font-normal text-base-content/60" x-text="guide ? 'Sembunyikan' : 'Tampilkan'"></span>
                                </button>
                                <div x-show="guide" x-cloak class="space-y-3 border-t border-base-300 p-3 text-sm">
                                    <div class="overflow-x-auto">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Kapan</th>
                                                    <th>Panggil</th>
                                                    <th>Isi dropdown</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Halaman dibuka</td>
                                                    <td class="font-mono text-xs">GET /api/regions</td>
                                                    <td>Region</td>
                                                </tr>
                                                <tr>
                                                    <td>Region dipilih</td>
                                                    <td class="font-mono text-xs">GET /api/provinces?region_id={id}<br>GET /api/regencies?region_id={id}</td>
                                                    <td>Provinsi &amp; Kabupaten/Kota (boleh dipanggil bersamaan)</td>
                                                </tr>
                                                <tr>
                                                    <td>Provinsi dipilih <span class="text-base-content/60">(opsional)</span></td>
                                                    <td class="font-mono text-xs">GET /api/regencies?region_id={id}&amp;province_id={id}</td>
                                                    <td>Kabupaten/Kota, disaring ke provinsi itu</td>
                                                </tr>
                                                <tr>
                                                    <td>Kabupaten/Kota dipilih</td>
                                                    <td class="font-mono text-xs">GET /api/opds?regency_id={id}</td>
                                                    <td>OPD</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <ul class="list-disc list-inside space-y-1 text-base-content/70">
                                        <li>Simpan <strong>id</strong> sebagai value option, bukan nama. Filter <span class="font-mono">*_id</span> mencocokkan persis, sedangkan filter nama bersifat partial
                                            (&ldquo;Kab. Bandung&rdquo; juga cocok dengan &ldquo;Kab. Bandung Barat&rdquo;).</li>
                                        <li>Saat pilihan di atas berubah, kosongkan dropdown di bawahnya sebelum memanggil API lagi.</li>
                                        <li>Region dipetakan per kabupaten/kota, jadi satu provinsi bisa muncul di lebih dari satu region.</li>
                                        <li>Semua endpoint publik dan read-only (60 request/menit per IP). Dari domain lain di browser, pastikan CORS mengizinkan, atau panggil dari backend.</li>
                                    </ul>
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold">Contoh JavaScript (tanpa library)</p>
                                        <button type="button" class="btn btn-ghost btn-xs"
                                            x-on:click="navigator.clipboard.writeText($refs.cascadeCode.innerText); copied = true; setTimeout(() => copied = false, 1500)">
                                            <span x-show="! copied">Salin</span><span x-show="copied" x-cloak>✓ Tersalin</span>
                                        </button>
                                    </div>
                                    <pre class="max-h-96 overflow-auto rounded-box bg-[#0d1117] p-3 text-xs text-[#c9d1d9]"><code x-ref="cascadeCode">{{ $cascadeExample }}</code></pre>
                                </div>
                            </div>
                        </div>

                        <div class="divider my-1 text-xs">Command siap pakai</div>
                        <p class="text-sm text-base-content/70">
                            Command GET siap pakai untuk endpoint OPD Lookup. Klik <span class="font-semibold">Test</span>
                            untuk menjalankan satu command, atau <span class="font-semibold">Jalankan semua</span> untuk
                            mengecek semuanya sekaligus. Setiap command bisa diubah langsung: ketik path
                            (<code class="text-xs">/api/opds?provinsi=Jawa Barat</code>), URL lengkap, atau tempel command
                            <code class="text-xs">curl</code>. Request selalu dikirim ke server ini
                            (<code class="text-xs" x-text="origin"></code>).
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn btn-primary btn-sm" x-on:click="runAll()" x-bind:disabled="runningAll">
                                <span x-show="runningAll" class="loading loading-spinner loading-xs"></span>
                                Jalankan semua
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="add()">+ Tambah command</button>
                            <span class="text-sm" x-show="summary.checked > 0" style="display: none">
                                <span x-bind:class="summary.passed === summary.checked ? 'text-success' : 'text-error'" class="font-semibold"
                                    x-text="summary.passed + '/' + summary.checked + ' sesuai harapan'"></span>
                            </span>
                            <label class="ml-auto flex items-center gap-2 text-xs text-base-content/70">
                                Salin sebagai
                                <select x-model="shell" class="select select-xs select-bordered">
                                    <option value="powershell">PowerShell (curl.exe)</option>
                                    <option value="bash">Bash (curl)</option>
                                </select>
                            </label>
                        </div>

                        <div class="alert alert-warning text-xs">
                            <span>Endpoint publik dibatasi <strong>60 request/menit per IP</strong>. &ldquo;Jalankan semua&rdquo;
                                memakai satu request per baris; kalau mulai mendapat <code>429</code>, tunggu 1 menit.</span>
                        </div>

                        <div class="divide-y divide-base-200 border border-base-300 rounded-box">
                            <template x-for="(row, index) in rows" :key="row.id">
                                <div>
                                    <p x-show="index === 0 || rows[index - 1].group !== row.group"
                                        class="bg-base-200 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-base-content/70" x-text="row.group"></p>

                                    <div class="px-3 py-2 space-y-1.5">
                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                            <span class="font-medium text-base-content/80" x-text="row.label"></span>
                                            <span x-show="expected(row) !== null" class="text-base-content/50" x-text="'harapan: ' + expected(row)"></span>
                                            <span x-show="row.original !== null && row.path !== row.original" class="badge badge-ghost badge-xs">diubah</span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <span class="badge badge-success badge-sm font-mono font-bold shrink-0">GET</span>
                                            <input type="text" x-model="row.path" x-bind:id="'playground-input-' + row.id"
                                                x-on:keydown.enter.prevent="run(row)" spellcheck="false" aria-label="Command"
                                                class="input input-sm input-bordered font-mono text-xs grow min-w-0" />
                                            <button type="button" class="btn btn-sm btn-primary btn-outline shrink-0" x-on:click="run(row)" x-bind:disabled="row.running">
                                                <span x-show="row.running" class="loading loading-spinner loading-xs"></span>
                                                Test
                                            </button>
                                            <button type="button" x-show="row.result && row.result.status !== undefined" x-on:click="selected = selected === row.id ? null : row.id"
                                                class="badge badge-sm font-mono shrink-0 cursor-pointer" title="Tampilkan / sembunyikan response"
                                                x-bind:class="row.result?.status < 300 ? 'badge-success' : (row.result?.status < 500 ? 'badge-warning' : 'badge-error')"
                                                x-text="row.result?.status"></button>
                                            <span x-show="passed(row)" class="text-success font-bold shrink-0" title="Sesuai harapan">✓</span>
                                            <span x-show="failed(row)" class="text-error font-bold shrink-0" title="Tidak sesuai harapan">✗</span>
                                            <button type="button" x-show="row.original !== null && row.path !== row.original" x-on:click="reset(row)" class="btn btn-ghost btn-xs shrink-0">Reset</button>
                                            <button type="button" x-show="row.custom" x-on:click="remove(row)" class="btn btn-ghost btn-xs shrink-0">Hapus</button>
                                        </div>

                                        <div x-show="selected === row.id && row.result" class="space-y-2 pt-1">
                                            <p x-show="row.result?.error" class="text-xs text-error" x-text="row.result?.error"></p>
                                            <div x-show="row.result && ! row.result.error" class="space-y-2">
                                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-base-content/70">
                                                    <span>Status <strong x-text="row.result?.status"></strong></span>
                                                    <span x-text="row.result?.ms + ' ms'"></span>
                                                    <span x-show="row.result?.count != null" x-text="row.result?.count + ' item di data'"></span>
                                                    <span x-show="row.result?.total != null" x-text="'total ' + row.result?.total"></span>
                                                    <span x-show="row.result?.remaining != null" x-text="'rate limit sisa ' + row.result?.remaining + '/' + row.result?.limit"></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <code class="text-xs bg-base-200 rounded px-2 py-1 grow break-all" x-text="curlFor(row)"></code>
                                                    <button type="button" class="btn btn-ghost btn-xs shrink-0" x-on:click="copy(curlFor(row))">Salin</button>
                                                </div>
                                                <pre class="bg-[#0d1117] text-[#c9d1d9] rounded-box p-3 text-xs overflow-auto max-h-96"><code x-text="row.result?.body"></code></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p class="text-xs text-base-content/50">
                            Playground hanya mengirim GET ke <code>/api/...</code> di server ini. Endpoint internal
                            (AI Data API, Employee API) butuh header token, jadi dari sini akan mendapat <code>401</code>;
                            untuk itu ikuti tab <a href="#ai-testing" x-on:click.prevent="open('ai-testing')" class="link">Cara Testing</a>.
                        </p>
                    </div>
                </div>

                {{-- /api/regions --}}
                <div id="regions" role="tabpanel" x-show="active === 'regions'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/regions</h2>
                        </div>
                        <p class="text-sm text-base-content/70">List region bisnis, level pertama dropdown
                            Region &rarr; Provinsi &rarr; Kabupaten/Kota &rarr; OPD. Tanpa parameter. Region dipetakan per
                            kabupaten/kota; <span class="font-mono">regencies_count</span> = jumlah kabupaten/kota di region itu.</p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh Request</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl "{{ config('app.url') }}/api/regions"</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "data": [
    { "id": 1, "name": "Region 1", "code": "REG-1", "regencies_count": 140 },
    { "id": 2, "name": "Region 2", "code": "REG-2", "regencies_count": 86 }
  ]
}</code></pre>
                        </div>

                        <p class="text-sm text-base-content/70">Lanjutkan dengan
                            <span class="font-mono">/api/provinces?region_id={id}</span> dan
                            <span class="font-mono">/api/regencies?region_id={id}</span>. Coba langsung di tab
                            <a href="#playground" x-on:click.prevent="open('playground')" class="link">Coba API (Playground)</a>.</p>
                    </div>
                </div>

                {{-- /api/provinces --}}
                <div id="provinces" role="tabpanel" x-show="active === 'provinces'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                                    <tr>
                                        <td class="font-mono">region_id</td>
                                        <td><span class="badge badge-ghost badge-sm">Tidak</span></td>
                                        <td>integer</td>
                                        <td>Hanya provinsi yang punya kabupaten/kota di region ini (id dari <span class="font-mono">/api/regions</span>).</td>
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
                <div id="regencies" role="tabpanel" x-show="active === 'regencies'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/regencies</h2>
                        </div>
                        <p class="text-sm text-base-content/70">List kabupaten/kota. Isi minimal salah satu dari
                            <span class="font-semibold">provinsi</span>, <span class="font-semibold">province_id</span>, atau
                            <span class="font-semibold">region_id</span>.
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
                                        <td><span class="badge badge-warning badge-sm">Salah satu</span></td>
                                        <td>string</td>
                                        <td>Nama provinsi, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">province_id</td>
                                        <td><span class="badge badge-warning badge-sm">Salah satu</span></td>
                                        <td>integer</td>
                                        <td>Id provinsi, cocok persis.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">region_id</td>
                                        <td><span class="badge badge-warning badge-sm">Salah satu</span></td>
                                        <td>integer</td>
                                        <td>Id region, cocok persis. Bisa digabung dengan <span class="font-mono">province_id</span>.</td>
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
                <div id="opds" role="tabpanel" x-show="active === 'opds'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/opds</h2>
                        </div>
                        <p class="text-sm text-base-content/70">List OPD (Organisasi Perangkat Daerah).
                            Isi <span class="font-semibold">provinsi</span> atau <span class="font-semibold">regency_id</span>,
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
                                        <td><span class="badge badge-warning badge-sm">Salah satu</span></td>
                                        <td>string</td>
                                        <td>Nama provinsi, partial match.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">regency_id</td>
                                        <td><span class="badge badge-warning badge-sm">Salah satu</span></td>
                                        <td>integer</td>
                                        <td>Id kabupaten/kota, cocok persis. Disarankan untuk dropdown (lihat Playground).</td>
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
                <div id="opds-search" role="tabpanel" x-show="active === 'opds-search'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                <div id="catatan" role="tabpanel" x-show="active === 'catatan'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                            <li>Keempat endpoint ini publik (tanpa token) tetapi dibatasi <strong>120 request per menit
                                    per IP</strong>, dihitung gabungan untuk semua endpoint OPD Lookup. Lewat batas itu,
                                response-nya <code class="text-xs">429 Too Many Requests</code>; header
                                <code class="text-xs">X-RateLimit-Remaining</code> menunjukkan sisa kuota dan
                                <code class="text-xs">Retry-After</code> (saat 429) lama menunggu dalam detik. Untuk
                                pemakaian normal (mengisi dropdown) batas ini tidak akan tercapai.</li>
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
                <div id="ai-data" role="tabpanel" x-show="active === 'ai-data'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                <div id="ai-auth" role="tabpanel" x-show="active === 'ai-auth'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                <div id="ai-datasets" role="tabpanel" x-show="active === 'ai-datasets'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                <div id="ai-query" role="tabpanel" x-show="active === 'ai-query'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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
                <div id="ai-testing" role="tabpanel" x-show="active === 'ai-testing'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
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

                {{-- Employee API: list/search --}}
                <div id="employees" role="tabpanel" x-show="active === 'employees'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/employees</h2>
                        </div>
                        <p class="text-sm text-base-content/70">
                            Daftar dan pencarian karyawan, dipaginasi. Khusus layanan internal: wajib
                            <code class="text-xs">Authorization: Bearer &lt;AI_DATA_API_TOKEN&gt;</code> (token yang
                            sama dengan AI Data API), <strong>tanpa</strong> header
                            <code class="text-xs">X-AI-User-Assertion</code>, jadi tidak dibatasi per user atau
                            departemen. Batas 120 request per menit.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Query
                            Parameter (semua opsional)</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-mono">q</td>
                                        <td>Pencarian bebas pada nama, email, username, dan NIS (partial match, tidak
                                            peka huruf besar/kecil).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">department</td>
                                        <td>Nama departemen (partial match).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">region</td>
                                        <td>Nama region (partial match).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">position</td>
                                        <td>Nama jabatan struktural, fungsional, atau teknis (partial match).</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">status</td>
                                        <td><code class="text-xs">active</code>, <code class="text-xs">resign</code>,
                                            <code class="text-xs">discontinued</code>, <code class="text-xs">phk</code>,
                                            atau <code class="text-xs">inactive</code>. Tanpa parameter ini, semua status
                                            ikut tampil, jadi filter <code class="text-xs">active</code> jika hanya
                                            butuh yang masih bekerja.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">employment_status</td>
                                        <td>Cocok persis, mis. <code class="text-xs">PKWT</code>,
                                            <code class="text-xs">PKWTT</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">sort, direction</td>
                                        <td><code class="text-xs">sort</code>: <code class="text-xs">name</code>
                                            (default) atau <code class="text-xs">nis</code>;
                                            <code class="text-xs">direction</code>: <code class="text-xs">asc</code>
                                            (default) atau <code class="text-xs">desc</code>.</td>
                                    </tr>
                                    <tr>
                                        <td class="font-mono">per_page, page</td>
                                        <td>Default 25, maksimal 100.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh
                            Request</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl -H "Authorization: Bearer $AI_DATA_API_TOKEN" \
  "{{ config('app.url') }}/api/employees?q=budi&amp;department=finance&amp;status=active"</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "data": [
    {
      "id": 7,
      "nis": "NIS-001",
      "name": "Budi Santoso",
      "email": "budi@smartid.co.id",
      "username": "budi",
      "gender": "L",
      "department": "Departemen Finance",
      "region": "Jawa Barat",
      "position_struktural": null,
      "position_fungsional": "Funding Officer",
      "position_teknis": null,
      "employment_status": "PKWTT",
      "is_cltp": false,
      "contract_start_date": "2024-01-01",
      "contract_end_date": null,
      "status": "active"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 }
}</code></pre>
                        </div>

                        <div class="alert alert-info text-xs mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Data sensitif (NIK, NPWP, tanggal lahir, nomor telepon, status pernikahan, target
                                dan KPI) sengaja <strong>tidak</strong> disertakan.</span>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Kode Status
                        </p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <tbody>
                                    <tr>
                                        <td><span class="badge badge-error badge-sm text-white">401</span></td>
                                        <td>Token kosong/salah, atau API belum dikonfigurasi.</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-error badge-sm text-white">422</span></td>
                                        <td>Parameter tidak valid (mis. <code class="text-xs">status</code> di luar
                                            daftar, <code class="text-xs">per_page</code> &gt; 100).</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-warning badge-sm">429</span></td>
                                        <td>Batas 120 request per menit tercapai.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Employee API: detail --}}
                <div id="employee-detail" role="tabpanel" x-show="active === 'employee-detail'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/employees/{id}</h2>
                        </div>
                        <p class="text-sm text-base-content/70">
                            Satu karyawan berdasarkan <code class="text-xs">id</code> (dari hasil
                            <code class="text-xs">/api/employees</code>). Autentikasi dan field sama dengan list;
                            response berupa <code class="text-xs">{ "data": { ... } }</code>.
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl -H "Authorization: Bearer $AI_DATA_API_TOKEN" \
  {{ config('app.url') }}/api/employees/7</code></pre>
                        </div>
                        <p class="text-sm text-base-content/70">
                            <span class="badge badge-error badge-sm text-white">401</span> token salah &middot;
                            <span class="badge badge-error badge-sm text-white">404</span> id tidak ditemukan atau
                            karyawan sudah dihapus.
                        </p>
                    </div>
                </div>

                {{-- SSO: overview --}}
                <div id="sso-overview" role="tabpanel" x-show="active === 'sso-overview'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Login with SmartID</h2>
                        <p class="text-sm text-base-content/70">
                            Aplikasi internal lain bisa memakai akun SmartID untuk login, jadi user tidak perlu akun
                            dan password terpisah. Password hanya pernah diketik di SmartID; aplikasi lain cuma menerima
                            profil user (nama, email, dan data lain yang diizinkan). Standarnya OAuth2 authorization code.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Pemasangan dalam 3 langkah</p>
                        <ol class="space-y-2 text-sm">
                            <li class="flex gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-content">1</span>
                                <span><strong>Admin SmartID</strong> mendaftarkan aplikasi di <span class="font-mono">Technology › SSO / Connected Apps</span>
                                    (<span class="font-mono">/tech/sso</span>): cukup isi nama dan URL aplikasi.</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-content">2</span>
                                <span><strong>Developer aplikasi client</strong> mengikuti tab
                                    <a href="#sso-laravel" x-on:click.prevent="open('sso-laravel')" class="link">Pasang di Laravel</a>.
                                    Versi yang nilainya sudah terisi (Client ID, secret, callback) ada di tombol
                                    <span class="font-semibold">Kredensial</span> pada halaman SSO.</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-content">3</span>
                                <span>Buka halaman login aplikasi client, klik <span class="font-semibold">Login with SmartID</span>.
                                    Kalau gagal, lihat <a href="#sso-troubleshooting" x-on:click.prevent="open('sso-troubleshooting')" class="link">Troubleshooting</a>.</span>
                            </li>
                        </ol>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Yang terjadi saat user klik "Login with SmartID"</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="1"><code>Aplikasi client  → redirect ke  {{ config('app.url') }}/oauth/authorize</code></pre>
                            <pre data-prefix="2"><code>SmartID          → user login (kalau belum) + layar "Izinkan akses?"</code></pre>
                            <pre data-prefix="3"><code>SmartID          → redirect balik ke callback client dengan ?code=...&amp;state=...</code></pre>
                            <pre data-prefix="4"><code>Server client    → POST {{ config('app.url') }}/oauth/token  (code + client secret → access token)</code></pre>
                            <pre data-prefix="5"><code>Server client    → GET  {{ config('app.url') }}/api/me      (access token → profil user)</code></pre>
                            <pre data-prefix="6"><code>Aplikasi client  → cari/buat user lokal, login, selesai</code></pre>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Endpoint</th>
                                        <th>URL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>Authorize</td><td class="font-mono text-xs">{{ config('app.url') }}/oauth/authorize</td></tr>
                                    <tr><td>Token</td><td class="font-mono text-xs">{{ config('app.url') }}/oauth/token</td></tr>
                                    <tr><td>User info</td><td class="font-mono text-xs">{{ config('app.url') }}/api/me</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info text-xs mt-1">
                            <span>Akses bisa dibatasi per aplikasi ke role atau departemen tertentu. User yang
                                di-suspend otomatis tidak bisa login lagi, dan admin bisa mencabut sesi kapan saja dari
                                halaman SSO.</span>
                        </div>
                    </div>
                </div>

                {{-- SSO: client setup --}}
                <div id="sso-laravel" role="tabpanel" x-show="active === 'sso-laravel'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Pasang di aplikasi Laravel (+ Filament)</h2>
                        <p class="text-sm text-base-content/70">
                            Untuk Laravel 11 ke atas, tanpa package tambahan. Ganti nilai
                            <code class="text-xs">CLIENT_ID_DARI_SMARTID</code>, <code class="text-xs">SECRET_DARI_SMARTID</code>, dan URL aplikasi
                            dengan milik Anda, atau salin versi yang sudah terisi dari tombol <span class="font-semibold">Kredensial</span>
                            di halaman <span class="font-mono">/tech/sso</span>.
                        </p>

                        @include('partials.sso-laravel-guide', [
                            'host' => rtrim(config('app.url'), '/'),
                            'clientId' => 'CLIENT_ID_DARI_SMARTID',
                            'clientSecret' => 'SECRET_DARI_SMARTID',
                            'redirect' => 'https://aplikasi-anda.co.id/auth/smartid/callback',
                            'scopes' => 'profile email',
                            'confidential' => true,
                        ])
                    </div>
                </div>

                {{-- SSO: /api/me --}}
                <div id="sso-api" role="tabpanel" x-show="active === 'sso-api'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-success font-mono font-bold">GET</span>
                            <h2 class="text-lg font-bold font-mono">/api/me</h2>
                        </div>
                        <p class="text-sm text-base-content/70">
                            Profil user pemilik access token. Field yang dikembalikan mengikuti scope yang diminta
                            aplikasi <em>dan</em> diizinkan admin untuk aplikasi tersebut.
                        </p>

                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Scope</th>
                                        <th>Field</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td class="font-mono">profile</td><td><code class="text-xs">id, name, username, avatar</code> (selalu ada)</td></tr>
                                    <tr><td class="font-mono">email</td><td><code class="text-xs">email, email_verified</code></td></tr>
                                    <tr><td class="font-mono">department</td><td><code class="text-xs">department { id, name }</code></td></tr>
                                    <tr><td class="font-mono">roles</td><td><code class="text-xs">roles[], permissions[]</code></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Contoh Request</p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix="$"><code>curl -H "Authorization: Bearer ACCESS_TOKEN" -H "Accept: application/json" {{ config('app.url') }}/api/me</code></pre>
                        </div>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Response
                            <span class="badge badge-success badge-sm">200 OK</span> &mdash; scope <code class="text-xs">profile email department</code>
                        </p>
                        <div class="mockup-code text-xs">
                            <pre data-prefix=""><code>{
  "id": 42,
  "name": "Budi Santoso",
  "username": "budi",
  "avatar": null,
  "email": "budi@smartid.co.id",
  "email_verified": true,
  "department": { "id": 3, "name": "Departemen Finance" }
}</code></pre>
                        </div>

                        <p class="text-sm text-base-content/70">
                            <span class="badge badge-error badge-sm text-white">401</span> token tidak ada, salah, kedaluwarsa, atau sudah dicabut &middot;
                            <span class="badge badge-error badge-sm text-white">403</span> user sudah tidak aktif atau tidak lagi diizinkan ke aplikasi ini.
                        </p>

                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mt-2">Masa berlaku token</p>
                        <p class="text-sm text-base-content/70">
                            Access token berlaku <strong>8 jam</strong>, refresh token <strong>30 hari</strong>. Panduan
                            pemasangan hanya memakai token sekali saat login (aplikasi client lalu memakai session-nya
                            sendiri), jadi refresh token tidak wajib dipakai.
                        </p>
                    </div>
                </div>

                {{-- SSO: troubleshooting --}}
                <div id="sso-troubleshooting" role="tabpanel" x-show="active === 'sso-troubleshooting'" style="display: none" class="card bg-base-100 border border-base-300 shadow-sm scroll-mt-20">
                    <div class="card-body p-5 gap-3">
                        <h2 class="text-lg font-bold">Troubleshooting SSO</h2>
                        <div class="overflow-x-auto">
                            <table class="table table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Gejala</th>
                                        <th>Penyebab &amp; solusi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code class="text-xs">invalid_client</code> / error 401 di halaman SmartID</td>
                                        <td>Client ID salah, aplikasi dinonaktifkan, atau <strong>Redirect URI tidak sama persis</strong>
                                            (http vs https, port, garis miring di akhir). Samakan <code class="text-xs">SMARTID_REDIRECT_URI</code>
                                            dengan callback di halaman SSO, lalu <code class="text-xs">php artisan config:clear</code>.</td>
                                    </tr>
                                    <tr>
                                        <td>"Sesi login SmartID kedaluwarsa" (403) setelah kembali dari SmartID</td>
                                        <td>Session aplikasi client hilang di tengah jalan. Paling sering terjadi di <strong>localhost</strong>:
                                            cookie tidak dipisah per port, jadi session SmartID dan client saling menimpa. Isi
                                            <code class="text-xs">SESSION_COOKIE=nama_unik_session</code> di <code class="text-xs">.env</code> client.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="text-xs">401</code> saat menukar code (<code class="text-xs">/oauth/token</code>)</td>
                                        <td>Client secret salah atau sudah di-generate ulang. Klik <em>Tampilkan secret</em> di halaman SSO,
                                            perbarui <code class="text-xs">.env</code> client, lalu <code class="text-xs">php artisan config:clear</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="text-xs">invalid_grant</code> saat menukar code</td>
                                        <td>Code hanya berlaku sekali dan sebentar. Jangan me-refresh halaman callback; ulangi login dari awal.</td>
                                    </tr>
                                    <tr>
                                        <td>"Tidak bisa masuk ke &hellip;" di SmartID</td>
                                        <td>Aplikasi dibatasi ke role/departemen tertentu dan user tidak termasuk. Ubah di halaman SSO (Edit).</td>
                                    </tr>
                                    <tr>
                                        <td><code class="text-xs">Undefined array key "email"</code> di controller client</td>
                                        <td>Scope <code class="text-xs">email</code> tidak diminta atau tidak diizinkan. Pastikan
                                            <code class="text-xs">SMARTID_SCOPES="profile email"</code> dan <em>Alamat email</em> tercentang di pengaturan aplikasi.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="text-xs">cURL error 60</code> / SSL certificate</td>
                                        <td>Server client tidak mempercayai sertifikat HTTPS SmartID (biasanya sertifikat lokal/self-signed).
                                            Pakai sertifikat valid, atau untuk development arahkan <code class="text-xs">SMARTID_HOST</code> ke http lokal.</td>
                                    </tr>
                                    <tr>
                                        <td>Filament: sudah login tapi 403</td>
                                        <td>Model User di aplikasi client belum mengizinkan akses panel. Implementasikan
                                            <code class="text-xs">FilamentUser::canAccessPanel()</code>.</td>
                                    </tr>
                                    <tr>
                                        <td>Logout di client tapi langsung login lagi tanpa ditanya</td>
                                        <td>Normal: user masih login di SmartID. Logout dari SmartID juga kalau ingin ganti akun.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Previous / next tab --}}
                <div class="flex items-center justify-between gap-2">
                    <a x-show="previous" style="display: none" :href="'#' + previous" x-on:click.prevent="open(previous)"
                        class="btn btn-sm btn-ghost">
                        &larr; <span x-text="previous && titles[previous].tab"></span>
                    </a>
                    <a x-show="next" :href="'#' + next" x-on:click.prevent="open(next)" class="btn btn-sm btn-ghost ms-auto">
                        <span x-text="next && titles[next].tab">{{ $titles[$tabIds[1]]['tab'] }}</span> &rarr;
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-layouts::app.sidebar>
