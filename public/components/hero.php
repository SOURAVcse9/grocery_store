<?php
/**
 * ==========================================================================
 * public/components/hero.php
 * ==========================================================================
 * Dynamic Hero Slider component.
 * Expects:
 *   - $banners (array): array of banner objects with image, title, subtitle,
 *                        button_text, and button_link keys.
 * ==========================================================================
 */

declare(strict_types=1);

if (empty($banners)) {
    return;
}
?>
<section class="hero-slider-section">
    <div class="hero-slider" id="heroSlider">
        <div class="hero-slides-container" id="heroSlidesContainer">
            <?php foreach ($banners as $index => $banner): 
                $imageUrl = image_url($banner['image'], 'ui');
                $title = $banner['title'] ?? '';
                $subtitle = $banner['subtitle'] ?? '';
                $btnText = $banner['button_text'] ?? 'Shop Now';
                $btnLink = $banner['button_link'] ?? 'products.php';
            ?>
                <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: linear-gradient(rgba(0,0,0,0.1), rgba(0,0,0,0.3)), url('<?= e($imageUrl) ?>');">
                    <div class="container hero-slide-content">
                        <div class="hero-text-card">
                            <span class="hero-badge"><i class="fas fa-leaf"></i> 100% Fresh & Organic</span>
                            <h1 class="hero-title"><?= e($title) ?></h1>
                            <p class="hero-subtitle"><?= e($subtitle) ?></p>
                            <a href="<?= e(url_for($btnLink)) ?>" class="btn btn-primary btn-hero-cta">
                                <?= e($btnText) ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Slider Controls -->
        <button type="button" class="slider-arrow slider-prev" id="sliderPrev" aria-label="Previous Slide">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button type="button" class="slider-arrow slider-next" id="sliderNext" aria-label="Next Slide">
            <i class="fas fa-chevron-right"></i>
        </button>

        <!-- Slider Indicators -->
        <div class="slider-dots" id="sliderDots">
            <?php foreach ($banners as $index => $banner): ?>
                <button type="button" class="slider-dot <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>" aria-label="Go to slide <?= $index + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</section>
