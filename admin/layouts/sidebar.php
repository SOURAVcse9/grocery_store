<?php
/**
 * ==========================================================================
 * admin/layouts/sidebar.php — Admin Collapsible Navigation Menu Drawer
 * ==========================================================================
 */

declare(strict_types=1);

$currentUri = $_SERVER['REQUEST_URI'];
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Title -->
    <div class="sidebar-brand">
        <i class="fas fa-leaf"></i>
        <span>GroCo Admin</span>
    </div>

    <!-- Scrollable links menu (Permission Aware) -->
    <nav class="sidebar-menu" aria-label="Main Administration Navigation">
        <ul class="sidebar-menu-list">
            
            <!-- Dashboard -->
            <?php if (has_admin_permission('dashboard.view')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/index.php') ? 'active' : '' ?>">
                        <i class="fas fa-gauge"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Products -->
            <?php if (has_admin_permission('products.view')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/products/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/products/') ? 'active' : '' ?>">
                        <i class="fas fa-boxes-stacked"></i>
                        <span>Products</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Categories -->
            <?php if (has_admin_permission('products.view')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/categories/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/categories/') ? 'active' : '' ?>">
                        <i class="fas fa-folder-tree"></i>
                        <span>Categories</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Brands -->
            <?php if (has_admin_permission('products.view')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/brands/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/brands/') ? 'active' : '' ?>">
                        <i class="fas fa-tags"></i>
                        <span>Brands</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Orders -->
            <?php if (has_admin_permission('orders.view')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/orders/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/orders/') ? 'active' : '' ?>">
                        <i class="fas fa-receipt"></i>
                        <span>Orders</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Customers -->
            <?php if (has_admin_permission('customers.view')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/customers/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/customers/') ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Reviews -->
            <?php if (has_admin_permission('reviews.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/reviews/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/reviews/') ? 'active' : '' ?>">
                        <i class="fas fa-comment-dots"></i>
                        <span>Reviews</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Coupons -->
            <?php if (has_admin_permission('settings.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/coupons/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/coupons/') ? 'active' : '' ?>">
                        <i class="fas fa-ticket"></i>
                        <span>Coupons</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Reports -->
            <?php if (has_admin_permission('reports.view')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/reports/dashboard.php" class="sidebar-link <?= str_contains($currentUri, 'admin/reports/') ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Banners/CMS -->
            <?php if (has_admin_permission('settings.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/banners/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/banners/') ? 'active' : '' ?>">
                        <i class="fas fa-images"></i>
                        <span>CMS / Banners</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Settings -->
            <?php if (has_admin_permission('settings.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/settings/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/settings/') ? 'active' : '' ?>">
                        <i class="fas fa-sliders"></i>
                        <span>Settings</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Administrators -->
            <?php if (has_admin_permission('admins.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/admins/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/admins/') ? 'active' : '' ?>">
                        <i class="fas fa-user-shield"></i>
                        <span>Administrators</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Role Permissions -->
            <?php if (has_admin_permission('roles.manage')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/../admin/roles/index.php" class="sidebar-link <?= str_contains($currentUri, 'admin/roles/') ? 'active' : '' ?>">
                        <i class="fas fa-shield-halved"></i>
                        <span>Role Permissions</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Separator line -->
            <li style="border-top: 1px solid rgba(255, 255, 255, 0.08); margin-block: 8px;"></li>

            <!-- My Profile -->
            <li>
                <a href="<?= BASE_URL ?>/../admin/profile.php" class="sidebar-link <?= str_contains($currentUri, 'admin/profile.php') ? 'active' : '' ?>">
                    <i class="fas fa-circle-user"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <!-- Logout -->
            <li>
                <a href="<?= BASE_URL ?>/../admin/logout.php" class="sidebar-link text-danger" style="color:#f03e3e;">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </li>
            
        </ul>
    </nav>
</aside>
