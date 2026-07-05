<?php
/**
 * ==========================================================================
 * public/contact.php — Customer Service & Contact Form
 * ==========================================================================
 * Provides CSRF-secured contact form, location coordinates cards, interactive
 * FAQ controls, newsletter updates, and social link navigations.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$successMessage = '';
$errorMessage = '';

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf(false)) {
        $errorMessage = 'Security validation failed (CSRF token mismatch). Please refresh and try again.';
    } else {
        $name = trim(input('name', ''));
        $email = trim(input('email', ''));
        $phone = trim(input('phone', ''));
        $subject = trim(input('subject', ''));
        $message = trim(input('message', ''));

        // Input validations
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $errorMessage = 'Please fill in all required fields marked with an asterisk (*).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address (e.g. name@domain.com).';
        } else {
            try {
                // Save to database
                $stmtDb = db()->prepare("
                    INSERT INTO contact_messages (name, email, phone, subject, message, is_read, is_archived, created_at)
                    VALUES (:name, :email, :phone, :subject, :message, 0, 0, NOW())
                ");
                $stmtDb->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'subject' => $subject,
                    'message' => $message
                ]);

                // Safely save the contact submission to storage/logs/contact_submissions.json
                $storageDir = __DIR__ . '/../storage/logs';
                if (!is_dir($storageDir)) {
                    mkdir($storageDir, 0775, true);
                }
                
                $logFile = $storageDir . '/contact_submissions.json';
                $existing = [];
                if (file_exists($logFile)) {
                    $existing = json_decode(file_get_contents($logFile), true) ?? [];
                }

                $existing[] = [
                    'id' => uniqid('msg_'),
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'subject' => $subject,
                    'message' => $message,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $successMessage = 'Thank you, ' . htmlspecialchars($name) . '! Your message has been sent successfully. Our support team will respond shortly.';
                
                // Clear post inputs
                $_POST = [];
            } catch (Exception $e) {
                error_log('[contact.php] Submission log failed: ' . $e->getMessage());
                $errorMessage = 'An error occurred while saving your message. Please try again.';
            }
        }
    }
}

// SEO Details
$pageTitle = 'Contact Us — ' . site_name();
$pageDescription = 'Get in touch with GroCo customer support. Send a message, find our office locations, view our working hours, or check our FAQs.';

$canonicalUrl = current_url();
$ogImage = asset('images/ui/logo.png');

$breadcrumbs = [
    ['title' => 'Contact Us']
];

require_once __DIR__ . '/header.php';
?>

<style>
  .contact-hero {
    background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
    color: var(--color-surface);
    padding: 60px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .contact-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.15) 0%, transparent 60%);
    pointer-events: none;
  }
  .contact-hero h1 {
    font-size: var(--fs-2xl);
    font-weight: 800;
    margin-bottom: var(--space-2);
    letter-spacing: -0.5px;
  }
  .contact-hero p {
    font-size: var(--fs-md);
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
  }

  .contact-section {
    padding: var(--space-8) 0;
    background: var(--color-surface);
  }
  .contact-section.alt-bg {
    background: var(--color-bg);
  }

  /* Contact Information Cards */
  .contact-info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-4);
    margin-bottom: var(--space-7);
  }
  .contact-info-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-5);
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: all var(--transition-normal);
  }
  .contact-info-card:hover {
    transform: translateY(-5px);
    border-color: var(--color-primary);
    box-shadow: var(--shadow-md);
  }
  .contact-card-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-pill);
    background: var(--color-primary-light);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--fs-lg);
    margin: 0 auto var(--space-4);
  }
  .contact-info-card h3 {
    font-size: var(--fs-base);
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: var(--space-2);
  }
  .contact-info-card p {
    font-size: var(--fs-sm);
    color: var(--color-text-muted);
    line-height: 1.6;
  }

  /* Core Contact Grid */
  .contact-core-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: var(--space-6);
  }

  .contact-form-wrapper {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    box-shadow: var(--shadow-sm);
  }
  .contact-form-wrapper h2 {
    font-size: var(--fs-lg);
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: var(--space-5);
  }

  .alert {
    padding: var(--space-3) var(--space-4);
    border-radius: var(--radius-sm);
    font-size: var(--fs-sm);
    margin-bottom: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-weight: 600;
  }
  .alert-success {
    background: var(--color-primary-light);
    color: var(--color-primary-dark);
    border: 1px solid rgba(26, 157, 85, 0.2);
  }
  .alert-error {
    background: #fdf2f2;
    color: var(--color-danger);
    border: 1px solid rgba(224, 48, 62, 0.2);
  }

  /* Floating form styling matching address.php */
  .contact-form-group {
    position: relative;
    margin-bottom: var(--space-4);
  }
  .contact-form-group input,
  .contact-form-group textarea {
    width: 100%;
    padding: 14px 16px 14px 44px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: var(--fs-sm);
    background: var(--color-surface);
    color: var(--color-text);
    outline: none;
    transition: all var(--transition-fast);
  }
  .contact-form-group textarea {
    padding-left: 16px;
    resize: vertical;
    min-height: 150px;
  }
  .contact-form-group input:focus,
  .contact-form-group textarea:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(26, 157, 85, 0.15);
  }
  .contact-form-group i.input-icon {
    position: absolute;
    left: 16px;
    top: 17px;
    color: var(--color-text-faint);
    font-size: var(--fs-sm);
  }

  /* Google Map Container */
  .map-wrapper {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-3);
    box-shadow: var(--shadow-sm);
    height: 100%;
  }
  .map-iframe {
    width: 100%;
    height: 100%;
    min-height: 380px;
    border: none;
    border-radius: var(--radius-md);
  }

  /* FAQs Section */
  .contact-faq-section {
    margin-top: var(--space-8);
  }
  .faq-accordion {
    max-width: 800px;
    margin: 0 auto;
  }
  .faq-item {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    margin-bottom: var(--space-2);
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

  /* Social Share Sidebar/Block */
  .social-grid {
    display: flex;
    justify-content: center;
    gap: var(--space-3);
    margin-top: var(--space-6);
  }
  .social-btn {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-pill);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-surface);
    font-size: var(--fs-base);
    transition: transform var(--transition-fast), filter var(--transition-fast);
    text-decoration: none;
  }
  .social-btn:hover {
    transform: scale(1.1);
    filter: brightness(0.9);
  }
  .btn-fb { background: #1877f2; }
  .btn-insta { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%,#d6249f 60%,#285aec 90%); }
  .btn-yt { background: #ff0000; }
  .btn-link { background: #0a66c2; }
  .btn-wa { background: #25d366; }

  /* Responsive styling */
  @media (max-width: 1024px) {
    .contact-info-grid {
      grid-template-columns: repeat(2, 1fr);
    }
    .contact-core-grid {
      grid-template-columns: 1fr;
    }
  }
  @media (max-width: 576px) {
    .contact-info-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<!-- Hero Section -->
<section class="contact-hero">
    <div class="container">
        <h1><?= e(t('contact') ?? 'Contact Us') ?></h1>
        <p>Have questions, order issues, or feedback? Reach out to our customer happiness center anytime.</p>
    </div>
</section>

<!-- Include Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<section class="contact-section">
    <div class="container">
        
        <!-- Contact Cards -->
        <div class="contact-info-grid">
            <div class="contact-info-card">
                <div class="contact-card-icon"><i class="fas fa-map-location-dot"></i></div>
                <h3>Our Office</h3>
                <p>House 14, Road 11, Banani<br>Dhaka - 1213, Bangladesh</p>
            </div>
            <div class="contact-info-card">
                <div class="contact-card-icon"><i class="fas fa-phone-volume"></i></div>
                <h3>Phone Hotline</h3>
                <p>+880 9612 47626<br>(GroCo Customer Support)</p>
            </div>
            <div class="contact-info-card">
                <div class="contact-card-icon"><i class="fas fa-envelope-open-text"></i></div>
                <h3>Email Support</h3>
                <p>support@groco.com.bd<br>info@groco.com.bd</p>
            </div>
            <div class="contact-info-card">
                <div class="contact-card-icon"><i class="fas fa-clock"></i></div>
                <h3>Working Hours</h3>
                <p>Mon - Sun: 07:00 AM - 11:00 PM<br>(Delivery & Support Hours)</p>
            </div>
        </div>

        <div class="contact-core-grid">
            
            <!-- Left: Contact Form -->
            <div class="contact-form-wrapper">
                <h2>Send Us a Message</h2>

                <!-- Alerts -->
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= e($successMessage) ?></div>
                <?php endif; ?>
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?= e($errorMessage) ?></div>
                <?php endif; ?>

                <form action="<?= e(current_url()) ?>" method="POST" id="contactForm">
                    <!-- CSRF Protection Field -->
                    <?= csrf_field() ?>

                    <div class="contact-form-group">
                        <i class="input-icon fas fa-user"></i>
                        <input type="text" name="name" placeholder="Full Name *" required value="<?= e(old('name', '')) ?>">
                    </div>

                    <div class="contact-form-group">
                        <i class="input-icon fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address *" required value="<?= e(old('email', '')) ?>">
                    </div>

                    <div class="contact-form-group">
                        <i class="input-icon fas fa-phone"></i>
                        <input type="text" name="phone" placeholder="Phone Number (Optional)" value="<?= e(old('phone', '')) ?>">
                    </div>

                    <div class="contact-form-group">
                        <i class="input-icon fas fa-circle-info"></i>
                        <input type="text" name="subject" placeholder="Subject *" required value="<?= e(old('subject', '')) ?>">
                    </div>

                    <div class="contact-form-group">
                        <textarea name="message" placeholder="Write your message here... *" required><?= e(old('message', '')) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-loading-state" style="border-radius: var(--radius-pill); font-weight:700; padding:14px 40px; border:none; cursor:pointer; width:100%;">
                        <span class="loading-spinner-form"></span>
                        <span class="btn-text-content">Submit Message</span>
                    </button>
                </form>
            </div>

            <!-- Right: Map Placeholder -->
            <div class="map-wrapper">
                <!-- OpenStreetMap or Google Maps Iframe showing Banani area Dhaka -->
                <iframe class="map-iframe" 
                        src="https://maps.google.com/maps?q=Banani%20Road%2011%2C%20Dhaka%201213&t=&z=14&ie=UTF8&iwloc=&output=embed" 
                        allowfullscreen 
                        loading="lazy" 
                        title="GroCo Headquarters Banani Map location">
                </iframe>
            </div>

        </div>

        <!-- Social Links block -->
        <div class="social-grid">
            <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="social-btn btn-fb" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="social-btn btn-insta" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="https://youtube.com" target="_blank" rel="noopener noreferrer" class="social-btn btn-yt" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
            <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" class="social-btn btn-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            <a href="https://whatsapp.com" target="_blank" rel="noopener noreferrer" class="social-btn btn-wa" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
        </div>

        <!-- FAQ Accordions -->
        <div class="contact-faq-section">
            <div class="section-header">
                <h2>Frequently Asked Questions</h2>
                <p>Find quick answers to common questions about shopping, delivery speeds, and refunds.</p>
            </div>
            
            <div class="faq-accordion">
                <div class="faq-item">
                    <button type="button" class="faq-trigger">What is your delivery coverage and time? <i class="fas fa-chevron-down"></i></button>
                    <div class="faq-content">
                        <p>We deliver to all areas within Dhaka metropolitan city inside under 60 minutes. For areas outside Dhaka, deliveries are processed via express partners and typically arrive within 24 to 48 hours.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" class="faq-trigger">How do I request a product refund? <i class="fas fa-chevron-down"></i></button>
                    <div class="faq-content">
                        <p>If you are not satisfied with the freshness or quality of any product, you can return it directly to the delivery rider at your doorstep for an instant cash refund. You can also file a ticket within 24 hours of delivery by calling support.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" class="faq-trigger">Is cash on delivery supported? <i class="fas fa-chevron-down"></i></button>
                    <div class="faq-content">
                        <p>Yes, we fully support cash on delivery (COD) for all cities. You can also pay online using credit/debit cards, bKash, Rocket, Nagad, or bank transfers during checkout.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Newsletter Component block -->
<?php include PUBLIC_PATH . '/components/newsletter-form.php'; ?>

<!-- FAQ Toggle Javascript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const trigger = item.querySelector('.faq-trigger');
        trigger.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');
            
            // Close other open accordions
            faqItems.forEach(otherItem => {
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
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
