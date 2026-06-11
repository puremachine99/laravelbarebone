<div class="p-6 lg:p-8 space-y-6">
    <flux:card class="p-6 space-y-4 bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
                <flux:icon name="adjustments-horizontal" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <flux:heading level="2" class="text-xl font-semibold">Modul C: Pipeline & Rules Engine</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Konfigurasi Alur Kerja Dinamis & Mesin Validasi Aturan Otomatis</flux:text>
            </div>
        </div>
        
        <flux:separator />

        <div class="space-y-4 text-zinc-600 dark:text-zinc-400">
            <p class="leading-relaxed">
                Modul ini menyediakan infrastruktur fleksibel bagi tiap departemen untuk menyusun, menyesuaikan, dan mengotomasi alur kerja (workflow) mereka. Melalui antarmuka visual, pimpinan dapat memantau pergerakan kartu tugas/proyek dan menetapkan aturan validasi yang ketat pada setiap tahap perpindahan.
            </p>

            <flux:heading level="3" class="text-lg font-medium text-zinc-800 dark:text-zinc-200 mt-6">Fitur Utama yang Akan Hadir:</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Master Pipeline Configuration</flux:heading>
                    <flux:text class="text-sm">Pengaturan tahap (stages) proyek secara dinamis. Setiap stage dirujuk dengan UUID unik agar aman dari perubahan nama (rename-proof) dan dapat diposisikan ulang dengan mudah.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Stage Rules (Conditional Validation)</flux:heading>
                    <flux:text class="text-sm">Aturan dinamis di setiap tahap perpindahan. Validasi bersyarat di server-side menjamin pengguna mengunggah berkas S3, mengisi nominal uang, memo catatan, atau memilih kontak yang valid.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Cross-Functional Trigger</flux:heading>
                    <flux:text class="text-sm">Otomasi pembuatan kartu atau tugas baru di departemen lain secara otomatis ketika sebuah kartu utama telah mencapai tahap tertentu, lengkap dengan pewarisan data kontak & berkas S3.</flux:text>
                </flux:card>

                <flux:card class="p-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <flux:heading level="4" class="font-semibold text-indigo-600 dark:text-indigo-400 mb-1">Safe Delete & Soft-Delete Guard</flux:heading>
                    <flux:text class="text-sm">Proteksi integritas alur kerja dengan menolak penghapusan tahap yang masih memiliki kartu aktif, serta menggunakan penanda is_active untuk menonaktifkan stage lama.</flux:text>
                </flux:card>
            </div>
        </div>
    </flux:card>
</div>
