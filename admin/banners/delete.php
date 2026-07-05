<?php
/**
 * ==========================================================================
 * admin/banners/delete.php — Delete Banner Action
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('banners.manage');

$pdo = db();
$bannerId = (int) input('id', '0', 'get');

if ($bannerId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT title, image_path FROM banners WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $bannerId]);
        $b = $stmt->fetch();

        if ($b) {
            $del = $pdo->prepare("DELETE FROM banners WHERE id = :id");
            $del->execute(['id' => $bannerId]);

            // Safe delete file from disk
            $filePath = __DIR__ . '/../../public/uploads/banners/' . $b['image_path'];
            if (file_exists($filePath) && is_file($filePath)) {
                unlink($filePath);
            }

            log_admin_activity('banners.delete', "Deleted banner: '{$b['title']}'");
            flash('banner_msg', 'Banner deleted successfully.', 'success');
        }
    } catch (PDOException $e) {
        error_log('[admin/banners/delete] failed: ' . $e->getMessage());
        flash('banner_msg', 'Failed to delete banner.', 'error');
    }
}

header('Location: index.php');
exit;
