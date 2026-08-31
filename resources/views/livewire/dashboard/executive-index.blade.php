<div class="p-6 lg:p-8 space-y-6">
 <div class="p-6 space-y-4 bg-base-100/50 border-base-300 rounded-2xl border">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <x-icon name="o-presentation-chart-bar" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <h2 class="text-xl font-semibold">Modul A: Executive BI Dashboard Overview</h2>
 <p class="text-sm text-base-content/50">Pusat Informasi Eksekutif & Pemantauan Kinerja Bisnis</p>
 </div>
 </div>
 
 <hr class="border-base-300">

 <!-- Goal Section -->
 <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 space-y-2">
 <div class="flex items-center gap-2 text-indigo-800 dark:text-indigo-300 font-semibold text-base">
 <x-icon name="o-flag" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
 <span>Upcoming Feature (Sistem HR & Dasbor Eksekutif)</span>
 </div>
 <p class="text-sm text-indigo-700 dark:text-indigo-300 leading-relaxed font-medium">
 Layar pantauan untuk bos/direksi melihat uang perusahaan, menilai target kerja (OKR/KPI) karyawan, dan memantau kesehatan bisnis secara keseluruhan.
 </p>
 </div>

 <!-- ETL Trigger Section -->
 <div class="p-4 rounded-xl bg-base-200/50 border border-base-300 space-y-3">
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
 <div class="space-y-1">
 <div class="flex items-center gap-2 text-base-content/90 font-semibold text-sm">
 <x-icon name="o-arrow-path" class="w-4 h-4 text-base-content/50" />
 <span>Sinkronisasi ETL Kontak Partnership</span>
 </div>
 <p class="text-xs block">Sengat (pull) data kontak dari database MySQL Partnership App luar, lalu lakukan sanitasi nomor HP, pemetaan wilayah, dan jabatan secara otomatis.</p>
 </div>
 <div>
 <button type="button" wire:click="syncContacts" wire:loading.attr="disabled" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs">
 <span wire:loading.remove wire:target="syncContacts">Mulai Sinkronisasi</span>
 <span wire:loading wire:target="syncContacts" class="flex items-center gap-2">
 <x-icon name="o-arrow-path" class="w-3.5 h-3.5 animate-spin" />
 Memproses ETL...
 </span>
 </button>
 </div>
 </div>

 @if (session()->has('sync_success'))
 <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-300 text-xs font-medium">
 {{ session('sync_success') }}
 </div>
 @endif

 @if (session()->has('sync_error'))
 <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/50 text-rose-800 dark:text-rose-300 text-xs font-medium">
 {{ session('sync_error') }}
 </div>
 @endif
 </div>

 <div class="space-y-4 text-base-content/70">
 <p class="leading-relaxed">
 Executive BI Dashboard dirancang khusus untuk memberikan pandangan helikopter (executive summary) secara komprehensif kepada jajaran direksi PT Ide Bangsa Mahardika (SmartID). Halaman ini menjadi gerbang utama dalam memantau kesehatan finansial serta efisiensi operasional seluruh divisi perusahaan secara real-time.
 </p>

 <h3 class="text-lg font-medium text-base-content/90 mt-6">Fitur Utama yang Akan Hadir:</h3>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">4-Column KPI Metrics Grid</h4>
 <p class="text-sm">Menampilkan data Revenue YTD (Target vs Realisasi), Company KPI Average, OPEX/Revenue Efficiency Ratio, serta Spotlight jumlah karyawan berkinerja terbaik dengan pencapaian KPI > 90%.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Visualisasi & Grafik Dinamis</h4>
 <p class="text-sm">Dilengkapi grafik komparasi pencapaian target per departemen serta visualisasi donut chart untuk membandingkan Budget Burn Rate dengan Revenue aktual secara interaktif.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Aktivitas Krusial Terbaru</h4>
 <p class="text-sm">Tabel pemantauan status karyawan dengan performa berstatus stalled atau in-progress berprioritas tinggi agar pimpinan dapat mengambil tindakan korektif dengan cepat.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Data Caching Berlapis</h4>
 <p class="text-sm">Optimasi query berat menggunakan mekanisme Redis Caching terintegrasi untuk menjamin performa rendering visualisasi tetap responsif tanpa membebani database utama.</p>
 </div>
 </div>
 </div>
 </div>
</div>
