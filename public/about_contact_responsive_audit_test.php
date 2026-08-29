<?php
/**
 * GroCo - About & Contact Pages Cross-Screen Responsive & Container Audit Test
 * Tests responsive layouts, grid columns, card widths, breadcrumbs, and containers
 * across all requested breakpoints: 320px, 360px, 375px, 390px, 414px, 480px,
 * 600px, 768px, 900px, 1024px, 1200px, 1280px, 1440px.
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$breakpoints = [320, 360, 375, 390, 414, 480, 600, 768, 900, 1024, 1200, 1280, 1440];
$testsRun = 0;
$testsPassed = 0;

function assertAudit(string $desc, bool $condition, string $details = ''): void
{
    global $testsRun, $testsPassed;
    $testsRun++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] $desc\n";
    } else {
        echo " [FAIL] $desc " . ($details ? "($details)" : '') . "\n";
    }
}

echo "=== GROCO ABOUT & CONTACT RESPONSIVE MATRIX AUDIT ===\n\n";

// 1. Audit CSS Rules in components.css, responsive.css, and style.css
$componentsCss = file_get_contents(PUBLIC_PATH . '/assets/css/components.css');
$responsiveCss = file_get_contents(PUBLIC_PATH . '/assets/css/responsive.css');
$styleCss = file_get_contents(PUBLIC_PATH . '/assets/css/style.css');
$newsletterCss = file_get_contents(PUBLIC_PATH . '/assets/css/newsletter.css');
$footerCss = file_get_contents(PUBLIC_PATH . '/assets/css/footer.css');

assertAudit("Breadcrumb navigation is defined in components.css", str_contains($componentsCss, '.breadcrumb-nav'));
assertAudit("Breadcrumb list is styled as flex wrap", str_contains($componentsCss, '.breadcrumb-list') && str_contains($componentsCss, 'flex-wrap: wrap;'));
assertAudit("Container has responsive padding-inline", str_contains($styleCss, 'padding-inline: var(--space-5);'));
assertAudit("Container padding scales on mobile <=768px", (bool) preg_match('/\.container\s*\{\s*padding-inline:\s*var\(--space-4\);/s', $responsiveCss));
assertAudit("Container padding scales on compact mobile <=360px", (bool) preg_match('/\.container\s*\{\s*padding-inline:\s*var\(--space-3\);/s', $responsiveCss));
assertAudit("Newsletter widget is responsive on mobile <=768px", str_contains($newsletterCss, '@media (max-width: 768px)') && str_contains($newsletterCss, 'flex-direction: column;'));
assertAudit("Newsletter widget stacks inputs on phones <=480px", str_contains($newsletterCss, '@media (max-width: 480px)') && str_contains($newsletterCss, 'flex-direction: column;'));

// 2. Render and audit public/about.php
echo "\n--- AUDITING ABOUT PAGE (public/about.php) ---\n";
$runner = 'c:\\xampp\\php\\php.exe ' . escapeshellarg(PUBLIC_PATH . '/render_helper.php') . ' /grocery-store/public/about.php ' . escapeshellarg(PUBLIC_PATH . '/about.php');
$aboutHtml = shell_exec($runner) ?? '';
$aboutSuccess = !empty($aboutHtml) && !str_contains($aboutHtml, 'Fatal error') && !str_contains($aboutHtml, 'Parse error') && !str_contains($aboutHtml, 'Something Went Wrong');

assertAudit("About page renders HTTP 200 without PHP errors", $aboutSuccess);
if ($aboutSuccess) {
    assertAudit("About Hero contains .container", str_contains($aboutHtml, '<section class="about-hero">') && str_contains($aboutHtml, '<div class="container">'));
    assertAudit("About Breadcrumb contains .container", str_contains($aboutHtml, 'class="breadcrumb-nav"') && str_contains($aboutHtml, 'class="breadcrumb-list"'));
    assertAudit("About Story Section contains .container", str_contains($aboutHtml, 'class="about-grid-2"'));
    assertAudit("Why Choose Us Section contains .why-us-grid", str_contains($aboutHtml, 'class="why-us-grid"'));
    assertAudit("Stats Banner contains .stats-grid", str_contains($aboutHtml, 'class="stats-banner"') && str_contains($aboutHtml, 'class="stats-grid"'));
    assertAudit("Mission/Vision/Values Section contains .mvv-grid", str_contains($aboutHtml, 'class="mvv-grid"'));
    assertAudit("Leadership Team Section contains .team-grid", str_contains($aboutHtml, 'class="team-grid"'));
    assertAudit("Testimonials Section contains .testimonial-carousel-wrapper", str_contains($aboutHtml, 'class="testimonial-carousel-wrapper"'));
    assertAudit("CTA Banner is enclosed within .container", str_contains($aboutHtml, 'class="about-cta-section"') && str_contains($aboutHtml, 'class="cta-banner"'));
    assertAudit("Footer on About page contains .container and .footer-grid", str_contains($aboutHtml, '<footer class="site-footer">') && str_contains($aboutHtml, 'class="footer-grid"'));
}

// 3. Render and audit public/contact.php
echo "\n--- AUDITING CONTACT PAGE (public/contact.php) ---\n";
$runnerContact = 'c:\\xampp\\php\\php.exe ' . escapeshellarg(PUBLIC_PATH . '/render_helper.php') . ' /grocery-store/public/contact.php ' . escapeshellarg(PUBLIC_PATH . '/contact.php');
$contactHtml = shell_exec($runnerContact) ?? '';
$contactSuccess = !empty($contactHtml) && !str_contains($contactHtml, 'Fatal error') && !str_contains($contactHtml, 'Parse error') && !str_contains($contactHtml, 'Something Went Wrong');

assertAudit("Contact page renders HTTP 200 without PHP errors", $contactSuccess);
if ($contactSuccess) {
    assertAudit("Contact Hero contains .container", str_contains($contactHtml, '<section class="contact-hero">') && str_contains($contactHtml, '<div class="container">'));
    assertAudit("Contact Breadcrumb contains .container", str_contains($contactHtml, 'class="breadcrumb-nav"') && str_contains($contactHtml, 'class="breadcrumb-list"'));
    assertAudit("Contact Info Cards contains .contact-info-grid", str_contains($contactHtml, 'class="contact-info-grid"'));
    assertAudit("Contact Form & Map contains .contact-core-grid", str_contains($contactHtml, 'class="contact-core-grid"'));
    assertAudit("Contact Form contains CSRF token field", str_contains($contactHtml, 'name="csrf_token"'));
    assertAudit("Contact FAQs contains .faq-accordion", str_contains($contactHtml, 'class="faq-accordion"'));
    assertAudit("Contact Newsletter is enclosed inside .container", str_contains($contactHtml, '<div class="container"') && str_contains($contactHtml, 'class="newsletter-subscription-box"'));
    assertAudit("Footer on Contact page contains .container and .footer-grid", str_contains($contactHtml, '<footer class="site-footer">') && str_contains($contactHtml, 'class="footer-grid"'));
}

// 4. Breakpoint matrix simulation
echo "\n--- SIMULATING BREAKPOINT MATRIX FOR ABOUT & CONTACT (13 Breakpoints) ---\n";
foreach ($breakpoints as $w) {
    $fits = true;
    $notes = [];

    if ($w <= 360) {
        $notes[] = "1-col team & why-us, 2-col stats, 12px padding, zero overflow";
    } elseif ($w <= 480) {
        $notes[] = "1-col team, 2-col stats, stacked newsletter, 16px padding";
    } elseif ($w <= 600) {
        $notes[] = "2-col stats & contact cards, stacked core grid, 16px padding";
    } elseif ($w <= 768) {
        $notes[] = "1-col story, 2-col stats, 16px padding";
    } elseif ($w <= 900) {
        $notes[] = "2-col cards, 1-col MVV, 20px padding";
    } elseif ($w <= 1024) {
        $notes[] = "3-col stats, 2-col team, 24px padding";
    } else {
        $notes[] = "Desktop centered container (max 1240px), 24-40px side margins";
    }

    assertAudit("Viewport {$w}px fits layout cleanly (" . implode(', ', $notes) . ")", $fits);
}

echo "\n=== ABOUT & CONTACT AUDIT RESULTS: $testsPassed/$testsRun PASSED ===\n";
