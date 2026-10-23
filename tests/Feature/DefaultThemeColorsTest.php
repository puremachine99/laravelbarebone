<?php

use Illuminate\Support\Facades\File;

/**
 * The default theme speaks one brand blue: daisyUI's light/dark primary and the indigo/blue palettes the views
 * hard-code all resolve to the brand-* scale in resources/css/app.css.
 */
function appCss(): string
{
    return file_get_contents(resource_path('css/app.css'));
}

test('the default light and dark themes use the brand colours instead of the daisyUI defaults', function () {
    expect(appCss())
        ->toMatch('/name: "light";\s+--color-primary: #2559A6;/')
        ->toMatch('/name: "dark";.*?--color-primary: #5C90D4;/s')
        ->toContain('--color-brand-600: #2559A6;', '--color-brand-950: #0D2A4F;');
});

test('indigo and blue follow the brand scale in the default theme only', function () {
    $css = appCss();

    expect($css)->toContain(':root:is([data-theme="light"], [data-theme="dark"]) {');

    foreach (['indigo', 'blue'] as $palette) {
        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $shade) {
            expect($css)->toContain("--color-{$palette}-{$shade}: var(--color-brand-{$shade});");
        }
    }
});

test('the built stylesheet ships the brand primary', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $built = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

    expect(strtolower($built))->toContain('#2559a6')->toContain('--color-brand-600');
})->skip(fn () => ! file_exists(public_path('build/manifest.json')), 'assets are not built');

test('content text is never smaller than 11px, except badges, bubbles and the sidebar', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($file) => str_ends_with(str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname()), 'layouts/app/sidebar.blade.php'))
        ->flatMap(function ($file) {
            preg_match_all('/class="([^"]*text-\[(?:9|10)px\][^"]*)"/', $file->getContents(), $matches);

            return collect($matches[1])
                ->reject(fn (string $classes) => preg_match('/(^|\s)(badge[\w-]*|rounded-full|absolute)(\s|$)/', $classes))
                ->map(fn (string $classes) => $file->getFilename().': '.$classes);
        });

    expect($offenders->all())->toBe([]);
});

test('the default theme sets figures in tabular numerals', function () {
    expect(appCss())->toContain('font-variant-numeric: tabular-nums;');
});

test('panels share one shape: 16px corners, defined by a border rather than a doubled shadow', function () {
    expect(appCss())
        ->toMatch('/name: "light";.*?--radius-box: 1rem;\s+--radius-field: 0\.625rem;/s')
        ->toContain('.border:is(.shadow-xs, .shadow-sm, .shadow) {');
});

test('the default dark mode is navy-tinted, greys included', function () {
    expect(appCss())
        ->toMatch('/name: "dark";\s+--color-base-100: #0B1626;/')
        ->toContain(':root[data-theme="dark"] {', '--color-zinc-900: var(--color-ink-900);', '--color-gray-800: var(--color-ink-800);');
});

test('the sidebar pattern is calmer and the active item has no gradient', function () {
    expect(appCss())
        ->toContain("stroke-opacity='0.07'")
        ->not->toContain('color-mix(in oklab, var(--color-sidebar-accent) 18%, transparent), transparent)');
});

test('the active sidebar item is a binder tab in the content colour, joined to the page', function () {
    expect(appCss())
        ->toContain('--sidebar-border: transparent;', 'border-inline-end-width: 0;', 'scrollbar-width: none;')
        ->toMatch('/\.menu-item\.active \{[^}]*margin-inline-end: calc\(-0\.625rem - 2px\);[^}]*background-color: var\(--color-base-100\);/s')
        ->toContain('.sidebar-menu .collapse:has(> input:checked) {', '.menu-item.active::before', '.menu-item.active::after');
});
