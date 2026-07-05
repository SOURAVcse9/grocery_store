<?php
/**
 * ==========================================================================
 * public/components/breadcrumb.php
 * ==========================================================================
 * Reusable breadcrumb navigation component.
 * Expects:
 *   - $breadcrumbs (array): list of items where each item is an array with
 *                           'title' and optional 'link' keys.
 * ==========================================================================
 */

declare(strict_types=1);

if (empty($breadcrumbs) || !is_array($breadcrumbs)) {
    return;
}
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
            <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="<?= url_for('index.php') ?>" itemprop="item">
                    <span itemprop="name"><i class="fas fa-house"></i> <?= e(t('home') ?? 'Home') ?></span>
                </a>
                <meta itemprop="position" content="1">
            </li>
            
            <?php 
            $position = 2;
            $count = count($breadcrumbs);
            foreach ($breadcrumbs as $index => $item): 
                $isLast = ($index === $count - 1);
                $title = $item['title'] ?? '';
                $link = $item['link'] ?? null;
            ?>
                <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <?php if ($link && !$isLast): ?>
                        <a href="<?= e(url_for($link)) ?>" itemprop="item">
                            <span itemprop="name"><?= e($title) ?></span>
                        </a>
                    <?php else: ?>
                        <span itemprop="name"><?= e($title) ?></span>
                    <?php endif; ?>
                    <meta itemprop="position" content="<?= $position++ ?>">
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>
