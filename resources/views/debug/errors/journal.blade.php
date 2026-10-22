{{--
    Sentry → Jurnal: the development journal from report/{year}{month}.md (App\Support\DevJournal), one tab per month.
    Every month is rendered up front and Alpine switches tabs, so changing month is instant.
--}}
<div class="space-y-4" x-data="{ month: {{ $journalActiveMonth }} }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div role="tablist" class="tabs tabs-box tabs-sm flex-wrap">
            @foreach ($journalMonths as $number => $journalMonth)
                @php($hasContent = $journalMonth['entries'] !== [] || $journalMonth['notes'] !== [])
                <button type="button" role="tab" x-on:click="month = {{ $number }}"
                    x-bind:class="{ 'tab-active': month === {{ $number }} }"
                    x-bind:aria-selected="month === {{ $number }}"
                    @class(['tab', 'text-base-content/35' => ! $hasContent])>
                    {{ mb_substr($journalMonth['name'], 0, 3) }}
                </button>
            @endforeach
        </div>

        {{-- Year picker: every year that has a report file; switching reloads the tab for that year --}}
        <label class="flex items-center gap-2 text-sm text-base-content/60">
            Tahun
            <select aria-label="Tahun jurnal" class="select select-sm w-28"
                x-on:change="Livewire.navigate(@js(route('dev.errors.index', ['tab' => 'journal'])) + '&year=' + $event.target.value)">
                @foreach ($journalYears ?: [$journalYear] as $year)
                    <option value="{{ $year }}" @selected($year === $journalYear)>{{ $year }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @foreach ($journalMonths as $number => $journalMonth)
        <section x-show="month === {{ $number }}" @if ($number !== $journalActiveMonth) style="display: none" @endif
            class="card bg-base-100 border border-base-300">
            <div class="card-body p-5 gap-5">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-lg font-bold text-base-content">{{ $journalMonth['name'] }} {{ $journalYear }}</h2>
                    @if ($journalMonth['entries'] !== [])
                        <span class="text-xs text-base-content/50">{{ count($journalMonth['entries']) }} hari tercatat</span>
                    @endif
                </div>

                @if ($journalMonth['entries'] === [] && $journalMonth['notes'] === [])
                    <div class="py-10 text-center text-sm text-base-content/50">
                        Belum ada jurnal untuk bulan ini.
                    </div>
                @else
                    {{-- Timeline: a date marker on a vertical line, the day's work beside it --}}
                    <ol class="relative ms-3 border-s-2 border-base-300">
                        @foreach ($journalMonth['entries'] as $entry)
                            <li class="relative ps-8 pb-8 last:pb-0">
                                <span class="absolute -start-[9px] top-1 size-4 rounded-full border-2 border-base-100 bg-primary"></span>
                                <p class="text-sm font-bold text-primary">
                                    {{ $entry['label'] }} {{ $journalMonth['name'] }}
                                </p>
                                <div class="dev-journal-entry mt-2 text-base-content/85">{!! $entry['html'] !!}</div>
                            </li>
                        @endforeach
                    </ol>

                    @foreach ($journalMonth['notes'] as $note)
                        <div class="rounded-xl border border-dashed border-base-300 bg-base-200/50 p-4">
                            <p class="mb-2 text-sm font-bold text-base-content">{{ $note['label'] }}</p>
                            <div class="dev-journal-entry text-base-content/85">{!! $note['html'] !!}</div>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>
    @endforeach
</div>
