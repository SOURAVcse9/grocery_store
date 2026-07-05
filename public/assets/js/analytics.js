/**
 * ==========================================================================
 * public/assets/js/analytics.js
 * ==========================================================================
 * Manage client-side Chart.js configurations:
 *   - Lazily loads Chart.js library CDN when loaded to minimize network sizes
 *   - Fetches JSON coordinates from api/analytics.php
 *   - Builds Line, Doughnut, and Bar charts for spending and purchase indexes
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    
    // Check if we are on the analytics dashboard page
    const pageCheck = document.getElementById('spendingTimelineChart');
    if (!pageCheck) return;

    // Lazily load Chart.js library
    if (typeof Chart === 'undefined') {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
      script.onload = fetchAndInitCharts;
      document.head.appendChild(script);
    } else {
      fetchAndInitCharts();
    }

    async function fetchAndInitCharts() {
      try {
        const res = await fetch('api/analytics.php', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
          renderTimelineChart(json.data.timeline ?? []);
          renderCategoryDistribution(json.data.categories ?? []);
          renderProductPurchases(json.data.products ?? []);
          renderOrderStatusPie(json.data.statuses ?? []);
        }
      } catch (err) {
        console.error('Failed to load analytics dashboard charts:', err);
      }
    }

    // 1. Monthly Spending & Orders Timeline Chart
    function renderTimelineChart(data) {
      const ctx = document.getElementById('spendingTimelineChart');
      if (!ctx) return;

      const labels = data.map(row => row.month_label);
      const spent = data.map(row => parseFloat(row.total_spent));
      const orders = data.map(row => parseInt(row.total_orders));

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Total Spent (৳)',
              data: spent,
              borderColor: '#0b7285',
              backgroundColor: 'rgba(11, 114, 133, 0.05)',
              borderWidth: 2,
              fill: true,
              yAxisID: 'y'
            },
            {
              label: 'Total Orders',
              data: orders,
              borderColor: '#e67e22',
              backgroundColor: 'rgba(230, 126, 34, 0.05)',
              borderWidth: 2,
              type: 'bar',
              yAxisID: 'y1'
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              grid: { drawOnChartArea: true }
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              grid: { drawOnChartArea: false }
            }
          }
        }
      });
    }

    // 2. Favorite Categories Doughnut Chart
    function renderCategoryDistribution(data) {
      const ctx = document.getElementById('categoryDistributionChart');
      if (!ctx) return;

      const labels = data.map(row => row.label);
      const counts = data.map(row => parseInt(row.count));

      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: counts,
            backgroundColor: ['#0b7285', '#2b8a3e', '#e67e22', '#7950f2', '#fa5252']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });
    }

    // 3. Product Purchases Bar Chart
    function renderProductPurchases(data) {
      const ctx = document.getElementById('productPurchasesChart');
      if (!ctx) return;

      const labels = data.map(row => row.label.length > 15 ? row.label.substring(0, 15) + '...' : row.label);
      const counts = data.map(row => parseInt(row.count));

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Quantity Purchased',
            data: counts,
            backgroundColor: '#0b7285',
            borderRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });
    }

    // 4. Order Status Distribution Pie Chart
    function renderOrderStatusPie(data) {
      const ctx = document.getElementById('orderStatusPieChart');
      if (!ctx) return;

      const labels = data.map(row => row.label);
      const counts = data.map(row => parseInt(row.count));

      // Color mapping
      const bgColors = labels.map(status => {
        const s = status.toLowerCase();
        if (s === 'delivered') return '#2b8a3e';
        if (s === 'pending') return '#e67e22';
        if (s === 'processing' || s === 'confirmed') return '#228be6';
        if (s === 'cancelled') return '#fa5252';
        return '#6c757d';
      });

      new Chart(ctx, {
        type: 'pie',
        data: {
          labels: labels,
          datasets: [{
            data: counts,
            backgroundColor: bgColors
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });
    }

  });
})();
