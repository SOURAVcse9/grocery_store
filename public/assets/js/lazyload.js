/**
 * ==========================================================================
 * public/assets/js/lazyload.js
 * ==========================================================================
 * Optimized Progressive Image Lazy Loading script using IntersectionObserver.
 * Triggers progressive blur-up loading for images.
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const lazyImages = [].slice.call(document.querySelectorAll('img.lazy'));

    if ('IntersectionObserver' in window) {
      const lazyImageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const lazyImage = entry.target;
            
            // Swap src
            if (lazyImage.dataset.src) {
              lazyImage.src = lazyImage.dataset.src;
            }
            // Swap srcset (if responsive sizes are compiled)
            if (lazyImage.dataset.srcset) {
              lazyImage.srcset = lazyImage.dataset.srcset;
            }

            lazyImage.addEventListener('load', () => {
              lazyImage.classList.add('lazy-loaded');
              lazyImage.classList.remove('lazy');
            });

            lazyImageObserver.unobserve(lazyImage);
          }
        });
      }, {
        rootMargin: '120px 0px', // start loading slightly before coming into viewport
        threshold: 0.01
      });

      lazyImages.forEach((lazyImage) => {
        lazyImageObserver.observe(lazyImage);
      });
    } else {
      // Fallback fallback loading for browsers without observer capabilities
      lazyImages.forEach((lazyImage) => {
        if (lazyImage.dataset.src) {
          lazyImage.src = lazyImage.dataset.src;
        }
        if (lazyImage.dataset.srcset) {
          lazyImage.srcset = lazyImage.dataset.srcset;
        }
        lazyImage.classList.add('lazy-loaded');
        lazyImage.classList.remove('lazy');
      });
    }
  });
})();
