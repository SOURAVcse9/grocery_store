<?php
/**
 * ==============================================================================
 * GroCo Modern Media Service & Cloudinary/Local Pipeline
 * ==============================================================================
 * High-performance, secure media manager supporting Cloudinary CDN integration
 * with automated WebP/AVIF format negotiation, dynamic transformations, responsive
 * srcsets, server-side MIME & dimension validation, and seamless local fallback.
 * ==============================================================================
 */

declare(strict_types=1);

class MediaService
{
    private static ?string $cloudName = null;
    private static ?string $apiKey = null;
    private static ?string $apiSecret = null;
    private static bool $initialized = false;

    /**
     * Allowed public upload categories and their storage folder hierarchy
     */
    public const ALLOWED_FOLDERS = [
        'products'   => 'groco/products',
        'categories' => 'groco/categories',
        'brands'     => 'groco/brands',
        'banners'    => 'groco/banners',
        'reviews'    => 'groco/reviews',
        'avatars'    => 'groco/avatars'
    ];

    /**
     * Maximum allowed file size in bytes (5MB)
     */
    public const MAX_FILE_SIZE = 5242880;

    /**
     * Allowed MIME types and extensions for public imagery
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/avif' => 'avif'
    ];

    /**
     * Initialize Cloudinary credentials from environment variables / constants
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : null);
        self::$apiKey = getenv('CLOUDINARY_API_KEY') ?: (defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : null);
        self::$apiSecret = getenv('CLOUDINARY_API_SECRET') ?: (defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : null);

        // Parse CLOUDINARY_URL if provided as a single string (cloudinary://api_key:api_secret@cloud_name)
        $cloudinaryUrl = getenv('CLOUDINARY_URL') ?: (defined('CLOUDINARY_URL') ? CLOUDINARY_URL : null);
        if ($cloudinaryUrl && !self::$cloudName) {
            $parsed = parse_url($cloudinaryUrl);
            if ($parsed && isset($parsed['host'])) {
                self::$cloudName = $parsed['host'];
                self::$apiKey = $parsed['user'] ?? null;
                self::$apiSecret = $parsed['pass'] ?? null;
            }
        }

        self::$initialized = true;
    }

    /**
     * Check if Cloudinary is configured with valid credentials
     */
    public static function isCloudinaryConfigured(): bool
    {
        self::init();
        return !empty(self::$cloudName) && !empty(self::$apiKey) && !empty(self::$apiSecret);
    }

    /**
     * Validate an uploaded image file on the server
     *
     * @param array $file $_FILES['input_name'] array
     * @throws RuntimeException on validation failure
     */
    public static function validateImage(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid file upload parameters.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload error code: ' . $file['error']);
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new RuntimeException('File size exceeds the 5MB maximum limit.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Uploaded file is not a valid HTTP POST upload.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
            throw new RuntimeException('Invalid file type: ' . htmlspecialchars($mime) . '. Only JPG, PNG, WebP, AVIF, and GIF are permitted.');
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new RuntimeException('File contents could not be verified as a valid image.');
        }

        return [
            'mime'   => $mime,
            'ext'    => self::ALLOWED_MIME_TYPES[$mime],
            'width'  => $imageInfo[0] ?? 0,
            'height' => $imageInfo[1] ?? 0
        ];
    }

    /**
     * Upload an image to Cloudinary (if configured) or local storage
     *
     * @param array $file $_FILES element
     * @param string $category One of products, categories, brands, banners, reviews, avatars
     * @param array $options Additional upload parameters
     * @return array Standardized upload result
     */
    public static function upload(array $file, string $category = 'products', array $options = []): array
    {
        self::init();
        $validated = self::validateImage($file);
        $folder = self::ALLOWED_FOLDERS[$category] ?? 'groco/misc';

        // 1. If Cloudinary is available, execute signed API upload
        if (self::isCloudinaryConfigured()) {
            return self::uploadToCloudinary($file['tmp_name'], $folder, $options);
        }

        // 2. Fallback to hardened local disk storage
        return self::uploadToLocal($file, $category, $validated);
    }

