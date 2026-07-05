<?php
/**
 * ==========================================================================
 * footer.php
 * ==========================================================================
 * Closes </main> opened by header.php, renders the footer, toast mount
 * point, and core scripts. The including page may set $extraScripts
 * (array of paths under assets/js/) BEFORE requiring this file to load
 * page-specific JS (cart.js, checkout.js, etc.) after app.js/toast.js.
 * ==========================================================================
 */

declare(strict_types=1);
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand"><i class="fas fa-leaf"></i> <?= e(site_name()) ?></div>
                <p class="footer-about"><?= e(t('about_text')) ?></p>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div>
                <h3 class="footer-heading"><?= e(t('quick_links')) ?></h3>
                <ul class="footer-links">
                    <li><a href="<?= url_for('index.php') ?>"><?= e(t('home')) ?></a></li>
                    <li><a href="<?= url_for('products.php') ?>"><?= e(t('shop')) ?></a></li>
                    <li><a href="<?= url_for('categories.php') ?>"><?= e(t('categories')) ?></a></li>
                    <li><a href="<?= url_for('account.php') ?>"><?= e(t('account')) ?></a></li>
                    <li><a href="<?= url_for('orders.php') ?>"><?= e(t('orders')) ?></a></li>
                    <li><a href="<?= url_for('faq.php') ?>">FAQ</a></li>
                </ul>
            </div>

            <div>
                <h3 class="footer-heading"><?= e(t('contact_info')) ?></h3>
                <ul class="footer-contact">
                    <li><i class="fas fa-location-dot"></i> <span><?= e(CONTACT_ADDRESS) ?></span></li>
                    <li><i class="fas fa-envelope"></i> <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a></li>
                    <li><i class="fas fa-phone"></i> <a href="tel:<?= e(CONTACT_PHONE) ?>"><?= e(CONTACT_PHONE) ?></a></li>
                    <li><i class="fas fa-clock"></i> <span>Sat – Fri: 8:00 AM – 10:00 PM</span></li>
                </ul>
            </div>

            <div>
                <h3 class="footer-heading"><?= e(t('newsletter')) ?></h3>
                <p class="footer-about"><?= e(t('newsletter_text')) ?></p>
                <form class="newsletter-form" action="<?= url_for('contact.php') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="email" name="newsletter_email" placeholder="<?= e(t('newsletter_placeholder')) ?>" required>
                    <button type="submit" class="btn btn-primary"><?= e(t('subscribe')) ?></button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; <?= date('Y') ?> <strong><?= e(site_name()) ?></strong>. <?= e(t('rights_reserved')) ?></div>
            <div class="footer-payments">
                <i class="fab fa-cc-visa" title="Visa"></i>
                <i class="fab fa-cc-mastercard" title="MasterCard"></i>
                <i class="fas fa-money-bill-wave" title="Cash on Delivery"></i>
            </div>
        </div>
    </div>
</footer>

<button id="backToTop" class="back-to-top" aria-label="Back to top">
    <i class="fas fa-arrow-up"></i>
</button>

<?php include PUBLIC_PATH . '/components/install-app.php'; ?>
<?php require_once PUBLIC_PATH . '/components/toast.php'; ?>

<script src="<?= asset('js/toast.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/notifications.js') ?>"></script>
<script src="<?= asset('js/lazyload.js') ?>"></script>
<script src="<?= asset('js/performance.js') ?>"></script>
<script src="<?= asset('js/pwa.js') ?>"></script>
<script src="<?= asset('js/security.js') ?>"></script>
<script src="<?= asset('js/newsletter.js') ?>"></script>
<?php if (!empty($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $__js): ?>
        <script src="<?= asset($__js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
