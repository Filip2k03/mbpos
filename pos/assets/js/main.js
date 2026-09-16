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

// =========================================================================
// MBPOS V5 application shell: language, installability, and honest offline UX
// =========================================================================
(function () {
    const translations = {
        "Dashboard": "ဒက်ရှ်ဘုတ်",
        "Ledger": "မှတ်တမ်း",
        "Financials": "ဘဏ္ဍာရေး",
        "Admin Tools": "စီမံခန့်ခွဲမှုကိရိယာများ",
        "Admin Dashboard": "စီမံခန့်ခွဲသူ ဒက်ရှ်ဘုတ်",
        "User Management": "အသုံးပြုသူ စီမံခန့်ခွဲမှု",
        "Dev Center": "Developer စင်တာ",
        "Branches": "ရုံးခွဲများ",
        "Home": "ပင်မ",
        "Alerts": "သတိပေးချက်များ",
        "Logout": "ထွက်ရန်",
        "Install app": "အက်ပ်ထည့်သွင်းရန်",
        "All rights reserved.": "မူပိုင်ခွင့်အားလုံး ထိန်းသိမ်းထားသည်။",
        "Engineered by": "ဖန်တီးသူ",
        "Create Delivery Voucher": "ပို့ဆောင်ရေးဘောက်ချာ ဖန်တီးရန်",
        "Record a new shipment into the ledger": "ပို့ဆောင်မှုအသစ်ကို မှတ်တမ်းတွင် ထည့်သွင်းပါ",
        "Sender Details": "ပို့သူအချက်အလက်",
        "Receiver Details": "လက်ခံသူအချက်အလက်",
        "Routing & Logistics": "လမ်းကြောင်းနှင့် လော့ဂျစ်တစ်",
        "Item Breakdown": "ပစ္စည်းအသေးစိတ်",
        "Add Row": "အတန်းထည့်ရန်",
        "Total Weight": "စုစုပေါင်းအလေးချိန်",
        "Grand Total": "စုစုပေါင်း",
        "Create Ledger Entry": "မှတ်တမ်းသွင်းရန်",
        "Confirm Voucher Details": "ဘောက်ချာအချက်အလက် အတည်ပြုရန်",
        "Go Back": "နောက်သို့",
        "Confirm & Issue": "အတည်ပြုပြီး ထုတ်ပေးရန်",
        "New": "အသစ်",
        "Existing": "ရှိပြီးသား",
        "Full Name": "အမည်အပြည့်အစုံ",
        "Phone Number": "ဖုန်းနံပါတ်",
        "Delivery Address": "ပို့ဆောင်မည့်လိပ်စာ",
        "Origin Point": "မူလနေရာ",
        "Dest. Region": "ခရီးဆုံးဒေသ",
        "Dest. Branch": "ခရီးဆုံးရုံးခွဲ",
        "Delivery Type": "ပို့ဆောင်မှုအမျိုးအစား",
        "Currency": "ငွေကြေး",
        "Additional Delivery Charge": "ထပ်ဆောင်းပို့ဆောင်ခ",
        "Operational Notes": "လုပ်ငန်းမှတ်ချက်များ",
        "Search by name or phone...": "အမည် သို့မဟုတ် ဖုန်းဖြင့်ရှာပါ...",
        "Select Region First": "ဒေသကို အရင်ရွေးပါ",
        "Select Destination": "ခရီးဆုံးနေရာရွေးပါ",
        "Select Category...": "အမျိုးအစားရွေးပါ...",
        "Remove Item": "ပစ္စည်းဖယ်ရှားရန်",
        "You are offline. Saved pages remain available; live records require a connection.": "အင်တာနက်ချိတ်ဆက်မှု မရှိပါ။ သိမ်းထားသောစာမျက်နှာများကို အသုံးပြုနိုင်သော်လည်း တိုက်ရိုက်မှတ်တမ်းများအတွက် ချိတ်ဆက်မှုလိုအပ်ပါသည်။",
        "Search customer, tracking number, or voucher...": "ဖောက်သည်၊ ခြေရာခံနံပါတ် သို့မဟုတ် ဘောက်ချာ ရှာရန်..."
    };
    const english = Object.keys(translations).reduce((map, key) => {
        map[translations[key]] = key;
        return map;
    }, {});
    const languageKey = 'mbpos_language';

    function currentLanguage() {
        try { return localStorage.getItem(languageKey) || 'en'; } catch (e) { return 'en'; }
    }

    function translateText(text, language) {
        const normalized = text.trim();
        if (!normalized) return text;
        const dictionary = language === 'mm' ? translations : english;
        const translated = dictionary[normalized];
        return translated ? text.replace(normalized, translated) : text;
    }

    function applyLanguage(language) {
        document.documentElement.lang = language === 'mm' ? 'my' : 'en';
        document.documentElement.dataset.language = language;
        document.querySelectorAll('[data-i18n]').forEach(function (node) {
            const key = node.dataset.i18n;
            node.textContent = language === 'mm' ? (translations[key] || key) : key;
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(function (node) {
            const key = node.dataset.i18nPlaceholder;
            node.placeholder = language === 'mm' ? (translations[key] || key) : key;
        });
        // Translate plain labels/options across legacy pages without touching form values.
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function (node) {
            if (node.parentElement && !node.parentElement.closest('script, style, textarea, input, select, option, [data-i18n]')) {
                node.nodeValue = translateText(node.nodeValue, language);
            }
        });
        document.querySelectorAll('#language-toggle .language-option').forEach(function (option) {
            option.classList.toggle('is-selected', option.classList.contains('language-option-mm') ? language === 'mm' : language === 'en');
        });
    }

    function setOfflineState() {
        const offline = !navigator.onLine;
        const banner = document.getElementById('offline-status');
        if (banner) banner.hidden = !offline;
        document.body.classList.toggle('is-offline', offline);
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyLanguage(currentLanguage());
        setOfflineState();

        const toggle = document.getElementById('language-toggle');
        if (toggle) toggle.addEventListener('click', function () {
            const language = currentLanguage() === 'mm' ? 'en' : 'mm';
            try { localStorage.setItem(languageKey, language); } catch (e) {}
            applyLanguage(language);
        });

        const search = document.getElementById('global-search');
        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k' && search) {
                event.preventDefault();
                search.focus();
            }
        });
        if (search) search.addEventListener('search', function () {
            if (!search.value.trim()) return;
            window.location.href = 'index.php?page=voucher_list';
        });
    });
    window.addEventListener('online', setOfflineState);
    window.addEventListener('offline', setOfflineState);

    let deferredInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        const installButton = document.getElementById('install-pwa');
        if (installButton) installButton.hidden = false;
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('#install-pwa') || !deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        deferredInstallPrompt.userChoice.finally(function () {
            deferredInstallPrompt = null;
            const installButton = document.getElementById('install-pwa');
            if (installButton) installButton.hidden = true;
        });
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function () {
                // Offline enhancement is progressive; the online POS remains usable if SW is unavailable.
            });
        });
    }
}());

