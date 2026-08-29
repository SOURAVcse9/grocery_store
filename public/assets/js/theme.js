/**
 * theme.js — GroCo Production Theme Switcher Logic (Vanilla JS)
 */
(function() {
    'use strict';

    // 1. Initialize and bind events when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        initThemeSwitcher();
    });

    // 2. Synchronize theme across multiple open tabs/windows
    window.addEventListener('storage', (e) => {
        if (e.key === 'groco-theme') {
            applyTheme(e.newValue || 'system');
            updateActiveStateInUI(e.newValue || 'system');
        }
    });

    // 3. Listen to OS prefers-color-scheme updates when active state is "system"
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', () => {
        const currentMode = localStorage.getItem('groco-theme') || 'system';
        if (currentMode === 'system') {
            applyTheme('system');
        }
    });

    /**
     * Resolves and sets data-theme on <html>
     */
    function applyTheme(mode) {
        if (!['light', 'dark', 'system'].includes(mode)) {
            mode = 'system';
        }
        let resolved = mode;
        if (mode === 'system') {
            resolved = mediaQuery.matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', resolved);
    }

    /**
     * Sets up toggle event listeners, keyboard navigation, and dropdown display
     */
    function initThemeSwitcher() {
        const toggleBtn = document.getElementById('themeToggleBtn');
        const menu = document.getElementById('themeDropdownMenu');
        if (!toggleBtn || !menu) return;

        const currentVal = localStorage.getItem('groco-theme') || 'system';
        updateActiveStateInUI(currentVal);

        // Toggle dropdown open/close
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = menu.style.display === 'block';
            if (isOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        // Dropdown options click handler
        const options = menu.querySelectorAll('.theme-dropdown-item');
        options.forEach(item => {
            item.addEventListener('click', (e) => {
                const targetVal = item.getAttribute('data-theme-val');
                localStorage.setItem('groco-theme', targetVal);
                applyTheme(targetVal);
                updateActiveStateInUI(targetVal);
                closeDropdown();
            });
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!toggleBtn.contains(e.target) && !menu.contains(e.target)) {
                closeDropdown();
            }
        });

        // Keyboard navigation accessibility
        toggleBtn.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                openDropdown();
                options[0].focus();
            }
        });

        options.forEach((opt, idx) => {
            opt.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const next = options[idx + 1] || options[0];
                    next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = options[idx - 1] || options[options.length - 1];
                    prev.focus();
                } else if (e.key === 'Escape') {
                    closeDropdown();
                    toggleBtn.focus();
                }
            });
        });

        function openDropdown() {
            menu.style.display = 'block';
            toggleBtn.setAttribute('aria-expanded', 'true');
            menu.setAttribute('aria-hidden', 'false');
        }

        function closeDropdown() {
            menu.style.display = 'none';
            toggleBtn.setAttribute('aria-expanded', 'false');
            menu.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Updates checkmarks and toggle icons based on selected theme
     */
    function updateActiveStateInUI(mode) {
        if (!['light', 'dark', 'system'].includes(mode)) {
            mode = 'system';
        }
        const toggleBtn = document.getElementById('themeToggleBtn');
        const menu = document.getElementById('themeDropdownMenu');
        if (!toggleBtn || !menu) return;

        // Reset checkmarks & toggle icons
        const options = menu.querySelectorAll('.theme-dropdown-item');
        options.forEach(opt => {
            const val = opt.getAttribute('data-theme-val');
            if (val === mode) {
                opt.classList.add('active');
            } else {
                opt.classList.remove('active');
            }
        });

        // Show corresponding icon on toggle button
        const icons = toggleBtn.querySelectorAll('i');
        icons.forEach(icon => icon.style.display = 'none');

        if (mode === 'light') {
            const icon = toggleBtn.querySelector('.theme-icon-light');
            if (icon) icon.style.display = 'inline-block';
        } else if (mode === 'dark') {
            const icon = toggleBtn.querySelector('.theme-icon-dark');
            if (icon) icon.style.display = 'inline-block';
        } else {
            const icon = toggleBtn.querySelector('.theme-icon-system');
            if (icon) icon.style.display = 'inline-block';
        }
    }
})();
