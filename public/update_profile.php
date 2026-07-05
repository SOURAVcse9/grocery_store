<?php
/**
 * ==========================================================================
 * public/update_profile.php — Customer Profile POST Action Processor
 * ==========================================================================
 * Sanitizes details, runs database uniqueness checks, processes avatar image
 * uploads securely, and saves profile records.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure endpoint access
require_login();
require_method('POST');
verify_csrf_or_fail();

$user = current_user();
$userId = (int) $user['id'];

$fullName = trim(input('full_name', ''));
$email = trim(input('email', ''));
$phone = trim(input('phone', ''));

// --------------------------------------------------------------------------
// 1. Inputs validation
// --------------------------------------------------------------------------
$v = new Validator();
$v->required('full_name', $fullName, 'Full name is required.')
  ->length('full_name', $fullName, 2, 100, 'Name must be between 2 and 100 characters.')
  ->required('email', $email, 'Email address is required.')
  ->email('email', $email)
  ->required('phone', $phone, 'Phone number is required.')
  ->phone('phone', $phone);

if ($v->hasErrors()) {
    flash('profile', $v->first(), 'error');
    set_old_input($_POST);
    redirect(url_for('profile.php'));
}

try {
    $pdo = db();

    // Check duplicate email (excluding current user)
    $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :uid LIMIT 1');
    $emailStmt->execute(['email' => $email, 'uid' => $userId]);
    if ($emailStmt->fetch()) {
        flash('profile', 'An account with this email address already exists.', 'error');
        set_old_input($_POST);
        redirect(url_for('profile.php'));
    }

    // Check duplicate phone (excluding current user)
    $phoneStmt = $pdo->prepare('SELECT id FROM users WHERE phone = :phone AND id != :uid LIMIT 1');
    $phoneStmt->execute(['phone' => $phone, 'uid' => $userId]);
    if ($phoneStmt->fetch()) {
        flash('profile', 'An account with this phone number already exists.', 'error');
        set_old_input($_POST);
        redirect(url_for('profile.php'));
    }

    // --------------------------------------------------------------------------
    // 2. Process Avatar Photo Upload
    // --------------------------------------------------------------------------
    $avatarDbValue = $user['avatar']; // keep existing if no upload

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];

        // Validate uploaded image via security library helper (checks size, mime, dimensions)
        if (!validate_uploaded_image($file, 2 * 1024 * 1024)) {
            flash('profile', 'Invalid avatar image. Must be JPG, PNG, or WEBP, under 2MB.', 'error');
            set_old_input($_POST);
            redirect(url_for('profile.php'));
        }

        // Get extension based on validated mime type
        $mime = mime_content_type($file['tmp_name']);
        $ext = 'jpg';
        if ($mime === 'image/png') $ext = 'png';
        if ($mime === 'image/webp') $ext = 'webp';

        // Crop and resize to 250x250 square using GD if available
        if (extension_loaded('gd')) {
            $srcImage = null;
            if ($mime === 'image/jpeg') {
                $srcImage = imagecreatefromjpeg($file['tmp_name']);
            } elseif ($mime === 'image/png') {
                $srcImage = imagecreatefrompng($file['tmp_name']);
            } elseif ($mime === 'image/webp') {
                $srcImage = imagecreatefromwebp($file['tmp_name']);
            }

            if ($srcImage) {
                $width = imagesx($srcImage);
                $height = imagesy($srcImage);
                $newWidth = 250;
                $newHeight = 250;

                $dstImage = imagecreatetruecolor($newWidth, $newHeight);
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($dstImage, false);
                    imagesavealpha($dstImage, true);
                }

                $minSize = min($width, $height);
                $srcX = (int)(($width - $minSize) / 2);
                $srcY = (int)(($height - $minSize) / 2);

                imagecopyresampled($dstImage, $srcImage, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $minSize, $minSize);

                if ($mime === 'image/jpeg') {
                    imagejpeg($dstImage, $file['tmp_name'], 90);
                } elseif ($mime === 'image/png') {
                    imagepng($dstImage, $file['tmp_name']);
                } elseif ($mime === 'image/webp') {
                    imagewebp($dstImage, $file['tmp_name'], 90);
                }

                imagedestroy($srcImage);
                imagedestroy($dstImage);
            }
        }

        $avatarFilename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $uploadDir = PUBLIC_PATH . '/../storage/uploads/users';

        // Create uploads folder if missing
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move temporary file
        $targetPath = $uploadDir . '/' . $avatarFilename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Delete old avatar if it exists
            if (!empty($user['avatar'])) {
                if (str_starts_with($user['avatar'], 'storage/')) {
                    $oldPath = PUBLIC_PATH . '/../' . $user['avatar'];
                } else {
                    $oldPath = PUBLIC_PATH . '/uploads/avatars/' . $user['avatar'];
                }
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $avatarDbValue = 'storage/uploads/users/' . $avatarFilename;
        } else {
            flash('profile', 'Failed to save uploaded file.', 'error');
            set_old_input($_POST);
            redirect(url_for('profile.php'));
        }
    }

    // --------------------------------------------------------------------------
    // 3. Update User DB Record
    // --------------------------------------------------------------------------
    $updateStmt = $pdo->prepare('
        UPDATE users 
        SET full_name = :name, email = :email, phone = :phone, avatar = :avatar, updated_at = NOW() 
        WHERE id = :uid
    ');
    $updateStmt->execute([
        'name'   => $fullName,
        'email'  => $email,
        'phone'  => $phone,
        'avatar' => $avatarDbValue,
        'uid'    => $userId
    ]);

    flash('profile', 'Profile details updated successfully.', 'success');
    clear_old_input();
    
} catch (PDOException $e) {
    error_log('[update_profile.php] Error: ' . $e->getMessage());
    flash('profile', 'A database error occurred. Please try again.', 'error');
}

redirect(url_for('profile.php'));