    /**
     * Upload directly to Cloudinary using signed REST API
     */
    private static function uploadToCloudinary(string $tmpPath, string $folder, array $options = []): array
    {
        $timestamp = time();
        $params = [
            'folder'    => $folder,
            'timestamp' => $timestamp,
        ];

        if (!empty($options['public_id'])) {
            $params['public_id'] = $options['public_id'];
        }
        if (!empty($options['tags'])) {
            $params['tags'] = is_array($options['tags']) ? implode(',', $options['tags']) : $options['tags'];
        }

        // Generate SHA-1 signature
        ksort($params);
        $sigParts = [];
        foreach ($params as $k => $v) {
            $sigParts[] = "{$k}={$v}";
        }
        $sigString = implode('&', $sigParts) . self::$apiSecret;
        $signature = sha1($sigString);

        $postFields = $params;
        $postFields['api_key'] = self::$apiKey;
        $postFields['signature'] = $signature;
        $postFields['file'] = new CURLFile($tmpPath);

        $endpoint = 'https://api.cloudinary.com/v1_1/' . self::$cloudName . '/image/upload';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            error_log("Cloudinary Upload Error [HTTP {$httpCode}]: " . ($curlError ?: $response));
            throw new RuntimeException('Cloudinary upload failed: ' . ($curlError ?: 'API returned error HTTP ' . $httpCode));
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['secure_url'])) {
            throw new RuntimeException('Invalid JSON payload returned by Cloudinary upload API.');
        }

        return [
            'driver'     => 'cloudinary',
            'public_id'  => $data['public_id'],
            'url'        => $data['secure_url'],
            'secure_url' => $data['secure_url'],
            'format'     => $data['format'] ?? 'jpg',
            'width'      => $data['width'] ?? 0,
            'height'     => $data['height'] ?? 0,
            'bytes'      => $data['bytes'] ?? 0,
            'filename'   => basename($data['secure_url'])
        ];
    }

    /**
     * Upload to local hardened disk storage
     */
    private static function uploadToLocal(array $file, string $category, array $validated): array
    {
        $baseUploadDir = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/uploads' : __DIR__ . '/../uploads';
        $targetDir = $baseUploadDir . '/' . $category;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $uniqueName = bin2hex(random_bytes(16)) . '_' . time() . '.' . $validated['ext'];
        $targetPath = $targetDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to move uploaded file to local destination directory.');
        }

        $relativePath = 'uploads/' . $category . '/' . $uniqueName;
        $url = function_exists('asset') ? asset($relativePath) : '/' . $relativePath;

        return [
            'driver'     => 'local',
            'public_id'  => $category . '/' . $uniqueName,
            'url'        => $url,
            'secure_url' => $url,
            'format'     => $validated['ext'],
            'width'      => $validated['width'],
            'height'     => $validated['height'],
            'bytes'      => filesize($targetPath),
            'filename'   => $uniqueName
        ];
    }

    /**
     * Delete an image from Cloudinary or local disk
     */
    public static function delete(string $identifier, string $category = 'products'): bool
    {
        self::init();

        // 1. If identifier is a Cloudinary public ID or Cloudinary URL
        if (strpos($identifier, 'http') === 0 || strpos($identifier, 'groco/') === 0) {
            if (self::isCloudinaryConfigured()) {
                return self::deleteFromCloudinary($identifier);
            }
        }

        // 2. Local deletion
        $filename = basename($identifier);
        $baseUploadDir = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/uploads' : __DIR__ . '/../uploads';
        $targetPath = $baseUploadDir . '/' . $category . '/' . $filename;

        if (file_exists($targetPath)) {
            return @unlink($targetPath);
        }

        return true;
    }

    /**
     * Delete an asset from Cloudinary via Admin API
     */
    private static function deleteFromCloudinary(string $publicId): bool
    {
        $timestamp = time();
        $params = [
            'public_id' => $publicId,
            'timestamp' => $timestamp
        ];

        ksort($params);
        $sigParts = [];
        foreach ($params as $k => $v) {
            $sigParts[] = "{$k}={$v}";
        }
        $sigString = implode('&', $sigParts) . self::$apiSecret;
        $signature = sha1($sigString);

        $postFields = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'api_key'   => self::$apiKey,
            'signature' => $signature
        ];

        $endpoint = 'https://api.cloudinary.com/v1_1/' . self::$cloudName . '/image/destroy';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Generate an optimized media URL with transformations
     */
    public static function getUrl(string $imageIdentifier, array $transformations = [], string $category = 'products'): string
    {
        if (empty($imageIdentifier)) {
            return function_exists('asset') ? asset('assets/images/placeholder.webp') : '/assets/images/placeholder.webp';
        }

        // If it's already an absolute URL
        if (filter_var($imageIdentifier, FILTER_VALIDATE_URL)) {
            if (strpos($imageIdentifier, 'res.cloudinary.com') !== false && !empty($transformations)) {
                return self::applyCloudinaryTransformsToUrl($imageIdentifier, $transformations);
            }
            return $imageIdentifier;
        }

        // If Cloudinary is configured and it's a Cloudinary public ID
        if (self::isCloudinaryConfigured() && strpos($imageIdentifier, 'groco/') === 0) {
            $transformString = self::buildCloudinaryTransformString($transformations);
            return "https://res.cloudinary.com/" . self::$cloudName . "/image/upload/{$transformString}" . $imageIdentifier;
        }

        // Local asset fallback
        $filename = basename($imageIdentifier);
        return function_exists('asset') ? asset('uploads/' . $category . '/' . $filename) : '/uploads/' . $category . '/' . $filename;
    }

    /**
     * Build responsive image srcset with widths
     */
    public static function getResponsiveUrls(string $imageIdentifier, array $widths = [300, 600, 900], string $category = 'products'): array
    {
        $urls = [];
        foreach ($widths as $w) {
            $urls[$w] = self::getUrl($imageIdentifier, ['w' => $w, 'c' => 'fill', 'f' => 'auto', 'q' => 'auto'], $category);
        }
        return $urls;
    }

    /**
     * Render a standard responsive HTML <img> tag with srcset, lazy loading, and dimension hints
     */
    public static function renderResponsiveImage(
        string $imageIdentifier,
        string $alt = '',
        array $attrs = [],
        string $category = 'products'
    ): string {
        $src = self::getUrl($imageIdentifier, ['w' => 600, 'f' => 'auto', 'q' => 'auto'], $category);
        $responsiveMap = self::getResponsiveUrls($imageIdentifier, [300, 600, 900], $category);

        $srcsetStrings = [];
        foreach ($responsiveMap as $w => $url) {
            $srcsetStrings[] = "{$url} {$w}w";
        }
        $srcset = implode(', ', $srcsetStrings);

        $defaultSizes = '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw';
        $sizes = $attrs['sizes'] ?? $defaultSizes;
        $loading = $attrs['loading'] ?? 'lazy';
        $decoding = $attrs['decoding'] ?? 'async';
        $class = $attrs['class'] ?? '';
        $width = $attrs['width'] ?? '600';
        $height = $attrs['height'] ?? '600';

        $escapedAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        $escapedClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<img src="%s" srcset="%s" sizes="%s" alt="%s" class="%s" width="%s" height="%s" loading="%s" decoding="%s">',
            htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8'),
            $escapedAlt,
            $escapedClass,
            htmlspecialchars((string)$width, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string)$height, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($loading, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($decoding, ENT_QUOTES, 'UTF-8')
        );
    }

    private static function buildCloudinaryTransformString(array $t): string
    {
        if (empty($t)) {
            return 'f_auto,q_auto/';
        }
        $parts = [];
        if (isset($t['w'])) $parts[] = 'w_' . (int)$t['w'];
        if (isset($t['h'])) $parts[] = 'h_' . (int)$t['h'];
        if (isset($t['c'])) $parts[] = 'c_' . preg_replace('/[^a-z_]/', '', $t['c']);
        $parts[] = 'f_' . ($t['f'] ?? 'auto');
        $parts[] = 'q_' . ($t['q'] ?? 'auto');

        return implode(',', $parts) . '/';
    }

    private static function applyCloudinaryTransformsToUrl(string $url, array $transforms): string
    {
        $transformString = self::buildCloudinaryTransformString($transforms);
        return preg_replace('#/image/upload/(v[0-9]+/)?#', '/image/upload/' . $transformString . '$1', $url);
    }
}
