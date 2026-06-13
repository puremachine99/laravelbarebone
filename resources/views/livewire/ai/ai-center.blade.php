<div class="p-6 lg:p-8 space-y-6">
 <flux:card class="p-6 space-y-4 bg-base-100/50 border-base-300">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <flux:icon name="sparkles" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <flux:heading level="2" class="text-xl font-semibold">Modul J: Nakama AI Center</flux:heading>
 <flux:text class="text-sm text-base-content/50">Pusat Komando Analisis Berkas & Eksekusi Perintah Asisten AI</flux:text>
 </div>
 </div>
 
 <flux:separator />

 <!-- Goal Section -->
 <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 space-y-2">
 <div class="flex items-center gap-2 text-indigo-800 dark:text-indigo-300 font-semibold text-base">
 <flux:icon name="flag" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
 <span>Upcoming Feature (Asisten AI — Nakama AI Center)</span>
 </div>
 <p class="text-sm text-indigo-700 dark:text-indigo-300 leading-relaxed font-medium">
 Fitur terminal teks di mana user bisa memberikan perintah menggunakan bahasa manusia biasa (contoh:"Update proyek Pemkab Malang jadi 50 juta dan lampirkan PDF ini"), lalu sistem AI akan mengeksekusi fungsi database secara otomatis.
 </p>
 </div>

 <div class="space-y-4 text-base-content/70">
 <p class="leading-relaxed">
 Nakama AI Center adalah modul asisten kecerdasan buatan (AI) terintegrasi yang berfungsi untuk mengotomatisasi interaksi dengan database ERP melalui perintah teks instruksi alami (natural language) serta membaca data dokumen SPJ dan nota di S3.
 </p>

 <flux:heading level="3" class="text-lg font-medium text-base-content/90 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">AI-Driven Prompt Terminal</flux:heading>
 <flux:text class="text-sm">Area input teks terminal pintar untuk mengeksekusi instruksi langsung ke sistem, seperti mengubah status kartu, menaikkan persentase OKR, atau meminta ringkasan log aktivitas.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Dropzone File Audit</flux:heading>
 <flux:text class="text-sm">Area drag-and-drop berkas dokumen fisik (PDF nota/bukti kas) untuk diunggah secara asinkron ke S3, untuk kemudian dibaca dan dianalisis nilainya oleh model AI secara otomatis.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Function Tool Calling & Log</flux:heading>
 <flux:text class="text-sm">Kemampuan model AI (via Laravel 13 AI SDK) untuk memanggil fungsi-fungsi sistem database secara aman, diiringi terminal log aktivitas real-time lewat koneksi Laravel Echo.</flux:text>
 </flux:card>

 <flux:card class="p-4 bg-base-100/50 border border-base-300">
 <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Structured Output Validation</flux:heading>
 <flux:text class="text-sm">Validasi schema output model AI dalam format JSON yang terstruktur ketat untuk mencegah halusinasi data numerik finansial dan ketidakcocokan relasi data.</flux:text>
 </flux:card>
 </div>
 </div>
 </flux:card>
</div>
