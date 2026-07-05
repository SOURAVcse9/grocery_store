/**
 * ==========================================================================
 * public/assets/js/slider.js
 * ==========================================================================
 * Slider control logic for the Hero Slider.
 * Handles automatic sliding, arrow click navigation, and indicator dots.
 * Pauses auto-slide when hovering over the slider.
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const slider = document.getElementById('heroSlider');
    if (!slider) return;

    const container = document.getElementById('heroSlidesContainer');
    const slides = container ? container.querySelectorAll('.hero-slide') : [];
    const prevBtn = document.getElementById('sliderPrev');
    const nextBtn = document.getElementById('sliderNext');
    const dotsContainer = document.getElementById('sliderDots');
    const dots = dotsContainer ? dotsContainer.querySelectorAll('.slider-dot') : [];

    if (slides.length <= 1) return;

    let currentIndex = 0;
    let slideInterval;
    const intervalTime = 5000; // 5 seconds

    function showSlide(index) {
      if (index < 0) {
        index = slides.length - 1;
      } else if (index >= slides.length) {
        index = 0;
      }

      // Remove active class from previous slide and dot
      slides[currentIndex].classList.remove('active');
      if (dots[currentIndex]) {
        dots[currentIndex].classList.remove('active');
      }

      // Update current index
      currentIndex = index;

      // Add active class to new slide and dot
      slides[currentIndex].classList.add('active');
      if (dots[currentIndex]) {
        dots[currentIndex].classList.add('active');
      }
    }

    function nextSlide() {
      showSlide(currentIndex + 1);
    }

    function prevSlide() {
      showSlide(currentIndex - 1);
    }

    function startAutoSlide() {
      stopAutoSlide();
      slideInterval = setInterval(nextSlide, intervalTime);
    }

    function stopAutoSlide() {
      if (slideInterval) {
        clearInterval(slideInterval);
      }
    }

    // Attach click events to buttons
    nextBtn?.addEventListener('click', () => {
      nextSlide();
      startAutoSlide(); // reset timer
    });

    prevBtn?.addEventListener('click', () => {
      prevSlide();
      startAutoSlide(); // reset timer
    });

    // Attach click events to dots
    dots.forEach((dot, idx) => {
      dot.addEventListener('click', () => {
        showSlide(idx);
        startAutoSlide(); // reset timer
      });
    });

    // Pause on hover
    slider.addEventListener('mouseenter', stopAutoSlide);
    slider.addEventListener('mouseleave', startAutoSlide);

    // Start auto slide on init
    startAutoSlide();
  });
})();
