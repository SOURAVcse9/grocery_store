<?php
/**
 * ==========================================================================
 * admin/layouts/header.php — Admin Dashboard Layout Header
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();

$__admin = current_admin();
$__admin_avatar = !empty($__admin['avatar']) ? asset('uploads/users/' . $__admin['avatar']) : asset('images/ui/placeholder.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= $pageTitle ?? 'Admin Panel — ' . site_name() ?></title>
    
    <!-- Inter Google Font & FontAwesome Pack -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Base Storefront stylesheet tokens + Dedicated Admin panel layout css -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/../admin/assets/css/admin.css">
    
    <!-- Chart.js CDN for responsive analytics widget integration -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">
    <div class="admin-layout-wrapper">
