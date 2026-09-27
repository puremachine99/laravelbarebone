{{--
    Chrome-style download popup for the CSV export buttons. Kept as a fetch() + streamed
    read (instead of a plain <a href> navigation) for two reasons: it lets us show real
    progress (rows streamed vs. `X-Csv-Total-Rows`), and it keeps the connection actively
    receiving bytes the whole time, which is what CsvExportController's flush-per-chunk
    streaming is for — large exports stay alive instead of looking "stuck" for a minute
    behind a reverse proxy/CDN with an idle-connection timeout.

    Logic lives in window.csvDownloadManager() (resources/js/app.js), not inline here —
    it needs double quotes for regex/JSON parsing, which can't safely sit inside this
    x-data="..." HTML attribute value.
--}}
<div
    x-data="csvDownloadManager()"
    x-on:csv-export:start.window="start($event.detail)"
    class="fixed bottom-4 right-4 z-70 flex flex-col gap-2 w-80 max-w-[calc(100vw-2rem)]"
>
    <template x-for="job in jobs" :key="job.id">
        <div class="bg-base-100 border border-base-300 rounded-lg shadow-lg p-3" x-transition>
            <div class="flex items-center gap-2 mb-1.5">
                <x-icon name="o-arrow-path" class="size-3.5 text-primary shrink-0 animate-spin" x-show="job.status === 'downloading'" x-cloak />
                <x-icon name="o-check-circle" class="size-3.5 text-success shrink-0" x-show="job.status === 'done'" x-cloak />
                <x-icon name="o-exclamation-triangle" class="size-3.5 text-error shrink-0" x-show="job.status === 'error'" x-cloak />
                <span class="text-sm font-medium text-base-content truncate" x-text="job.filename"></span>
            </div>
            <div class="h-1.5 w-full bg-base-200 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-200"
                    x-bind:class="{
                        'bg-error': job.status === 'error',
                        'bg-primary': job.status !== 'error',
                        'animate-pulse': job.percent === null && job.status === 'downloading',
                    }"
                    x-bind:style="`width: ${job.percent ?? 40}%`"
                ></div>
            </div>
            <p class="mt-1 text-[11px] text-base-content/50" x-text="job.statusText"></p>
        </div>
    </template>
</div>
