<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
 {{ filled($title ?? null) ? $title.' -'.config('app.name','Laravel') : config('app.name','Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<script>
    function applyGlobalTheme() {
        let theme = localStorage.getItem('theme');
        let isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
        
        if (isDark) {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-theme', 'light');
        }
    }

    applyGlobalTheme();

    document.addEventListener('livewire:navigated', applyGlobalTheme);
</script>

@fonts

@vite(['resources/css/app.css','resources/js/app.js'])
@fluxAppearance
