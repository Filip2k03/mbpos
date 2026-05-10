document.addEventListener('DOMContentLoaded', function () {
    // --- Page Loader ---
    const loader = document.getElementById('shipLoader');
    const progress = document.getElementById('loaderProgress');

    if (loader && progress) {
        // Simulate loading progress
        let width = 0;
        const interval = setInterval(function () {
            if (width >= 100) {
                clearInterval(interval);
                setTimeout(() => loader.classList.add('hidden'), 300); // Hide after a short delay
            } else {
                width += 2;
                progress.style.width = width + '%';
            }
        }, 20); // Adjust timing for desired speed
    }

    // --- Mobile menu toggle ---
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // --- Register Page Logic ---
    const userTypeSelect = document.getElementById('user_type');
    const regionField = document.getElementById('region-field');
    const regionSelect = document.getElementById('region_id');

    function toggleRegionField() {
        if (!userTypeSelect || !regionField || !regionSelect) return;

        const userType = userTypeSelect.value;
        regionSelect.disabled = false; // Reset disabled state

        // Show region field for Admin, Developer, and General users, allowing them to choose
        if (userType === 'ADMIN' || userType === 'Developer' || userType === 'General') {
            regionField.classList.remove('hidden');
        } else if (userType === 'Myanmar' || userType === 'Malay') {
            // Hide the field for Myanmar/Malay users as it's set automatically on the backend
            regionField.classList.add('hidden');
        } else {
            // Hide for the default empty option
            regionField.classList.add('hidden');
        }
    }

    if (userTypeSelect) {
        userTypeSelect.addEventListener('change', toggleRegionField);
        toggleRegionField(); // Run once on page load to set the initial state
    }
});
