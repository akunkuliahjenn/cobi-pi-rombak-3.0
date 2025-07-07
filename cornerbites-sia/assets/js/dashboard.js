
// Dashboard JavaScript untuk HPP Calculator
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard features
    initQuickActions();
    initAlerts();

    console.log('HPP Dashboard loaded successfully');
});

function initQuickActions() {
    // Add hover effects and analytics tracking for quick action buttons
    const quickActions = document.querySelectorAll('.bg-blue-50, .bg-green-50, .bg-purple-50');

    quickActions.forEach(action => {
        action.addEventListener('click', function(e) {
            // You can add analytics tracking here
            const actionName = this.querySelector('h4').textContent;
            console.log(`Quick action clicked: ${actionName}`);
        });
    });
}

function initAlerts() {
    // Auto-hide alert messages after 5 seconds
    const alerts = document.querySelectorAll('.bg-yellow-50, .bg-red-50');

    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0.8';
        }, 5000);
    });
}

// Function to load ranking data with AJAX
function loadRankingData(page = 1) {
    const container = document.getElementById('ranking-container');
    
    if (!container) {
        console.error('Ranking container not found');
        return;
    }

    // Show loading
    container.innerHTML = '<div class="flex justify-center items-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600"></div><span class="ml-2 text-gray-600">Memuat ranking...</span></div>';

    const params = new URLSearchParams({
        ajax: 'ranking',
        ranking_page: page
    });

    fetch(`/cornerbites-sia/pages/dashboard.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading ranking data:', error);
            container.innerHTML = '<div class="text-center text-red-500 py-8">Error loading ranking data. Please try again.</div>';
        });
}

// Make loadRankingData global
window.loadRankingData = loadRankingData;

// Utility function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Function to refresh dashboard data (for future AJAX implementation)
function refreshDashboard() {
    // Implementation for live data refresh
    console.log('Refreshing dashboard data...');
    location.reload();
}
