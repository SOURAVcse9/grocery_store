<?php
/**
 * ==========================================================================
 * public/includes/error_handler.php — System Error & Exception Interceptor
 * ==========================================================================
 * Registers global handlers to silence raw backtraces in production and
 * renders custom templates.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/logger.php';

// Turn off display of PHP notices/warnings to client browsers
ini_set('display_errors', '0');
error_reporting(E_ALL);

/**
 * exception_handler()
 *
 * Logs details of uncaught exceptions and prints a clean, styled HTTP 500 page.
 */
function exception_handler(Throwable $e): void
{
    // Log exception metadata using our audit logger helper
    $logMsg = sprintf(
        "%s in %s on line %d | Code: %s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getCode()
    );
    log_action('FATAL_EXCEPTION', $logMsg);

    // Set correct HTTP 500 error code
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }

    // Clear any previous output buffers to avoid broken layouts
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Render a friendly error page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Internal Server Error — Groco Store</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            :root {
                --color-primary: #0b7285;
                --color-text: #212529;
                --color-text-muted: #868e96;
                --color-bg: #f8f9fa;
                --radius-pill: 50px;
                --radius-lg: 12px;
            }
            body {
                font-family: 'Inter', sans-serif;
                background-color: var(--color-bg);
                color: var(--color-text);
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
                text-align: center;
            }
            .error-card {
                background: #ffffff;
                border: 1px solid #dee2e6;
                border-radius: var(--radius-lg);
                padding: 48px 32px;
                max-width: 480px;
                width: 100%;
                box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            }
            .error-icon {
                font-size: 64px;
                color: #fa5252;
                margin-bottom: 24px;
            }
            h1 {
                font-size: 20px;
                font-weight: 800;
                margin: 0 0 12px 0;
            }
            p {
                font-size: 13px;
                color: var(--color-text-muted);
                line-height: 1.6;
                margin: 0 0 32px 0;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background-color: var(--color-primary);
                color: #ffffff;
                border: none;
                border-radius: var(--radius-pill);
                padding: 12px 28px;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                transition: opacity 150ms ease;
            }
            .btn:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><i class="fas fa-circle-exclamation"></i></div>
            <h1>Something Went Wrong</h1>
            <p>We encountered an unexpected error processing your request. Our engineering team has been notified and is currently investigating.</p>
            <a href="index.php" class="btn"><i class="fas fa-house"></i> Go to Homepage</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * error_handler()
 *
 * Catches PHP warning/notices, converting them to ErrorExceptions to ensure
 * consistency.
 */
function error_handler(int $severity, string $message, string $file, int $line): void
{
    // Ignore suppressed errors (with @ operator)
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}

// Register global handlers
set_exception_handler('exception_handler');
set_error_handler('error_handler');
