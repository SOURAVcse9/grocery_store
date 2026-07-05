/**
 * ==========================================================================
 * public/assets/js/notifications.js
 * ==========================================================================
 * Handle client-side notifications center, dropdown, and AJAX polling:
 *   - Clicking bell toggles dropdown panel
 *   - Auto-refresh unread counts and dropdown logs every 30 seconds
 *   - Mark individual or all notifications as read
 *   - Delete notifications with row collapse animations
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const bellBtn = document.getElementById('btnNotificationBell');
    const panel = document.getElementById('notificationsDropdownPanel');
    const badge = document.getElementById('headerNotifBadge');

    let currentUnread = parseInt(badge?.textContent || '0');

    // ---------------------------------------------------------------------
    // 1. Toggle dropdown panel
    // ---------------------------------------------------------------------
    bellBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = panel?.classList.toggle('is-open');

      if (isOpen) {
        // Fetch fresh dropdown logs
        loadDropdownNotifications();
      }
    });

    // Close panel on clicking outside
    document.addEventListener('click', (e) => {
      if (panel && !panel.contains(e.target) && !bellBtn?.contains(e.target)) {
        panel.classList.remove('is-open');
      }
    });

    async function loadDropdownNotifications() {
      if (!panel) return;
      try {
        const res = await fetch('api/notifications.php?action=dropdown', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
          panel.innerHTML = json.data.html ?? '';
          updateBadgeCount(json.data.unread_count);
        }
      } catch (err) {
        console.error('Failed to load notification dropdown:', err);
      }
    }

    function updateBadgeCount(count) {
      currentUnread = count;
      if (!badge) return;

      if (count > 0) {
        badge.textContent = count.toString();
        badge.style.display = 'flex';
      } else {
        badge.textContent = '0';
        badge.style.display = 'none';
      }
    }

    // ---------------------------------------------------------------------
    // 2. AJAX Auto-refresh Polling (Every 30 seconds)
    // ---------------------------------------------------------------------
    setInterval(async () => {
      // Skip if user is guest (handled by backend 401 response anyway, but lets save requests)
      const loginCheck = document.querySelector('.user-menu');
      if (!loginCheck) return;

      try {
        const res = await fetch('api/notifications.php?action=count_latest', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data?.unread_count !== undefined) {
          const newCount = json.data.unread_count;
          if (newCount !== currentUnread) {
            updateBadgeCount(newCount);
            
            // If dropdown panel is open, refresh list in real-time
            if (panel?.classList.contains('is-open')) {
              loadDropdownNotifications();
            }
            
            // If user is on notifications.php page, refresh content in real-time
            const mainContainer = document.getElementById('notificationsListContainer');
            if (mainContainer) {
              refreshNotificationsCenterPage(1);
            }
          }
        }
      } catch (err) {
        // Silently swallow polling errors to prevent user console clutter
      }
    }, 30000);

    // ---------------------------------------------------------------------
    // 3. Mark Single Notification as Read
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      // Matches card action button or dropdown dot
      const btn = e.target.closest('.btn-mark-read');
      const dot = e.target.closest('.notif-dropdown-dot');
      
      const target = btn || dot;
      if (!target) return;

      e.preventDefault();
      e.stopPropagation();

      const notifId = target.dataset.id || target.closest('.notification-dropdown-item')?.dataset.id || target.closest('.notification-card')?.dataset.id;
      if (!notifId) return;

      const json = await window.apiPost('ajax/notification.php', {
        action: 'mark_read',
        notification_id: notifId
      });

      if (json.success) {
        updateBadgeCount(json.data?.unread_count);

        // Update card in center page
        const card = document.getElementById(`notification-card-${notifId}`);
        if (card) {
          card.classList.replace('unread', 'read');
          card.querySelector('.btn-mark-read')?.remove();
        }

        // Update dropdown item in real-time if open
        if (panel?.classList.contains('is-open')) {
          loadDropdownNotifications();
        }
      }
    });

    // ---------------------------------------------------------------------
    // 4. Mark All as Read (dropdown or center)
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('#btnDropdownMarkAllRead') || e.target.closest('.btn-mark-all-read-center');
      if (!btn) return;

      btn.disabled = true;

      const json = await window.apiPost('ajax/notification.php', {
        action: 'mark_all_read'
      });

      if (json.success) {
        window.showToast?.(json.message, 'success');
        updateBadgeCount(0);

        // If on center page, reload listing
        const centerContainer = document.getElementById('notificationsListContainer');
        if (centerContainer) {
          refreshNotificationsCenterPage(1);
        }

        // If dropdown is open, reload dropdown
        if (panel?.classList.contains('is-open')) {
          loadDropdownNotifications();
        }
      } else {
        btn.disabled = false;
      }
    });

    // ---------------------------------------------------------------------
    // 5. Delete Notification Card/Row
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-delete-notif');
      if (!btn) return;

      const notifId = btn.dataset.id;
      if (!notifId) return;

      btn.disabled = true;

      const json = await window.apiPost('ajax/notification.php', {
        action: 'delete',
        notification_id: notifId
      });

      if (json.success) {
        updateBadgeCount(json.data?.unread_count);

        // Animate row removal smoothly
        const card = document.getElementById(`notification-card-${notifId}`);
        if (card) {
          card.style.transition = 'all 300ms ease';
          card.style.opacity = '0';
          card.style.transform = 'scale(0.9)';
          setTimeout(() => {
            card.remove();
            checkEmptyNotifications();
          }, 300);
        }
      } else {
        btn.disabled = false;
      }
    });

    // Helper: refresh full center page container via API
    async function refreshNotificationsCenterPage(page = 1) {
      const container = document.getElementById('notificationsListContainer');
      const pagination = document.getElementById('notificationsPaginationContainer');
      if (!container) return;

      try {
        const res = await fetch(`api/notifications.php?page=${page}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
          container.innerHTML = json.data.html ?? '';
          if (pagination) {
            pagination.innerHTML = json.data.pagination_html ?? '';
          }
        }
      } catch (err) {
        console.error('Failed to reload notifications center page:', err);
      }
    }

    function checkEmptyNotifications() {
      const container = document.getElementById('notificationsListContainer');
      const cards = container?.querySelectorAll('.notification-card');
      if (container && (!cards || cards.length === 0)) {
        container.innerHTML = `
          <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
            <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="far fa-bell-slash"></i></div>
            <h2>No Notifications Yet</h2>
            <p>We will alert you here on order status changes, new coupon offers, and security warnings!</p>
          </div>
        `;
        document.querySelector('.btn-mark-all-read-center')?.remove();
      }
    }

  });
})();
