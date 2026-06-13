# Bulk DaisyUI semantic color replacement across all blade files
# Scope: resources/views (exclude welcome.blade.php dan layouts/app/sidebar.blade.php yang sudah clean)

$targetDir = "d:\App\laragon\www\smartdashboard\resources\views"
$excludeFiles = @("welcome.blade.php")

# Get all blade files excluding welcome.blade.php and the already-fixed sidebar
$files = Get-ChildItem -Path $targetDir -Recurse -Filter "*.blade.php" | Where-Object {
    $excludeFiles -notcontains $_.Name
}

$totalReplacements = 0

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    $original = $content

    # === Background Colors ===
    $content = $content -replace 'bg-white dark:bg-zinc-900', 'bg-base-100'
    $content = $content -replace 'bg-white dark:bg-zinc-800', 'bg-base-100'
    $content = $content -replace 'bg-zinc-50 dark:bg-zinc-950', 'bg-base-100'
    $content = $content -replace 'bg-zinc-50/50 dark:bg-zinc-900/50', 'bg-base-100/50'
    $content = $content -replace 'bg-zinc-800/50 dark:bg-zinc-800/50', 'bg-base-200/50'
    $content = $content -replace 'dark:bg-zinc-800/50', ''
    $content = $content -replace 'dark:bg-zinc-900/50', ''
    $content = $content -replace 'bg-zinc-100 dark:bg-zinc-800', 'bg-base-200'
    $content = $content -replace 'bg-zinc-200 dark:bg-zinc-800', 'bg-base-300'
    $content = $content -replace 'bg-zinc-100 dark:bg-zinc-700', 'bg-base-200'
    $content = $content -replace 'bg-zinc-50 dark:bg-zinc-800', 'bg-base-200'
    $content = $content -replace 'dark:bg-zinc-900', ''
    $content = $content -replace 'dark:bg-zinc-800', ''
    $content = $content -replace 'dark:bg-zinc-700', ''

    # === Border Colors ===
    $content = $content -replace 'border-zinc-200 dark:border-zinc-800', 'border-base-300'
    $content = $content -replace 'border-zinc-200 dark:border-zinc-700', 'border-base-300'
    $content = $content -replace 'border-zinc-100 dark:border-zinc-800', 'border-base-300'
    $content = $content -replace 'border-zinc-300 dark:border-zinc-700', 'border-base-300'
    $content = $content -replace 'divide-zinc-200 dark:divide-zinc-800', 'divide-base-300'
    $content = $content -replace 'dark:border-zinc-800', ''
    $content = $content -replace 'dark:border-zinc-700', ''
    $content = $content -replace 'dark:border-zinc-600', ''

    # === Text Colors ===
    $content = $content -replace 'text-zinc-950 dark:text-zinc-100', 'text-base-content'
    $content = $content -replace 'text-zinc-900 dark:text-zinc-100', 'text-base-content'
    $content = $content -replace 'text-zinc-800 dark:text-zinc-200', 'text-base-content/90'
    $content = $content -replace 'text-zinc-700 dark:text-zinc-300', 'text-base-content/80'
    $content = $content -replace 'text-zinc-600 dark:text-zinc-400', 'text-base-content/70'
    $content = $content -replace 'text-zinc-600 dark:text-zinc-300', 'text-base-content/70'
    $content = $content -replace 'text-zinc-500 dark:text-zinc-400', 'text-base-content/50'
    $content = $content -replace 'text-zinc-400 dark:text-zinc-500', 'text-base-content/40'
    $content = $content -replace 'dark:text-zinc-100', ''
    $content = $content -replace 'dark:text-zinc-200', ''
    $content = $content -replace 'dark:text-zinc-300', ''
    $content = $content -replace 'dark:text-zinc-400', ''
    $content = $content -replace 'dark:text-zinc-500', ''
    # Standalone zinc text (no dark variant)
    $content = $content -replace '\btext-zinc-950\b', 'text-base-content'
    $content = $content -replace '\btext-zinc-900\b', 'text-base-content'
    $content = $content -replace '\btext-zinc-800\b', 'text-base-content/90'
    $content = $content -replace '\btext-zinc-700\b', 'text-base-content/80'
    $content = $content -replace '\btext-zinc-600\b', 'text-base-content/70'
    $content = $content -replace '\btext-zinc-500\b', 'text-base-content/50'
    $content = $content -replace '\btext-zinc-400\b', 'text-base-content/40'
    $content = $content -replace '\btext-zinc-300\b', 'text-base-content/30'

    # === Hover Colors ===
    $content = $content -replace 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50', 'hover:bg-base-200'
    $content = $content -replace 'hover:bg-zinc-50 dark:hover:bg-zinc-900/50', 'hover:bg-base-200'
    $content = $content -replace 'hover:bg-zinc-100 dark:hover:bg-zinc-800', 'hover:bg-base-200'
    $content = $content -replace 'hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30', 'hover:bg-base-200/50'
    $content = $content -replace 'dark:hover:bg-zinc-800', ''
    $content = $content -replace 'dark:hover:bg-zinc-900', ''
    $content = $content -replace '\bhover:bg-zinc-50\b', 'hover:bg-base-200'
    $content = $content -replace '\bhover:bg-zinc-100\b', 'hover:bg-base-200'

    # Standalone bg zinc (no dark variant, not in flux/external)
    $content = $content -replace '\bbg-zinc-50\b', 'bg-base-100'
    $content = $content -replace '\bbg-zinc-100\b', 'bg-base-200'
    $content = $content -replace '\bbg-zinc-200\b', 'bg-base-300'
    $content = $content -replace '\bborder-zinc-100\b', 'border-base-300'
    $content = $content -replace '\bborder-zinc-200\b', 'border-base-300'
    $content = $content -replace '\bborder-zinc-300\b', 'border-base-300'
    
    # Stone colors in 2fa modal
    $content = $content -replace 'bg-stone-100 dark:bg-stone-200', 'bg-base-200'
    $content = $content -replace 'bg-stone-100 dark:bg-stone-700', 'bg-base-200'
    $content = $content -replace 'border-stone-200 dark:border-stone-600', 'border-base-300'
    $content = $content -replace 'border-stone-200 dark:border-stone-700', 'border-base-300'
    $content = $content -replace 'divide-stone-200 dark:divide-stone-300', 'divide-base-300'
    $content = $content -replace 'bg-white dark:bg-stone-700', 'bg-base-100'
    $content = $content -replace 'bg-white dark:bg-stone-800', 'bg-base-100'
    $content = $content -replace 'bg-white dark:bg-stone-850', 'bg-base-100'
    $content = $content -replace 'border-stone-100 dark:border-stone-600', 'border-base-300'
    $content = $content -replace 'dark:border-stone-700', ''
    $content = $content -replace 'text-stone-900 dark:text-stone-100', 'text-base-content'
    $content = $content -replace 'text-stone-600 dark:text-stone-400', 'text-base-content/60'
    $content = $content -replace 'bg-stone-200 dark:bg-stone-600', 'bg-base-300'

    # Clean up double spaces left by removing dark: classes
    $content = $content -replace '  +', ' '
    # Clean up trailing spaces before quotes
    $content = $content -replace ' "', '"'
    $content = $content -replace " '", "'"

    if ($content -ne $original) {
        Set-Content -Path $file.FullName -Value $content -Encoding UTF8 -NoNewline
        $totalReplacements++
        Write-Host "Fixed: $($file.FullName)"
    }
}

Write-Host "`nTotal files modified: $totalReplacements"
