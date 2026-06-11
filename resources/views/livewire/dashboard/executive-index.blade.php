<div class="p-6 lg:p-8 space-y-6">
    <flux:card class="p-6 space-y-4 bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
                <flux:icon name="presentation-chart-bar" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <flux:heading level="2" class="text-xl font-semibold">Modul A: Executive BI Dashboard Overview</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Pusat Informasi Eksekutif & Pemantauan Kinerja Bisnis</flux:text>
            </div>
        </div>
        
        <flux:separator />

        <div class="space-y-4 text-zinc-600 dark:text-zinc-400">
            <p class="leading-relaxed">
                Executive BI Dashboard dirancang khusus untuk memberikan pandangan helikopter (executive summary) secara komprehensif kepada jajaran direksi PT Ide Bangsa Mahardika (SmartID). Halaman ini menjadi gerbang utama dalam memantau kesehatan finansial serta efisiensi operasional seluruh divisi perusahaan secara real-time.
            </p>

            <flux:heading level="3" class="text-lg font-medium text-zinc-800 dark:text-zinc-200 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">4-Column KPI Metrics Grid</flux:heading>
                    <flux:text class="text-sm">Menampilkan data Revenue YTD (Target vs Realisasi), Company KPI Average, OPEX/Revenue Efficiency Ratio, serta Spotlight jumlah karyawan berkinerja terbaik dengan pencapaian KPI > 90%.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Visualisasi & Grafik Dinamis</flux:heading>
                    <flux:text class="text-sm">Dilengkapi grafik komparasi pencapaian target per departemen serta visualisasi donut chart untuk membandingkan Budget Burn Rate dengan Revenue aktual secara interaktif.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Aktivitas Krusial Terbaru</flux:heading>
                    <flux:text class="text-sm">Tabel pemantauan status karyawan dengan performa berstatus stalled atau in-progress berprioritas tinggi agar pimpinan dapat mengambil tindakan korektif dengan cepat.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Data Caching Berlapis</flux:heading>
                    <flux:text class="text-sm">Optimasi query berat menggunakan mekanisme Redis Caching terintegrasi untuk menjamin performa rendering visualisasi tetap responsif tanpa membebani database utama.</flux:text>
                </flux:card>
            </div>
        </div>
    </flux:card>
</div>
