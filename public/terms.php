<?php
/**
 * ==========================================================================
 * public/terms.php — Terms of Service Page
 * ==========================================================================
 * Standard terms agreement statement regarding user registration accounts,
 * purchasing guidelines, delivery commitments, and return policies.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pdo = db();
$cmsPage = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE page_key = 'terms' LIMIT 1");
    $stmt->execute();
    $cmsPage = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[storefront/terms] ' . $e->getMessage());
}

// SEO Details
$pageTitle = ($cmsPage && !empty($cmsPage['meta_title'])) ? $cmsPage['meta_title'] : ('Terms of Service — ' . site_name());
$pageDescription = ($cmsPage && !empty($cmsPage['meta_description'])) ? $cmsPage['meta_description'] : 'Review the Terms of Service of GroCo.';

$canonicalUrl = current_url();
$ogImage = asset('images/ui/logo.png');

$breadcrumbs = [
    ['title' => 'Terms of Service']
];

require_once __DIR__ . '/header.php';
?>

<style>
  .legal-hero {
    background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
    color: var(--color-surface);
    padding: 60px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .legal-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.15) 0%, transparent 60%);
    pointer-events: none;
  }
  .legal-hero h1 {
    font-size: var(--fs-2xl);
    font-weight: 800;
    margin-bottom: var(--space-2);
    letter-spacing: -0.5px;
  }
  .legal-hero p {
    font-size: var(--fs-md);
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
  }

  .legal-section {
    padding: var(--space-6) 0 var(--space-8) 0;
    background: var(--color-bg);
  }

  .legal-wrapper {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6) var(--space-7);
    box-shadow: var(--shadow-sm);
    max-width: 900px;
    margin: 0 auto;
  }
  .legal-wrapper h2 {
    font-size: var(--fs-lg);
    font-weight: 700;
    color: var(--color-primary-dark);
    margin: var(--space-5) 0 var(--space-2) 0;
  }
  .legal-wrapper h2:first-of-type {
    margin-top: 0;
  }
  .legal-wrapper p {
    color: var(--color-text-muted);
    font-size: var(--fs-sm);
    line-height: 1.7;
    margin-bottom: var(--space-4);
  }
  .legal-wrapper ul {
    margin-bottom: var(--space-4);
    padding-left: var(--space-4);
    list-style-type: disc;
  }
  .legal-wrapper ul li {
    font-size: var(--fs-sm);
    color: var(--color-text-muted);
    line-height: 1.6;
    margin-bottom: var(--space-1);
  }
  .last-updated {
    font-size: var(--fs-xs);
    color: var(--color-text-faint);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: var(--space-5);
    display: block;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: var(--space-3);
  }
</style>

<section class="legal-hero">
    <div class="container">
        <h1>Terms of Service</h1>
        <p>Please read these Terms of Service carefully before using our storefront or placing orders.</p>
    </div>
</section>

<!-- Include Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<section class="legal-section">
    <div class="container">
        <div class="legal-wrapper">
            <span class="last-updated">Last Updated: <?= date('F d, Y', strtotime($cmsPage['updated_at'] ?? 'now')) ?></span>
            <?= $cmsPage ? $cmsPage['content'] : 'No terms of service content available.' ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
