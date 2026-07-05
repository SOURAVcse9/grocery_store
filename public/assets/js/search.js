/**
 * ==========================================================================
 * public/assets/js/search.js
 * ==========================================================================
 * Handle search queries, mobile filters side panel toggle, catalog view mode
 * toggles, and AJAX catalog filtering.
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------------------
    // 1. Grid/List View Toggler
    // ---------------------------------------------------------------------
    const btnGrid = document.getElementById('btnGridView');
    const btnList = document.getElementById('btnListView');
    const productsGrid = document.getElementById('productsCatalogGrid');

    if (btnGrid && btnList && productsGrid) {
      // Load saved preference
      const savedMode = localStorage.getItem('catalogViewMode') ?? 'grid';
      setViewMode(savedMode);

      btnGrid.addEventListener('click', () => setViewMode('grid'));
      btnList.addEventListener('click', () => setViewMode('list'));
    }

    function setViewMode(mode) {
      if (mode === 'list') {
        productsGrid?.classList.add('list-view');
        btnList?.classList.add('active');
        btnGrid?.classList.remove('active');
      } else {
        productsGrid?.classList.remove('list-view');
        btnGrid?.classList.add('active');
        btnList?.classList.remove('active');
      }
      localStorage.setItem('catalogViewMode', mode);
    }

    // ---------------------------------------------------------------------
    // 2. Mobile Filter Sidebar Toggle
    // ---------------------------------------------------------------------
    const btnMobileFilter = document.getElementById('btnMobileFilter');
    const filterSidebar = document.getElementById('filterSidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');

    if (btnMobileFilter && filterSidebar) {
      btnMobileFilter.addEventListener('click', () => {
        filterSidebar.classList.toggle('is-open');
        mobileOverlay?.classList.toggle('is-open');
        if (filterSidebar.classList.contains('is-open')) {
          document.body.style.overflow = 'hidden';
        } else {
          document.body.style.overflow = '';
        }
      });

      // Clicking overlay closes sidebar
      mobileOverlay?.addEventListener('click', () => {
        filterSidebar.classList.remove('is-open');
        document.body.style.overflow = '';
      });
    }

    // ---------------------------------------------------------------------
    // 3. Dynamic AJAX Filtering
    // ---------------------------------------------------------------------
    const filterForm = document.getElementById('filterForm');
    const catalogWrapper = document.getElementById('catalogWrapper');
    const loadingOverlay = document.getElementById('catalogLoadingOverlay');
    const sortSelect = document.getElementById('sortSelect');

    if (filterForm && productsGrid) {
      // Gather inputs
      const checkboxes = filterForm.querySelectorAll('input[type="checkbox"], input[type="radio"]');
      const numberInputs = filterForm.querySelectorAll('input[type="number"]');

      // Trigger filtering on checkbox changes
      checkboxes.forEach((input) => {
        input.addEventListener('change', () => triggerFilter(1));
      });

      // Debounce price input typing changes
      let priceDebounce;
      numberInputs.forEach((input) => {
        input.addEventListener('input', () => {
          clearTimeout(priceDebounce);
          priceDebounce = setTimeout(() => triggerFilter(1), 500);
        });
      });

      // Sort change
      sortSelect?.addEventListener('change', () => triggerFilter(1));

      // Handle Pagination click events (AJAX delegation)
      document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.pagination-link');
        if (!link || link.parentElement.classList.contains('disabled') || link.parentElement.classList.contains('active')) return;

        // Parse page number from URL if we are doing AJAX
        const href = link.getAttribute('href');
        if (href) {
          const urlParams = new URLSearchParams(href.split('?')[1] ?? '');
          const page = parseInt(urlParams.get('page') ?? '1');
          e.preventDefault();
          triggerFilter(page);
          
          // Scroll smoothly to top of catalog
          window.scrollTo({
            top: catalogWrapper ? catalogWrapper.offsetTop - 100 : 0,
            behavior: 'smooth'
          });
        }
      });

      // Clear Filters button
      const clearBtn = document.getElementById('btnClearFilters');
      clearBtn?.addEventListener('click', () => {
        filterForm.reset();
        // Reset sort
        if (sortSelect) sortSelect.selectedIndex = 0;
        triggerFilter(1);
      });
    }

    // Fetch products based on filters
    async function triggerFilter(page = 1) {
      if (!filterForm) return;

      // Show loader
      loadingOverlay?.classList.add('is-loading');

      const formData = new FormData(filterForm);
      const params = new URLSearchParams();

      // Set standard filters
      formData.forEach((value, key) => {
        if (value) {
          params.append(key, value.toString());
        }
      });

      // Add sort option
      if (sortSelect && sortSelect.value) {
        params.append('sort', sortSelect.value);
      }

      // Add search query if present in current URL
      const currentUrlParams = new URLSearchParams(window.location.search);
      const searchQ = currentUrlParams.get('q');
      if (searchQ) {
        params.append('q', searchQ);
      }

      // Set page
      params.append('page', page.toString());

      const queryString = params.toString();

      const apiEndpoint = window.location.pathname.endsWith('search.php') ? 'api/search.php' : 'api/products.php';

      try {
        const res = await fetch(`${apiEndpoint}?${queryString}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
          // Replace catalog grid HTML
          const catalogContainer = document.getElementById('catalogProductsContainer');
          if (catalogContainer) {
            catalogContainer.innerHTML = json.data.html ?? '';
          }

          // Replace pagination HTML
          const paginationContainer = document.getElementById('catalogPaginationContainer');
          if (paginationContainer) {
            paginationContainer.innerHTML = json.data.pagination_html ?? '';
          }

          // Replace product count label
          const countLabel = document.getElementById('productCountLabel');
          if (countLabel && json.data.total_products !== undefined) {
            const start = json.data.offset + 1;
            const end = Math.min(json.data.offset + json.data.limit, json.data.total_products);
            if (json.data.total_products === 0) {
              countLabel.textContent = 'Showing 0 products';
            } else {
              countLabel.textContent = `Showing ${start}-${end} of ${json.data.total_products} products`;
            }
          }

          // Re-trigger lazy loading
          triggerLazyImages();

          // Push history state so filters stay bookmarkable
          const cleanPath = window.location.pathname;
          window.history.pushState({}, '', `${cleanPath}?${queryString}`);
        } else {
          window.showToast?.(json.message || 'Error filtering catalog.', 'error');
        }
      } catch (err) {
        window.showToast?.('Connection error. Please try again.', 'error');
      } finally {
        // Hide loader
        loadingOverlay?.classList.remove('is-loading');
        
        // Close mobile sidebar if open
        if (filterSidebar && filterSidebar.classList.contains('is-open')) {
          filterSidebar.classList.remove('is-open');
          mobileOverlay?.classList.remove('is-open');
          document.body.style.overflow = '';
        }
      }
    }

    function triggerLazyImages() {
      const lazyImages = [].slice.call(document.querySelectorAll("img.lazy"));
      if ("IntersectionObserver" in window) {
        let lazyImageObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              let lazyImage = entry.target;
              lazyImage.src = lazyImage.dataset.src;
              lazyImage.classList.remove("lazy");
              lazyImageObserver.unobserve(lazyImage);
            }
          });
        });
        lazyImages.forEach(function (lazyImage) {
          lazyImageObserver.observe(lazyImage);
        });
      } else {
        lazyImages.forEach(function (lazyImage) {
          lazyImage.src = lazyImage.dataset.src;
        });
      }
    }
  });
})();
