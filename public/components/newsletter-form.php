<?php
/**
 * ==========================================================================
 * public/components/newsletter-form.php
 * ==========================================================================
 * Reusable Newsletter Subscription Form Widget.
 * Styled via public/assets/css/newsletter.css.
 * ==========================================================================
 */

declare(strict_types=1);
?>
<div class="newsletter-subscription-box" id="newsletterBox">
    <!-- Left Column: Content -->
    <div class="newsletter-text-content">
        <h3>Join Our Newsletter</h3>
        <p>Subscribe to receive weekly fresh updates, seasonal discounts, and get <strong>10% off</strong> on your next purchase!</p>
    </div>

    <!-- Right Column: Form -->
    <div class="newsletter-form-content">
        <form action="<?= url_for('newsletter.php') ?>" method="post" id="newsletterForm" class="newsletter-inline-form">
            <?= csrf_field() ?>
            <div class="newsletter-input-group">
                <i class="newsletter-mail-icon far fa-envelope"></i>
                <input type="email" 
                       name="email" 
                       placeholder="Enter your email address..." 
                       required 
                       aria-label="Email address for subscription"
                       autocomplete="email">
                <button type="submit" class="btn btn-primary" id="btnSubscribeNewsletter">
                    <i class="far fa-paper-plane"></i> Subscribe
                </button>
            </div>
        </form>
    </div>
</div>
