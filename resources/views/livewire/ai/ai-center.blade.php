<div class="p-6 lg:p-8 space-y-6">
    <flux:card class="p-6 space-y-4 bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
                <flux:icon name="sparkles" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <flux:heading level="2" class="text-xl font-semibold">Modul J: Nakama AI Center</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Pusat Komando Analisis Berkas & Eksekusi Perintah Asisten AI</flux:text>
            </div>
        </div>
        
        <flux:separator />

        <div class="space-y-4 text-zinc-600 dark:text-zinc-400">
            <p class="leading-relaxed">
                Nakama AI Center adalah modul asisten kecerdasan buatan (AI) terintegrasi yang berfungsi untuk mengotomatisasi interaksi dengan database ERP melalui perintah teks instruksi alami (natural language) serta membaca data dokumen SPJ dan nota di S3.
            </p>

            <flux:heading level="3" class="text-lg font-medium text-zinc-800 dark:text-zinc-200 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">AI-Driven Prompt Terminal</flux:heading>
                    <flux:text class="text-sm">Area input teks terminal pintar untuk mengeksekusi instruksi langsung ke sistem, seperti mengubah status kartu, menaikkan persentase OKR, atau meminta ringkasan log aktivitas.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Dropzone File Audit</flux:heading>
                    <flux:text class="text-sm">Area drag-and-drop berkas dokumen fisik (PDF nota/bukti kas) untuk diunggah secara asinkron ke S3, untuk kemudian dibaca dan dianalisis nilainya oleh model AI secara otomatis.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Function Tool Calling & Log</flux:heading>
                    <flux:text class="text-sm">Kemampuan model AI (via Laravel 13 AI SDK) untuk memanggil fungsi-fungsi sistem database secara aman, diiringi terminal log aktivitas real-time lewat koneksi Laravel Echo.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Structured Output Validation</flux:heading>
                    <flux:text class="text-sm">Validasi schema output model AI dalam format JSON yang terstruktur ketat untuk mencegah halusinasi data numerik finansial dan ketidakcocokan relasi data.</flux:text>
                </flux:card>
            </div>
        </div>
    </flux:card>
</div>
