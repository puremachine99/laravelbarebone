<div class="p-6 lg:p-8 space-y-6">
 <flux:card class="p-6 space-y-4 bg-base-100/50 border-base-300">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <flux:icon name="banknotes" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <flux:heading level="2" class="text-xl font-semibold">Modul E: Finance - Rekonsiliasi, Pajak & Alur Disposisi Dokumen S3</flux:heading>
 <flux:text class="text-sm text-base-content/50">Audit Finansial, Perhitungan Nilai Bersih, dan Alur Pengajuan Revisi Berkas</flux:text>
 </div>
 </div>
 
 <flux:separator />

 <!-- Goal Section -->
 <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 space-y-2">
 <div class="flex items-center gap-2 text-indigo-800 dark:text-indigo-300 font-semibold text-base">
 <flux:icon name="flag" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
 <span>Upcoming Feature (Sistem Keuangan & Arsip Ketat)</span>
 </div>
 <p class="text-sm text-indigo-700 dark:text-indigo-300 leading-relaxed font-medium">
 Mesin penghitung uang perusahaan yang menghitung nilai kotor proyek, memotong pajak otomatis, alokasi gaji vendor/HPP secara real-time, serta mengamankan alur revisi dokumen disputed.
 </p>
 </div>

 <div class="space-y-4 text-base-content/70">
 <p class="leading-relaxed">
 Modul Rekonsiliasi Keuangan bertanggung jawab untuk mengaudit rincian nilai transaksi, mengurangkan komponen pajak, memetakan beban HPP (Cost of Goods Sold/COGS), serta memproses persetujuan termin pembayaran. Modul ini juga menangani siklus perbaikan berkas (disputed workflow) apabila dokumen transaksi dinilai salah atau tidak lengkap.
 </p>

 <flux:heading level="3" class="text-lg font-medium text-base-content/90 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Locked Sales Data (Read-Only)</flux:heading>
 <flux:text class="text-sm">Data nilai nominal kotor (gross value) dari modul Sales dikunci sepenuhnya untuk mencegah manipulasi angka transaksi utama selama proses audit keuangan.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Pajak & HPP Repeater</flux:heading>
 <flux:text class="text-sm">Form dinamis untuk memasukkan berbagai potongan pajak (e.g. PPh, PPN) beserta rincian pengeluaran vendor pihak ketiga (HPP) untuk proyek berjalan.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Kalkulasi Netto Reaktif</flux:heading>
 <flux:text class="text-sm">Sistem menghitung secara instan estimasi Internal Net Value menggunakan lifecycle hooks Livewire begitu nilai potongan pajak atau pengeluaran vendor diubah.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Alur Disputed & Re-Submit Dokumen</flux:heading>
 <flux:text class="text-sm">Tombol penolakan yang meminta pengisian alasan (rejection reason), memicu notifikasi database kepada pengunggah asal, serta memfasilitasi unggah ulang (re-submit) berkas.</flux:text>
 </flux:card>
 </div>
 </div>
 </flux:card>
</div>
