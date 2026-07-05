<?php
/**
 * ==========================================================================
 * admin/layouts/footer.php — Admin Dashboard Layout Footer
 * ==========================================================================
 */

declare(strict_types=1);
?>
    </div> <!-- Close admin-layout-wrapper -->

    <!-- Responsive sidebar navigation collapsible handlers -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('btnToggleSidebar');
        const sidebar = document.getElementById('adminSidebar');
        
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (window.innerWidth > 992) {
                    sidebar.classList.toggle('collapsed');
                } else {
                    sidebar.classList.toggle('mobile-open');
                }
            });
            
            // Close mobile sidebar when clicking on main panel
            const mainPanel = document.querySelector('.admin-main-panel');
            if (mainPanel) {
                mainPanel.addEventListener('click', () => {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('mobile-open');
                    }
                });
            }
        }
    });
    </script>
</body>
</html>
