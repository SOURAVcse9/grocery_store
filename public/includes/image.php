<?php
/**
 * ==========================================================================
 * public/includes/image.php — Production Image Helpers & Responsive Engine
 * ==========================================================================
 * Formats image ALT tags dynamically, outputs srcset properties, and provides
 * responsive image rendering with Core Web Vitals (CLS/LCP) optimization.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/cloudinary.php';

/**
 * generate_image_alt()
 *
 * Generates clean, human-readable, non-stuffed image ALT tags according
 * to SEO guidelines. Allows admin custom alt override.
 */
function generate_image_alt(string $productName, ?string $customAlt = null): string
{
    if (!empty($customAlt)) {
        $cleanCustom = trim(preg_replace('/\s+/', ' ', $customAlt));
        if ($cleanCustom !== '') {
            return htmlspecialchars($cleanCustom, ENT_QUOTES, 'UTF-8');
        }
    }

    $clean = trim(preg_replace('/\s+/', ' ', $productName));
    return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
}

/**
 * get_responsive_srcset()
 *
 * Compiles a list of responsive image sizes for screens of varying pixel densities,
 * improving mobile loading times and eliminating unnecessary desktop bandwidth consumption.
 */
function get_responsive_srcset(?string $imageFilename, string $subfolder = 'products'): string
{
    if (empty($imageFilename)) {
        return '';
    }

    // 1. If Cloudinary is configured or the asset is on Cloudinary
    if (CloudinaryService::isConfigured() || str_contains($imageFilename, 'res.cloudinary.com') || str_starts_with($imageFilename, 'groco/')) {
        return CloudinaryService::srcset($imageFilename, [300, 500, 800, 1200], $subfolder);
    }

    // 2. Local file variant fallback
    $originalUrl = image_url($imageFilename, $subfolder);
    $parts = pathinfo($imageFilename);
    $ext = $parts['extension'] ?? 'jpg';
    $filename = $parts['filename'] ?? '';

    $smallName = $filename . '_300w.' . $ext;
    $mediumName = $filename . '_600w.' . $ext;

    $baseUpload = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/uploads/' : __DIR__ . '/../uploads/';
    $smallPath = $baseUpload . $subfolder . '/' . $smallName;
    $mediumPath = $baseUpload . $subfolder . '/' . $mediumName;

    $srcset = [];
    if (file_exists($smallPath)) {
        $srcset[] = asset('uploads/' . $subfolder . '/' . $smallName) . ' 300w';
    }
    if (file_exists($mediumPath)) {
        $srcset[] = asset('uploads/' . $subfolder . '/' . $mediumName) . ' 600w';
    }

    $srcset[] = $originalUrl . ' 800w';

    return implode(', ', $srcset);
}

/**
 * render_optimized_product_image()
 *
 * Outputs a production-ready responsive <img> tag with explicit width, height,
 * aspect-ratio style to eliminate CLS, and appropriate fetchpriority for LCP.
 */
function render_optimized_product_image(
    ?string $imageIdentifier,
    string $productName,
    mixed $arg3 = null,
    mixed $arg4 = null,
    mixed $arg5 = null,
    int $width = 300,
    int $height = 300,
    string $sizes = '(max-width: 576px) 50vw, (max-width: 992px) 33vw, 280px'
): string {
    $customAlt = null;
    $className = 'product-image';
    $isLcp = false;

    // Pattern A: ($id, $name, 300, 300, ['is_lcp' => true, ...])
    if (is_int($arg3)) {
        $width  = (int)$arg3;
        $height = is_int($arg4) ? (int)$arg4 : $width;
        if (is_array($arg5)) {
            $isLcp     = !empty($arg5['is_lcp']);
            $className = $arg5['class'] ?? $className;
            $customAlt = $arg5['alt'] ?? null;
            $sizes     = $arg5['sizes'] ?? $sizes;
        }
    }
    // Pattern B: ($id, $name, ['alt' => '...', 'is_lcp' => true, ...])
    elseif (is_array($arg3)) {
        $customAlt = $arg3['alt'] ?? null;
        $className = $arg3['class'] ?? $className;
        $isLcp     = !empty($arg3['is_lcp']);
        $width     = isset($arg3['width']) ? (int)$arg3['width'] : $width;
        $height    = isset($arg3['height']) ? (int)$arg3['height'] : $height;
        $sizes     = $arg3['sizes'] ?? $sizes;
    }
    // Pattern C: ($id, $name, 'alt text', 'product-image', false, 300, 300)
    else {
        $customAlt = is_string($arg3) ? $arg3 : null;
        $className = is_string($arg4) ? $arg4 : $className;
        $isLcp     = is_bool($arg5) ? $arg5 : false;
    }

    $alt = generate_image_alt($productName, $customAlt);
    $src = CloudinaryService::url($imageIdentifier, ['w' => $width, 'h' => $height, 'c' => 'fill', 'f' => 'auto', 'q' => 'auto'], 'products');
    $srcset = get_responsive_srcset($imageIdentifier, 'products');

    $loading = $isLcp ? 'eager' : 'lazy';
    $fetchpriority = $isLcp ? 'fetchpriority="high"' : '';
    $decoding = 'decoding="async"';

    $srcsetAttribute = !empty($srcset) ? 'srcset="' . htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8') . '" sizes="' . htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') . '"' : '';

    $aspectRatio = ($width === $height) ? '1/1' : "{$width}/{$height}";

    return sprintf(
        '<img src="%s" %s alt="%s" class="%s" width="%d" height="%d" loading="%s" %s %s style="aspect-ratio: %s; object-fit: contain;">',
        htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
        $srcsetAttribute,
        $alt,
        htmlspecialchars($className, ENT_QUOTES, 'UTF-8'),
        $width,
        $height,
        $loading,
        $fetchpriority,
        $decoding,
        $aspectRatio
    );
}

/**
 * getProductImageUrl()
 *
 * Primary image URL resolver supporting custom dimensions with automatic
 * format, quality compression, and resilient placeholder fallback.
 */
function getProductImageUrl(?string $identifier, int $width = 500, int $height = 500): string
{
    if (empty($identifier)) {
        return function_exists('asset') ? asset('images/ui/placeholder.png') : '/assets/images/ui/placeholder.png';
    }
    return CloudinaryService::url($identifier, [
        'w' => $width,
        'h' => $height,
        'c' => 'fill',
        'f' => 'auto',
        'q' => 'auto'
    ], 'products');
}

/**
 * getThumbnailUrl()
 *
 * Fast 300x300 card & cart thumbnail URL resolver.
 */
function getThumbnailUrl(?string $identifier): string
{
    return getProductImageUrl($identifier, 300, 300);
}

/**
 * getProductDetailImageUrl()
 *
 * High-definition 800x800 product gallery hero resolver.
 */
function getProductDetailImageUrl(?string $identifier): string
{
    return getProductImageUrl($identifier, 800, 800);
}

/**
 * getOgImageUrl()
 *
 * Social media Open Graph (1200x630) social share card image resolver.
 */
function getOgImageUrl(?string $identifier): string
{
    if (empty($identifier)) {
        return function_exists('asset') ? asset('images/ui/logo.png') : '/assets/images/ui/logo.png';
    }
    return CloudinaryService::url($identifier, [
        'w' => 1200,
        'h' => 630,
        'c' => 'fill',
        'f' => 'auto',
        'q' => 'auto'
    ], 'products');
}

