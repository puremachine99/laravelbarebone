@props(['name', 'size' => 'w-6 h-6'])

<div class="avatar placeholder shrink-0">
    <div {{ $attributes->class(['bg-primary/10 text-primary rounded-full flex items-center justify-center', $size]) }}>
        <span class="text-[10px] font-bold not-italic leading-none">{{ mb_substr($name, 0, 1) }}</span>
    </div>
</div>
