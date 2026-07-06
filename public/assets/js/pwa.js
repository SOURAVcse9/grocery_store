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

    window.addEventListener('beforeinstallprompt', (e) => {
      // Prevent Chrome 67 and earlier from automatically showing the prompt
      e.preventDefault();
      // Stash the event so it can be triggered later
      deferredPrompt = e;

      // Check if user already dismissed install drawer in this session
      const dismissed = sessionStorage.getItem('pwa_install_dismissed');

      if (installDrawer && !dismissed) {
        installDrawer.classList.add('show');
      }
    });

    btnConfirm?.addEventListener('click', async () => {
      if (!deferredPrompt) return;

      // Hide custom drawer
      installDrawer?.classList.remove('show');
      // Show native installation prompt
      deferredPrompt.prompt();

      // Wait for the user to respond to the prompt
      const { outcome } = await deferredPrompt.userChoice;
      console.log(`User response to install prompt: ${outcome}`);

      // Clear deferred prompt variable
      deferredPrompt = null;
    });

    btnDismiss?.addEventListener('click', () => {
      installDrawer?.classList.remove('show');
      // Set session flag to not prompt again during active session
      sessionStorage.setItem('pwa_install_dismissed', '1');
    });

    // Detect successful installation
    window.addEventListener('appinstalled', (e) => {
      console.log('Groco App was installed successfully!');
      installDrawer?.classList.remove('show');
      deferredPrompt = null;
      window.showToast?.('Thank you for installing Groco App!', 'success');
    });

  });
})();
