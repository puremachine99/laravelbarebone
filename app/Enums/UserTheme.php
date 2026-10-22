<?php

namespace App\Enums;

/**
 * The look of the whole app, chosen in profile → Appearance. This enum is the theme registry: adding a case adds
 * the theme to the profile picker. Its daisyUI themes are "{value}" and "{value}-dark" (the default theme uses
 * daisyUI's own "light" / "dark"); see the THEMES notes in resources/css/app.css for the CSS side.
 */
enum UserTheme: string
{
    case Default = 'default';
    case Espresso = 'espresso';
    case Cinnamoroll = 'cinnamoroll';

    public function label(): string
    {
        return match ($this) {
            self::Default => __('Default'),
            self::Espresso => __('Espresso'),
            self::Cinnamoroll => __('Cinnamoroll'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Default => __('Tampilan standar SmartID Dashboard.'),
            self::Espresso => __('Hangat bernuansa kopi, seperti halaman Pendampingan.'),
            self::Cinnamoroll => __('Biru langit dan awan putih, pipi pink, payung kuning.'),
        };
    }

    /**
     * Colours for the small mock-up on the profile picker (fixed, so it shows the theme rather than the one in use).
     *
     * @return array{frame: string, canvas: string, panel: string, line: string, muted: string, accent: string, accent_alt: string, outline: string, shadow: string, radius: string}
     */
    public function preview(): array
    {
        return match ($this) {
            self::Default => [
                'frame' => '#0d2a4f',
                'canvas' => '#f5f5f5',
                'panel' => '#ffffff',
                'line' => '#e5e5e5',
                'muted' => '#d4d4d4',
                'accent' => '#2563eb',
                'accent_alt' => '#e5e5e5',
                'outline' => '1px solid #e5e5e5',
                'shadow' => 'none',
                'radius' => '6px',
            ],
            self::Espresso => [
                'frame' => '#1C1210',
                'canvas' => '#FFF8F2',
                'panel' => '#FFFFFF',
                'line' => '#3A2418',
                'muted' => '#F5E8D8',
                'accent' => '#3B2A22',
                'accent_alt' => '#FFE3A1',
                'outline' => '2px solid #3A2418',
                'shadow' => '2px 2px 0 #3A2418',
                'radius' => '9999px',
            ],
            self::Cinnamoroll => [
                'frame' => '#AFDCEE',
                'canvas' => '#FBFDFF',
                'panel' => '#FFFFFF',
                'line' => '#5AB0DC',
                'muted' => '#EEF7FD',
                'accent' => '#75C2E6',
                'accent_alt' => '#F0F6C0',
                'outline' => '2px solid #5AB0DC',
                'shadow' => '2px 2px 0 #F4B6CF',
                'radius' => '9999px',
            ],
        };
    }

    /**
     * "toon": built on the shared neo-brutalist structure (resources/css/themes/toon.css), which then only needs a
     * token file; "default": the plain daisyUI look.
     */
    public function style(): string
    {
        return match ($this) {
            self::Default => 'default',
            self::Espresso, self::Cinnamoroll => 'toon',
        };
    }

    /**
     * The values of the themes that use the toon style, for the browser (partials/head.blade.php).
     *
     * @return array<int, string>
     */
    public static function toonValues(): array
    {
        return collect(self::cases())->filter(fn (self $theme) => $theme->style() === 'toon')->map->value->values()->all();
    }

    /**
     * The `data-theme` value for the light or dark variant.
     */
    public function dataTheme(bool $dark): string
    {
        if ($this === self::Default) {
            return $dark ? 'dark' : 'light';
        }

        return $dark ? $this->value.'-dark' : $this->value;
    }
}
