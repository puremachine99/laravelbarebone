<div class="p-6 lg:p-8 space-y-6">
    <flux:card class="p-6 space-y-4 bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
                <flux:icon name="chat-bubble-left-right" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <flux:heading level="2" class="text-xl font-semibold">Modul I: Nakama Chat</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Pusat Komunikasi Real-time, Kolaborasi Tim, & Penyelarasan OKR</flux:text>
            </div>
        </div>
        
        <flux:separator />

        <div class="space-y-4 text-zinc-600 dark:text-zinc-400">
            <p class="leading-relaxed">
                Nakama Chat berfungsi sebagai hub komunikasi terintegrasi untuk mengeliminasi sekat (silo) informasi antar-karyawan PT Ide Bangsa Mahardika. Halaman obrolan ini menyatukan interaksi formal terkait pekerjaan dengan komunikasi kasual harian secara real-time.
            </p>

            <flux:heading level="3" class="text-lg font-medium text-zinc-800 dark:text-zinc-200 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Real-Time Messaging Engine</flux:heading>
                    <flux:text class="text-sm">Didukung oleh server WebSocket Laravel Reverb, obrolan mengalir tanpa delay, lengkap dengan fitur pencarian kontak, status keaktifan user, dan gelembung chat (chat bubbles) kustom.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Pemisahan File Kerja vs Kasual</flux:heading>
                    <flux:text class="text-sm">Berkas yang diunggah diklasifikasi otomatis secara logis di AWS S3; dokumen kerja disimpan di bucket privat, sementara gambar kasual ditaruh di bucket publik.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Quick OKR Sync Sidebar</flux:heading>
                    <flux:text class="text-sm">Tombol tautan cepat pada header obrolan untuk memicu laci samping (drawer) yang memuat data capaian OKR dan target kerja milik lawan bicara untuk sinkronisasi proyek cepat.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Layout Antarmuka Premium</flux:heading>
                    <flux:text class="text-sm">Desain antarmuka 2 kolom berskala penuh memanfaatkan flexbox/grid Tailwind v4 dengan daftar ruang obrolan di sisi kiri dan kanvas percakapan di sisi kanan.</flux:text>
                </flux:card>
            </div>
        </div>
    </flux:card>
</div>
