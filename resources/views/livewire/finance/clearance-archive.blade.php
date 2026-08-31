<div class="p-6 lg:p-8 space-y-6">
 <div class="p-6 space-y-4 bg-base-100/50 border-base-300 rounded-2xl border">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <x-icon name="o-banknotes" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <h2 class="text-xl font-semibold">Modul G: Finance - Financial Clearance Archive</h2>
 <p class="text-sm text-base-content/50">Penyimpanan & Pengarsipan Data Finansial Permanen yang Terkunci</p>
 </div>
 </div>
 
 <hr class="border-base-300">

 <!-- Goal Section -->
 <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 space-y-2">
 <div class="flex items-center gap-2 text-indigo-800 dark:text-indigo-300 font-semibold text-base">
 <x-icon name="o-flag" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
 <span>Upcoming Feature (Sistem Keuangan & Arsip Ketat)</span>
 </div>
 <p class="text-sm text-indigo-700 dark:text-indigo-300 leading-relaxed font-medium">
 Penyimpanan arsip permanen untuk proyek selesai dengan pengamanan ketat (gembok dokumen terverifikasi) agar tidak bisa dihapus atau diubah lagi.
 </p>
 </div>

 <div class="space-y-4 text-base-content/70">
 <p class="leading-relaxed">
 Financial Clearance Archive merupakan tempat penyimpanan akhir bagi seluruh proyek yang telah rampung dan melalui proses audit keuangan. Halaman ini dirancang untuk menjaga integritas data transaksi historis agar aman dari segala bentuk perubahan ilegal, tidak sengaja, atau penghapusan berkas.
 </p>

 <h3 class="text-lg font-medium text-base-content/90 mt-6">Fitur Utama yang Akan Hadir:</h3>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Locked Read-Only State</h4>
 <p class="text-sm">Menegakkan otorisasi level server secara ketat melalui Laravel Policies yang mengembalikan nilai false untuk seluruh aksi penulisan, pengubahan, atau penghapusan data.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Tamper-Proof File Indicator</h4>
 <p class="text-sm">Tampilan ikon gembok khusus di samping setiap berkas bukti pembayaran di S3 untuk menandakan bahwa file tersebut telah dikunci dan tidak bisa ditimpa.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Bucket Versioning & Pre-signed URLs</h4>
 <p class="text-sm">Memanfaatkan fitur bucket versioning Amazon S3 guna mencegah penghapusan fisik, serta menghasilkan pre-signed download link yang kedaluwarsa setelah 15 menit.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Metadata Description List</h4>
 <p class="text-sm">Mengganti semua elemen input formulir dengan tampilan list deskripsi (&lt;flux:description-list&gt;) yang bersih untuk menyajikan detail audit historis proyek.</p>
 </div>
 </div>
 </div>
 </div>
</div>
