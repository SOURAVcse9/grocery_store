<?php
/**
 * ==============================================================================
 * public/includes/cloudinary.php — Production Cloudinary Media & CDN Service
 * ==============================================================================
 * High-performance, secure Cloudinary integration service.
 * Supports signed uploads, safe replacements, asset destructions, dynamic
 * responsive URLs (f_auto, q_auto, dpr_auto), transformations, and local fallbacks.
 * Credentials strictly enforced server-side. Never exposed to clients.
 * ==============================================================================
 */

declare(strict_types=1);

final class CloudinaryService
{
    private static ?string $cloudName = null;
    private static ?string $apiKey = null;
    private static ?string $apiSecret = null;
    private static bool $bootstrapped = false;

    /**
     * Allowed upload folders structure
     */
    public const FOLDERS = [
        'products'   => 'groco/products',
        'categories' => 'groco/categories',
        'brands'     => 'groco/brands',
        'banners'    => 'groco/banners',
        'users'      => 'groco/users',
        'blog'       => 'groco/blog'
    ];

    /**
     * Standard responsive breakpoints
     */
    public const BREAKPOINT_THUMB = 150;
    public const BREAKPOINT_CARD  = 300;
    public const BREAKPOINT_LIST  = 500;
    public const BREAKPOINT_DETAIL = 800;
    public const BREAKPOINT_LARGE = 1200;

    /**
     * Maximum allowed upload size: 5MB
     */
    public const MAX_BYTES = 5242880;

