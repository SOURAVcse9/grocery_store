<?php
/**
 * ==========================================================================
 * public/faq.php — Frequently Asked Questions Page
 * ==========================================================================
 * Displays categorized accordion style questions and answers regarding orders,
 * payments, refunds, and delivery tracking.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// SEO Details
$pageTitle = 'Frequently Asked Questions — ' . site_name();
$pageDescription = 'Find answers to common questions about ordering, delivery speeds, payment methods, refund policies, and account settings.';

$canonicalUrl = current_url();
$ogImage = asset('images/ui/logo.png');

$breadcrumbs = [
    ['title' => 'FAQs']
];

require_once __DIR__ . '/header.php';
?>

<style>
  .faq-hero {
    background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
    color: var(--color-surface);
    padding: 60px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .faq-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.15) 0%, transparent 60%);
    pointer-events: none;
  }
  .faq-hero h1 {
    font-size: var(--fs-2xl);
    font-weight: 800;
    margin-bottom: var(--space-2);
    letter-spacing: -0.5px;
  }
  .faq-hero p {
    font-size: var(--fs-md);
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
  }

  .faq-section {
    padding: var(--space-6) 0 var(--space-8) 0;
    background: var(--color-bg);
  }

  .faq-layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: var(--space-6);
    align-items: start;
  }

  /* Sidebar Navigation */
  .faq-nav {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    box-shadow: var(--shadow-sm);
    position: sticky;
    top: 100px;
  }
  .faq-nav-title {
    font-size: var(--fs-sm);
    font-weight: 700;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: var(--space-3);
    padding-bottom: var(--space-2);
    border-bottom: 1px solid var(--color-border);
  }
  .faq-nav-list li {
    margin-bottom: var(--space-2);
  }
  .faq-nav-link {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: 10px 12px;
    font-size: var(--fs-sm);
    color: var(--color-text);
    border-radius: var(--radius-sm);
    font-weight: 600;
    transition: all var(--transition-fast);
  }
  .faq-nav-link:hover,
  .faq-nav-link.active {
    background: var(--color-primary-light);
    color: var(--color-primary-dark);
  }

  /* Accordions content area */
  .faq-group-wrapper {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--space-5);
  }
  .faq-group-title {
    font-size: var(--fs-lg);
    font-weight: 800;
    color: var(--color-primary-dark);
    margin-bottom: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-2);
  }

  .faq-item {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    margin-bottom: var(--space-3);
    overflow: hidden;
  }
  .faq-trigger {
    width: 100%;
    padding: var(--space-4);
    background: var(--color-surface);
    border: none;
    text-align: left;
    font-weight: 700;
    font-size: var(--fs-base);
    color: var(--color-text);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    outline: none;
  }
  .faq-trigger:hover {
    background: var(--color-bg);
  }
  .faq-trigger i {
    transition: transform var(--transition-fast);
    color: var(--color-primary);
  }
  .faq-item.is-open .faq-trigger i {
    transform: rotate(180deg);
  }
  .faq-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    background: var(--color-surface);
    padding: 0 var(--space-4);
    font-size: var(--fs-sm);
    color: var(--color-text-muted);
    line-height: 1.6;
  }
  .faq-item.is-open .faq-content {
    padding: 0 var(--space-4) var(--space-4);
    max-height: 300px;
  }

  @media (max-width: 768px) {
    .faq-layout {
      grid-template-columns: 1fr;
    }
    .faq-nav {
      position: relative;
      top: 0;
    }
  }
</style>

<!-- Hero Section -->
<section class="faq-hero">
    <div class="container">
        <h1>Frequently Asked Questions</h1>
        <p>Browse through commonly asked questions or choose a category below to get help instantly.</p>
    </div>
</section>

<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>
<?php
try {
    $faqsList = $pdo->query("SELECT * FROM faqs WHERE is_active = 1 ORDER BY category ASC, sort_order ASC")->fetchAll(PDO::FETCH_GROUP);
} catch (PDOException $e) {
    $faqsList = [];
}
?>
<section class="faq-section">
    <div class="container">
        <div class="faq-layout">
            
            <!-- Left: Sidebar navigation -->
            <nav class="faq-nav" aria-label="FAQ Categories">
                <div class="faq-nav-title">Categories</div>
                <ul class="faq-nav-list">
                    <?php 
                    $first = true;
                    foreach (array_keys($faqsList) as $catName): 
                        $catSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $catName));
                    ?>
                        <li><a href="#<?= $catSlug ?>" class="faq-nav-link <?= $first ? 'active' : '' ?>"><i class="fas fa-circle-question"></i> <?= e($catName) ?></a></li>
                    <?php 
                        $first = false;
                    endforeach; 
                    ?>
                </ul>
            </nav>

            <!-- Right: Accordion content -->
            <div class="faq-contents">
                <?php foreach ($faqsList as $catName => $itemsList): 
                    $catSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $catName));
                ?>
                    <div class="faq-group-wrapper" id="<?= $catSlug ?>">
                        <h2 class="faq-group-title"><i class="fas fa-circle-question"></i> <?= e($catName) ?> Questions</h2>
                        
                        <?php foreach ($itemsList as $f): ?>
                            <div class="faq-item">
                                <button type="button" class="faq-trigger"><?= e($f['question']) ?> <i class="fas fa-chevron-down"></i></button>
                                <div class="faq-content">
                                    <p><?= nl2br(e($f['answer'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
</section>

<!-- FAQ Accordion Action Scripts -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Accordion expand/collapse
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const trigger = item.querySelector('.faq-trigger');
        trigger.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');
            
            // Close other accordions in the same group
            const group = item.closest('.faq-group-wrapper');
            group.querySelectorAll('.faq-item').forEach(otherItem => {
                otherItem.classList.remove('is-open');
                otherItem.querySelector('.faq-content').style.maxHeight = null;
            });

            if (!isOpen) {
                item.classList.add('is-open');
                const content = item.querySelector('.faq-content');
                content.style.maxHeight = content.scrollHeight + "px";
            }
        });
    });

    // Side navigation active link highlighting on scroll or click
    const navLinks = document.querySelectorAll('.faq-nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        });
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
