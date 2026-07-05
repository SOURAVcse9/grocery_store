<?php
/**
 * ==========================================================================
 * public/includes/image.php — Responsive Image & Optimization Helpers
 * ==========================================================================
 * Formats image ALT tags dynamically, outputs srcset properties, and provides
 * fallback files.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * generate_image_alt()
 *
 * Cleans name strings into SEO-friendly, descriptive image ALT tags.
 */
function generate_image_alt(string $productName): string
{
    $clean = trim(preg_replace('/\s+/', ' ', $productName));
    return htmlspecialchars($clean . ' — buy fresh organic groceries online');
}

/**
 * get_responsive_srcset()
 *
 * Compiles a list of responsive image sizes (e.g. small, medium, original)
 * for screens of varying pixel densities, improving loading times on mobile devices.
 */
function get_responsive_srcset(string $imageFilename, string $subfolder = 'products'): string
{
    if (empty($imageFilename)) {
        return '';
    }

    $originalUrl = image_url($imageFilename, $subfolder);
    
    // In our file structure, if we have pre-resized images, we link them.
    // For standard products, we can fall back to the main image or check for size variations.
    $parts = pathinfo($imageFilename);
    $ext = $parts['extension'] ?? 'jpg';
    $filename = $parts['filename'] ?? '';

    $smallName = $filename . '_300w.' . $ext;
    $mediumName = $filename . '_600w.' . $ext;

    $smallPath = PUBLIC_PATH . '/uploads/' . $subfolder . '/' . $smallName;
    $mediumPath = PUBLIC_PATH . '/uploads/' . $subfolder . '/' . $mediumName;

    $srcset = [];

    if (file_exists($smallPath)) {
        $srcset[] = asset('uploads/' . $subfolder . '/' . $smallName) . ' 300w';
    }
    if (file_exists($mediumPath)) {
        $srcset[] = asset('uploads/' . $subfolder . '/' . $mediumName) . ' 600w';
    }

    // Always include the original image as fallback
    $srcset[] = $originalUrl . ' 900w';

    return implode(', ', $srcset);
}
