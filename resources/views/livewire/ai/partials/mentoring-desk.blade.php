{{--
    "Pendampingan" tab: the guided work-mapping chat (core UI: report/ai/desk-nakama.html), driven by the
    `aiMentoringDesk` Alpine component in resources/js/ai-mentoring.js. Every request goes through
    MentoringController, so the Nakama API key never reaches the browser.
--}}
<div wire:ignore wire:key="ai-center-mentoring"
    x-data="aiMentoringDesk({
        unitsUrl: @js(route('ai-center.mentoring.units')),
        sessionsUrl: @js(route('ai-center.mentoring.sessions.index')),
        sessionUrl: @js(route('ai-center.mentoring.sessions.show', 'map_SESSIONID')),
        answerUrl: @js(route('ai-center.mentoring.sessions.answer', 'map_SESSIONID')),
        pauseUrl: @js(route('ai-center.mentoring.sessions.pause', 'map_SESSIONID')),
        cancelUrl: @js(route('ai-center.mentoring.sessions.cancel', 'map_SESSIONID')),
        exportUrl: @js(route('ai-center.mentoring.sessions.export', 'map_SESSIONID')),
        assignedUnit: @js(app(\App\Services\AiCenter\NakamaMentoring::class)->assignedUnitFor(auth()->user())),
    })"
    class="mentoring-desk relative h-[calc(100vh-8.5rem)] lg:h-[calc(100vh-10rem)] overflow-hidden rounded-2xl border-[2.5px] border-cocoa antialiased selection:bg-peach selection:text-ink">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

    <div data-ref="floating" class="pointer-events-none absolute inset-0 overflow-hidden select-none z-0" aria-hidden="true"></div>

    <div data-ref="scroller" class="absolute inset-0 z-10 overflow-y-auto">
        {{-- Onboarding --}}
        <section data-screen="onboarding" class="relative min-h-full flex items-start sm:items-center justify-center px-4 sm:px-6 py-8 sm:py-10 hidden">
            <div class="w-full max-w-4xl flex flex-col gap-8">
                <div class="bg-paper toon rounded-full px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-butter toon-sm flex items-center justify-center -rotate-3">
                            <span class="material-symbols-outlined text-[26px] text-cocoa">cognition</span>
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-latte">Inisiatif Pemetaan Internal</span>
                            <span class="text-[15px] font-extrabold text-cocoa">SmartID Companion · Safe Space FGD</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" data-sound-toggle
                            class="toon-sm toon-press flex items-center gap-2 bg-butter hover:bg-honey px-4 py-2 rounded-full text-cocoa font-bold text-sm">
                            <span class="material-symbols-outlined text-[20px]" data-sound-icon>graphic_eq</span>
                            <span data-sound-label>Sound: Nyala</span>
                        </button>
                        <span class="hidden sm:flex items-center gap-1.5 text-latte text-xs font-bold">
                            <span class="material-symbols-outlined text-[18px]">hourglass_top</span>Estimasi 10–15 menit
                        </span>
                    </div>
                </div>

                <div class="flex flex-col items-center text-center gap-4 max-w-2xl mx-auto">
                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-mint toon-sm text-leaf text-sm font-bold">
                        <span class="material-symbols-outlined text-[18px]">coffee</span>Santai · Tanpa Tekanan Audit · Cerita Apa Adanya
                    </span>
                    <h1 class="text-[30px] leading-[38px] sm:text-[40px] sm:leading-[48px] font-extrabold text-espresso tracking-tight">
                        Selamat Datang di Sesi Ngobrol Santai Pemetaan Kerja
                    </h1>
                    <p class="text-[17px] leading-7 text-bark font-medium">
                        Bantu kami memahami alur kerja, tantangan, dan kebutuhan timmu sehari-hari. Bukan untuk penilaian kinerja,
                        melainkan menyiapkan alat bantu kerja digital yang benar-benar mempermudah tugasmu.
                    </p>
                </div>

                <div data-ref="resume-card" class="hidden bg-butter toon rounded-3xl p-5 sm:p-6 flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-paper toon-sm flex items-center justify-center text-cocoa shrink-0 -rotate-3">
                            <span class="material-symbols-outlined text-[28px]">pause_circle</span>
                        </div>
                        <div>
                            <p class="font-extrabold text-cocoa text-lg">Ada sesi yang belum selesai</p>
                            <p class="text-bark text-sm font-medium" data-ref="resume-text"></p>
                        </div>
                    </div>
                    <div class="flex gap-3 shrink-0">
                        <button type="button" data-ref="btn-resume"
                            class="toon-sm toon-press px-5 py-3 rounded-2xl bg-cocoa text-paper font-bold flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">play_circle</span>Lanjutkan
                        </button>
                        <button type="button" data-ref="btn-discard" class="px-4 py-3 rounded-2xl text-bark font-bold hover:bg-peach">Mulai baru</button>
                    </div>
                </div>

                <div class="bg-paper toon rounded-3xl p-5 sm:p-10 flex flex-col gap-8">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-cocoa text-[26px]">badge</span>
                            <span class="text-[21px] font-extrabold text-cocoa">Halo, kita ngobrol sebagai</span>
                        </div>
                        <div class="flex items-center gap-4 bg-cream toon-sm rounded-2xl px-5 py-4">
                            <div class="w-12 h-12 rounded-2xl bg-honey toon-sm flex items-center justify-center font-extrabold text-cocoa rotate-3 shrink-0">
                                {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-[17px] font-extrabold text-ink truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[13px] font-semibold text-latte truncate">{{ auth()->user()->email }}</p>
                            </div>
                        </div>
                        <p class="text-[13px] font-semibold text-latte flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">verified_user</span>
                            Nama diambil dari akun login. Ceritamu dipakai khusus untuk pemetaan efisiensi proses internal, bukan penilaian kinerja.
                        </p>
                    </div>

                    <div class="h-[2px] w-full border-t-2 border-dashed border-biscuit"></div>

                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-rust text-[26px]">domain</span>
                                <span data-ref="unit-heading" class="text-[21px] font-extrabold text-cocoa">Pilih Unit / Departemen Kamu</span>
                            </div>
                            <span data-ref="unit-hint" class="text-[12px] font-bold text-latte">Setiap unit punya identitas &amp; alur probing kontekstual</span>
                        </div>
                        <div data-ref="unit-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" role="radiogroup" aria-label="Pilih unit kerja">
                            <div class="col-span-full flex items-center justify-center gap-2 py-10 text-latte font-bold">
                                <span class="material-symbols-outlined animate-spin">progress_activity</span>Memuat daftar unit...
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 p-5 rounded-2xl bg-sand border-2 border-dashed border-latte/50">
                        <span class="material-symbols-outlined text-rust text-[30px] shrink-0">tips_and_updates</span>
                        <div class="flex flex-col gap-1">
                            <span class="font-extrabold text-cocoa">Mengapa langkah ini penting?</span>
                            <p class="text-bark font-medium leading-relaxed">
                                Jawabanmu otomatis terhubung ke identitas unit yang dipilih. Kita akan membedah proses per satu output kerja
                                secara teratur, lengkap dengan titik tersulit yang sering bikin lembur atau bingung.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col items-center gap-3 pt-2">
                        <button type="button" data-ref="btn-start" disabled
                            class="toon toon-press w-full sm:w-auto px-10 py-4 rounded-full bg-rust hover:bg-[#B74300] text-white text-[19px] font-extrabold flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span>Mulai Sesi Ngobrol (Output #1)</span>
                            <span class="material-symbols-outlined text-[24px]">arrow_forward</span>
                        </button>
                        <span class="text-[12px] font-bold text-latte text-center">Dapat dijeda kapan saja · Tersimpan otomatis per output selesai</span>
                    </div>
                </div>

                <div data-ref="history" class="hidden bg-paper toon rounded-3xl p-5 sm:p-6 flex-col gap-4">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-cocoa text-[26px]">history</span>
                        <span class="text-[19px] font-extrabold text-cocoa">Riwayat pendampinganmu</span>
                    </div>
                    <p class="text-[13px] font-semibold text-latte -mt-2">Klik untuk membaca ulang. Riwayat hanya bisa dibaca, tidak bisa diubah.</p>
                    <div data-ref="history-list" class="flex flex-col gap-2.5"></div>
                </div>

                <div class="flex flex-wrap justify-center items-center gap-x-4 gap-y-1 text-latte text-[12px] font-bold pb-4">
                    <span>SmartID Workspace 2026</span><span>•</span><span>Framework Digitalisasi Probis Internal</span><span>•</span><span>13 Langkah Pemetaan</span>
                </div>
            </div>
        </section>

        {{-- Guided chat --}}
        <section data-screen="chat" class="relative min-h-full flex-col hidden">
            <header class="sticky top-0 z-40 bg-paper/95 backdrop-blur-md border-b-[3px] border-cocoa">
                <div class="h-[72px] max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <button type="button" data-ref="btn-home" aria-label="Kembali ke beranda pendampingan"
                            class="w-10 h-10 rounded-2xl bg-paper toon-sm toon-press flex items-center justify-center text-cocoa hover:bg-peach shrink-0">
                            <span class="material-symbols-outlined text-[22px]">arrow_back</span>
                        </button>
                        <div class="w-11 h-11 rounded-2xl bg-butter toon-sm flex items-center justify-center -rotate-3 shrink-0">
                            <span class="material-symbols-outlined text-[26px] text-cocoa">cognition</span>
                        </div>
                        <div class="hidden sm:flex flex-col leading-tight min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[18px] font-extrabold text-cocoa tracking-tight">SmartID Companion</span>
                                <span class="px-2 py-0.5 rounded-full bg-honey text-cocoa text-[10px] font-extrabold border border-cocoa">PROBIS DESK</span>
                            </div>
                            <span class="text-[12px] font-semibold text-latte flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">coffee</span>Sesi Ngobrol Alur Kerja Santai
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                        <div class="hidden md:flex items-center gap-2 px-3.5 py-1.5 unit-soft toon-sm rounded-full text-cocoa text-sm font-extrabold">
                            <span class="w-2.5 h-2.5 rounded-full unit-bg animate-bounce"></span><span data-ref="chat-unit-label">Unit</span>
                        </div>
                        <div class="flex items-center gap-1.5 px-3 py-1.5 bg-paper toon-sm rounded-full text-cocoa font-bold">
                            <span class="text-latte text-xs font-semibold">Langkah</span><span data-ref="chat-step-pill" class="px-2 py-0.5 bg-honey rounded-full text-xs">1/13</span>
                        </div>
                        <button type="button" data-sound-toggle aria-label="Nyalakan atau matikan suara"
                            class="w-10 h-10 rounded-2xl bg-butter toon-sm toon-press flex items-center justify-center text-cocoa hover:bg-honey">
                            <span class="material-symbols-outlined text-[22px]" data-sound-icon>volume_up</span>
                        </button>
                        <div data-ref="chat-avatar" class="w-10 h-10 rounded-full bg-honey toon-sm flex items-center justify-center font-extrabold text-cocoa">?</div>
                    </div>
                </div>
            </header>

            <main class="w-full max-w-3xl mx-auto px-3 sm:px-0 pt-5 pb-0 flex-1 flex flex-col gap-5">
                <div class="bg-paper toon rounded-3xl p-4 sm:p-5 flex flex-col gap-3 relative">
                    <span class="absolute -top-3 right-6 bg-tangerine text-white toon-sm rounded-full px-3 py-0.5 text-[11px] font-extrabold uppercase tracking-wider flex items-center gap-1">
                        <span class="material-symbols-outlined text-[13px]">flag</span>Mode Santai
                    </span>
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-12 h-12 rounded-2xl unit-soft toon-sm flex items-center justify-center -rotate-3 shrink-0">
                                <span class="material-symbols-outlined text-[28px] unit-text" data-ref="banner-icon">alt_route</span>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span data-ref="banner-output-no" class="px-2.5 py-1 rounded-xl bg-honey border border-cocoa text-cocoa text-[12px] font-extrabold">Output 1</span>
                                    <span class="text-latte font-bold text-xs">•</span>
                                    <span data-ref="banner-output-name" class="font-extrabold text-cocoa truncate">Memetakan output kerja</span>
                                </div>
                                <p data-ref="banner-title" class="text-bark text-sm font-semibold mt-0.5">Langkah 1 · Output kerja</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-auto sm:ml-0 bg-sand px-3 py-1.5 rounded-2xl toon-sm">
                            <span>🎯</span><span data-ref="banner-percent" class="font-extrabold text-cocoa text-sm">0% Selesai</span>
                        </div>
                    </div>
                    <div class="w-full bg-sand border-2 border-cocoa h-4 rounded-full overflow-hidden p-0.5">
                        <div data-ref="banner-bar"
                            class="bg-gradient-to-r from-tangerine to-honey h-full rounded-full border border-cocoa transition-all duration-700 ease-out flex items-center justify-end pr-1"
                            style="width: 2%">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                        </div>
                    </div>
                </div>

                <div data-ref="feed" class="flex flex-col gap-6 pb-2" aria-live="polite"></div>

                <div data-ref="composer-dock" class="sticky bottom-0 z-30 pt-3 pb-3 bg-gradient-to-t from-cream via-cream to-cream/0">
                    <div data-ref="composer" class="bg-paper/95 backdrop-blur-md toon rounded-3xl p-4 sm:p-5 flex flex-col gap-4"></div>
                    <div data-ref="dock-actions" class="mt-3 flex items-center justify-between gap-3 px-1">
                        <button type="button" data-ref="btn-pause"
                            class="toon-sm toon-press px-4 py-2.5 rounded-2xl bg-sand hover:bg-peach text-cocoa font-bold text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">pause_circle</span><span class="hidden sm:inline">Jeda &amp; Simpan Sesi</span><span class="sm:hidden">Jeda</span>
                        </button>
                        <span class="flex items-center gap-2 text-bark text-xs sm:text-sm font-bold"><span>☁️</span><span data-ref="saved-label">Tersimpan aman</span></span>
                    </div>
                </div>
            </main>
        </section>

        {{-- Closing summary --}}
        <section data-screen="closing" class="relative min-h-full hidden">
            <main data-ref="closing-root" class="w-full max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-10 flex flex-col gap-8"></main>
        </section>
    </div>

    <div data-ref="toast" class="absolute z-50 left-1/2 -translate-x-1/2 bottom-6 hidden max-w-[92%]">
        <div class="toon rounded-2xl px-5 py-3 font-bold flex items-center gap-2" data-ref="toast-body"></div>
    </div>
</div>
