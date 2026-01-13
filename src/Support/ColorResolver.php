<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\View\Components\BadgeComponent;

/**
 * Resolves color values to ensure consistency across all custom field components.
 *
 * Supports:
 * - Semantic colors (primary, danger, gray, etc.)
 * - Hex colors (#ff0000)
 * - Color constant arrays (already full palettes)
 *
 * This ensures that badges, filters, and other components display the same
 * color for the same option, using Filament's color system.
 */
final class ColorResolver
{
    /**
     * Resolve a hex color to a full Filament color palette.
     *
     * @return array<int, string>|null
     */
    public static function hexToPalette(?string $hexColor): ?array
    {
        if (blank($hexColor)) {
            return null;
        }

        if (! str_starts_with($hexColor, '#') || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hexColor)) {
            return null;
        }

        try {
            return Color::hex($hexColor);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get the accessible text color shade for a given color palette.
     * Uses Filament's BadgeComponent logic to ensure proper contrast.
     *
     * @param  array<int, string>  $palette
     */
    public static function getTextShade(array $palette): int
    {
        $component = new BadgeComponent;
        $colorMap = $component->getColorMap($palette);

        return $colorMap['text'];
    }

    /**
     * Convert an OKLCH color string to RGB format.
     *
     * @param  string  $oklch  OKLCH color string from Color palette (e.g., "oklch(0.72 0.11 178)")
     * @return string RGB color string (e.g., "rgb(107, 114, 128)")
     */
    public static function oklchToRgb(string $oklch): string
    {
        return Color::convertToRgb($oklch);
    }

    /**
     * Get badge-style colors (background and text) for a hex color.
     * Returns the same colors that Filament badges use for visual consistency.
     * Returns gray colors if hex color is null or invalid.
     *
     * @return array{background: string, text: string}
     */
    public static function getBadgeColors(?string $hexColor): array
    {
        $palette = self::hexToPalette($hexColor);

        if ($palette === null) {
            return self::getGrayBadgeColors();
        }

        $textShade = self::getTextShade($palette);

        return [
            'background' => self::oklchToRgb($palette[50]),
            'text' => self::oklchToRgb($palette[$textShade]),
        ];
    }

    /**
     * Get gray badge colors for arbitrary/uncolored values.
     *
     * @return array{background: string, text: string}
     */
    public static function getGrayBadgeColors(): array
    {
        /** @var array<int, string> $grayPalette */
        $grayPalette = FilamentColor::getColor('gray');

        return [
            'background' => self::oklchToRgb($grayPalette[100]),
            'text' => self::oklchToRgb($grayPalette[600]),
        ];
    }
}
