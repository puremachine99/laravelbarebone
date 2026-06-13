<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class ExecutiveIndex extends Component
{
    public function syncContacts()
    {
        try {
            // Run the Artisan command directly
            $exitCode = Artisan::call('app:sync-partnership-contacts');

            if ($exitCode === 0) {
                $output = Artisan::output();
                // Clean output formatting for display
                $cleanOutput = preg_replace('/\[.*\]|Checking|Extracting|\.\.\./', '', $output);
                session()->flash('sync_success', 'Sinkronisasi Berhasil: '.trim($cleanOutput));
            } else {
                session()->flash('sync_error', 'Gagal menjalankan sinkronisasi. Periksa koneksi database MySQL luar.');
            }
        } catch (\Exception $e) {
            session()->flash('sync_error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.dashboard.executive-index');
    }
}
