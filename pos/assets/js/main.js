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

// =========================================================================
// Maintenance, High-Load Advisory & Voucher Breakdown Modal Controllers
// =========================================================================

/**
 * Opens the Maintenance & High Load Diagnostic Modal
 */
window.openMaintenanceModal = function() {
    const modal = document.getElementById('pos-maintenance-modal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
};

/**
 * Closes the Maintenance Modal and records user acknowledgement
 * @param {boolean} isContinueWorking - Whether user clicked 'Continue to Working'
 */
window.closeMaintenanceModal = function(isContinueWorking = false) {
    const modal = document.getElementById('pos-maintenance-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
        
        if (isContinueWorking) {
            const shiftKey = modal.getAttribute('data-shift-key') || 'mbpos_shift_default';
            // Save acknowledgement for this specific GMT+6:30 shift window
            try {
                sessionStorage.setItem('mbpos_maintenance_dismissed', 'true');
                sessionStorage.setItem(shiftKey, 'true');
                localStorage.setItem(shiftKey, 'true');
            } catch(e) {}

            // Display a sleek Toast notification acknowledging continuous work
            if (typeof Toastify === 'function') {
                Toastify({
                    text: "✅ Shift Checkpoint Acknowledged: You can continue issuing vouchers and managing logistics smoothly.",
                    duration: 6000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    style: {
                        background: "linear-gradient(135deg, #10b981, #059669)",
                        color: "#ffffff",
                        borderRadius: "14px",
                        boxShadow: "0 10px 25px -5px rgba(16, 185, 129, 0.4)",
                        fontWeight: "700",
                        fontSize: "13px",
                        padding: "14px 20px"
                    }
                }).showToast();
            }
        }
    }
};

/**
 * Dismisses the sticky top alert banner
 */
window.dismissMaintenanceBanner = function() {
    const banner = document.getElementById('pos-maintenance-banner');
    if (banner) {
        banner.style.transition = 'all 0.3s ease';
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(-100%)';
        setTimeout(() => {
            banner.style.display = 'none';
            try {
                sessionStorage.setItem('mbpos_banner_dismissed', 'true');
            } catch(e) {}
        }, 300);
    }
};

/**
 * Toggles the Technical Support & Contact drawer within the modal
 */
window.toggleTechSupportCard = function() {
    const card = document.getElementById('tech-support-card');
    if (card) {
        card.classList.toggle('hidden');
        if (!card.classList.contains('hidden')) {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
};

/**
 * Copies diagnostic token and system statistics to clipboard
 * @param {string} token - Diagnostic Token ID
 */
window.copyDiagnosticToken = function(token) {
    const infoText = `[MBPOS Diagnostic Token: ${token}] - Timezone: GMT+6:30 - Date: ${new Date().toISOString()} - Node: High Load & Maintenance Active`;
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(infoText).then(() => {
            handleCopiedState();
        }).catch(() => fallbackCopy(infoText));
    } else {
        fallbackCopy(infoText);
    }

    function fallbackCopy(text) {
        const tempInput = document.createElement('textarea');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        try {
            document.execCommand('copy');
            handleCopiedState();
        } catch (e) {
            alert('Token: ' + token);
        }
        document.body.removeChild(tempInput);
    }

    function handleCopiedState() {
        const btnText = document.getElementById('copy-token-btn-text');
        if (btnText) {
            const orig = btnText.textContent;
            btnText.textContent = 'Copied to Clipboard!';
            setTimeout(() => { btnText.textContent = orig; }, 3000);
        }
        if (typeof Toastify === 'function') {
            Toastify({
                text: "📋 Diagnostic Token copied! Share this with TechyyFilip / Payvia Support for fast troubleshooting.",
                duration: 5000,
                close: true,
                gravity: "top",
                position: "right",
                style: {
                    background: "#0f172a",
                    color: "#38bdf8",
                    borderLeft: "4px solid #38bdf8",
                    borderRadius: "12px",
                    fontWeight: "600",
                    fontSize: "13px"
                }
            }).showToast();
        }
    }
};

// Check if modal or banner should be auto-restored or auto-shown for the current shift checkpoint
document.addEventListener('DOMContentLoaded', function() {
    // Check banner dismissed state
    try {
        if (sessionStorage.getItem('mbpos_banner_dismissed') === 'true') {
            const banner = document.getElementById('pos-maintenance-banner');
            if (banner) banner.style.display = 'none';
        }
    } catch(e) {}
});

