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