    /**
     * Permitted MIME types and extension mapping
     */
    public const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif'
    ];

    /**
     * Bootstrap credentials from environment
     */
    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : null);
        self::$apiKey    = getenv('CLOUDINARY_API_KEY') ?: (defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : null);
        self::$apiSecret = getenv('CLOUDINARY_API_SECRET') ?: (defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : null);

        // Optional single URL format: cloudinary://api_key:api_secret@cloud_name
        $url = getenv('CLOUDINARY_URL') ?: (defined('CLOUDINARY_URL') ? CLOUDINARY_URL : null);
        if ($url && !self::$cloudName) {
            $parsed = parse_url($url);
            if ($parsed && isset($parsed['host'])) {
                self::$cloudName = $parsed['host'];
                self::$apiKey    = $parsed['user'] ?? null;
                self::$apiSecret = $parsed['pass'] ?? null;
            }
        }

        self::$bootstrapped = true;
    }

    /**
     * Determine if Cloudinary has valid API credentials configured
     */
    public static function isConfigured(): bool
    {
        self::bootstrap();
        return !empty(self::$cloudName) && !empty(self::$apiKey) && !empty(self::$apiSecret);
    }

    /**
     * Validate image file strictly on the server before attempting upload
     *
     * @param array $file $_FILES element
     * @return array [mime, ext, width, height, size]
     * @throws InvalidArgumentException on validation failure
     */
    public static function validateUpload(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new InvalidArgumentException('Invalid file upload parameters.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds PHP upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds HTML form MAX_FILE_SIZE directive.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No image file was selected.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
            ];
            throw new InvalidArgumentException($errors[$file['error']] ?? ('Upload failed with error code ' . $file['error']));
        }

        if ($file['size'] > self::MAX_BYTES) {
            throw new InvalidArgumentException('Image file size exceeds maximum 5MB limit.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Security violation: uploaded file is not a legitimate HTTP POST upload.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new InvalidArgumentException('Unsupported image format: ' . htmlspecialchars((string)$mime) . '. Only JPG, PNG, WebP, and AVIF are permitted.');
        }

        // Verify image integrity and dimensions
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new InvalidArgumentException('File corrupted or not a valid image.');
        }

        return [
            'mime'   => $mime,
            'ext'    => self::ALLOWED_MIMES[$mime],
            'width'  => (int)$imageInfo[0],
            'height' => (int)$imageInfo[1],
            'size'   => (int)$file['size']
        ];
    }

    /**
     * Upload an image to Cloudinary (or fallback to local disk)
     *
     * @param array $file $_FILES element
     * @param string $folderCategory products|categories|brands|banners|users
     * @param string|null $customSlug Optional slug to create descriptive public ID
     * @return array Standardized result [driver, url, public_id, width, height, format, bytes]
     */
    public static function upload(array $file, string $folderCategory = 'products', ?string $customSlug = null): array
    {
        self::bootstrap();
        $validated = self::validateUpload($file);

        $folder = self::FOLDERS[$folderCategory] ?? ('groco/' . $folderCategory);

        // Generate clean, descriptive public ID
        $cleanSlug = $customSlug ? preg_replace('/[^a-z0-9_-]/', '', strtolower($customSlug)) : 'asset';
        $publicId = $cleanSlug . '-' . bin2hex(random_bytes(4));

        if (self::isConfigured()) {
            return self::executeSignedUpload($file['tmp_name'], $folder, $publicId, $validated);
        }

        // Fallback to local storage if Cloudinary credentials are not configured
        return self::storeLocal($file, $folderCategory, $validated);
    }

    /**
     * Execute signed REST API upload to Cloudinary
     */
    private static function executeSignedUpload(string $tmpPath, string $folder, string $publicId, array $validated): array
    {
        $timestamp = time();
        $params = [
            'folder'    => $folder,
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

        $postFields = $params;
        $postFields['api_key']   = self::$apiKey;
        $postFields['signature'] = $signature;
        $postFields['file']      = new CURLFile($tmpPath, $validated['mime'], basename($tmpPath));

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
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            $errMsg = $curlErr ?: ('API HTTP ' . $httpCode . ': ' . $response);
            error_log('[CloudinaryService] Upload failure: ' . $errMsg);
            throw new RuntimeException('Cloudinary upload rejected: ' . $errMsg);
        }

        $data = json_decode($response, true);
        if (!$data || empty($data['secure_url'])) {
            throw new RuntimeException('Invalid response payload from Cloudinary.');
        }

        return [
            'driver'     => 'cloudinary',
            'public_id'  => $data['public_id'],
            'url'        => $data['secure_url'],
            'secure_url' => $data['secure_url'],
            'width'      => (int)($data['width'] ?? $validated['width']),
            'height'     => (int)($data['height'] ?? $validated['height']),
            'format'     => (string)($data['format'] ?? $validated['ext']),
            'bytes'      => (int)($data['bytes'] ?? $validated['size'])
        ];
    }

    /**
     * Local storage fallback
     */
    private static function storeLocal(array $file, string $folderCategory, array $validated): array
    {
        $baseDir = defined('PUBLIC_PATH') ? PUBLIC_PATH . '/uploads/' . $folderCategory : dirname(__DIR__) . '/uploads/' . $folderCategory;
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $validated['ext'];
        $destPath = $baseDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Failed to move uploaded file into local storage.');
        }

        $url = function_exists('url_for') ? url_for('uploads/' . $folderCategory . '/' . $filename) : ('/uploads/' . $folderCategory . '/' . $filename);

        return [
            'driver'     => 'local',
            'public_id'  => $folderCategory . '/' . $filename,
            'url'        => $url,
            'secure_url' => $url,
            'width'      => $validated['width'],
            'height'     => $validated['height'],
            'format'     => $validated['ext'],
            'bytes'      => $validated['size'],
            'filename'   => $filename
        ];
    }

    /**
     * Delete an asset from Cloudinary by public ID
     */
    public static function delete(string $publicId): bool
    {
        self::bootstrap();

        if (empty($publicId)) {
            return false;
        }

        if (!self::isConfigured()) {
            // Local fallback deletion
            $filename = basename($publicId);
            $parts = explode('/', ltrim($publicId, '/'));
            $folder = (count($parts) > 1) ? $parts[0] : 'products';
            $localFile = (defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__)) . '/uploads/' . $folder . '/' . $filename;
            if (file_exists($localFile)) {
                return @unlink($localFile);
            }
            return true;
        }

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
        $signature = sha1(implode('&', $sigParts) . self::$apiSecret);

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

        return ($httpCode === 200);
    }

    /**
     * Safely replace an existing asset:
     * 1. Upload new image.
     * 2. Confirm successful upload.
     * 3. Return new metadata so database can be updated.
     * 4. Delete old Cloudinary asset using old public ID AFTER database update.
     */
    public static function replace(array $newFile, ?string $oldPublicId, string $folderCategory = 'products', ?string $customSlug = null): array
    {
        // 1. Upload new
        $uploadResult = self::upload($newFile, $folderCategory, $customSlug);

        // 2. Schedule old asset deletion safely (or delete immediately if upload was successful)
        if (!empty($oldPublicId) && $oldPublicId !== $uploadResult['public_id']) {
            try {
                self::delete($oldPublicId);
            } catch (Throwable $e) {
                error_log('[CloudinaryService] Warning: Failed to prune old asset: ' . $e->getMessage());
            }
        }

        return $uploadResult;
    }

    /**
     * Build an ultra-fast optimized delivery URL with Cloudinary transformations
     *
     * @param string|null $identifier Cloudinary public_id, Cloudinary URL, or local filename
     * @param array $transforms e.g. ['w' => 500, 'h' => 500, 'c' => 'fill', 'f' => 'auto', 'q' => 'auto', 'dpr' => 'auto']
     * @param string $category Folder fallback
     * @return string Fully qualified, transformed URL
     */
    public static function url(?string $identifier, array $transforms = [], string $category = 'products'): string
    {
        if (empty($identifier)) {
            return function_exists('asset') ? asset('images/ui/placeholder.png') : '/assets/images/ui/placeholder.png';
        }

        self::bootstrap();

        // 1. If it's already an absolute Cloudinary URL
        if (str_contains($identifier, 'res.cloudinary.com')) {
            return self::injectTransformsIntoCloudinaryUrl($identifier, $transforms);
        }

        // 2. If it's an external URL (e.g. Google avatar)
        if (filter_var($identifier, FILTER_VALIDATE_URL)) {
            return $identifier;
        }

        // 3. If identifier is a Cloudinary public ID or Cloudinary is configured
        if (str_starts_with($identifier, 'groco/') || (self::isConfigured() && !str_contains($identifier, '.'))) {
            $cloud = self::$cloudName ?: 'groco-demo';
            $transformString = self::buildTransformSegment($transforms);
            return 'https://res.cloudinary.com/' . $cloud . '/image/upload/' . $transformString . ltrim($identifier, '/');
        }

        // 4. Local asset fallback
        if (function_exists('image_url')) {
            return image_url($identifier, $category);
        }

        return '/' . ltrim($identifier, '/');
    }

    /**
     * Build standard responsive srcset map for an image
     *
     * @param string|null $identifier
     * @param array $widths [300, 500, 800, 1200]
     * @param string $category
     * @return string e.g. "url-300 300w, url-500 500w, ..."
     */
    public static function srcset(?string $identifier, array $widths = [150, 300, 500, 800, 1200], string $category = 'products'): string
    {
        if (empty($identifier)) {
            return '';
        }

        $parts = [];
        foreach ($widths as $w) {
            $transforms = [
                'w'   => $w,
                'c'   => 'limit',
                'f'   => 'auto',
                'q'   => 'auto',
                'dpr' => 'auto'
            ];
            if ($category === 'categories') {
                $transforms['h'] = $w;
                $transforms['c'] = 'fill';
                $transforms['g'] = 'auto';
            }
            $url = self::url($identifier, $transforms, $category);
            $parts[] = "{$url} {$w}w";
        }

        return implode(', ', $parts);
    }

    /**
     * Build Cloudinary transform URL segment
     */
    private static function buildTransformSegment(array $t): string
    {
        $segments = [];

        // Automatic format & quality defaults
        $f = $t['f'] ?? ($t['format'] ?? 'auto');
        $q = $t['q'] ?? ($t['quality'] ?? 'auto');
        $dpr = $t['dpr'] ?? 'auto';

        $segments[] = "f_{$f}";
        $segments[] = "q_{$q}";
        $segments[] = "dpr_{$dpr}";

        $w = $t['w'] ?? ($t['width'] ?? null);
        if ($w !== null) {
            $segments[] = 'w_' . (int)$w;
        }
        $h = $t['h'] ?? ($t['height'] ?? null);
        if ($h !== null) {
            $segments[] = 'h_' . (int)$h;
        }
        $c = $t['c'] ?? ($t['crop'] ?? null);
        if ($c !== null) {
            $crop = preg_replace('/[^a-z_]/', '', (string)$c);
            $segments[] = "c_{$crop}";
        }
        $g = $t['g'] ?? ($t['gravity'] ?? null);
        if ($g !== null) {
            $gravity = preg_replace('/[^a-z0-9_]/', '', (string)$g);
            $segments[] = "g_{$gravity}";
        }
        $r = $t['r'] ?? ($t['radius'] ?? null);
        if ($r !== null) {
            $radius = preg_replace('/[^a-z0-9_]/', '', (string)$r);
            $segments[] = "r_{$radius}";
        }

        return implode(',', $segments) . '/';
    }

    /**
     * Inject dynamic transforms into an existing Cloudinary URL
     */
    private static function injectTransformsIntoCloudinaryUrl(string $url, array $transforms): string
    {
        $transformString = self::buildTransformSegment($transforms);
        // Match /image/upload/(v12345/)? and inject transforms
        if (preg_match('#(/image/upload/)(?:[^/]+,\w+/)?(v[0-9]+/)?(.*)$#i', $url, $m)) {
            return 'https://res.cloudinary.com/' . self::$cloudName . '/image/upload/' . $transformString . ($m[2] ?? '') . $m[3];
        }
        return preg_replace('#/image/upload/#', '/image/upload/' . $transformString, $url, 1);
    }
}

// --------------------------------------------------------------------------
// Convenient global helper aliases for Cloudinary & Media rendering
// --------------------------------------------------------------------------

function cloudinary_url(?string $identifier, array $transforms = [], string $category = 'products'): string
{
    return CloudinaryService::url($identifier, $transforms, $category);
}

function cloudinary_srcset(?string $identifier, array $widths = [300, 500, 800, 1200], string $category = 'products'): string
{
    return CloudinaryService::srcset($identifier, $widths, $category);
}
