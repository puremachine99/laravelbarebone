<div class="p-6 lg:p-8 space-y-6">
 <flux:card class="p-6 space-y-4 bg-base-100/50 border-base-300">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <flux:icon name="banknotes" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <flux:heading level="2" class="text-xl font-semibold">Modul D: Finance - Incoming Ticket Queue (Intake)</flux:heading>
 <flux:text class="text-sm text-base-content/50">Gerbang Antrean & Validasi Awal Tiket Transaksi Finansial</flux:text>
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
 Bagian dari sistem keuangan untuk mengelola antrean masuk tiket transaksi yang diajukan oleh divisi Sales/Ops guna diverifikasi sebelum dihitung secara detail oleh sistem.
 </p>
 </div>

 <div class="space-y-4 text-base-content/70">
 <p class="leading-relaxed">
 Halaman Finance Intake Queue bertindak sebagai kotak masuk terpusat untuk semua data transaksi yang diajukan oleh departemen komersial (Sales/Ops). Setiap pengajuan tiket akan dikarantina di sini terlebih dahulu untuk diverifikasi kelayakannya oleh tim audit keuangan sebelum masuk ke proses rekonsiliasi.
 </p>

 <flux:heading level="3" class="text-lg font-medium text-base-content/90 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Daftar Antrean Tiket Finansial</flux:heading>
 <flux:text class="text-sm">Menyajikan data antrean tiket dengan format ID standar #FIN-YYYY-XXX, dilengkapi informasi nama klien, modul asal pengirim, nominal bruto, dan tanggal pengajuan.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Dinamis Navigasi Counter Badge</flux:heading>
 <flux:text class="text-sm">Menampilkan badge penunjuk jumlah tiket aktif yang membutuhkan tindakan verifikasi (e.g."[4] Butuh Verifikasi") yang terupdate secara real-time via Laravel Reverb/Echo.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Aksi Audit Slide-Over (SPA Feel)</flux:heading>
 <flux:text class="text-sm">Membuka lembar detail tiket audit langsung dalam bentuk slide-over drawer (laci) tanpa memicu reload halaman penuh untuk meminimalisir delay navigasi.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Status Karantina Transaksi</flux:heading>
 <flux:text class="text-sm">Pengkategorian status tiket yang transparan dari: Pending Audit (dalam antrean), Verified (disetujui untuk rekonsiliasi), atau Disputed (ditolak untuk direvisi).</flux:text>
 </flux:card>
 </div>
 </div>
 </flux:card>
</div>
