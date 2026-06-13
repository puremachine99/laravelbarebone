<div class="p-6 lg:p-8 space-y-6">
 <flux:card class="p-6 space-y-4 bg-base-100/50 border-base-300">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <flux:icon name="banknotes" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <flux:heading level="2" class="text-xl font-semibold">Modul F: Finance - Disbursements, Cash-In & Cash-Out</flux:heading>
 <flux:text class="text-sm text-base-content/50">Pengelolaan Arus Kas Masuk, Pencairan Dana, Pembagian Dividen, & Pelaporan SPJ</flux:text>
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
 Pusat kontrol perputaran dana tunai perusahaan, termasuk pelacakan modal, pembagian dividen ke investor secara otomatis/real-time, serta pencocokan anggaran SPJ operasional.
 </p>
 </div>

 <div class="space-y-4 text-base-content/70">
 <p class="leading-relaxed">
 Modul Disbursements bertanggung jawab penuh atas kontrol sirkulasi dana tunai perusahaan, mulai dari penerimaan modal investor, pembayaran dividen mitra, pengeluaran operasional (OPEX), hingga validasi Surat Pertanggungjawaban (SPJ) dari tim lapangan.
 </p>

 <flux:heading level="3" class="text-lg font-medium text-base-content/90 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Disbursement Kanban Board</flux:heading>
 <flux:text class="text-sm">Papan visual drag-and-drop interaktif untuk melacak tahapan pencairan dana: Verifikasi & Pecah Nominal → Penagihan & Piutang → Selesai & Kas Lunas.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Real-Time Dividend Payout</flux:heading>
 <flux:text class="text-sm">Ketika status pencairan dana berubah menjadi'Kas Lunas', sistem otomatis menghitung persentase bagi hasil investor (Net Value x Share %) dan mencatatnya ke database.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Procurement & Rekonsiliasi SPJ</flux:heading>
 <flux:text class="text-sm">Alur pembanding pengajuan anggaran dana operasional dengan bukti fisik pengeluaran riil di lapangan yang diunggah ke cloud storage S3.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Split Panel Audit View</flux:heading>
 <flux:text class="text-sm">Desain layout split screen untuk menyejajarkan form detail pengajuan anggaran di sisi kiri dan pratinjau bukti dokumen nota/kuitansi fisik di sisi kanan.</flux:text>
 </flux:card>
 </div>
 </div>
 </flux:card>
</div>
