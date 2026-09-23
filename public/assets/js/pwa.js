/**
 * ==========================================================================
 * public/assets/js/pwa.js
 * ==========================================================================
 * Registers service workers, monitors network connectivity events, and
 * manages custom installation prompts.
 * ==========================================================================
 */

(function () {
  'use strict';

  // ---------------------------------------------------------------------
  // 1. Service Worker Registration
  // ---------------------------------------------------------------------
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((registrations) => {
      for (let registration of registrations) {
        registration.unregister();
        console.log('ServiceWorker unregistered successfully.');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const offlineBanner = document.getElementById('offlineStatusBanner');
    const offlineMsg = document.getElementById('offlineStatusMsg');

    // ---------------------------------------------------------------------
    // 2. Network Online / Offline Status Events
    // ---------------------------------------------------------------------
    function updateOnlineStatus() {
      if (!offlineBanner) return;

      if (navigator.onLine) {
        // Recovered connection
        if (offlineBanner.classList.contains('show')) {
          offlineBanner.classList.add('online-recovered');
          if (offlineMsg) {
            offlineMsg.innerHTML = '<i class="fas fa-circle-check"></i> Connection restored! Syncing data...';
          }
          setTimeout(() => {
            offlineBanner.classList.remove('show');
            offlineBanner.classList.remove('online-recovered');
          }, 2000);
        }
      } else {
        // Connection lost
        offlineBanner.classList.remove('online-recovered');
        if (offlineMsg) {
          offlineMsg.innerHTML = '<i class="fas fa-triangle-exclamation"></i> You are browsing offline. Pages are loaded from browser cache.';
        }
        offlineBanner.classList.add('show');
      }
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);

    // Initial check on page load
    if (!navigator.onLine) {
      updateOnlineStatus();
    }

    // ---------------------------------------------------------------------
    // 3. PWA Installation Prompt Toggles
    // ---------------------------------------------------------------------
    let deferredPrompt = null;
    const installDrawer = document.getElementById('pwaInstallDrawer');
    const btnConfirm = document.getElementById('btnPwaInstallConfirm');
    const btnDismiss = document.getElementById('btnPwaInstallDismiss');
    const btnCloseX = document.getElementById('btnPwaCloseX');

    // Do not show install prompt on authentication or admin pages
    const isAuthOrAdminPage = /login\.php|register\.php|forgot-password\.php|reset-password\.php|\/admin\//i.test(window.location.pathname);

    window.addEventListener('beforeinstallprompt', (e) => {
      // Prevent browser's default prompt banner
      e.preventDefault();
      // Stash event so it can be triggered on user action
      deferredPrompt = e;

      // Check persistent dismissal
      const dismissed = localStorage.getItem('pwa_install_dismissed') || sessionStorage.getItem('pwa_install_dismissed');

      if (installDrawer && !dismissed && !isAuthOrAdminPage) {
        setTimeout(() => {
          if (deferredPrompt && !localStorage.getItem('pwa_install_dismissed')) {
            installDrawer.classList.add('show');
          }
        }, 4000);
      }
    });

    btnConfirm?.addEventListener('click', async () => {
      if (!deferredPrompt) return;

      // Hide custom drawer
      installDrawer?.classList.remove('show');
      localStorage.setItem('pwa_install_dismissed', '1');

      // Show native installation prompt
      deferredPrompt.prompt();

      // Wait for the user to respond to the prompt
      const { outcome } = await deferredPrompt.userChoice;
      console.log(`User response to install prompt: ${outcome}`);

      // Clear deferred prompt variable
      deferredPrompt = null;
    });

    const dismissDrawer = () => {
      installDrawer?.classList.remove('show');
      localStorage.setItem('pwa_install_dismissed', '1');
      sessionStorage.setItem('pwa_install_dismissed', '1');
    };

    btnDismiss?.addEventListener('click', dismissDrawer);
    btnCloseX?.addEventListener('click', dismissDrawer);

    // Detect successful installation
    window.addEventListener('appinstalled', (e) => {
      console.log('Groco App was installed successfully!');
      installDrawer?.classList.remove('show');
      deferredPrompt = null;
      window.showToast?.('Thank you for installing Groco App!', 'success');
    });

  });
})();
