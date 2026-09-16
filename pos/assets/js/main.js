document.addEventListener('DOMContentLoaded', function () {
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
        regionSelect.disabled = false;
        if (userType === 'ADMIN' || userType === 'Developer' || userType === 'General') {
            regionField.classList.remove('hidden');
        } else if (userType === 'Myanmar' || userType === 'Malay') {
            regionField.classList.add('hidden');
        } else {
            regionField.classList.add('hidden');
        }
    }

    if (userTypeSelect) {
        userTypeSelect.addEventListener('change', toggleRegionField);
        toggleRegionField();
    }
});

// =========================================================================
// MBPOS V5 application shell: language, installability, and honest offline UX
// =========================================================================
(function () {
    const inflightGets = new Map();

    // Dependency-free request helper for live UI enhancements. Concurrent GETs
    // to the same URL share one network operation, stalled requests abort, and
    // only transient failures are retried. Dynamic PHP responses bypass the
    // browser cache so authenticated records cannot become stale.
    window.mbposFetch = function (url, options) {
        options = options || {};
        const method = (options.method || 'GET').toUpperCase();
        const maxAttempts = method === 'GET' ? Math.max(1, Number(options.attempts || 2)) : 1;
        const timeoutMs = Math.max(1000, Number(options.timeout || 8000));
        const dedupe = method === 'GET' && options.dedupe !== false;
        const requestKey = method + ':' + String(url);
        let attempt = 0;

        function request() {
            attempt += 1;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), timeoutMs);
            const requestOptions = Object.assign({}, options, {
                signal: controller.signal,
                credentials: 'same-origin'
            });
            delete requestOptions.timeout;
            delete requestOptions.attempts;
            delete requestOptions.dedupe;
            if (method === 'GET' && String(url).includes('index.php')) {
                requestOptions.cache = 'no-store';
            }
            return fetch(url, requestOptions).then(function (response) {
                clearTimeout(timeout);
                if (!response.ok) {
                    const error = new Error('Request failed: ' + response.status);
                    error.retryable = response.status === 429 || response.status >= 500;
                    throw error;
                }
                return response;
            }).catch(function (error) {
                clearTimeout(timeout);
                const retryable = error.name === 'AbortError' || error.retryable !== false;
                if (retryable && attempt < maxAttempts) return request();
                throw error;
            });
        }

        if (!dedupe) return request();
        if (!inflightGets.has(requestKey)) {
            const operation = request().finally(function () {
                inflightGets.delete(requestKey);
            });
            inflightGets.set(requestKey, operation);
        }
        // Each consumer receives an independent body stream.
        return inflightGets.get(requestKey).then(function (response) {
            return response.clone();
        });
    };

    const translations = {
        "Dashboard": "ဒက်ရှ်ဘုတ်",
        "Create Voucher": "ဘောက်ချာဖန်တီးရန်",
        "Shipments": "ပို့ဆောင်မှုများ",
        "Customers": "ဖောက်သည်များ",
        "Reports": "အစီရင်ခံစာများ",
        "Settings": "ဆက်တင်များ",
        "System Online": "စနစ်အွန်လိုင်း",
        "Login Securely": "လုံခြုံစွာ ဝင်ရောက်ရန်",
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
        "Destination Region": "ခရီးဆုံးဒေသ",
        "Destination Branch": "ခရီးဆုံးရုံးခွဲ",
        "Delivery Type": "ပို့ဆောင်မှုအမျိုးအစား",
        "Currency": "ငွေကြေး",
        "Additional Delivery Charge": "ထပ်ဆောင်းပို့ဆောင်ခ",
        "Operational Notes": "လုပ်ငန်းမှတ်ချက်များ",
        "Search by name or phone...": "အမည် သို့မဟုတ် ဖုန်းဖြင့်ရှာပါ...",
        "Select Region First": "ဒေသကို အရင်ရွေးပါ",
        "Select Destination": "ခရီးဆုံးနေရာရွေးပါ",
        "Select Category...": "အမျိုးအစားရွေးပါ...",
        "Remove Item": "ပစ္စည်းဖယ်ရှားရန်",
        "Order Summary": "အမှာစာ အကျဉ်းချုပ်",
        "Subtotal": "ကျသင့်ငွေ",
        "Delivery Charge": "ပို့ဆောင်ခ",
        "Additional Charge": "ထပ်ဆောင်းခ",
        "Discount": "လျှော့စျေး",
        "Pending": "ဆိုင်းငံ့ထားဆဲ",
        "Created": "ဖန်တီးပြီး",
        "Picked Up": "လက်ခံရရှိပြီး",
        "In Transit": "ပို့ဆောင်ဆဲ",
        "Delivered": "ပို့ဆောင်ပြီး",
        "Completed": "ပြီးစီးပါပြီ",
        "Cancelled": "ပယ်ဖျက်ပြီး",
        "Paid": "ငွေချေပြီး",
        "Unpaid": "ငွေမချေသေး",
        "Save Draft": "မူကြမ်းသိမ်းရန်",
        "Preview": "အကြိုကြည့်ရှုရန်",
        "Print": "ပရင့်ထုတ်ရန်",
        "Print Voucher": "ဘောက်ချာ ပရင့်ထုတ်ရန်",
        "Export": "ထုတ်ယူရန်",
        "Export Excel": "Excel ထုတ်ယူရန်",
        "Save": "သိမ်းဆည်းရန်",
        "Edit": "ပြင်ဆင်ရန်",
        "Delete": "ဖျက်ရန်",
        "Search": "ရှာဖွေရန်",
        "Filter": "စစ်ထုတ်ရန်",
        "Reset": "မူလအတိုင်းထားရန်",
        "Update": "မွမ်းမံရန်",
        "Cancel": "မလုပ်တော့ပါ",
        "Submit": "တင်သွင်းရန်",
        "Download": "ဒေါင်းလုဒ်လုပ်ရန်",
        "Add New": "အသစ်ထည့်ရန်",
        "Bulk Update": "အစုလိုက် မွမ်းမံရန်",
        "Voucher Code": "ဘောက်ချာကုဒ်",
        "Tracking Number": "ခြေရာခံနံပါတ်",
        "Sender": "ပို့သူ",
        "Receiver": "လက်ခံသူ",
        "Destination": "ခရီးဆုံး",
        "Total Amount": "စုစုပေါင်းငွေ",
        "Status": "အခြေအနေ",
        "Date": "ရက်စွဲ",
        "Actions": "ဆောင်ရွက်ချက်များ",
        "Weight": "အလေးချိန်",
        "Category": "အမျိုးအစား",
        "Price": "ဈေးနှုန်း",
        "Price / Kg": "၁ ကီလိုဈေးနှုန်း",
        "Revenue": "ဝင်ငွေ",
        "Expenses": "အသုံးစရိတ်များ",
        "Expense": "အသုံးစရိတ်",
        "Profit": "အမြတ်",
        "Loss": "အရှုံး",
        "Profit & Loss": "အမြတ်နှင့် အရှုံး",
        "Net Profit": "အသားတင်အမြတ်",
        "Total Income": "စုစုပေါင်းဝင်ငွေ",
        "Total Expenses": "စုစုပေါင်းအသုံးစရိတ်",
        "Other Income": "အခြားဝင်ငွေ",
        "Customer List": "ဖောက်သည်များ စာရင်း",
        "Customer Name": "ဖောက်သည်အမည်",
        "Register Customer": "ဖောက်သည်အသစ် မှတ်ပုံတင်ရန်",
        "Item Types": "ပစ္စည်းအမျိုးအစားများ",
        "Delivery Types": "ပို့ဆောင်မှုအမျိုးအစားများ",
        "Currencies": "ငွေကြေးများ",
        "Branch Management": "ရုံးခွဲ စီမံခန့်ခွဲမှု",
        "Error Logs": "ချို့ယွင်းချက် မှတ်တမ်း",
        "System Diagnostics": "စနစ်စစ်ဆေးမှု",
        "High Load Alert": "ဝန်ထုပ်ဝန်ပိုးမြင့်မားနေပါသည်",
        "Routine maintenance & updates in progress": "ပုံမှန်စနစ်ထိန်းသိမ်းမှုနှင့် အဆင့်မြှင့်တင်မှုများ ဆောင်ရွက်နေပါသည်",
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
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta && csrfMeta.content) {
            document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function (form) {
                if (form.querySelector('input[name="csrf_token"]')) return;
                const csrfField = document.createElement('input');
                csrfField.type = 'hidden';
                csrfField.name = 'csrf_token';
                csrfField.value = csrfMeta.content;
                form.appendChild(csrfField);
            });
        }
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
        if (search) {
            const runSearch = function () {
                const value = search.value.trim();
                if (!value) return;
                window.location.href = 'index.php?page=voucher_list&search=' + encodeURIComponent(value);
            };
            search.addEventListener('search', runSearch);
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    runSearch();
                }
            });
        }
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
