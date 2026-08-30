<label
    class="swap swap-rotate btn btn-ghost btn-circle btn-sm cursor-pointer"
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    x-init="$watch('dark', (value) => {
        localStorage.setItem('theme', value ? 'dark' : 'light');
        applyGlobalTheme();
    })">
    <input type="checkbox" x-model="dark" class="hidden" />
    <x-icon name="o-sun" class="swap-off w-4 h-4" />
    <x-icon name="o-moon" class="swap-on w-4 h-4" />
</label>
