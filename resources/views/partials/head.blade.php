<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="authenticated" content="{{ auth()->check() ? '1' : '0' }}" />

<title>
 {{ filled($title ?? null) ? $title.' -'.config('app.name','Laravel') : config('app.name','Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{--
    Theme (Default / Nakama Desk) and appearance (light / dark / system). Logged-in users' choice is stored on their
    account (users.theme, users.appearance) and saved through profile.appearance.update; guests keep it in localStorage.
    The `.dark` class (Tailwind `dark:`) is set for both dark variants, so check that class — not data-theme — for
    "is dark".
--}}
<script>
    window.themePreferences = @js(auth()->check() ? [
        'theme' => auth()->user()->theme?->value ?? 'default',
        'appearance' => auth()->user()->appearance?->value ?? 'system',
        'saveUrl' => route('profile.appearance.update'),
        'toonThemes' => \App\Enums\UserTheme::toonValues(),
    ] : null);

    function currentThemePreferences() {
        if (window.themePreferences) {
            return window.themePreferences;
        }

        let stored = null;
        try { stored = localStorage.getItem('theme'); } catch (error) { }

        return { theme: 'default', appearance: stored || 'system', saveUrl: null };
    }

    function applyGlobalTheme() {
        const { theme, appearance } = currentThemePreferences();
        const isDark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        // Same rule as App\Enums\UserTheme::dataTheme(): "light"/"dark" for the default theme, "{theme}"/"{theme}-dark" otherwise.
        const dataTheme = theme === 'default' ? (isDark ? 'dark' : 'light') : (isDark ? `${theme}-dark` : theme);

        document.documentElement.classList.toggle('dark', isDark);
        document.documentElement.setAttribute('data-theme', dataTheme);
        // Themes with the shared neo-brutalist structure (resources/css/themes/toon.css)
        document.documentElement.toggleAttribute('data-toon', (currentThemePreferences().toonThemes || []).includes(theme));
        document.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme, appearance, isDark } }));
    }

    /**
     * Applies right away, then stores the change: on the account when logged in, otherwise in this browser.
     */
    function saveThemePreferences(changes) {
        const preferences = { ...currentThemePreferences(), ...changes };

        if (window.themePreferences) {
            window.themePreferences = preferences;
            fetch(preferences.saveUrl, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(changes),
            }).catch(() => { });
        } else {
            try { localStorage.setItem('theme', preferences.appearance); } catch (error) { }
        }

        applyGlobalTheme();
    }

    // One-off: a light/dark choice made before it was stored on the account moves to the account.
    (function moveBrowserChoiceToAccount() {
        if (!window.themePreferences || window.themePreferences.appearance !== 'system') {
            return;
        }

        try {
            const stored = localStorage.getItem('theme');
            localStorage.removeItem('theme');

            if (stored === 'light' || stored === 'dark') {
                saveThemePreferences({ appearance: stored });
            }
        } catch (error) { }
    })();

    /**
     * Chart colours from the active theme (--chart-* in resources/css/app.css, overridden per theme), so charts
     * never hard-code hex values: chartTheme().color(1), .text, .grid, .onBar, .mode.
     */
    window.chartTheme = function () {
        const style = getComputedStyle(document.documentElement);
        const read = (name) => style.getPropertyValue(name).trim();
        const isDark = document.documentElement.classList.contains('dark');

        return {
            color: (index) => read(`--chart-${index}`),
            text: read('--chart-text'),
            grid: read('--chart-grid'),
            onBar: read('--chart-on-bar'),
            mode: isDark ? 'dark' : 'light',
        };
    };

    applyGlobalTheme();

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (currentThemePreferences().appearance === 'system') {
            applyGlobalTheme();
        }
    });

    document.addEventListener('livewire:navigated', applyGlobalTheme);
</script>

@fonts

@vite(['resources/css/app.css','resources/js/app.js'])
