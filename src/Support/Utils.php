<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

final class Utils
{
    public static function getResourceCluster(): ?string
    {
        return config('custom-fields.management.cluster');
    }

    public static function getResourceSlug(): string
    {
        return config('custom-fields.management.slug', 'custom-fields');
    }

    public static function getResourceNavigationSort(): ?int
    {
        return config('custom-fields.management.navigation_sort', -1);
    }

    public static function isResourceNavigationGroupEnabled(): bool
    {
        return config('custom-fields.management.navigation_group_enabled', true);
    }

    /**
     * Determine the text color (black or white) based on background color for optimal contrast.
     *
     * @param  string  $backgroundColor  The background color in hex format (e.g., '#FF5500')
     * @return string The text color in hex format ('#000000' for black or '#FFFFFF' for white)
     */
    public static function getTextColor(string $backgroundColor): string
    {
        // Strip the leading # if present
        $backgroundColor = ltrim($backgroundColor, '#');

        // Convert hex to RGB
        $r = hexdec(substr($backgroundColor, 0, 2));
        $g = hexdec(substr($backgroundColor, 2, 2));
        $b = hexdec(substr($backgroundColor, 4, 2));

        // Calculate luminance (perceived brightness)
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Return black for light colors, white for dark colors
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
}
