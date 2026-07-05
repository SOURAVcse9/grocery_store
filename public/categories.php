<?php
/**
 * ==========================================================================
 * public/categories.php — Product Categories Listing Page
 * ==========================================================================
 * Displays all active root categories with their respective product counts,
 * category cards, banner header, and breadcrumbs.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// SEO details
$pageTitle = 'Product Categories — ' . site_name();
$pageDescription = 'Browse products by category. Find fresh fruits, vegetables, organic foods, meat, dairy, snacks, cooking ingredients and household essentials.';

$canonicalUrl = current_url();
$ogImage = asset('images/ui/logo.png');

$breadcrumbs = [
    ['title' => 'Categories']
];

try {
    $pdo = db();
    
    // Fetch all active parent categories with product counts
    $stmt = $pdo->query('
        SELECT c.id, c.name, c.slug, c.image, COUNT(p.id) AS product_count 
        FROM categories c 
        LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
        WHERE c.is_active = 1 AND c.parent_id IS NULL
        GROUP BY c.id 
        ORDER BY c.name ASC
    ');
    $categories = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('[categories.php] Database load error: ' . $e->getMessage());
    $categories = [];
}

$extraStylesheets = ['css/home.css'];

require_once __DIR__ . '/header.php';
?>

<!-- Custom styles for Categories page layout -->
<style>
  .categories-hero {
    background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
    color: var(--color-surface);
    padding: 60px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .categories-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.15) 0%, transparent 60%);
    pointer-events: none;
  }
  .categories-hero h1 {
    font-size: var(--fs-2xl);
    font-weight: 800;
    margin-bottom: var(--space-2);
    letter-spacing: -0.5px;
  }
  .categories-hero p {
    font-size: var(--fs-md);
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
  }
  
  .categories-container {
    padding: var(--space-6) 0 var(--space-8) 0;
    background: var(--color-bg);
  }

  .empty-categories-state {
    text-align: center;
    padding: var(--space-7) var(--space-5);
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    max-width: 600px;
    margin: 0 auto;
    border: 1px solid var(--color-border);
  }
  .empty-categories-icon {
    font-size: 60px;
    color: var(--color-text-faint);
    margin-bottom: var(--space-4);
  }
  .empty-categories-state h2 {
    font-size: var(--fs-lg);
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: var(--space-2);
  }
  .empty-categories-state p {
    color: var(--color-text-muted);
    font-size: var(--fs-sm);
    margin-bottom: var(--space-5);
  }
</style>

<!-- Hero Section -->
<section class="categories-hero">
    <div class="container">
        <h1>All Categories</h1>
        <p>Explore our wide selection of fresh, organic, and premium grocery essentials sorted by category.</p>
    </div>
</section>

<!-- Breadcrumb component -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<!-- Categories Main Grid Section -->
<div class="categories-container">
    <div class="container">
        <?php if (!empty($categories)): ?>
            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <?php include PUBLIC_PATH . '/components/category-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-categories-state">
                <div class="empty-categories-icon"><i class="fas fa-folder-open"></i></div>
                <h2>No Categories Found</h2>
                <p>We are currently updating our store directory. Please check back later or search for products directly.</p>
                <a href="<?= url_for('products.php') ?>" class="btn btn-primary" style="border-radius: var(--radius-pill); font-weight:700; padding:12px 32px; border:none; text-decoration:none; display:inline-block;">Browse Catalog</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
