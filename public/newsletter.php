<?php
/**
 * ==========================================================================
 * public/newsletter.php
 * ==========================================================================
 * POST processor for newsletter subscription requests.
 * Records subscriptions in contact_messages as opt-ins to respect the
 * final schema, responding with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

$email = trim(input('email', ''));

$v = new Validator();
$v->required('email', $email, 'Email address is required.')
  ->email('email', $email);

if ($v->hasErrors()) {
    json_response(false, $v->first(), [], 422);
}

try {
    $pdo = db();

    // Check if already subscribed to prevent duplications
    $check = $pdo->prepare('
        SELECT id FROM contact_messages 
        WHERE email = :email AND subject = \'Newsletter Opt-in\' 
        LIMIT 1
    ');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        json_response(true, 'You are already subscribed to our newsletter list! Thank you.', [
            'already_subscribed' => true
        ]);
    }

    // Insert as a new message tracking opt-in
    $insert = $pdo->prepare('
        INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
        VALUES (\'Newsletter Subscriber\', :email, \'Newsletter Opt-in\', \'User subscribed to newsletter updates.\', 0, NOW())
    ');
    $insert->execute(['email' => $email]);

    json_response(true, 'Thank you! You have successfully subscribed to our newsletter list.');

} catch (PDOException $e) {
    error_log('[newsletter.php] Subscription failed: ' . $e->getMessage());
    json_response(false, 'Failed to process subscription due to database error.', [], 500);
}
