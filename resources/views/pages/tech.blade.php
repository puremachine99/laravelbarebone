<x-layouts::app.sidebar title="Timeline Calculator">
    <div x-data="techCalculator()" x-init="init()" class="space-y-4">

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-base-content">Enterprise Timeline Calculator</h1>
                <p class="text-sm text-base-content/60 mt-0.5">Estimasi story points & timeline proyek berdasarkan task
                    categories</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- Team size --}}
                <div class="flex items-center gap-2 bg-base-200 rounded-lg px-3 py-2">
                    <span class="text-xs text-base-content/60">Tim:</span>
                    <button @click="teamSize = Math.max(1, teamSize - 1)"
                        class="btn btn-xs btn-ghost btn-square font-bold">−</button>
                    <span class="font-mono text-sm font-semibold w-5 text-center" x-text="teamSize"></span>
                    <button @click="teamSize = Math.min(20, teamSize + 1)"
                        class="btn btn-xs btn-ghost btn-square font-bold">+</button>
                    <span class="text-xs text-base-content/60">dev</span>
                </div>
                {{-- SP per dev --}}
                <div class="flex items-center gap-2 bg-base-200 rounded-lg px-3 py-2">
                    <span class="text-xs text-base-content/60">SP/dev/hari:</span>
                    <select x-model.number="spPerDev"
                        class="select select-xs bg-transparent border-none outline-none font-mono font-semibold min-h-0 h-auto p-0">
                        <option value="1">1</option>
                        <option value="1.5">1.5</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </select>
                </div>
                {{-- Reset --}}
                <button @click="resetAll()" class="btn btn-sm btn-ghost text-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Reset
                </button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-4 items-start">

            {{-- LEFT: Task Selector --}}
            <div class="lg:w-1/2 min-w-0 space-y-3 w-full">

                {{-- Search & Filter --}}
                <div class="flex gap-2">
                    <label class="input input-sm flex-1 flex items-center gap-2">
                        <svg class="size-4 text-base-content/40" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="search" type="text" placeholder="Cari task..." class="grow">
                    </label>
                    <select x-model="filterPhase" class="select select-sm">
                        <option value="">Semua Phase</option>
                        <option value="P0">P0 — Discovery</option>
                        <option value="P1">P1 — Foundation</option>
                        <option value="P2">P2 — Core Dev</option>
                        <option value="P3">P3 — Integration</option>
                        <option value="P4">P4 — Go-Live</option>
                    </select>
                </div>

                {{-- Categories --}}
                <template x-for="cat in filteredCategories()" :key="cat.id">
                    <div class="card bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                        {{-- Category header --}}
                        <div @click="toggleCat(cat.id)"
                            class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-base-200/50 transition-colors select-none">
                            <div class="badge badge-sm font-bold text-white border-0" :class="cat.badgeColor"
                                x-text="cat.code"></div>
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold text-sm" x-text="cat.name"></span>
                                <span class="text-xs text-base-content/50 ml-2"
                                    x-text="`${getCatSelectedCount(cat.id)} / ${cat.subcats.reduce((a,s)=>a+s.tasks.length,0)} task dipilih`"></span>
                            </div>
                            <span class="text-xs font-mono font-semibold text-primary"
                                x-text="`${getCatSP(cat.id)} SP`"></span>
                            <svg class="size-4 text-base-content/40 transition-transform duration-200"
                                :class="openCats.includes(cat.id) ? 'rotate-180' : ''" fill="none"
                                stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>

                        {{-- Subcategories --}}
                        <div x-show="openCats.includes(cat.id)" x-collapse class="border-t border-base-300">
                            <template x-for="sub in cat.subcats" :key="sub.id">
                                <div>
                                    {{-- Subcat header --}}
                                    <div
                                        class="flex items-center gap-2 px-4 py-2 bg-base-200/40 border-b border-base-300">
                                        <input type="checkbox" class="checkbox checkbox-xs checkbox-primary"
                                            :checked="isSubcatChecked(sub)" :indeterminate="isSubcatIndeterminate(sub)"
                                            @change="toggleSubcat(sub, $event.target.checked)">
                                        <span class="text-xs font-semibold text-base-content/70 uppercase tracking-wide"
                                            x-text="sub.name"></span>
                                        <span class="ml-auto text-xs text-base-content/40 font-mono"
                                            x-text="`${sub.tasks.filter(t=>selectedTasks.includes(t.id)).length}/${sub.tasks.length}`"></span>
                                    </div>
                                    {{-- Tasks --}}
                                    <template x-for="task in filteredTasks(sub.tasks)" :key="task.id">
                                        <label
                                            class="flex items-start gap-3 px-4 py-2.5 cursor-pointer hover:bg-base-200/30 transition-colors border-b border-base-200 last:border-0">
                                            <input type="checkbox"
                                                class="checkbox checkbox-xs checkbox-primary mt-0.5 flex-shrink-0"
                                                :value="task.id" x-model="selectedTasks">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm leading-tight" x-text="task.name"></div>
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                    <template x-for="ph in task.phases" :key="ph">
                                                        <span class="badge badge-xs font-mono font-semibold"
                                                            :class="phaseBadge(ph)" x-text="ph"></span>
                                                    </template>
                                                    <span x-show="task.note"
                                                        class="text-xs text-base-content/40 italic"
                                                        x-text="task.note"></span>
                                                </div>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <div class="badge badge-sm font-mono font-bold badge-ghost"
                                                    x-text="task.sp + ' SP'"></div>
                                                <div class="text-xs text-base-content/40 mt-0.5"
                                                    x-text="spHours(task.sp)"></div>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <p x-show="filteredCategories().length === 0" class="text-center py-12 text-base-content/40 text-sm">
                    Tidak ada task yang cocok dengan filter.</p>
            </div>

            {{-- RIGHT: Summary Panel --}}
            <div class="lg:w-1/2 space-y-4 w-full lg:sticky lg:top-4">

                {{-- Total SP --}}
                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-base-content/50 text-xs font-medium uppercase tracking-wider">Total
                                    Story Points</p>
                                <p class="font-mono text-4xl font-bold mt-1 text-primary" x-text="totalSP()"></p>
                                <p class="text-base-content/50 text-sm mt-1"
                                    x-text="`${selectedTasks.length} task dipilih`"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-base-content/50 text-xs font-medium uppercase tracking-wider">Estimasi
                                </p>
                                <p class="font-mono text-2xl font-bold mt-1" x-text="estimateDays() + ' hari'"></p>
                                <p class="text-base-content/50 text-sm mt-1" x-text="estimateWeeks()"></p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex justify-between text-xs text-base-content/40 mb-1">
                                <span>Kompleksitas</span>
                                <span x-text="complexityLabel()"></span>
                            </div>
                            <progress class="progress progress-primary w-full" :value="Math.min(100, totalSP() / 3)"
                                max="100"></progress>
                        </div>
                    </div>
                </div>

                {{-- Phase breakdown --}}
                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-4 gap-3">
                        <h3 class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">Distribusi per
                            Phase</h3>
                        <div class="space-y-2.5">
                            <template x-for="ph in phases" :key="ph.code">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="badge badge-xs font-mono font-semibold"
                                                :class="phaseBadge(ph.code)" x-text="ph.code"></span>
                                            <span class="text-xs text-base-content/60" x-text="ph.name"></span>
                                        </div>
                                        <span class="font-mono text-xs font-semibold"
                                            x-text="getPhaseSP(ph.code) + ' SP'"></span>
                                    </div>
                                    <progress class="progress w-full h-1.5" :class="ph.progressColor"
                                        :value="totalSP() > 0 ? getPhaseSP(ph.code) / totalSP() * 100 : 0"
                                        max="100"></progress>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Category breakdown --}}
                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-4 gap-3">
                        <h3 class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">Breakdown
                            Kategori</h3>
                        <div class="space-y-1.5">
                            <template x-for="cat in categories" :key="cat.id">
                                <div x-show="getCatSP(cat.id) > 0" class="flex items-center gap-2">
                                    <div class="badge badge-xs font-bold text-white border-0 flex-shrink-0"
                                        :class="cat.badgeColor" x-text="cat.code"></div>
                                    <span class="text-xs text-base-content/60 flex-1 truncate"
                                        x-text="cat.name"></span>
                                    <span class="font-mono text-xs font-semibold"
                                        x-text="getCatSP(cat.id) + ' SP'"></span>
                                </div>
                            </template>
                            <p x-show="totalSP() === 0" class="text-xs text-base-content/40 text-center py-2">Pilih
                                task untuk melihat breakdown</p>
                        </div>
                    </div>
                </div>

                {{-- Gantt estimate --}}
                <div x-show="selectedTasks.length > 0" class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-4 gap-3">
                        <h3 class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">Estimasi Gantt
                            (Minggu)</h3>
                        <div class="space-y-2">
                            <template x-for="ph in phases" :key="ph.code">
                                <div x-show="getPhaseSP(ph.code) > 0">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <span class="text-xs text-base-content/50"
                                            x-text="ph.code + ' ' + ph.name"></span>
                                        <span class="font-mono text-xs text-base-content/50"
                                            x-text="ganttWeeks(ph.code) + ' minggu'"></span>
                                    </div>
                                    <div class="flex">
                                        <div class="h-5 rounded flex items-center justify-center text-white text-xs font-semibold transition-all duration-300"
                                            :class="ph.ganttColor"
                                            :style="`width: ${Math.max(40, ganttWeeks(ph.code) * 24)}px`"
                                            x-text="ganttWeeks(ph.code) + 'w'"></div>
                                    </div>
                                </div>
                            </template>
                            <div class="pt-2 border-t border-base-300 flex justify-between items-center">
                                <span class="text-xs text-base-content/50">Total (serial)</span>
                                <span class="font-mono text-sm font-bold text-primary"
                                    x-text="totalCalendarWeeks() + ' minggu'"></span>
                            </div>
                            <p class="text-xs text-base-content/40">*Asumsi serial. Dengan paralelisasi bisa lebih
                                singkat.</p>
                        </div>
                    </div>
                </div>

                {{-- Selected tasks --}}
                <div x-show="selectedTasks.length > 0" class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-4 gap-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">Task
                                Dipilih</h3>
                            <button @click="selectedTasks = []" class="text-xs text-error hover:underline">Hapus
                                semua</button>
                        </div>
                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            <template x-for="tid in selectedTasks" :key="tid">
                                <div class="flex items-center gap-2 py-0.5">
                                    <button @click="selectedTasks = selectedTasks.filter(x=>x!==tid)"
                                        class="size-4 shrink-0 text-base-content/30 hover:text-error transition-colors">
                                        <svg fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    <span class="text-xs text-base-content/60 truncate flex-1"
                                        x-text="getTaskName(tid)"></span>
                                    <span class="font-mono text-xs font-semibold text-base-content/50 shrink-0"
                                        x-text="getTaskSP(tid) + ' SP'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- SP reference --}}
                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-4 gap-3">
                        <h3 class="text-xs font-semibold text-base-content/50 uppercase tracking-wider">Referensi Story
                            Points</h3>
                        <table class="table table-xs w-full">
                            <thead>
                                <tr>
                                    <th>SP</th>
                                    <th>Kompleksitas</th>
                                    <th class="text-right">Estimasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-mono font-semibold">1</td>
                                    <td>Trivial</td>
                                    <td class="text-right text-base-content/50">&lt; 2 jam</td>
                                </tr>
                                <tr>
                                    <td class="font-mono font-semibold">2</td>
                                    <td>Sederhana</td>
                                    <td class="text-right text-base-content/50">2–4 jam</td>
                                </tr>
                                <tr>
                                    <td class="font-mono font-semibold">3</td>
                                    <td>Kecil</td>
                                    <td class="text-right text-base-content/50">4–8 jam</td>
                                </tr>
                                <tr>
                                    <td class="font-mono font-semibold">5</td>
                                    <td>Sedang</td>
                                    <td class="text-right text-base-content/50">1–2 hari</td>
                                </tr>
                                <tr>
                                    <td class="font-mono font-semibold">8</td>
                                    <td>Besar</td>
                                    <td class="text-right text-base-content/50">3–5 hari</td>
                                </tr>
                                <tr>
                                    <td class="font-mono font-semibold">13</td>
                                    <td>Kompleks</td>
                                    <td class="text-right text-base-content/50">1–2 minggu</td>
                                </tr>
                                <tr>
                                    <td class="font-mono font-semibold">21</td>
                                    <td>Sangat Kompleks</td>
                                    <td class="text-right text-base-content/50">&gt; 2 minggu</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function techCalculator() {
            return {
                search: '',
                filterPhase: '',
                teamSize: 1,
                spPerDev: 2,
                selectedTasks: [],
                openCats: [],
                devMode: false,

                phases: [{
                        code: 'P0',
                        name: 'Discovery',
                        progressColor: 'progress-secondary',
                        ganttColor: 'bg-secondary'
                    },
                    {
                        code: 'P1',
                        name: 'Foundation',
                        progressColor: 'progress-info',
                        ganttColor: 'bg-info'
                    },
                    {
                        code: 'P2',
                        name: 'Core Dev',
                        progressColor: 'progress-primary',
                        ganttColor: 'bg-primary'
                    },
                    {
                        code: 'P3',
                        name: 'Integration',
                        progressColor: 'progress-warning',
                        ganttColor: 'bg-warning'
                    },
                    {
                        code: 'P4',
                        name: 'Go-Live',
                        progressColor: 'progress-success',
                        ganttColor: 'bg-success'
                    },
                ],

                categories: [{
                        id: 'cat-cr',
                        code: 'CR',
                        name: 'Change Request & Maintenance',
                        badgeColor: 'bg-neutral',
                        subcats: [{
                                id: 'cr-ui',
                                name: '6.1 UI/UX Change Request',
                                tasks: [{
                                        id: 'cr-1',
                                        name: 'Perubahan layout / struktur halaman',
                                        nameNonTeknis: 'Mengubah susunan & tampilan halaman yang sudah ada',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: 'Estimasi per halaman yang berubah'
                                    },
                                    {
                                        id: 'cr-2',
                                        name: 'Reskin / perubahan tema & warna',
                                        nameNonTeknis: 'Mengubah warna, font, dan gaya visual keseluruhan aplikasi',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Lebih mudah jika sudah pakai design token'
                                    },
                                    {
                                        id: 'cr-3',
                                        name: 'Tambah / ubah komponen UI minor',
                                        nameNonTeknis: 'Menambah atau mengubah elemen kecil tampilan (tombol, label, ikon)',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-4',
                                        name: 'Redesign komponen besar (tabel, form, dll)',
                                        nameNonTeknis: 'Merombak tampilan komponen utama seperti tabel atau form besar',
                                        sp: 8,
                                        phases: ['P2', 'P3'],
                                        note: 'Pastikan tidak breaking di komponen lain'
                                    },
                                    {
                                        id: 'cr-5',
                                        name: 'Perbaikan responsivitas halaman existing',
                                        nameNonTeknis: 'Memperbaiki tampilan agar sesuai di semua ukuran layar',
                                        sp: 3,
                                        phases: ['P2', 'P3'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-6',
                                        name: 'Tambah / ubah animasi & transisi',
                                        nameNonTeknis: 'Menambah atau mengubah efek gerak pada antarmuka',
                                        sp: 2,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                ]
                            },
                            {
                                id: 'cr-schema',
                                name: '6.2 Data & Schema Change',
                                tasks: [{
                                        id: 'cr-7',
                                        name: 'Tambah kolom baru di tabel / form',
                                        nameNonTeknis: 'Menambah kolom data baru pada tabel database dan tampilan',
                                        sp: 3,
                                        phases: ['P2', 'P3'],
                                        note: 'Mencakup migrasi DB + update FE + BE'
                                    },
                                    {
                                        id: 'cr-8',
                                        name: 'Ubah tipe data / rename kolom',
                                        nameNonTeknis: 'Mengubah format atau nama kolom data yang sudah ada',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: 'Risiko tinggi di produksi — perlu rollback plan'
                                    },
                                    {
                                        id: 'cr-9',
                                        name: 'Tambah relasi / foreign key baru',
                                        nameNonTeknis: 'Menghubungkan tabel data yang sebelumnya tidak terhubung',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-10',
                                        name: 'Migrasi / transformasi data produksi',
                                        nameNonTeknis: 'Mengubah atau memindahkan data yang sudah ada di produksi',
                                        sp: 8,
                                        phases: ['P3', 'P4'],
                                        note: 'Wajib dry-run di staging sebelum prod'
                                    },
                                    {
                                        id: 'cr-11',
                                        name: 'Tambah / ubah index database',
                                        nameNonTeknis: 'Mengoptimalkan kecepatan pencarian data di database',
                                        sp: 3,
                                        phases: ['P3', 'P4'],
                                        note: 'Monitor query plan setelah perubahan'
                                    },
                                    {
                                        id: 'cr-12',
                                        name: 'Tambah enum / lookup value baru',
                                        nameNonTeknis: 'Menambah pilihan nilai baru pada dropdown atau kategori',
                                        sp: 2,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                ]
                            },
                            {
                                id: 'cr-enhance',
                                name: '6.3 Feature Enhancement',
                                tasks: [{
                                        id: 'cr-13',
                                        name: 'Tambah kolom / filter di tabel listing',
                                        nameNonTeknis: 'Menambah kolom tampil atau opsi filter di halaman daftar data',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-14',
                                        name: 'Tambah format ekspor baru (PDF, Excel)',
                                        nameNonTeknis: 'Menambah pilihan unduh laporan dalam format baru',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-15',
                                        name: 'Perubahan business logic / rule kalkulasi',
                                        nameNonTeknis: 'Mengubah aturan atau cara perhitungan pada proses bisnis',
                                        sp: 8,
                                        phases: ['P2', 'P3'],
                                        note: 'Sering ada hidden dependency — investigasi dulu'
                                    },
                                    {
                                        id: 'cr-16',
                                        name: 'Tambah role / permission baru',
                                        nameNonTeknis: 'Menambah jenis hak akses atau peran pengguna baru',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Uji seluruh alur yang terpengaruh'
                                    },
                                    {
                                        id: 'cr-17',
                                        name: 'Tambah / ubah field di form existing',
                                        nameNonTeknis: 'Menambah atau mengubah isian pada formulir yang sudah ada',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: 'Termasuk validasi & update API'
                                    },
                                    {
                                        id: 'cr-18',
                                        name: 'Integrasi endpoint baru ke sistem existing',
                                        nameNonTeknis: 'Menghubungkan layanan data baru ke aplikasi yang sudah berjalan',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-19',
                                        name: 'Tambah notifikasi baru (email / push / in-app)',
                                        nameNonTeknis: 'Menambah jenis notifikasi baru ke pengguna',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-20',
                                        name: 'Tambah halaman / modul baru ke apps existing',
                                        nameNonTeknis: 'Menambah halaman atau modul baru pada aplikasi yang sudah berjalan',
                                        sp: 8,
                                        phases: ['P2', 'P3'],
                                        note: 'Estimasi terpisah per modul'
                                    },
                                ]
                            },
                            {
                                id: 'cr-bug',
                                name: '6.4 Bug Fix & Hotfix',
                                tasks: [{
                                        id: 'cr-21',
                                        name: 'Bug minor (tampilan, typo, validasi kecil)',
                                        nameNonTeknis: 'Perbaikan masalah kecil yang tidak mengganggu fungsi utama',
                                        sp: 2,
                                        phases: ['P3', 'P4'],
                                        note: ''
                                    },
                                    {
                                        id: 'cr-22',
                                        name: 'Bug fungsional (alur rusak, kalkulasi salah)',
                                        nameNonTeknis: 'Perbaikan bug yang menyebabkan alur atau perhitungan tidak benar',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Sering lebih kompleks dari yang tampak'
                                    },
                                    {
                                        id: 'cr-23',
                                        name: 'Bug kritis / potensi data corruption',
                                        nameNonTeknis: 'Perbaikan bug yang berpotensi merusak atau menghilangkan data',
                                        sp: 8,
                                        phases: ['P3', 'P4'],
                                        note: 'Prioritas tertinggi — hentikan release lain'
                                    },
                                    {
                                        id: 'cr-24',
                                        name: 'Hotfix produksi (emergency patch)',
                                        nameNonTeknis: 'Perbaikan darurat langsung ke sistem yang sedang berjalan',
                                        sp: 3,
                                        phases: ['P4'],
                                        note: 'Sertakan rollback plan & post-mortem'
                                    },
                                    {
                                        id: 'cr-25',
                                        name: 'Investigasi & root cause analysis',
                                        nameNonTeknis: 'Mencari penyebab akar masalah sebelum melakukan perbaikan',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Jangan skip — cegah bug berulang'
                                    },
                                    {
                                        id: 'cr-26',
                                        name: 'Regression testing setelah perubahan besar',
                                        nameNonTeknis: 'Pengujian ulang menyeluruh setelah ada perubahan signifikan',
                                        sp: 3,
                                        phases: ['P3', 'P4'],
                                        note: ''
                                    },
                                ]
                            },
                            {
                                id: 'cr-maintenance',
                                name: '6.5 Maintenance & Tech Debt',
                                tasks: [{
                                        id: 'cr-27',
                                        name: 'Update dependency / library',
                                        nameNonTeknis: 'Memperbarui paket & pustaka pihak ketiga ke versi terbaru',
                                        sp: 3,
                                        phases: ['P3', 'P4'],
                                        note: 'Cek breaking change & test regresi'
                                    },
                                    {
                                        id: 'cr-28',
                                        name: 'Security patch & vulnerability fix',
                                        nameNonTeknis: 'Menambal celah keamanan yang ditemukan di sistem produksi',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Tidak bisa ditunda untuk sistem production'
                                    },
                                    {
                                        id: 'cr-29',
                                        name: 'Refactor kode lama / bayar tech debt',
                                        nameNonTeknis: 'Merapikan kode lama yang menumpuk hutang teknis',
                                        sp: 8,
                                        phases: ['P3', 'P4'],
                                        note: 'Harus ada test coverage sebelum refactor'
                                    },
                                    {
                                        id: 'cr-30',
                                        name: 'Optimasi query / endpoint yang lambat',
                                        nameNonTeknis: 'Mempercepat fitur atau halaman yang berjalan lambat di produksi',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Ukur dengan profiling sebelum & sesudah'
                                    },
                                    {
                                        id: 'cr-31',
                                        name: 'Cleanup data & purge log lama',
                                        nameNonTeknis: 'Membersihkan data & log lama yang tidak diperlukan',
                                        sp: 2,
                                        phases: ['P4'],
                                        note: 'Dry-run di staging, backup sebelum prod'
                                    },
                                    {
                                        id: 'cr-32',
                                        name: 'Update dokumentasi & API spec',
                                        nameNonTeknis: 'Memperbarui dokumentasi sesuai perubahan yang sudah dilakukan',
                                        sp: 2,
                                        phases: ['P3', 'P4'],
                                        note: ''
                                    },
                                ]
                            },
                        ]
                    },
                    {
                        id: 'cat-fe',
                        code: 'FE',
                        name: 'Frontend',
                        badgeColor: 'bg-info',
                        subcats: [{
                                id: 'fe-setup',
                                name: '1.1 Setup & Arsitektur FE',
                                tasks: [{
                                        id: 'fe-1',
                                        name: 'Inisiasi project (framework, folder structure)',
                                        nameNonTeknis: 'Persiapan awal struktur aplikasi',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Blocker untuk semua task FE'
                                    },
                                    {
                                        id: 'fe-2',
                                        name: 'Konfigurasi design system / component library',
                                        nameNonTeknis: 'Menyiapkan panduan tampilan & komponen visual',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Koordinasi dengan UI/UX designer'
                                    },
                                    {
                                        id: 'fe-3',
                                        name: 'Setup routing & state management',
                                        nameNonTeknis: 'Menyiapkan sistem navigasi antar halaman',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Pastikan pattern sudah disepakati tim'
                                    },
                                    {
                                        id: 'fe-4',
                                        name: 'Konfigurasi i18n / multi-bahasa',
                                        nameNonTeknis: 'Menyiapkan dukungan multi-bahasa pada aplikasi',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Wajib jika target pasar > 1 bahasa'
                                    },
                                    {
                                        id: 'fe-5',
                                        name: 'Setup environment variables & config management',
                                        nameNonTeknis: 'Menyiapkan konfigurasi lingkungan (dev/staging/prod)',
                                        sp: 2,
                                        phases: ['P1'],
                                        note: 'Harus sync dengan DevOps'
                                    },
                                ]
                            },
                            {
                                id: 'fe-ui',
                                name: '1.2 Komponen UI',
                                tasks: [{
                                        id: 'fe-6',
                                        name: 'Atomic components (button, input, label, dll)',
                                        nameNonTeknis: 'Membuat elemen dasar tampilan (tombol, input, label)',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Satu kali, reusable di semua halaman'
                                    },
                                    {
                                        id: 'fe-7',
                                        name: 'Layout components (navbar, sidebar, footer)',
                                        nameNonTeknis: 'Membuat kerangka tampilan utama (menu, sidebar, footer)',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Prioritaskan sebelum halaman lain'
                                    },
                                    {
                                        id: 'fe-8',
                                        name: 'Form components + validasi',
                                        nameNonTeknis: 'Membuat formulir isian beserta validasi datanya',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Sering underestimate — alokasikan lebih'
                                    },
                                    {
                                        id: 'fe-9',
                                        name: 'Data table + pagination + sorting + filter',
                                        nameNonTeknis: 'Membuat tabel data dengan fitur halaman, urutan & filter',
                                        sp: 8,
                                        phases: ['P2'],
                                        note: 'Kompleks, uji edge case data kosong'
                                    },
                                    {
                                        id: 'fe-10',
                                        name: 'Modal, drawer, toast notification',
                                        nameNonTeknis: 'Membuat jendela popup, panel geser, dan notifikasi',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'fe-11',
                                        name: 'File upload component',
                                        nameNonTeknis: 'Membuat fitur unggah berkas',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Koordinasi backend (presigned URL, dll)'
                                    },
                                    {
                                        id: 'fe-12',
                                        name: 'Chart / visualisasi data',
                                        nameNonTeknis: 'Membuat grafik & visualisasi data',
                                        sp: 8,
                                        phases: ['P2', 'P3'],
                                        note: 'Tergantung library & kompleksitas data'
                                    },
                                    {
                                        id: 'fe-13',
                                        name: 'Rich text editor',
                                        nameNonTeknis: 'Membuat editor teks kaya (seperti Word/Google Docs)',
                                        sp: 8,
                                        phases: ['P2'],
                                        note: 'Perlu tes di berbagai browser'
                                    },
                                ]
                            },
                            {
                                id: 'fe-pages',
                                name: '1.3 Halaman / View',
                                tasks: [{
                                        id: 'fe-14',
                                        name: 'Halaman login, logout, SSO',
                                        nameNonTeknis: 'Halaman masuk, keluar, dan login via akun perusahaan',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Harus selesai sebelum halaman lain'
                                    },
                                    {
                                        id: 'fe-15',
                                        name: 'Dashboard & analytics view',
                                        nameNonTeknis: 'Halaman ringkasan data & statistik utama',
                                        sp: 13,
                                        phases: ['P2'],
                                        note: 'Bergantung jumlah widget & data source'
                                    },
                                    {
                                        id: 'fe-16',
                                        name: 'CRUD halaman (per modul)',
                                        nameNonTeknis: 'Halaman kelola data: tambah, lihat, ubah, hapus (per modul)',
                                        sp: 8,
                                        phases: ['P2'],
                                        note: 'Estimasi per modul, bukan global'
                                    },
                                    {
                                        id: 'fe-17',
                                        name: 'Halaman setting & profil user',
                                        nameNonTeknis: 'Halaman pengaturan akun & profil pengguna',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'fe-18',
                                        name: 'Halaman notifikasi & aktivitas log',
                                        nameNonTeknis: 'Halaman riwayat aktivitas & notifikasi pengguna',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: 'Perlu integrasi real-time (WebSocket/SSE)'
                                    },
                                    {
                                        id: 'fe-19',
                                        name: 'Halaman laporan & ekspor',
                                        nameNonTeknis: 'Halaman cetak & unduh laporan',
                                        sp: 8,
                                        phases: ['P3'],
                                        note: 'Sering muncul di akhir proyek — antisipasi'
                                    },
                                ]
                            },
                            {
                                id: 'fe-integration',
                                name: '1.4 Integrasi & Optimasi FE',
                                tasks: [{
                                        id: 'fe-20',
                                        name: 'Integrasi REST API / GraphQL',
                                        nameNonTeknis: 'Menghubungkan tampilan dengan data dari server (per modul)',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: 'Hitung per-modul, bukan global'
                                    },
                                    {
                                        id: 'fe-21',
                                        name: 'Implementasi autentikasi (JWT/OAuth/SSO)',
                                        nameNonTeknis: 'Mengamankan akses halaman berdasarkan status login',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Termasuk token refresh & interceptor'
                                    },
                                    {
                                        id: 'fe-22',
                                        name: 'Error handling & fallback UI',
                                        nameNonTeknis: 'Menampilkan pesan & tampilan yang tepat saat terjadi error',
                                        sp: 3,
                                        phases: ['P2', 'P3'],
                                        note: 'Sering terlupakan dalam estimasi'
                                    },
                                    {
                                        id: 'fe-23',
                                        name: 'Optimasi performa (lazy load, code splitting)',
                                        nameNonTeknis: 'Mempercepat waktu muat & performa aplikasi',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Lakukan sebelum UAT'
                                    },
                                    {
                                        id: 'fe-24',
                                        name: 'Responsivitas & cross-browser testing',
                                        nameNonTeknis: 'Memastikan tampilan baik di semua ukuran layar & browser',
                                        sp: 5,
                                        phases: ['P3'],
                                        note: 'Sering underestimate — alokasikan buffer'
                                    },
                                    {
                                        id: 'fe-25',
                                        name: 'Aksesibilitas (WCAG compliance)',
                                        nameNonTeknis: 'Memastikan aplikasi dapat digunakan oleh semua pengguna termasuk difabel',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Mandatory untuk enterprise pemerintah/BUMN'
                                    },
                                ]
                            }
                        ]
                    },
                    {
                        id: 'cat-be',
                        code: 'BE',
                        name: 'Backend',
                        badgeColor: 'bg-success',
                        subcats: [{
                                id: 'be-setup',
                                name: '2.1 Setup & Arsitektur BE',
                                tasks: [{
                                        id: 'be-1',
                                        name: 'Inisiasi project & struktur direktori',
                                        nameNonTeknis: 'Persiapan awal struktur server & layanan',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Blocker semua task BE'
                                    },
                                    {
                                        id: 'be-2',
                                        name: 'Desain arsitektur (monolith / microservices)',
                                        nameNonTeknis: 'Merancang cetak biru teknis sistem secara keseluruhan',
                                        sp: 13,
                                        phases: ['P0'],
                                        note: 'SA harus finalisasi sebelum development'
                                    },
                                    {
                                        id: 'be-3',
                                        name: 'Setup database & ORM/query builder',
                                        nameNonTeknis: 'Menyiapkan sistem penyimpanan data utama',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Pilih sesuai kebutuhan skala'
                                    },
                                    {
                                        id: 'be-4',
                                        name: 'Konfigurasi middleware & request pipeline',
                                        nameNonTeknis: 'Menyiapkan lapisan keamanan & pemrosesan setiap permintaan',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Auth, logging, rate limit, dll'
                                    },
                                    {
                                        id: 'be-5',
                                        name: 'Setup logging & tracing (structured log)',
                                        nameNonTeknis: 'Menyiapkan sistem pencatatan aktivitas server',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Wajib untuk enterprise — jangan defer'
                                    },
                                    {
                                        id: 'be-6',
                                        name: 'Konfigurasi environment & secrets management',
                                        nameNonTeknis: 'Menyiapkan konfigurasi rahasia & sensitif server',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Koordinasi dengan DevOps'
                                    },
                                ]
                            },
                            {
                                id: 'be-db',
                                name: '2.2 Database & Data Model',
                                tasks: [{
                                        id: 'be-7',
                                        name: 'Desain ERD & schema awal',
                                        nameNonTeknis: 'Merancang struktur & relasi data aplikasi',
                                        sp: 8,
                                        phases: ['P0', 'P1'],
                                        note: 'SA wajib review sebelum implementasi'
                                    },
                                    {
                                        id: 'be-8',
                                        name: 'Migrasi database (awal)',
                                        nameNonTeknis: 'Membuat tabel & struktur data awal di database',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Gunakan migration tool (Flyway, Liquibase, dll)'
                                    },
                                    {
                                        id: 'be-9',
                                        name: 'Migrasi tambahan (per iterasi)',
                                        nameNonTeknis: 'Penambahan & perubahan struktur data sesuai kebutuhan baru',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: 'Track versi migrasi dengan ketat'
                                    },
                                    {
                                        id: 'be-10',
                                        name: 'Seeding data & fixtures untuk dev/staging',
                                        nameNonTeknis: 'Mengisi data awal untuk keperluan pengujian & demo',
                                        sp: 2,
                                        phases: ['P1'],
                                        note: 'Penting untuk tes dan demo'
                                    },
                                    {
                                        id: 'be-11',
                                        name: 'Indexing & optimasi query',
                                        nameNonTeknis: 'Mempercepat pencarian & pengambilan data dari database',
                                        sp: 8,
                                        phases: ['P3', 'P4'],
                                        note: 'Lakukan setelah data load testing'
                                    },
                                    {
                                        id: 'be-12',
                                        name: 'Partisi tabel / sharding (jika diperlukan)',
                                        nameNonTeknis: 'Memecah penyimpanan data untuk skala sangat besar',
                                        sp: 13,
                                        phases: ['P4'],
                                        note: 'Hanya jika data volume sangat besar'
                                    },
                                ]
                            },
                            {
                                id: 'be-api',
                                name: '2.3 API Development',
                                tasks: [{
                                        id: 'be-13',
                                        name: 'Desain kontrak API (OpenAPI/Swagger)',
                                        nameNonTeknis: 'Mendefinisikan daftar layanan & format pertukaran data antar sistem',
                                        sp: 5,
                                        phases: ['P0', 'P1'],
                                        note: 'FE dan BE harus sepakat sebelum coding'
                                    },
                                    {
                                        id: 'be-14',
                                        name: 'Implementasi CRUD endpoint (per resource)',
                                        nameNonTeknis: 'Membangun layanan data: tambah, baca, ubah, hapus (per fitur)',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Estimasi per resource, bukan keseluruhan'
                                    },
                                    {
                                        id: 'be-15',
                                        name: 'Autentikasi & autorisasi (RBAC/ABAC)',
                                        nameNonTeknis: 'Membangun sistem hak akses berdasarkan peran pengguna',
                                        sp: 8,
                                        phases: ['P2'],
                                        note: 'Kompleks — jangan underestimate'
                                    },
                                    {
                                        id: 'be-16',
                                        name: 'Pagination, filtering, sorting pada API',
                                        nameNonTeknis: 'Menambahkan fitur halaman, filter & urutan pada data layanan',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: 'Standarkan pattern di awal proyek'
                                    },
                                    {
                                        id: 'be-17',
                                        name: 'Validasi request & error response standar',
                                        nameNonTeknis: 'Memastikan data masuk valid & pesan error konsisten',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: 'Buat konvensi format error sejak awal'
                                    },
                                    {
                                        id: 'be-18',
                                        name: 'Webhook & event-driven API',
                                        nameNonTeknis: 'Membangun notifikasi otomatis antar sistem saat ada kejadian tertentu',
                                        sp: 8,
                                        phases: ['P2', 'P3'],
                                        note: 'Perlu retry logic & idempotency'
                                    },
                                    {
                                        id: 'be-19',
                                        name: 'GraphQL schema & resolver (jika digunakan)',
                                        nameNonTeknis: 'Membangun layanan data fleksibel dengan query tunggal',
                                        sp: 13,
                                        phases: ['P2'],
                                        note: 'Kompleks, alokasikan waktu lebih'
                                    },
                                    {
                                        id: 'be-20',
                                        name: 'Versioning API',
                                        nameNonTeknis: 'Menyiapkan pengelolaan versi layanan agar tidak breaking change',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Tentukan strategi di awal'
                                    },
                                ]
                            },
                            {
                                id: 'be-3rd',
                                name: '2.4 Integrasi Pihak Ketiga',
                                tasks: [{
                                        id: 'be-21',
                                        name: 'Integrasi payment gateway',
                                        nameNonTeknis: 'Menghubungkan sistem dengan layanan pembayaran online',
                                        sp: 13,
                                        phases: ['P2', 'P3'],
                                        note: 'Bergantung provider (Midtrans, Xendit, dll)'
                                    },
                                    {
                                        id: 'be-22',
                                        name: 'Integrasi email service (SendGrid, SES, dll)',
                                        nameNonTeknis: 'Menghubungkan sistem dengan layanan pengiriman email',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Sertakan templating & retry'
                                    },
                                    {
                                        id: 'be-23',
                                        name: 'Integrasi SMS / OTP gateway',
                                        nameNonTeknis: 'Menghubungkan sistem dengan layanan SMS & kode verifikasi',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Antisipasi SLA provider'
                                    },
                                    {
                                        id: 'be-24',
                                        name: 'Integrasi SSO / LDAP / Active Directory',
                                        nameNonTeknis: 'Menghubungkan login dengan akun internal perusahaan/klien',
                                        sp: 13,
                                        phases: ['P2'],
                                        note: 'Wajib koordinasi tim IT klien'
                                    },
                                    {
                                        id: 'be-25',
                                        name: 'Integrasi ERP / legacy system',
                                        nameNonTeknis: 'Menghubungkan sistem dengan aplikasi lama atau ERP yang sudah ada',
                                        sp: 21,
                                        phases: ['P2', 'P3'],
                                        note: 'Risiko tinggi — SA harus terlibat penuh'
                                    },
                                    {
                                        id: 'be-26',
                                        name: 'Integrasi storage (S3, GCS, Azure Blob)',
                                        nameNonTeknis: 'Menghubungkan sistem dengan layanan penyimpanan berkas cloud',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Sertakan presigned URL & access policy'
                                    },
                                    {
                                        id: 'be-27',
                                        name: 'Integrasi notifikasi push (FCM, APNs)',
                                        nameNonTeknis: 'Menghubungkan sistem dengan layanan notifikasi push mobile',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: ''
                                    },
                                ]
                            },
                            {
                                id: 'be-bg',
                                name: '2.5 Background Processing & Messaging',
                                tasks: [{
                                        id: 'be-28',
                                        name: 'Setup job queue (Bull, Sidekiq, Celery, dll)',
                                        nameNonTeknis: 'Menyiapkan sistem antrian tugas yang berjalan di latar belakang',
                                        sp: 5,
                                        phases: ['P1', 'P2'],
                                        note: 'Tentukan infrastruktur sebelum task ini'
                                    },
                                    {
                                        id: 'be-29',
                                        name: 'Implementasi background job (per use case)',
                                        nameNonTeknis: 'Membangun proses otomatis yang berjalan tanpa interaksi pengguna',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'be-30',
                                        name: 'Setup message broker (Kafka, RabbitMQ)',
                                        nameNonTeknis: 'Menyiapkan sistem komunikasi antar layanan secara asinkron',
                                        sp: 8,
                                        phases: ['P1', 'P2'],
                                        note: 'Hanya jika arsitektur event-driven'
                                    },
                                    {
                                        id: 'be-31',
                                        name: 'Implementasi consumer & publisher',
                                        nameNonTeknis: 'Membangun komponen pengirim & penerima pesan antar layanan',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'be-32',
                                        name: 'Dead Letter Queue & retry strategy',
                                        nameNonTeknis: 'Menangani pesan/proses yang gagal agar tidak hilang',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: 'Wajib untuk sistem kritis'
                                    },
                                    {
                                        id: 'be-33',
                                        name: 'Scheduled task / cron job',
                                        nameNonTeknis: 'Membangun tugas terjadwal otomatis (harian, mingguan, dst)',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                ]
                            }
                        ]
                    },
                    {
                        id: 'cat-devops',
                        code: 'DO',
                        name: 'DevOps & Infrastructure',
                        badgeColor: 'bg-warning',
                        subcats: [{
                                id: 'do-infra',
                                name: '3.1 Setup Infrastruktur',
                                tasks: [{
                                        id: 'do-1',
                                        name: 'Desain arsitektur infrastruktur (cloud/on-prem)',
                                        nameNonTeknis: 'Merancang infrastruktur server & jaringan sistem',
                                        sp: 13,
                                        phases: ['P0'],
                                        note: 'SA dan DevOps harus kolaborasi'
                                    },
                                    {
                                        id: 'do-2',
                                        name: 'Provisioning server / cloud resources (IaC)',
                                        nameNonTeknis: 'Menyiapkan server & sumber daya cloud secara otomatis',
                                        sp: 8,
                                        phases: ['P1'],
                                        note: 'Gunakan Terraform/Pulumi'
                                    },
                                    {
                                        id: 'do-3',
                                        name: 'Konfigurasi networking (VPC, subnet, firewall)',
                                        nameNonTeknis: 'Mengatur jaringan & keamanan koneksi antar layanan',
                                        sp: 8,
                                        phases: ['P1'],
                                        note: 'Perlu approval tim security klien'
                                    },
                                    {
                                        id: 'do-4',
                                        name: 'Setup DNS & SSL/TLS',
                                        nameNonTeknis: 'Menyiapkan alamat domain & sertifikat keamanan (HTTPS)',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: ''
                                    },
                                    {
                                        id: 'do-5',
                                        name: 'Setup object storage & CDN',
                                        nameNonTeknis: 'Menyiapkan penyimpanan berkas & percepatan distribusi konten',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: ''
                                    },
                                    {
                                        id: 'do-6',
                                        name: 'Setup database server & backup schedule',
                                        nameNonTeknis: 'Menyiapkan server database beserta jadwal backup otomatis',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Sertakan disaster recovery plan'
                                    },
                                ]
                            },
                            {
                                id: 'do-cicd',
                                name: '3.2 CI/CD Pipeline',
                                tasks: [{
                                        id: 'do-7',
                                        name: 'Setup source control strategy (branching)',
                                        nameNonTeknis: 'Menetapkan alur kerja pengelolaan kode tim',
                                        sp: 2,
                                        phases: ['P1'],
                                        note: 'GitFlow / trunk-based — tentukan di P0'
                                    },
                                    {
                                        id: 'do-8',
                                        name: 'Setup CI pipeline (lint, test, build)',
                                        nameNonTeknis: 'Menyiapkan proses otomatis pemeriksaan & build kode',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Blocker untuk semua iterasi development'
                                    },
                                    {
                                        id: 'do-9',
                                        name: 'Setup CD ke environment staging',
                                        nameNonTeknis: 'Menyiapkan deploy otomatis ke lingkungan staging/uji',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Harus berjalan sebelum P2'
                                    },
                                    {
                                        id: 'do-10',
                                        name: 'Setup CD ke environment production',
                                        nameNonTeknis: 'Menyiapkan deploy otomatis ke lingkungan production',
                                        sp: 5,
                                        phases: ['P4'],
                                        note: 'Gunakan blue-green atau canary deployment'
                                    },
                                    {
                                        id: 'do-11',
                                        name: 'Setup automated testing dalam pipeline',
                                        nameNonTeknis: 'Mengintegrasikan pengujian otomatis dalam proses rilis',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: 'Integrasi dengan unit & integration test'
                                    },
                                    {
                                        id: 'do-12',
                                        name: 'Setup notifikasi pipeline (Slack, email)',
                                        nameNonTeknis: 'Menyiapkan notifikasi hasil build & deploy ke tim',
                                        sp: 2,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                ]
                            },
                            {
                                id: 'do-container',
                                name: '3.3 Containerisasi & Orkestrasi',
                                tasks: [{
                                        id: 'do-13',
                                        name: 'Dockerisasi semua service',
                                        nameNonTeknis: 'Mengemas seluruh layanan dalam container portabel',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Sertakan multi-stage build'
                                    },
                                    {
                                        id: 'do-14',
                                        name: 'Setup docker-compose untuk local dev',
                                        nameNonTeknis: 'Menyiapkan lingkungan lokal yang seragam untuk seluruh tim developer',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Meningkatkan konsistensi dev environment'
                                    },
                                    {
                                        id: 'do-15',
                                        name: 'Setup Kubernetes cluster',
                                        nameNonTeknis: 'Menyiapkan sistem orkestrasi container untuk skala besar',
                                        sp: 13,
                                        phases: ['P1'],
                                        note: 'Hanya jika skala besar — diskusikan di P0'
                                    },
                                    {
                                        id: 'do-16',
                                        name: 'Konfigurasi Helm chart per service',
                                        nameNonTeknis: 'Menyiapkan paket konfigurasi deploy per layanan',
                                        sp: 5,
                                        phases: ['P1', 'P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'do-17',
                                        name: 'Setup HPA (auto-scaling)',
                                        nameNonTeknis: 'Menyiapkan sistem scaling otomatis saat beban meningkat',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Setelah load testing'
                                    },
                                    {
                                        id: 'do-18',
                                        name: 'Konfigurasi resource limits & requests',
                                        nameNonTeknis: 'Mengatur batas penggunaan CPU & memori per layanan',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: 'Wajib sebelum production'
                                    },
                                ]
                            },
                            {
                                id: 'do-monitor',
                                name: '3.4 Monitoring & Observability',
                                tasks: [{
                                        id: 'do-19',
                                        name: 'Setup log aggregation (ELK, Loki, dll)',
                                        nameNonTeknis: 'Menyiapkan sistem pengumpulan & pencarian log terpusat',
                                        sp: 8,
                                        phases: ['P1', 'P2'],
                                        note: 'Jangan defer ke P4'
                                    },
                                    {
                                        id: 'do-20',
                                        name: 'Setup metrics & dashboard (Prometheus + Grafana)',
                                        nameNonTeknis: 'Menyiapkan dasbor pemantauan kesehatan & performa sistem',
                                        sp: 8,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'do-21',
                                        name: 'Setup alerting & on-call (PagerDuty, dll)',
                                        nameNonTeknis: 'Menyiapkan sistem peringatan otomatis saat terjadi gangguan',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Diskusikan SLA alerting dengan klien'
                                    },
                                    {
                                        id: 'do-22',
                                        name: 'Setup distributed tracing (Jaeger, Tempo)',
                                        nameNonTeknis: 'Menyiapkan sistem pelacakan alur permintaan antar layanan',
                                        sp: 5,
                                        phases: ['P2', 'P3'],
                                        note: 'Penting untuk microservices'
                                    },
                                    {
                                        id: 'do-23',
                                        name: 'Setup uptime monitoring',
                                        nameNonTeknis: 'Menyiapkan pemantauan ketersediaan sistem 24/7',
                                        sp: 2,
                                        phases: ['P4'],
                                        note: ''
                                    },
                                    {
                                        id: 'do-24',
                                        name: 'Konfigurasi APM (Datadog, New Relic)',
                                        nameNonTeknis: 'Menyiapkan pemantauan performa aplikasi secara mendetail',
                                        sp: 5,
                                        phases: ['P2', 'P4'],
                                        note: 'Bergantung budget klien'
                                    },
                                ]
                            },
                            {
                                id: 'do-security',
                                name: '3.5 Security & Compliance',
                                tasks: [{
                                        id: 'do-25',
                                        name: 'Setup secret management (Vault, AWS Secrets)',
                                        nameNonTeknis: 'Menyiapkan sistem penyimpanan data rahasia yang aman',
                                        sp: 5,
                                        phases: ['P1'],
                                        note: 'Jangan hardcode secret — enforce dari awal'
                                    },
                                    {
                                        id: 'do-26',
                                        name: 'Konfigurasi WAF & DDoS protection',
                                        nameNonTeknis: 'Memasang proteksi terhadap serangan siber dari luar',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: ''
                                    },
                                    {
                                        id: 'do-27',
                                        name: 'Vulnerability scanning (SAST/DAST)',
                                        nameNonTeknis: 'Memindai celah keamanan pada kode & aplikasi',
                                        sp: 5,
                                        phases: ['P3'],
                                        note: 'Integrasikan dalam pipeline CI'
                                    },
                                    {
                                        id: 'do-28',
                                        name: 'Dependency audit & patch management',
                                        nameNonTeknis: 'Memeriksa & memperbarui library/paket yang rentan',
                                        sp: 3,
                                        phases: ['P3', 'P4'],
                                        note: ''
                                    },
                                    {
                                        id: 'do-29',
                                        name: 'Penetration testing (koordinasi pihak ketiga)',
                                        nameNonTeknis: 'Uji keamanan sistem oleh tim eksternal (ethical hacking)',
                                        sp: 13,
                                        phases: ['P4'],
                                        note: 'Alokasikan waktu untuk remediation'
                                    },
                                    {
                                        id: 'do-30',
                                        name: 'Setup audit log & compliance report',
                                        nameNonTeknis: 'Menyiapkan pencatatan audit & laporan kepatuhan regulasi',
                                        sp: 5,
                                        phases: ['P3', 'P4'],
                                        note: 'Mandatory untuk BUMN, keuangan, kesehatan'
                                    },
                                ]
                            }
                        ]
                    },
                    {
                        id: 'cat-qa',
                        code: 'QA',
                        name: 'Quality Assurance',
                        badgeColor: 'bg-secondary',
                        subcats: [{
                                id: 'qa-plan',
                                name: '4.1 Perencanaan & Setup Testing',
                                tasks: [{
                                        id: 'qa-1',
                                        name: 'Membuat test plan & test strategy',
                                        nameNonTeknis: 'Menyusun rencana & strategi pengujian keseluruhan proyek',
                                        sp: 5,
                                        phases: ['P0', 'P1'],
                                        note: 'SA dan PM harus review'
                                    },
                                    {
                                        id: 'qa-2',
                                        name: 'Setup testing framework (unit, integration)',
                                        nameNonTeknis: 'Menyiapkan alat & kerangka kerja pengujian otomatis',
                                        sp: 3,
                                        phases: ['P1'],
                                        note: 'Pastikan coverage threshold ditetapkan'
                                    },
                                    {
                                        id: 'qa-3',
                                        name: 'Setup E2E testing (Cypress, Playwright, dll)',
                                        nameNonTeknis: 'Menyiapkan pengujian otomatis alur pengguna dari ujung ke ujung',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: ''
                                    },
                                    {
                                        id: 'qa-4',
                                        name: 'Setup load & performance testing (k6, Locust)',
                                        nameNonTeknis: 'Menyiapkan alat uji beban & performa sistem',
                                        sp: 5,
                                        phases: ['P3'],
                                        note: ''
                                    },
                                ]
                            },
                            {
                                id: 'qa-exec',
                                name: '4.2 Eksekusi Testing',
                                tasks: [{
                                        id: 'qa-5',
                                        name: 'Unit testing (per modul)',
                                        nameNonTeknis: 'Pengujian fungsi-fungsi kecil per bagian kode (per modul)',
                                        sp: 5,
                                        phases: ['P2'],
                                        note: 'Target coverage > 70%'
                                    },
                                    {
                                        id: 'qa-6',
                                        name: 'Integration testing',
                                        nameNonTeknis: 'Pengujian integrasi antar komponen & layanan sistem',
                                        sp: 8,
                                        phases: ['P2', 'P3'],
                                        note: ''
                                    },
                                    {
                                        id: 'qa-7',
                                        name: 'E2E testing (per user journey)',
                                        nameNonTeknis: 'Pengujian alur pengguna dari awal hingga akhir (per skenario)',
                                        sp: 5,
                                        phases: ['P3'],
                                        note: ''
                                    },
                                    {
                                        id: 'qa-8',
                                        name: 'Performance & load testing',
                                        nameNonTeknis: 'Pengujian kemampuan sistem saat diakses banyak pengguna sekaligus',
                                        sp: 8,
                                        phases: ['P3'],
                                        note: 'Sertakan laporan hasil dan rekomendasi'
                                    },
                                    {
                                        id: 'qa-9',
                                        name: 'Security testing (OWASP checklist)',
                                        nameNonTeknis: 'Pengujian keamanan berdasarkan standar kerentanan umum',
                                        sp: 8,
                                        phases: ['P3', 'P4'],
                                        note: ''
                                    },
                                    {
                                        id: 'qa-10',
                                        name: 'UAT (User Acceptance Testing)',
                                        nameNonTeknis: 'Pengujian penerimaan oleh pengguna/klien sebelum go-live',
                                        sp: 13,
                                        phases: ['P3'],
                                        note: 'Alokasikan waktu untuk revisi dari klien'
                                    },
                                    {
                                        id: 'qa-11',
                                        name: 'Regression testing sebelum release',
                                        nameNonTeknis: 'Pengujian ulang menyeluruh sebelum peluncuran resmi',
                                        sp: 5,
                                        phases: ['P4'],
                                        note: ''
                                    },
                                ]
                            }
                        ]
                    },
                    {
                        id: 'cat-cross',
                        code: 'CC',
                        name: 'Cross-Cutting',
                        badgeColor: 'bg-error',
                        subcats: [{
                                id: 'cc-docs',
                                name: '5.1 Dokumentasi',
                                tasks: [{
                                        id: 'cc-1',
                                        name: 'Dokumentasi arsitektur (ADR)',
                                        nameNonTeknis: 'Mendokumentasikan keputusan arsitektur & alasan teknis',
                                        sp: 5,
                                        phases: ['P0', 'P1'],
                                        note: 'SA bertanggung jawab'
                                    },
                                    {
                                        id: 'cc-2',
                                        name: 'Dokumentasi API (Swagger/Postman Collection)',
                                        nameNonTeknis: 'Mendokumentasikan seluruh layanan & endpoint yang tersedia',
                                        sp: 3,
                                        phases: ['P2'],
                                        note: 'Update setiap sprint'
                                    },
                                    {
                                        id: 'cc-3',
                                        name: 'Runbook & playbook operasional',
                                        nameNonTeknis: 'Menyusun panduan operasional & penanganan insiden sistem',
                                        sp: 5,
                                        phases: ['P4'],
                                        note: 'Sebelum go-live'
                                    },
                                    {
                                        id: 'cc-4',
                                        name: 'Panduan onboarding developer',
                                        nameNonTeknis: 'Menyusun panduan untuk developer baru bergabung ke proyek',
                                        sp: 3,
                                        phases: ['P1', 'P2'],
                                        note: 'Percepat onboarding anggota baru'
                                    },
                                    {
                                        id: 'cc-5',
                                        name: 'Release notes & changelog',
                                        nameNonTeknis: 'Mencatat daftar perubahan & fitur baru setiap rilis',
                                        sp: 2,
                                        phases: ['P4'],
                                        note: ''
                                    },
                                ]
                            },
                            {
                                id: 'cc-mgmt',
                                name: '5.2 Manajemen & Koordinasi',
                                tasks: [{
                                        id: 'cc-6',
                                        name: 'Sprint planning & refinement',
                                        nameNonTeknis: 'Perencanaan & penyesuaian pekerjaan setiap sprint',
                                        sp: 2,
                                        phases: ['P0', 'P1', 'P2', 'P3', 'P4'],
                                        note: 'PM memimpin, SA memvalidasi estimasi'
                                    },
                                    {
                                        id: 'cc-7',
                                        name: 'Technical grooming & spike research',
                                        nameNonTeknis: 'Analisis & penelitian teknis untuk mengurangi ketidakpastian',
                                        sp: 8,
                                        phases: ['P0', 'P1', 'P2'],
                                        note: 'Jangan abaikan — mengurangi risiko'
                                    },
                                    {
                                        id: 'cc-8',
                                        name: 'Code review & pull request process',
                                        nameNonTeknis: 'Proses pemeriksaan kode oleh sesama developer',
                                        sp: 2,
                                        phases: ['P0', 'P1', 'P2', 'P3', 'P4'],
                                        note: 'Alokasikan 10–15% kapasitas tim'
                                    },
                                    {
                                        id: 'cc-9',
                                        name: 'Bug triage & prioritization',
                                        nameNonTeknis: 'Penyortiran & penentuan prioritas perbaikan bug',
                                        sp: 2,
                                        phases: ['P3', 'P4'],
                                        note: 'PM dan QA memimpin sesi ini'
                                    },
                                    {
                                        id: 'cc-10',
                                        name: 'Koordinasi dengan tim klien',
                                        nameNonTeknis: 'Pertemuan & komunikasi rutin dengan tim klien',
                                        sp: 3,
                                        phases: ['P0', 'P1', 'P2', 'P3', 'P4'],
                                        note: 'PM bertanggung jawab penuh'
                                    },
                                    {
                                        id: 'cc-11',
                                        name: 'Demo & sprint review',
                                        nameNonTeknis: 'Presentasi hasil pekerjaan kepada klien setiap akhir sprint',
                                        sp: 2,
                                        phases: ['P0', 'P1', 'P2', 'P3', 'P4'],
                                        note: ''
                                    },
                                ]
                            }
                        ]
                    }
                ],

                init() {},

                taskDisplayName(task) {
                    return this.devMode ? task.name : task.nameNonTeknis;
                },

                allTasks() {
                    return this.categories.flatMap(c => c.subcats.flatMap(s => s.tasks));
                },
                getTask(id) {
                    return this.allTasks().find(t => t.id === id);
                },
                getTaskName(id) {
                    const t = this.getTask(id);
                    if (!t) return '';
                    return this.devMode ? t.name : t.nameNonTeknis;
                },
                getTaskSP(id) {
                    return this.getTask(id)?.sp ?? 0;
                },

                filteredCategories() {
                    return this.categories.map(cat => ({
                        ...cat,
                        subcats: cat.subcats.map(sub => ({
                            ...sub,
                            tasks: this.filteredTasks(sub.tasks)
                        })).filter(sub => sub.tasks.length > 0)
                    })).filter(cat => cat.subcats.length > 0);
                },

                filteredTasks(tasks) {
                    return tasks.filter(t => {
                        const displayName = this.devMode ? t.name : t.nameNonTeknis;
                        const matchSearch = !this.search || displayName.toLowerCase().includes(this.search
                            .toLowerCase());
                        const matchPhase = !this.filterPhase || t.phases.some(p => p.startsWith(this.filterPhase));
                        return matchSearch && matchPhase;
                    });
                },

                toggleCat(catId) {
                    if (this.openCats.includes(catId)) {
                        this.openCats = this.openCats.filter(c => c !== catId);
                    } else {
                        this.openCats.push(catId);
                    }
                },

                isSubcatChecked(sub) {
                    return sub.tasks.length > 0 && sub.tasks.every(t => this.selectedTasks.includes(t.id));
                },
                isSubcatIndeterminate(sub) {
                    const cnt = sub.tasks.filter(t => this.selectedTasks.includes(t.id)).length;
                    return cnt > 0 && cnt < sub.tasks.length;
                },
                toggleSubcat(sub, checked) {
                    if (checked) {
                        sub.tasks.forEach(t => {
                            if (!this.selectedTasks.includes(t.id)) this.selectedTasks.push(t.id);
                        });
                    } else {
                        this.selectedTasks = this.selectedTasks.filter(id => !sub.tasks.map(t => t.id).includes(id));
                    }
                },

                getCatSelectedCount(catId) {
                    const cat = this.categories.find(c => c.id === catId);
                    if (!cat) return 0;
                    return cat.subcats.flatMap(s => s.tasks).filter(t => this.selectedTasks.includes(t.id)).length;
                },
                getCatSP(catId) {
                    const cat = this.categories.find(c => c.id === catId);
                    if (!cat) return 0;
                    return cat.subcats.flatMap(s => s.tasks).filter(t => this.selectedTasks.includes(t.id)).reduce((a, t) =>
                        a + t.sp, 0);
                },
                totalSP() {
                    return this.allTasks().filter(t => this.selectedTasks.includes(t.id)).reduce((a, t) => a + t.sp, 0);
                },
                getPhaseSP(phCode) {
                    return this.allTasks().filter(t => this.selectedTasks.includes(t.id) && t.phases.some(p => p ===
                        phCode)).reduce((a, t) => a + t.sp, 0);
                },
                estimateDays() {
                    const sp = this.totalSP();
                    if (sp === 0) return 0;
                    return Math.ceil(sp / (this.teamSize * this.spPerDev));
                },
                estimateWeeks() {
                    const days = this.estimateDays();
                    if (days === 0) return '—';
                    const weeks = Math.ceil(days / 5);
                    const months = (days / 22).toFixed(1);
                    return `≈ ${weeks} minggu (${months} bln)`;
                },
                ganttWeeks(phCode) {
                    const sp = this.getPhaseSP(phCode);
                    if (sp === 0) return 0;
                    return Math.max(1, Math.ceil(sp / (this.teamSize * this.spPerDev * 5)));
                },
                totalCalendarWeeks() {
                    return this.phases.reduce((a, ph) => a + this.ganttWeeks(ph.code), 0);
                },
                complexityLabel() {
                    const sp = this.totalSP();
                    if (sp === 0) return '—';
                    if (sp < 50) return 'Kecil';
                    if (sp < 150) return 'Sedang';
                    if (sp < 300) return 'Besar';
                    return 'Enterprise';
                },
                spHours(sp) {
                    const map = {
                        1: '< 2j',
                        2: '2–4j',
                        3: '4–8j',
                        5: '1–2h',
                        8: '3–5h',
                        13: '1–2mg',
                        21: '> 2mg'
                    };
                    return map[sp] ?? '';
                },
                phaseBadge(ph) {
                    const map = {
                        P0: 'badge-secondary',
                        P1: 'badge-info',
                        P2: 'badge-primary',
                        P3: 'badge-warning',
                        P4: 'badge-success',
                    };
                    return map[ph] ?? 'badge-ghost';
                },
                resetAll() {
                    this.selectedTasks = [];
                    this.search = '';
                    this.filterPhase = '';
                }
            }
        }
    </script>
</x-layouts::app.sidebar>
