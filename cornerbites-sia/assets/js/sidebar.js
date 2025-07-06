
// sidebar.js - Handle sidebar functionality including logout modal

document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logout-btn');
    const logoutModal = document.getElementById('logout-modal');
    const cancelLogout = document.getElementById('cancel-logout');
    const confirmLogout = document.getElementById('confirm-logout');

    // Show logout modal
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            logoutModal.classList.remove('hidden');
            // Animate modal appearance
            setTimeout(() => {
                logoutModal.querySelector('.bg-white').classList.remove('scale-95');
                logoutModal.querySelector('.bg-white').classList.add('scale-100');
            }, 10);
        });
    }

    // Hide logout modal
    function hideModal() {
        logoutModal.querySelector('.bg-white').classList.remove('scale-100');
        logoutModal.querySelector('.bg-white').classList.add('scale-95');
        setTimeout(() => {
            logoutModal.classList.add('hidden');
        }, 200);
    }

    // Cancel logout
    if (cancelLogout) {
        cancelLogout.addEventListener('click', hideModal);
    }

    // Confirm logout
    if (confirmLogout) {
        confirmLogout.addEventListener('click', function() {
            window.location.href = '/cornerbites-sia/auth/logout.php';
        });
    }

    // Close modal when clicking outside
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                hideModal();
            }
        });
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !logoutModal.classList.contains('hidden')) {
            hideModal();
        }
    });
});
