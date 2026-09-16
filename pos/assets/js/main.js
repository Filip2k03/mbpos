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
    // Non-GET requests automatically attach the X-CSRF-Token header if available.
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

            // CSRF header injection for non-GET requests
            if (method !== 'GET') {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta && csrfMeta.content) {
                    if (!requestOptions.headers) requestOptions.headers = {};
                    if (typeof requestOptions.headers.set === 'function') {
                        if (!requestOptions.headers.has('X-CSRF-Token')) {
                            requestOptions.headers.set('X-CSRF-Token', csrfMeta.content);
                        }
                    } else if (!requestOptions.headers['X-CSRF-Token']) {
                        requestOptions.headers['X-CSRF-Token'] = csrfMeta.content;
                    }
                }
            }

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
        // Core Shell & Navigation
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
        "Admin Control Center": "စီမံခန့်ခွဲသူ ထိန်းချုပ်ကွက်",
        "User Management": "အသုံးပြုသူ စီမံခန့်ခွဲမှု",
        "Dev Center": "Developer စင်တာ",
        "Branches": "ရုံးခွဲများ",
        "Home": "ပင်မ",
        "Alerts": "သတိပေးချက်များ",
        "Logout": "ထွက်ရန်",
        "Install app": "အက်ပ်ထည့်သွင်းရန်",
        "All rights reserved.": "မူပိုင်ခွင့်အားလုံး ထိန်းသိမ်းထားသည်။",
        "Engineered by": "ဖန်တီးသူ",
        "Menu": "မီနူး",
        "Navigation": "လမ်းညွှန်",
        "Close Menu": "မီနူးပိတ်ရန်",
        "Skip to content": "အဓိကအကြောင်းအရာသို့ သွားရန်",
        "Primary navigation": "အဓိက လမ်းညွှန်မီနူး",
        "Close menu": "မီနူးပိတ်ရန်",
        "Open menu": "မီနူးဖွင့်ရန်",
        "Global search": "အထွေထွေ ရှာဖွေမှု",
        "Switch language": "ဘာသာစကား ပြောင်းရန်",
        "Voucher creation progress": "ဘောက်ချာဖန်တီးမှု အဆင့်များ",
        "Currency selection": "ငွေကြေး ရွေးချယ်မှု",

        // Target IA Groups
        "Operations": "လုပ်ငန်းဆောင်ရွက်မှု",
        "Finance": "ဘဏ္ဍာရေး",
        "Configuration": "စနစ်ဖွဲ့စည်းမှု",
        "Administration": "စီမံခန့်ခွဲမှု",
        "Voucher Ledger": "ဘောက်ချာ မှတ်တမ်း",
        "Maintenance Zones": "စနစ်ထိန်းသိမ်းမှု ဇုန်များ",

        // Command Palette & Search
        "Command Palette": "အမြန်ညွှန်ကြားချက် ပလက်ဖောင်း",
        "Quick Actions": "အမြန်ဆောင်ရွက်ချက်များ",
        "Press Esc to close": "ပိတ်ရန် Esc ကိုနှိပ်ပါ",
        "Search vouchers or jump to screen...": "ဘောက်ချာရှာရန် သို့မဟုတ် စာမျက်နှာသို့သွားရန်...",
        "Search vouchers, actions...": "ဘောက်ချာ သို့မဟုတ် စာမျက်နှာ ရှာရန်...",
        "Search vouchers, jumps, actions...": "ဘောက်ချာ သို့မဟုတ် ညွှန်ကြားချက်များ ရှာရန်...",
        "Search vouchers": "ဘောက်ချာများ ရှာဖွေရန်",
        "Jump to": "သွားရောက်ရန်",

        // Voucher Create & Management
        "Create Delivery Voucher": "ပို့ဆောင်ရေးဘောက်ချာ ဖန်တီးရန်",
        "Record a new shipment into the ledger": "ပို့ဆောင်မှုအသစ်ကို မှတ်တမ်းတွင် ထည့်သွင်းပါ",
        "Enter shipment details and review totals before creating the voucher.": "ဘောက်ချာမဖန်တီးမီ ပို့ဆောင်မှုအချက်အလက်များကို ဖြည့်စွက်ပြီး ကျသင့်ငွေကို စစ်ဆေးပါ။",
        "Online": "အွန်လိုင်း",
        "Live records available": "တိုက်ရိုက်မှတ်တမ်းများ အသုံးပြုနိုင်ပါသည်",
        "Search tracking number or voucher": "ခြေရာခံနံပါတ် သို့မဟုတ် ဘောက်ချာ ရှာရန်",
        "Sender & Receiver": "ပို့သူနှင့် လက်ခံသူ",
        "Routing": "လမ်းကြောင်း",
        "Routing & Service": "လမ်းကြောင်းနှင့် ဝန်ဆောင်မှု",
        "Items": "ပစ္စည်းများ",
        "Item": "ပစ္စည်း",
        "Package Items": "ပို့ဆောင်မည့် ပစ္စည်းများ",
        "Review": "ပြန်လည်စစ်ဆေးရန်",
        "Order Review": "အမှာစာ ပြန်လည်စစ်ဆေးရန်",
        "Create": "ဖန်တီးရန်",
        "Issue Voucher": "ဘောက်ချာ ထုတ်ပေးရန်",
        "V5 Workspace": "V5 လုပ်ငန်းခွင်",
        "Sender Details": "ပို့သူအချက်အလက်",
        "Receiver Details": "လက်ခံသူအချက်အလက်",
        "New Sender Details": "ပို့သူအချက်အလက် အသစ်",
        "New Receiver Details": "လက်ခံသူအချက်အလက် အသစ်",
        "Manual entry": "ကိုယ်တိုင် ထည့်သွင်းခြင်း",
        "Routing & Logistics": "လမ်းကြောင်းနှင့် လော့ဂျစ်တစ်",
        "Destination and service": "ခရီးဆုံးနှင့် ဝန်ဆောင်မှု",
        "Item Breakdown": "ပစ္စည်းအသေးစိတ်",
        "Package calculation": "ပစ္စည်းကုန်ကျစရိတ် တွက်ချက်မှု",
        "Add Row": "အတန်းထည့်ရန်",
        "Add Item": "ပစ္စည်းထည့်ရန်",
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
        "Phone number": "ဖုန်းနံပါတ်",
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
        "Select destination": "ခရီးဆုံးနေရာရွေးပါ",
        "Select Category...": "အမျိုးအစားရွေးပါ...",
        "Select category": "အမျိုးအစားရွေးပါ",
        "Select branch": "ရုံးခွဲရွေးပါ",
        "Select region first": "ဒေသကို ဦးစွာရွေးပါ",
        "No branches available": "အသုံးပြုနိုင်သော ရုံးခွဲမရှိပါ",
        "Select origin": "မူလနေရာရွေးပါ",
        "Select origin branch": "မူလရုံးခွဲရွေးပါ",
        "Select currency": "ငွေကြေးရွေးပါ",
        "Select delivery type": "ပို့ဆောင်မှုရွေးပါ",
        "Remove Item": "ပစ္စည်းဖယ်ရှားရန်",
        "Item Category": "ပစ္စည်းအမျိုးအစား",
        "Action": "လုပ်ဆောင်ချက်",
        "Order Summary": "အမှာစာ အကျဉ်းချုပ်",
        "Subtotal": "ကျသင့်ငွေ",
        "Delivery Charge": "ပို့ဆောင်ခ",
        "Additional Charge": "ထပ်ဆောင်းခ",
        "Discount": "လျှော့စျေး",
        "Preview and Print Voucher": "ဘောက်ချာ အကြိုကြည့် / ပရင့်ထုတ်ရန်",
        "Preview / Print Voucher": "ဘောက်ချာ အကြိုကြည့် / ပရင့်ထုတ်ရန်",
        "Voucher Preview": "ဘောက်ချာ အကြိုကြည့်ရှုမှု",
        "Tracking & Voucher Info": "ခြေရာခံနှင့် ဘောက်ချာ အချက်အလက်",
        "Voucher ID": "ဘောက်ချာ အမှတ်",
        "Pending Save": "ဖန်တီးပြီးမှ သတ်မှတ်ပါမည်",
        "Shipment Status": "ပို့ဆောင်မှု အခြေအနေ",
        "Not created": "မဖန်တီးရသေးပါ",
        "Tracking begins after creation": "ဘောက်ချာဖန်တီးပြီးမှ ခြေရာခံနိုင်ပါမည်",
        "Weight (kg)": "အလေးချိန် (ကီလို)",
        "Price / kg": "၁ ကီလိုဈေးနှုန်း",
        "Total": "စုစုပေါင်း",
        "Sender full name": "ပို့သူ အမည်အပြည့်အစုံ",
        "Receiver full name": "လက်ခံသူ အမည်အပြည့်အစုံ",
        "Delivery address": "ပို့ဆောင်မည့် လိပ်စာ",
        "Operational notes (optional)...": "လိုအပ်ပါက လုပ်ငန်းမှတ်ချက် ရေးပါ...",
        "Validated securely when submitted": "တင်သွင်းချိန်တွင် လုံခြုံစွာ စစ်ဆေးအတည်ပြုပါမည်",
        "Important Notes": "အရေးကြီးသော မှတ်ချက်များ",
        "No items added": "ပစ္စည်း မထည့်သွင်းရသေးပါ",
        "Not entered": "မထည့်သွင်းရသေးပါ",
        "Not selected": "မရွေးချယ်ရသေးပါ",
        "Shipment Voucher": "ပို့ဆောင်ရေး ဘောက်ချာ",
        "Original print style": "မူလပရင့်ပုံစံ",
        "Voucher": "ဘောက်ချာ",
        "Tracking": "ခြေရာခံနံပါတ်",
        "Keep this voucher for tracking and enquiry support. Delivery timing may vary by destination. Operational notes are visible to authorized logistics personnel only.": "ခြေရာခံရန်နှင့် စုံစမ်းမေးမြန်းရန် ဤဘောက်ချာကို သိမ်းဆည်းထားပါ။ ခရီးဆုံးနေရာအလိုက် ပို့ဆောင်ချိန် ကွာခြားနိုင်ပါသည်။ လုပ်ငန်းမှတ်ချက်များကို ခွင့်ပြုထားသော ဝန်ထမ်းများသာ ကြည့်ရှုနိုင်ပါသည်။",
        "Illegal or prohibited goods are not accepted.": "ဥပဒေနှင့်မလွတ်ကင်းသောပစ္စည်းများ လုံးဝ လက်မခံပါ။",
        "Items must be declared accurately and may be inspected for security.": "ပစ္စည်းအမျိုးအစားကို မှန်ကန်စွာကြေညာပြီး လုံခြုံရေးစစ်ဆေးမှုကို လက်ခံရပါမည်။",
        "Food and fragile items must be packed for safe handling.": "အစားအသောက်နှင့် ပျက်စီးလွယ်သောပစ္စည်းများကို သတ်မှတ်ချက်အတိုင်း ထုပ်ပိုးရပါမည်။",

        // Voucher interaction and validation feedback
        "Voucher requires at least one item.": "ဘောက်ချာတွင် အနည်းဆုံး ပစ္စည်းတစ်မျိုး ထည့်သွင်းပါ။",
        "Sender full name is required.": "ပို့သူအမည်အပြည့်အစုံ ထည့်သွင်းပါ။",
        "Sender phone number is required.": "ပို့သူဖုန်းနံပါတ် ထည့်သွင်းပါ။",
        "Receiver full name is required.": "လက်ခံသူအမည်အပြည့်အစုံ ထည့်သွင်းပါ။",
        "Receiver phone number is required.": "လက်ခံသူဖုန်းနံပါတ် ထည့်သွင်းပါ။",
        "Delivery address is required.": "ပို့ဆောင်မည့်လိပ်စာ ထည့်သွင်းပါ။",
        "Destination region is required.": "ခရီးဆုံးဒေသ ရွေးချယ်ပါ။",
        "Destination branch is required.": "ခရီးဆုံးရုံးခွဲ ရွေးချယ်ပါ။",
        "Select a currency.": "အသုံးပြုမည့် ငွေကြေးကို ရွေးချယ်ပါ။",
        "Select a delivery type.": "ပို့ဆောင်မှုအမျိုးအစားကို ရွေးချယ်ပါ။",
        "Add at least one item.": "အနည်းဆုံး ပစ္စည်းတစ်မျိုး ထည့်သွင်းပါ။",
        "Select an item category.": "ပစ္စည်းအမျိုးအစားကို ရွေးချယ်ပါ။",
        "Enter a weight greater than 0 kg for each item.": "ပစ္စည်းတစ်မျိုးစီအတွက် ၀ ကီလိုထက်များသော အလေးချိန် ထည့်သွင်းပါ။",
        "Creating ledger entry...": "မှတ်တမ်းသွင်းနေပါသည်...",
        "Submitting voucher securely...": "ဘောက်ချာကို လုံခြုံစွာ တင်သွင်းနေပါသည်...",
        "Voucher details loaded. Review them before creating the new voucher.": "ဘောက်ချာအချက်အလက်များ ထည့်သွင်းပြီးပါပြီ။ ဘောက်ချာအသစ် မဖန်တီးမီ ပြန်လည်စစ်ဆေးပါ။",

        // Statuses
        "Pending": "ဆိုင်းငံ့ထားဆဲ",
        "Created": "ဖန်တီးပြီး",
        "Picked Up": "လက်ခံရရှိပြီး",
        "In Transit": "ပို့ဆောင်ဆဲ",
        "Delivered": "ပို့ဆောင်ပြီး",
        "Completed": "ပြီးစီးပါပြီ",
        "Cancelled": "ပယ်ဖျက်ပြီး",
        "Paid": "ငွေချေပြီး",
        "Unpaid": "ငွေမချေသေး",

        // Actions & Buttons
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
        "Selected Vouchers": "ရွေးချယ်ထားသော ဘောက်ချာများ",
        "Apply Changes": "ပြောင်းလဲချက်များ အတည်ပြုရန်",
        "Apply Bulk Updates": "အစုလိုက် မွမ်းမံမှု အတည်ပြုရန်",
        "Reset Filters": "စစ်ထုတ်မှုများ ပြန်လည်သတ်မှတ်ရန်",
        "Duplicate as New": "အသစ်အဖြစ် ကူးယူရန်",
        "Keyboard Shortcuts": "ကီးဘုတ် ဖြတ်လမ်းများ",
        "Command Palette / Global Search": "အမြန်ညွှန်ကြားချက် / အထွေထွေရှာဖွေမှု",
        "Create New Voucher": "ဘောက်ချာအသစ် ဖန်တီးရန်",
        "Shipments Queue": "ပို့ဆောင်ရေး စာရင်း",
        "Shortcuts Cheat Sheet": "ဖြတ်လမ်း လမ်းညွှန်",
        "Close Dialog / Menu": "ပိတ်ရန်",
        "Back to Ledger": "စာရင်းသို့ ပြန်သွားရန်",

        // Data Fields
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

        // Finance & Reports
        "Revenue": "ဝင်ငွေ",
        "Gross Revenue": "စုစုပေါင်း ဝင်ငွေ",
        "Expenses": "အသုံးစရိတ်များ",
        "Expense": "အသုံးစရိတ်",
        "Profit": "အမြတ်",
        "Loss": "အရှုံး",
        "Profit & Loss": "အမြတ်နှင့် အရှုံး",
        "Net Profit": "အသားတင်အမြတ်",
        "Net Margin": "အသားတင် အမြတ်နှုန်း",
        "Total Income": "စုစုပေါင်းဝင်ငွေ",
        "Total Expenses": "စုစုပေါင်းအသုံးစရိတ်",
        "Other Income": "အခြားဝင်ငွေ",
        "Daily Ledger": "နေ့စဉ် မှတ်တမ်း",
        "Monthly Ledger": "လစဉ် မှတ်တမ်း",
        "By Currency": "ငွေကြေးအလိုက်",
        "Net Positive": "အသားတင် အမြတ်ပေါ်",
        "Net Deficit": "အသားတင် အရှုံးပေါ်",
        "Net Worth": "အသားတင် တန်ဖိုး",
        "Strict currency separation enforced": "ငွေကြေးအလိုက် သီးခြားတွက်ချက်ထားပါသည်",
        "Voucher Income": "ဘောက်ချာ ဝင်ငွေ",

        // Configuration
        "Operating Branches": "ဖွင့်လှစ်ထားသော ရုံးခွဲများ",
        "Item Types": "ပစ္စည်းအမျိုးအစားများ",
        "Item Categories": "ကုန်ပစ္စည်း အမျိုးအစားများ",
        "Delivery Types": "ပို့ဆောင်မှုအမျိုးအစားများ",
        "Currencies": "ငွေကြေးများ",
        "Branch Management": "ရုံးခွဲ စီမံခန့်ခွဲမှု",
        "System Currencies": "စနစ်သုံး ငွေကြေးများ",
        "Active Delivery Services": "လက်ရှိ ပို့ဆောင်မှု ဝန်ဆောင်မှုများ",
        "Network Directory": "ကွန်ရက် လမ်းညွှန်",
        "Shipment Operations": "ပို့ဆောင်ရေး လုပ်ငန်းစဉ်များ",
        "Shipment Stock": "ပို့ဆောင်ရေး စာရင်း",
        "Active Shipment Queue": "လက်ရှိ ပို့ဆောင်မှု တန်းစီစာရင်း",
        "Update Selected": "ရွေးချယ်ထားသည်ကို မွမ်းမံရန်",
        "Bulk Voucher Update": "ဘောက်ချာ အစုလိုက် မွမ်းမံရန်",
        "Filtered Voucher Ledger": "စစ်ထုတ်ထားသော ဘောက်ချာ စာရင်း",
        "Selected Status:": "ရွေးချယ်ထားသော အခြေအနေ-",
        "Set Selected To:": "ရွေးချယ်ထားသည်ကို ပြောင်းရန်-",
        "Choose status": "အခြေအနေ ရွေးပါ",
        "Maximum 200 updates per batch": "တစ်ကြိမ်လျှင် အများဆုံး ၂၀၀ သာ ပြင်ဆင်နိုင်ပါသည်",
        "Universal Search": "အထွေထွေ ရှာဖွေမှု",
        "Tracking Code": "ခြေရာခံ ကုဒ်",
        "Master Voucher Ledger": "ပင်မ ဘောက်ချာ စာရင်း",
        "Total entries": "စုစုပေါင်း မှတ်တမ်း",
        "total entries": "စုစုပေါင်း မှတ်တမ်း",
        "Export CSV": "CSV ထုတ်ယူရန်",

        // Diagnostics & Maintenance
        "Error Logs": "ချို့ယွင်းချက် မှတ်တမ်း",
        "System Diagnostics": "စနစ်စစ်ဆေးမှု",
        "High Load Alert": "ဝန်ထုပ်ဝန်ပိုးမြင့်မားနေပါသည်",
        "System Advisory": "စနစ်ဆိုင်ရာ အကြံပြုချက်",
        "Modify in Dev Center": "စနစ်ပြုပြင်ရေးတွင် ပြင်ရန်",
        "Dev Center": "စနစ်ပြုပြင်ရေး ဗဟို",
        "Developer-Only Area": "စနစ်ပြုပြင်သူများသာ ဝင်ရောက်နိုင်သော နေရာ",
        "Routine maintenance & updates in progress": "ပုံမှန်စနစ်ထိန်းသိမ်းမှုနှင့် အဆင့်မြှင့်တင်မှုများ ဆောင်ရွက်နေပါသည်",
        "Active Maintenance": "စနစ်ထိန်းသိမ်းနေဆဲ",
        "Normal Operation": "ပုံမှန်လည်ပတ်နေသည်",

        // Auth & Portal
        "MBPOS Portal": "MBPOS စနစ်ဝင်ရောက်ရန်",
        "Secure access to your operational dashboard": "လုပ်ငန်းခွင်ဒက်ရှ်ဘုတ်သို့ လုံခြုံစွာဝင်ရောက်ပါ",
        "System Identity": "အသုံးပြုသူ အမည်",
        "Access Key": "လျှို့ဝှက်ကုဒ်",
        "Keep me signed in": "အကောင့်ဝင်ထားပြီးသားထားရန်",
        "Authorize Access": "စနစ်သို့ ဝင်ရောက်မည်",
        "Reveal": "ပြပါ",
        "Hide": "ဝှက်ပါ",
        "Enter your username": "အသုံးပြုသူအမည် ရိုက်ထည့်ပါ",

        // Voucher View & Details
        "Voucher Details": "ဘောက်ချာ အချက်အလက်များ",
        "Issued by": "ထုတ်ပေးသူ",
        "Deliver To": "ပို့ဆောင်ရမည့်လိပ်စာ",
        "Origin": "မူရင်းဒေသ",
        "Total Due": "ပေးချေရန် စုစုပေါင်း",
        "Operational Timeline": "လုပ်ငန်းစဉ် အချိန်မှတ်တမ်း",
        "No operational notes recorded yet.": "မှတ်တမ်းမှတ်ရာများ မရှိသေးပါ",
        "Current Status": "လက်ရှိအခြေအနေ",
        "Add to Conversation": "မှတ်ချက်အသစ် ထည့်သွင်းရန်",
        "Write an operational note... (Supports English & Myanmar)": "မှတ်ချက်ရေးသားရန်... (အင်္ဂလိပ် / မြန်မာ)",
        "Add Entry & Update": "မှတ်တမ်းထည့်ပြီး အတည်ပြုရန်",
        "Print Waybill": "ဘောက်ချာ ပရင့်ထုတ်ရန်",

        // Notifications
        "Notification History": "အသိပေးချက် မှတ်တမ်း",
        "Operational dispatch alerts and status changes": "လုပ်ငန်းဆိုင်ရာ အသိပေးချက်များနှင့် အခြေအနေပြောင်းလဲမှုများ",
        "Mark All as Read": "အားလုံး ဖတ်ပြီးအဖြစ် သတ်မှတ်ရန်",
        "You have no notifications yet.": "အသိပေးချက် မရှိသေးပါ",

        // Pagination
        "Page": "စာမျက်နှာ",
        "of": "၏",
        "Previous": "ရှေ့သို့",
        "Next": "နောက်သို့",

        // User Management & Access Control
        "User Management": "အသုံးပြုသူ စီမံခန့်ခွဲမှု",
        "Register new personnel and manage access roles": "ဝန်ထမ်းအသစ် စာရင်းသွင်းခြင်းနှင့် လုပ်ပိုင်ခွင့် သတ်မှတ်ခြင်း",
        "Register Account": "အကောင့် စာရင်းသွင်းရန်",
        "Username": "အသုံးပြုသူအမည်",
        "Access Role": "အသုံးပြုခွင့် အဆင့်",
        "Select Role...": "အဆင့် ရွေးချယ်ပါ…",
        "Administrator": "အက်ဒမင်",
        "Developer": "ဆော့ဖ်ဝဲရေးသားသူ",
        "Standard Staff": "ရုံးဝန်ထမ်း",
        "General (No Region/Branch)": "အထွေထွေ (ရုံးခွဲမသတ်မှတ်)",
        "Assigned Region": "တာဝန်ကျ ဒေသ",
        "Select Region First": "ဒေသ အရင်ရွေးပါ",
        "Assigned Branch": "တာဝန်ကျ ရုံးခွဲ",
        "Password": "လျှို့ဝှက်နံပါတ်",
        "Confirm Password": "လျှို့ဝှက်နံပါတ် အတည်ပြုပါ",
        "Create Account": "အကောင့် ဖွင့်ရန်",
        "Active User Directory": "လက်ရှိ အသုံးပြုသူများ စာရင်း",
        "Assignment (Region/Branch)": "တာဝန်ကျ (ဒေသ/ရုံးခွဲ)",
        "No users found.": "အသုံးပြုသူ မတွေ့ရှိပါ",
        "Register the first account using the form above.": "အထက်ပါဖောင်ဖြင့် ပထမဆုံး အကောင့်ကို စာရင်းသွင်းပါ",
        "Global Access": "အထွေထွေ အသုံးပြုခွင့်",
        "users": "ဦး",

        // System messages & feedback
        "You are offline. Saved pages remain available; live records require a connection.": "အင်တာနက်ချိတ်ဆက်မှု မရှိပါ။ သိမ်းထားသောစာမျက်နှာများကို အသုံးပြုနိုင်သော်လည်း တိုက်ရိုက်မှတ်တမ်းများအတွက် ချိတ်ဆက်မှုလိုအပ်ပါသည်။",
        "Search customer, tracking number, or voucher...": "ဖောက်သည်၊ ခြေရာခံနံပါတ် သို့မဟုတ် ဘောက်ချာ ရှာရန်...",
        "No records found": "မှတ်တမ်းမတွေ့ရှိပါ",
        "Authenticating...": "စစ်ဆေးနေပါသည်...",
        "System Restored Online": "စနစ်အင်တာနက် ပြန်လည်ရရှိပါပြီ"
    };

    const english = Object.keys(translations).reduce((map, key) => {
        map[translations[key]] = key;
        return map;
    }, {});
    const languageKey = 'mbpos_language';

    function currentLanguage() {
        try { return localStorage.getItem(languageKey) || 'en'; } catch (e) { return 'en'; }
    }

    window.mbposT = function (key) {
        return currentLanguage() === 'mm' ? (translations[key] || key) : key;
    };

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
        document.querySelectorAll('[data-i18n-aria-label]').forEach(function (node) {
            const key = node.dataset.i18nAriaLabel;
            node.setAttribute('aria-label', language === 'mm' ? (translations[key] || key) : key);
        });
        document.querySelectorAll('[data-i18n-title]').forEach(function (node) {
            const key = node.dataset.i18nTitle;
            node.title = language === 'mm' ? (translations[key] || key) : key;
        });
        // Translate plain text nodes without touching scripts, styles, or inputs
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
        document.dispatchEvent(new CustomEvent('mbpos:languagechange', { detail: { language: language } }));
    }

    function setOfflineState() {
        const offline = !navigator.onLine;
        const banner = document.getElementById('offline-status');
        if (banner) banner.hidden = !offline;
        document.body.classList.toggle('is-offline', offline);

        if (!offline && typeof Toastify === 'function') {
            Toastify({
                text: currentLanguage() === 'mm' ? 'စနစ်အင်တာနက် ပြန်လည်ရရှိပါပြီ' : 'System Online: Connection restored',
                duration: 3500,
                gravity: 'top',
                position: 'right',
                style: {
                    background: 'linear-gradient(135deg, #0ca678, #23c993)',
                    color: '#ffffff',
                    borderRadius: '12px',
                    fontWeight: '700',
                    fontSize: '13px'
                }
            }).showToast();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Automatic CSRF input injection for all POST forms
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

        // Apply saved language & offline status
        applyLanguage(currentLanguage());
        setOfflineState();

        // Language toggle button listener
        const toggle = document.getElementById('language-toggle');
        if (toggle) toggle.addEventListener('click', function () {
            const language = currentLanguage() === 'mm' ? 'en' : 'mm';
            try { localStorage.setItem(languageKey, language); } catch (e) {}
            applyLanguage(language);
        });

        // ---------------------------------------------------------------------
        // Command Palette (⌘K / Ctrl+K) Logic
        // ---------------------------------------------------------------------
        const cmdModal = document.getElementById('mbpos-cmd-palette');
        const cmdInput = document.getElementById('mbpos-cmd-input');
        const cmdBackdrop = cmdModal ? cmdModal.querySelector('.mbpos-cmd-backdrop') : null;
        const cmdItems = cmdModal ? cmdModal.querySelectorAll('.mbpos-cmd-item[data-jump]') : [];
        const cmdSearchItem = document.getElementById('mbpos-cmd-search-jump');

        function openCommandPalette() {
            if (!cmdModal) return;
            cmdModal.hidden = false;
            if (cmdInput) {
                cmdInput.value = '';
                filterCommandItems('');
                setTimeout(() => cmdInput.focus(), 50);
            }
        }

        function closeCommandPalette() {
            if (!cmdModal) return;
            cmdModal.hidden = true;
        }

        function filterCommandItems(query) {
            const q = (query || '').toLowerCase().trim();
            cmdItems.forEach(item => {
                const text = (item.textContent || '').toLowerCase();
                const match = !q || text.includes(q);
                item.style.display = match ? 'flex' : 'none';
            });
            if (cmdSearchItem) {
                if (q) {
                    cmdSearchItem.style.display = 'flex';
                    const targetSpan = cmdSearchItem.querySelector('.mbpos-cmd-query-text');
                    if (targetSpan) targetSpan.textContent = query;
                    cmdSearchItem.href = 'index.php?page=voucher_list&search=' + encodeURIComponent(query);
                } else {
                    cmdSearchItem.style.display = 'none';
                }
            }
        }

        const shortcutsModal = document.getElementById('mbpos-shortcuts-modal');
        const shortcutsClose = document.getElementById('mbpos-shortcuts-close');
        const shortcutsBackdrop = shortcutsModal ? shortcutsModal.querySelector('.mbpos-cmd-backdrop') : null;

        function openShortcutsModal() {
            if (!shortcutsModal) return;
            shortcutsModal.hidden = false;
        }

        function closeShortcutsModal() {
            if (!shortcutsModal) return;
            shortcutsModal.hidden = true;
        }

        if (shortcutsClose) shortcutsClose.addEventListener('click', closeShortcutsModal);
        if (shortcutsBackdrop) shortcutsBackdrop.addEventListener('click', closeShortcutsModal);

        function isTypingInField(el) {
            if (!el) return false;
            const tag = (el.tagName || '').toLowerCase();
            return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
        }

        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                if (cmdModal && !cmdModal.hidden) {
                    closeCommandPalette();
                } else {
                    if (shortcutsModal && !shortcutsModal.hidden) closeShortcutsModal();
                    openCommandPalette();
                }
                return;
            }

            if (event.key === 'Escape') {
                if (cmdModal && !cmdModal.hidden) closeCommandPalette();
                if (shortcutsModal && !shortcutsModal.hidden) closeShortcutsModal();
                if (sidebar && sidebar.classList.contains('drawer-open')) toggleMobileDrawer(false);
                return;
            }

            // Keyboard shortcuts helper (?) and quick single-key jumps (N, L, S) when not typing in an input
            if (!event.metaKey && !event.ctrlKey && !event.altKey && !isTypingInField(document.activeElement)) {
                if (event.key === '?') {
                    event.preventDefault();
                    if (shortcutsModal && !shortcutsModal.hidden) {
                        closeShortcutsModal();
                    } else {
                        if (cmdModal && !cmdModal.hidden) closeCommandPalette();
                        openShortcutsModal();
                    }
                } else if (event.key === 'n' || event.key === 'N') {
                    event.preventDefault();
                    window.location.href = 'index.php?page=voucher_create';
                } else if (event.key === 'l' || event.key === 'L') {
                    event.preventDefault();
                    window.location.href = 'index.php?page=voucher_list';
                }
            }
        });

        if (cmdBackdrop) {
            cmdBackdrop.addEventListener('click', closeCommandPalette);
        }

        if (cmdInput) {
            cmdInput.addEventListener('input', function () {
                filterCommandItems(this.value);
            });
            cmdInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const val = this.value.trim();
                    if (!val) return;
                    // If a visible item matches or search jump
                    if (cmdSearchItem && cmdSearchItem.style.display !== 'none') {
                        window.location.href = cmdSearchItem.href;
                    } else {
                        window.location.href = 'index.php?page=voucher_list&search=' + encodeURIComponent(val);
                    }
                }
            });
        }

        // Global search input in header also opens the command palette or searches directly
        const headerSearch = document.getElementById('global-search');
        if (headerSearch) {
            headerSearch.addEventListener('click', function (e) {
                e.preventDefault();
                openCommandPalette();
            });
            headerSearch.addEventListener('focus', function (e) {
                e.preventDefault();
                this.blur();
                openCommandPalette();
            });
        }

        // ---------------------------------------------------------------------
        // Mobile Drawer Toggle
        // ---------------------------------------------------------------------
        const sidebar = document.querySelector('.mbpos-sidebar');
        const drawerToggle = document.getElementById('mobile-drawer-toggle');
        const mobileNavMore = document.getElementById('mobile-nav-more');
        const sidebarClose = document.getElementById('mbpos-sidebar-close');

        function toggleMobileDrawer(open) {
            if (!sidebar) return;
            const shouldOpen = (open !== undefined) ? open : !sidebar.classList.contains('drawer-open');
            sidebar.classList.toggle('drawer-open', shouldOpen);

            let backdrop = document.querySelector('.mbpos-drawer-backdrop');
            if (shouldOpen) {
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'mbpos-drawer-backdrop';
                    backdrop.addEventListener('click', () => toggleMobileDrawer(false));
                    document.body.appendChild(backdrop);
                }
            } else {
                if (backdrop) backdrop.remove();
            }
        }

        if (drawerToggle) {
            drawerToggle.addEventListener('click', () => toggleMobileDrawer());
        }
        if (mobileNavMore) {
            mobileNavMore.addEventListener('click', () => toggleMobileDrawer());
        }
        if (sidebarClose) {
            sidebarClose.addEventListener('click', () => toggleMobileDrawer(false));
        }
    });

    // -------------------------------------------------------------------------
    // Unsaved Work Guard (beforeunload protection)
    // -------------------------------------------------------------------------
    let isFormDirty = false;
    let isFormSubmitting = false;

    document.addEventListener('input', function (e) {
        const form = e.target.closest('form[data-protect-unsaved], #voucherForm, #voucher-form');
        if (form) {
            isFormDirty = true;
        }
    });

    document.addEventListener('change', function (e) {
        const form = e.target.closest('form[data-protect-unsaved], #voucherForm, #voucher-form');
        if (form) {
            isFormDirty = true;
        }
    });

    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form');
        if (form) {
            isFormSubmitting = true;
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (isFormDirty && !isFormSubmitting) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    window.addEventListener('online', setOfflineState);
    window.addEventListener('offline', setOfflineState);

    // Progressive PWA Install Prompt Handler
    let deferredInstallPrompt = null;
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        const installButton = document.getElementById('install-pwa');
        if (installButton) installButton.hidden = false;
    });

    // iOS Safari does not support beforeinstallprompt, provide Share -> Add to Home Screen guidance
    if (isIos && !isStandalone) {
        const installButton = document.getElementById('install-pwa');
        if (installButton) installButton.hidden = false;
    }

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('#install-pwa');
        if (!btn) return;
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.finally(function () {
                deferredInstallPrompt = null;
                const installButton = document.getElementById('install-pwa');
                if (installButton) installButton.hidden = true;
            });
        } else if (isIos) {
            const isMm = (document.documentElement.dataset.language || 'en') === 'mm';
            const msg = isMm
                ? "iOS တွင် အက်ပ်သွင်းရန်: Safari အောက်ခြေရှိ Share (မျှဝေရန်) ခလုတ်ကို နှိပ်ပြီး 'Add to Home Screen' ကို ရွေးချယ်ပါ"
                : "To install on iOS: Tap the Share button in Safari, then select 'Add to Home Screen'.";
            if (typeof Toastify === 'function') {
                Toastify({
                    text: msg,
                    duration: 8000,
                    close: true,
                    gravity: "top",
                    position: "center",
                    style: {
                        background: "rgba(16, 35, 63, 0.96)",
                        color: "#fff",
                        borderRadius: "14px",
                        padding: "16px 20px",
                        fontWeight: "600",
                        boxShadow: "0 15px 35px rgba(0,0,0,0.25)"
                    }
                }).showToast();
            } else {
                alert(msg);
            }
        }
    });

    // Progressive Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function () {
                // Progressive enhancement: offline shell gracefully falls back
            });
        });
    }
}());
