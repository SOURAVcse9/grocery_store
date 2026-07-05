<?php
/**
 * ==========================================================================
 * public/includes/security.php — Security Infrastructure
 * ==========================================================================
 * Sets cookie parameters, checks password complexity, and validates images
 * upload payloads.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * secure_session_start()
 *
 * Configures cookie security options to block session hijacking and CSRF.
 */
function secure_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    session_name($isSecure ? '__Host-groco_session' : 'groco_session');

    session_set_cookie_params([
        'lifetime' => 0, // session-only
        'path'     => '/',
        'domain'   => '', // omit to force Host-only cookie binding
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    // Regenerate session IDs periodically to block fixation attacks
    if (!isset($_SESSION['session_created_time'])) {
        $_SESSION['session_created_time'] = time();
    } elseif (time() - $_SESSION['session_created_time'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['session_created_time'] = time();
    }
}

/**
 * validate_password_strength()
 *
 * Enforces strong password criteria: minimum 8 characters, at least 1 letter, and 1 number.
 */
function validate_password_strength(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }
    // Must contain letters
    if (!preg_match('/[a-zA-Z]/', $password)) {
        return false;
    }
    // Must contain numbers
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    return true;
}

/**
 * validate_uploaded_image()
 *
 * Rigorous image file payload validations: checks size constraints, MIME types,
 * extensions, and file dimensions to block malicious execution attempts.
 */
function validate_uploaded_image(array $file, int $maxSize = 2097152): bool
{
    // Check error codes
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return false;
    }

    // Check size limit
    if ($file['size'] > $maxSize) {
        return false;
    }

    $tempPath = $file['tmp_name'];

    // Verify file mime type using mime_content_type
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $mime = mime_content_type($tempPath);

    if (!in_array($mime, $allowedMimes, true)) {
        return false;
    }

    // Verify file extension
    $parts = pathinfo($file['name']);
    $ext = strtolower($parts['extension'] ?? '');
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowedExtensions, true)) {
        return false;
    }

    // Verify actual image dimensions (blocks dummy files)
    $dimensions = getimagesize($tempPath);
    if ($dimensions === false) {
        return false;
    }

    // Double check dimensions are positive integers
    $width = $dimensions[0] ?? 0;
    $height = $dimensions[1] ?? 0;
    if ($width <= 0 || $height <= 0) {
        return false;
    }

    return true;
}
