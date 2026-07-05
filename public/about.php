<?php
/**
 * ==========================================================================
 * public/about.php — Company Information & About Page
 * ==========================================================================
 * Displays story, mission, why choose us, animated statistics, team profiles,
 * and customer testimonials carousel. Fully accessible and responsive.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pdo = db();
$cmsPage = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE page_key = 'about' LIMIT 1");
    $stmt->execute();
    $cmsPage = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[storefront/about] ' . $e->getMessage());
}

// SEO details
$pageTitle = ($cmsPage && !empty($cmsPage['meta_title'])) ? $cmsPage['meta_title'] : ('About Us — ' . site_name());
$pageDescription = ($cmsPage && !empty($cmsPage['meta_description'])) ? $cmsPage['meta_description'] : 'Discover Bangladesh\'s premium online grocery platform.';

$canonicalUrl = current_url();
$ogImage = asset('images/ui/logo.png');

// Breadcrumb configuration
$breadcrumbs = [
    ['title' => 'About Us']
];

require_once __DIR__ . '/header.php';
?>

<!-- Custom inline styles for About page to keep it modular and self-contained -->
<style>
  .about-hero {
    background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
    color: var(--color-surface);
    padding: 60px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .about-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.15) 0%, transparent 60%);
    pointer-events: none;
  }
  .about-hero h1 {
    font-size: var(--fs-2xl);
    font-weight: 800;
    margin-bottom: var(--space-2);
    letter-spacing: -0.5px;
  }
  .about-hero p {
    font-size: var(--fs-md);
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
  }

  .about-section {
    padding: var(--space-8) 0;
    background: var(--color-surface);
  }
  .about-section.alt-bg {
    background: var(--color-bg);
  }

  .section-header {
    text-align: center;
    max-width: 700px;
    margin: 0 auto var(--space-6);
  }
  .section-header h2 {
    font-size: var(--fs-xl);
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: var(--space-2);
  }
  .section-header p {
    color: var(--color-text-muted);
    font-size: var(--fs-base);
  }

  /* Two-column layout */
  .about-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-6);
    align-items: center;
  }

  .story-content h3 {
    font-size: var(--fs-lg);
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--space-3);
  }
  .story-content p {
    color: var(--color-text-muted);
    line-height: 1.7;
    margin-bottom: var(--space-4);
  }
  .story-badge-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-3);
  }
  .story-badge-item {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    color: var(--color-text);
    font-weight: 600;
    font-size: var(--fs-sm);
  }
  .story-badge-item i {
    color: var(--color-primary);
    font-size: var(--fs-base);
  }

  .story-image {
    background: linear-gradient(135deg, var(--color-primary-light) 0%, rgba(26, 157, 85, 0.1) 100%);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    text-align: center;
    border: 1px dashed var(--color-primary);
  }
  .story-image i {
    font-size: 80px;
    color: var(--color-primary);
    margin-bottom: var(--space-3);
  }

  /* Why Choose Us Cards */
  .why-us-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-4);
  }
  .why-us-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-5);
    transition: all var(--transition-normal);
    box-shadow: var(--shadow-sm);
  }
  .why-us-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
    border-color: var(--color-primary);
  }
  .why-icon-wrapper {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--fs-lg);
    margin-bottom: var(--space-4);
  }
  .why-us-card h3 {
    font-size: var(--fs-base);
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: var(--space-2);
  }
  .why-us-card p {
    font-size: var(--fs-sm);
    color: var(--color-text-muted);
    line-height: 1.5;
  }

  /* Specific color variations */
  .icon-green { background: #e6f6ec; color: #1a9d55; }
  .icon-orange { background: #fdf2e9; color: #f5821f; }
  .icon-blue { background: #eef4ff; color: #3538cd; }
  .icon-gold { background: #fefdf0; color: #f2b705; }
  .icon-purple { background: #f9f5ff; color: #7f56d9; }
  .icon-teal { background: #ecfdf3; color: #027a48; }

  /* Stats Section */
  .stats-banner {
    background: linear-gradient(rgba(26, 157, 85, 0.95), rgba(20, 122, 66, 0.98)), url('<?= asset("images/ui/placeholder.png") ?>') center/cover;
    color: var(--color-surface);
    padding: var(--space-7) 0;
    text-align: center;
  }
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: var(--space-4);
  }
  .stat-item h3 {
    font-size: var(--fs-2xl);
    font-weight: 800;
    margin-bottom: var(--space-1);
  }
  .stat-item p {
    font-size: var(--fs-sm);
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
  }

  /* Mission, Vision, Values layout */
  .mvv-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: var(--space-5);
  }
  .mvv-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-5);
    height: 100%;
  }
  .mvv-card h3 {
    font-size: var(--fs-lg);
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--space-3);
    display: flex;
    align-items: center;
    gap: var(--space-2);
  }
  .mvv-card p {
    color: var(--color-text-muted);
    line-height: 1.6;
    font-size: var(--fs-sm);
  }
  .values-list {
    margin-top: var(--space-3);
    padding-left: var(--space-2);
  }
  .values-list li {
    list-style: none;
    margin-bottom: var(--space-2);
    font-size: var(--fs-sm);
    color: var(--color-text);
    display: flex;
    align-items: center;
    gap: var(--space-2);
  }
  .values-list li i {
    color: var(--color-primary);
  }

  /* Team Section */
  .team-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-4);
  }
  .team-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    text-align: center;
    transition: all var(--transition-normal);
  }
  .team-card:hover {
    transform: translateY(-5px);
    border-color: var(--color-primary);
    box-shadow: var(--shadow-md);
  }
  .team-avatar {
    width: 100px;
    height: 100px;
    border-radius: var(--radius-pill);
    background: var(--color-bg);
    margin: 0 auto var(--space-3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: var(--color-text-muted);
    border: 2px solid var(--color-border);
  }
  .team-card h3 {
    font-size: var(--fs-base);
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: var(--space-1);
  }
  .team-card p {
    font-size: var(--fs-xs);
    color: var(--color-primary);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: var(--space-3);
  }
  .team-socials {
    display: flex;
    justify-content: center;
    gap: var(--space-2);
  }
  .team-socials a {
    color: var(--color-text-faint);
    transition: color var(--transition-fast);
    font-size: var(--fs-sm);
  }
  .team-socials a:hover {
    color: var(--color-primary);
  }

  /* Testimonials Carousel */
  .testimonial-carousel-wrapper {
    position: relative;
    max-width: 800px;
    margin: 0 auto;
    overflow: hidden;
  }
  .testimonial-track {
    display: flex;
    transition: transform 0.5s ease-in-out;
  }
  .testimonial-slide {
    min-width: 100%;
    box-sizing: border-box;
    padding: var(--space-5);
    text-align: center;
  }
  .testimonial-bubble {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    position: relative;
    box-shadow: var(--shadow-sm);
    margin-bottom: var(--space-4);
  }
  .testimonial-bubble::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%) rotate(45deg);
    width: 20px;
    height: 20px;
    background: var(--color-surface);
    border-right: 1px solid var(--color-border);
    border-bottom: 1px solid var(--color-border);
  }
  .testimonial-quote {
    font-size: var(--fs-md);
    line-height: 1.6;
    color: var(--color-text);
    font-style: italic;
  }
  .testimonial-author {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
  }
  .testimonial-author-img {
    width: 45px;
    height: 45px;
    border-radius: var(--radius-pill);
    background: var(--color-primary-light);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: var(--fs-sm);
  }
  .testimonial-author-info {
    text-align: left;
  }
  .testimonial-author-name {
    font-weight: 700;
    color: var(--color-text);
    font-size: var(--fs-sm);
  }
  .testimonial-author-city {
    font-size: var(--fs-xs);
    color: var(--color-text-muted);
  }
  .carousel-controls {
    display: flex;
    justify-content: center;
    gap: var(--space-3);
    margin-top: var(--space-4);
  }
  .carousel-btn {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-pill);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-fast);
  }
  .carousel-btn:hover {
    background: var(--color-primary);
    color: var(--color-surface);
    border-color: var(--color-primary);
  }

  /* Call To Action */
  .cta-banner {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: var(--color-surface);
    padding: var(--space-7) 0;
    text-align: center;
    border-radius: var(--radius-lg);
    margin: var(--space-6) auto;
    max-width: var(--container-max);
    width: calc(100% - 32px);
    box-shadow: var(--shadow-md);
  }
  .cta-banner h2 {
    font-size: var(--fs-2xl);
    font-weight: 800;
    margin-bottom: var(--space-3);
  }
  .cta-banner p {
    font-size: var(--fs-md);
    opacity: 0.95;
    max-width: 600px;
    margin: 0 auto var(--space-5);
  }

  /* Responsive breakpoints */
  @media (max-width: 1024px) {
    .stats-grid {
      grid-template-columns: repeat(3, 1fr);
      gap: var(--space-3);
    }
    .mvv-grid {
      grid-template-columns: 1fr;
      gap: var(--space-4);
    }
  }
  @media (max-width: 768px) {
    .about-grid-2 {
      grid-template-columns: 1fr;
      gap: var(--space-5);
    }
    .team-grid {
      grid-template-columns: repeat(2, 1fr);
    }
    .stats-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }
  @media (max-width: 480px) {
    .team-grid {
      grid-template-columns: 1fr;
    }
    .stats-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<!-- Section 1: Hero Banner -->
<section class="about-hero">
    <div class="container">
        <h1><?= e(t('about') ?? 'About Us') ?></h1>
        <p>Your ultimate destination for fresh, pesticide-free, and high-quality groceries delivered straight to your door step.</p>
    </div>
</section>

<!-- Include Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<!-- Section 2: Company Story -->
<section class="about-section">
    <div class="container">
        <div class="about-grid-2">
            <div class="story-content">
                <h3>Our Humble Story</h3>
                <?= $cmsPage ? $cmsPage['content'] : 'Company story content is currently unavailable.' ?>
                <div class="story-badge-list">
                    <div class="story-badge-item"><i class="fas fa-check-circle"></i> 100% Organically Sourced</div>
                    <div class="story-badge-item"><i class="fas fa-check-circle"></i> Direct-from-Farm Logistics</div>
                    <div class="story-badge-item"><i class="fas fa-check-circle"></i> Strict Quality Assurance</div>
                    <div class="story-badge-item"><i class="fas fa-check-circle"></i> Under 60 Minutes Delivery</div>
                </div>
            </div>
            <div class="story-image">
                <i class="fas fa-seedling"></i>
                <h4 style="font-size: var(--fs-lg); font-weight:700; color:var(--color-primary); margin-bottom: var(--space-2);">Guaranteed Freshness</h4>
                <p style="color:var(--color-text-muted); font-size:var(--fs-sm);">From local fields to your family meals, we maintain a strict cold chain to ensure nutrition is intact.</p>
            </div>
        </div>
    </div>
</section>

<!-- Section 3: Why Choose Us -->
<section class="about-section alt-bg">
    <div class="container">
        <div class="section-header">
            <h2>Why Choose GroCo?</h2>
            <p>We redefine convenience and safety in daily shopping through state-of-the-art logistics and premium quality guarantees.</p>
        </div>
        <div class="why-us-grid">
            <div class="why-us-card">
                <div class="why-icon-wrapper icon-green"><i class="fas fa-carrot"></i></div>
                <h3>Fresh Products</h3>
                <p>We source vegetables, fruits, and dairy daily from certified partners to guarantee maximum freshness.</p>
            </div>
            <div class="why-us-card">
                <div class="why-icon-wrapper icon-orange"><i class="fas fa-truck-fast"></i></div>
                <h3>Fast Delivery</h3>
                <p>Our dedicated regional logistics network ensures your groceries arrive within under 60 minutes.</p>
            </div>
            <div class="why-us-card">
                <div class="why-icon-wrapper icon-blue"><i class="fas fa-shield-halved"></i></div>
                <h3>Secure Payments</h3>
                <p>Pay with complete confidence using SSLCommerz, cash-on-delivery, or leading mobile banking options.</p>
            </div>
            <div class="why-us-card">
                <div class="why-icon-wrapper icon-gold"><i class="fas fa-tags"></i></div>
                <h3>Best Prices</h3>
                <p>Enjoy seasonal discounts, regular coupons, and bulk pricing options lower than local brick-and-mortar stores.</p>
            </div>
            <div class="why-us-card">
                <div class="why-icon-wrapper icon-purple"><i class="fas fa-headset"></i></div>
                <h3>24/7 Support</h3>
                <p>Our friendly customer satisfaction desk is always awake to help resolve order issues or questions.</p>
            </div>
            <div class="why-us-card">
                <div class="why-icon-wrapper icon-teal"><i class="fas fa-rotate-left"></i></div>
                <h3>Easy Returns</h3>
                <p>Not satisfied with any item? Return it on the spot at your doorstep for an instant cash refund or replacement.</p>
            </div>
        </div>
    </div>
</section>

<!-- Section 4: Statistics with animated counters -->
<section class="stats-banner">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <h3 class="stat-counter" data-target="15000">0</h3>
                <p>Products</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-counter" data-target="50000">0</h3>
                <p>Customers</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-counter" data-target="200000">0</h3>
                <p>Orders Delivered</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-counter" data-target="12">0</h3>
                <p>Cities Covered</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-counter" data-target="5">0</h3>
                <p>Years of Service</p>
            </div>
        </div>
    </div>
</section>

<!-- Section 5, 6, 7: Our Mission, Vision, and Core Values -->
<section class="about-section">
    <div class="container">
        <div class="mvv-grid">
            <div class="mvv-card">
                <h3><i class="fas fa-bullseye"></i> Our Mission</h3>
                <p>To provide healthy, affordable, and safe fresh food to urban communities in Bangladesh while building a transparent marketplace that respects the hard work of rural farmers.</p>
            </div>
            <div class="mvv-card">
                <h3><i class="fas fa-eye"></i> Our Vision</h3>
                <p>To be the country's most customer-centric e-commerce network, paving the way for digital agriculture supply chains and pesticide-free food choices in every single household.</p>
            </div>
            <div class="mvv-card">
                <h3><i class="fas fa-award"></i> Core Values</h3>
                <ul class="values-list">
                    <li><i class="fas fa-circle-check"></i> Customer First Priority</li>
                    <li><i class="fas fa-circle-check"></i> Absolute Transparency</li>
                    <li><i class="fas fa-circle-check"></i> Support Local Farmers</li>
                    <li><i class="fas fa-circle-check"></i> Zero-Waste Policy</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Section 8: Meet Our Team -->
<section class="about-section alt-bg">
    <div class="container">
        <div class="section-header">
            <h2>Meet Our Leadership</h2>
            <p>Our operations are managed by experienced logistics, tech, and quality experts committed to customer excellence.</p>
        </div>
        <div class="team-grid">
            <div class="team-card">
                <div class="team-avatar"><i class="fas fa-user-tie"></i></div>
                <h3>Farhan Ahmed</h3>
                <p>Chief Executive Officer</p>
                <div class="team-socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="team-card">
                <div class="team-avatar"><i class="fas fa-user-gear"></i></div>
                <h3>Nusrat Jahan</h3>
                <p>Head of Operations</p>
                <div class="team-socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="team-card">
                <div class="team-avatar"><i class="fas fa-user-astronaut"></i></div>
                <h3>Tahmid Rahman</h3>
                <p>Customer Support Lead</p>
                <div class="team-socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="team-card">
                <div class="team-avatar"><i class="fas fa-user-ninja"></i></div>
                <h3>Arif Chowdhury</h3>
                <p>Logistics & Delivery Manager</p>
                <div class="team-socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 9: Customer Testimonials (Carousel) -->
<section class="about-section">
    <div class="container">
        <div class="section-header">
            <h2>Loved by Thousands</h2>
            <p>Read what our regular grocery customers say about their ordering experience.</p>
        </div>
        <div class="testimonial-carousel-wrapper">
            <div class="testimonial-track" id="testimonialTrack">
                <div class="testimonial-slide">
                    <div class="testimonial-bubble">
                        <p class="testimonial-quote">"The vegetables are always fresh and crisp. I no longer have to spend my weekends bargaining in crowded wet markets. Delivery is fast, and customer service is top-notch!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-author-img">SR</div>
                        <div class="testimonial-author-info">
                            <div class="testimonial-author-name">Sabrina Rahman</div>
                            <div class="testimonial-author-city">Gulshan, Dhaka</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-slide">
                    <div class="testimonial-bubble">
                        <p class="testimonial-quote">"I am highly impressed with their delivery speed. My order arrived in 35 minutes! Prices are cheaper than my local grocery store. Highly recommended."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-author-img">AH</div>
                        <div class="testimonial-author-info">
                            <div class="testimonial-author-name">Anisul Haque</div>
                            <div class="testimonial-author-city">Dhanmondi, Dhaka</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-slide">
                    <div class="testimonial-bubble">
                        <p class="testimonial-quote">"Their zero-middlemen supply chain makes total sense. Safe, chemical-free food at direct prices, plus they support local farmers. Amazing service!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-author-img">MK</div>
                        <div class="testimonial-author-info">
                            <div class="testimonial-author-name">Mahbubul Kabir</div>
                            <div class="testimonial-author-city">Chittagong</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="carousel-controls">
                <button type="button" class="carousel-btn" id="prevTestimonial" aria-label="Previous slide"><i class="fas fa-chevron-left"></i></button>
                <button type="button" class="carousel-btn" id="nextTestimonial" aria-label="Next slide"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </div>
</section>

<!-- Section 10: Call To Action -->
<section class="cta-banner">
    <h2>Experience the Freshest Groceries Today</h2>
    <p>Sign up now to receive standard free delivery on your first order. Safe, local, and premium quality products are just one click away.</p>
    <a href="products.php" class="btn btn-accent" style="border-radius:var(--radius-pill); font-weight:700; padding:14px 40px; text-decoration:none; display:inline-block;">Start Shopping Now</a>
</section>

<!-- Include script for testimonial slider and statistics counter animations -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Animated Stats Counter
    const counters = document.querySelectorAll('.stat-counter');
    const animateCounters = () => {
        counters.forEach(counter => {
            const target = parseInt(counter.dataset.target);
            let count = 0;
            const step = Math.ceil(target / 100);
            const timer = setInterval(() => {
                count += step;
                if (count >= target) {
                    counter.textContent = target.toLocaleString() + (target > 50 ? '+' : '');
                    clearInterval(timer);
                } else {
                    counter.textContent = count.toLocaleString();
                }
            }, 15);
        });
    };

    // Trigger counters animation immediately
    animateCounters();

    // 2. Testimonials Slider Carousel
    const track = document.getElementById('testimonialTrack');
    const slides = Array.from(track.children);
    const prevBtn = document.getElementById('prevTestimonial');
    const nextBtn = document.getElementById('nextTestimonial');
    let currentIndex = 0;

    const updateSlidePosition = () => {
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
    };

    nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % slides.length;
        updateSlidePosition();
    });

    prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + slides.length) % slides.length;
        updateSlidePosition();
    });

    // Auto-advance testimonials slide every 6 seconds
    setInterval(() => {
        currentIndex = (currentIndex + 1) % slides.length;
        updateSlidePosition();
    }, 6000);
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
