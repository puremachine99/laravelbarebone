@props(['paginator', 'model' => 'perPage'])

@php
    $pageName = $paginator->getPageName();
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    $pages = collect([1, $last])
        ->merge(range(max(1, $current - 1), min($last, $current + 1)))
        ->unique()
        ->sort()
        ->values();
@endphp

<div {{ $attributes->class('flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-base-content/60">
        <label class="flex items-center gap-2">
            <span>Baris</span>
            <select wire:model.live="{{ $model }}" class="select select-sm select-bordered w-[4.5rem] bg-base-100 text-base-content">
                @foreach (\App\Support\PerPage::OPTIONS as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <span>
            @if ($paginator->total() > 0)
                <span class="font-medium text-base-content">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                dari <span class="font-medium text-base-content">{{ number_format($paginator->total(), 0, ',', '.') }}</span> data
            @else
                Tidak ada data
            @endif
        </span>
    </div>

    @if ($paginator->hasPages())
        <div class="join self-start sm:self-auto" role="navigation" aria-label="Pagination">
            <button type="button" class="join-item btn btn-sm" @disabled($paginator->onFirstPage()) wire:click="previousPage('{{ $pageName }}')" aria-label="Sebelumnya">
                <x-icon name="o-chevron-left" class="h-4 w-4" />
            </button>

            @foreach ($pages as $page)
                @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                    <span class="join-item btn btn-sm btn-disabled">…</span>
                @endif

                <button
                    type="button"
                    wire:key="pager-{{ $pageName }}-{{ $page }}"
                    wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                    @class(['join-item btn btn-sm', 'btn-primary' => $page === $current])
                    @if ($page === $current) aria-current="page" @endif
                >{{ $page }}</button>
            @endforeach

            <button type="button" class="join-item btn btn-sm" @disabled(! $paginator->hasMorePages()) wire:click="nextPage('{{ $pageName }}')" aria-label="Berikutnya">
                <x-icon name="o-chevron-right" class="h-4 w-4" />
            </button>
        </div>
    @endif
</div>
