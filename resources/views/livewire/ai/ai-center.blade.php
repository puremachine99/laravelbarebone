<div class="p-6 lg:p-8 space-y-6">
 <div class="p-6 space-y-4 bg-base-100/50 border-base-300 rounded-2xl border">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <x-icon name="o-sparkles" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <h2 class="text-xl font-semibold">Modul J: Nakama AI Center</h2>
 <p class="text-sm text-base-content/50">Pusat Komando Analisis Berkas & Eksekusi Perintah Asisten AI</p>
 </div>
 </div>
 
 <hr class="border-base-300">

 <!-- Goal Section -->
 <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 space-y-2">
 <div class="flex items-center gap-2 text-indigo-800 dark:text-indigo-300 font-semibold text-base">
 <x-icon name="o-flag" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
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

 <h3 class="text-lg font-medium text-base-content/90 mt-6">Fitur Utama yang Akan Hadir:</h3>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">AI-Driven Prompt Terminal</h4>
 <p class="text-sm">Area input teks terminal pintar untuk mengeksekusi instruksi langsung ke sistem, seperti mengubah status kartu, menaikkan persentase OKR, atau meminta ringkasan log aktivitas.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Dropzone File Audit</h4>
 <p class="text-sm">Area drag-and-drop berkas dokumen fisik (PDF nota/bukti kas) untuk diunggah secara asinkron ke S3, untuk kemudian dibaca dan dianalisis nilainya oleh model AI secara otomatis.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Function Tool Calling & Log</h4>
 <p class="text-sm">Kemampuan model AI (via Laravel 13 AI SDK) untuk memanggil fungsi-fungsi sistem database secara aman, diiringi terminal log aktivitas real-time lewat koneksi Laravel Echo.</p>
 </div>

 <div class="p-4 bg-base-100/50 border border-base-300 rounded-2xl border">
 <h4 class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Structured Output Validation</h4>
 <p class="text-sm">Validasi schema output model AI dalam format JSON yang terstruktur ketat untuk mencegah halusinasi data numerik finansial dan ketidakcocokan relasi data.</p>
 </div>
 </div>
 </div>
 </div>
</div>
