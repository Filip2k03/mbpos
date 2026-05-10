<?php
// pos/dashboard.php - Main dashboard for staff and general users.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Role-based routing ---
if (!is_logged_in()) {
    redirect('index.php?page=login');
}

// Redirect Admins and Developers to their specific dashboards
// if (is_developer()) {
//     redirect('index.php?page=developer_dashboard');
// } elseif (is_admin()) {
//     redirect('index.php?page=admin_dashboard');
// }

global $connection;
$user_id = $_SESSION['user_id'];

// --- Fetch recent vouchers created by the current user ---
$recent_vouchers = [];
// FIX: Added 'currency' to the SELECT statement to fix the display bug.
$query = "SELECT id, voucher_code, receiver_name, total_amount, currency, status 
          FROM vouchers 
          WHERE created_by_user_id = ? 
          ORDER BY created_at DESC 
          LIMIT 5";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_vouchers[] = $row;
    }
}
mysqli_stmt_close($stmt);

include_template('header', ['page' => 'dashboard']);
?>
<div class="min-h-screen bg-gray-50">
  <header class="bg-white shadow-sm">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
      <h1 class="text-3xl font-extrabold text-indigo-700">Logistics POS Dashboard</h1>
      <div class="text-gray-700 font-semibold">Welcome, <?= htmlspecialchars($_SESSION['username']); ?></div>
    </div>
  </header>

  <main class="container mx-auto px-6 py-8">
    <!-- Quick Actions -->
    <section class="mb-10">
      <?php include_template('dashboard_actions'); ?>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Recent Vouchers -->
      <section class="lg:col-span-2 bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Your Recent Vouchers</h2>
        <?php if (empty($recent_vouchers)): ?>
          <p class="text-center text-gray-500 py-20 text-lg">No vouchers created yet.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-indigo-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 uppercase tracking-wider">Voucher Code</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 uppercase tracking-wider">Receiver</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <?php foreach ($recent_vouchers as $voucher): ?>
                  <tr class="hover:bg-indigo-50 transition-colors duration-150">
                    <td class="px-6 py-4 font-mono text-indigo-700"><?= htmlspecialchars($voucher['voucher_code']) ?></td>
                    <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($voucher['receiver_name']) ?></td>
                    <td class="px-6 py-4 font-semibold text-gray-900"><?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['total_amount'], 2) ?></td>
                    <td class="px-6 py-4">
                      <span class="inline-block px-3 py-1 text-sm rounded-full font-semibold
                        <?php
                          $statusClass = 'bg-gray-200 text-gray-700';
                          switch (strtolower($voucher['status'])) {
                            case 'pending': $statusClass = 'bg-yellow-100 text-yellow-800'; break;
                            case 'in transit': $statusClass = 'bg-blue-100 text-blue-800'; break;
                            case 'delivered': $statusClass = 'bg-green-100 text-green-800'; break;
                            case 'returned': $statusClass = 'bg-red-100 text-red-800'; break;
                            case 'cancelled': $statusClass = 'bg-purple-100 text-purple-800'; break;
                          }
                          echo $statusClass;
                        ?>">
                        <?= htmlspecialchars($voucher['status']) ?>
                      </span>
                    </td>
                    <td class="px-6 py-4">
                      <a href="index.php?page=voucher_view&id=<?= $voucher['id'] ?>" class="text-indigo-600 hover:text-indigo-900 font-medium transition">View Details</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <!-- Support / Contact Card -->
      <section class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8 flex flex-col items-center text-center">
        <div class="mb-5 bg-indigo-100 p-4 rounded-full">
          <svg class="w-12 h-12 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
          </svg>
        </div>
        <h2 class="text-2xl font-semibold text-gray-800 mb-3">Need Help?</h2>
        <p class="text-gray-600 mb-6 max-w-xs">If you encounter any issues or have questions, feel free to reach out to the developer.</p>
        <button id="contactDeveloperBtn" class="btn bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg transition duration-200">Contact Developer</button>
      </section>
    </section>
  </main>

  <!-- Contact Modal -->
  <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-3xl shadow-2xl p-8 w-full max-w-md relative">
      <button id="closeModalBtn" class="absolute top-4 right-6 text-gray-400 hover:text-gray-700 text-3xl font-bold">&times;</button>
      <h2 class="text-3xl font-bold text-indigo-700 mb-6 text-center">Contact Developer</h2>
      <p class="text-indigo-600 text-4xl font-extrabold text-center mb-8">+95 9954480806</p>
      <div class="flex justify-center gap-5 flex-wrap">
        <a href="tel:+959954480806" class="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-5 rounded-2xl shadow transition duration-200">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
          </svg>
          Call Now
        </a>
        <a href="https://t.me/stephanfilip2k03" target="_blank" class="flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-5 rounded-2xl shadow transition duration-200">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 16 16">
            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.608 1.976l-.623 2.406.846.065 1.706-.938c.834-.46 1.137-.624 1.288-.661.04-.017.071-.03.092-.04.072-.049.088-.047.172-.004.09.042.138.113.177.26.04.148.026.316-.06.495-.061.168-.117.29-.16.38-.11.234-.2.416-.232.462-.02.036-.026.044-.028.047-.002.003-.005.006-.008.01L7.182 11.89c-.194.165-.42.261-.603.261-.586 0-1.068-.377-1.391-.659-.62-.542-1.2-.956-1.282-1.003-.054-.03-.109-.048-.163-.048-.092 0-.125.016-.166.043l-.062.042-.164.117-.104.07a1 1 0 0 1-.354.129c-.326.076-.41.07-.517-.006l-.06-.05-.167-.145-1.47-1.336c-.44-.41-.75-.6-.916-.628-.06-.01-.105-.015-.147-.015-.093 0-.178.04-.252.115-.12.148-.18.276-.22.465-.036.162-.056.28-.064.307-.005.013-.008.016-.011.018-.002.002-.004.004-.007.006-.003.003-.006.005-.008.008a6.76 6.76 0 0 1-.162.09c-.026.012-.057.028-.087.04-.03.013-.058.022-.088.033-.3.093-.654.097-.7.091C.013 9.728 0 9.693 0 9.636c0-.024.029-.074.103-.178.026-.038.059-.074.097-.108l.056-.051 1.077-.965c1.078-.96 1.45-1.295 1.552-1.356.12-.072.247-.132.379-.18.237-.087.48-.17.714-.242.42-.137.833-.242 1.092-.261.685-.045 1.38-.104 2.052-.168.973-.096 1.254-.127 1.524-.127H16V8a8 8 0 0 0-7.713-7.906z"/>
          </svg>
          Telegram
        </a>
        <a href="https://payvia.asia" target="_blank" class="flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-5 rounded-2xl shadow transition duration-200">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 6a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2v-4a2 2 0 00-2-2H4z"/></svg>
          Pay via payvia.asia
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  const contactDeveloperBtn = document.getElementById('contactDeveloperBtn');
  const contactModal = document.getElementById('contactModal');
  const closeModalBtn = document.getElementById('closeModalBtn');

  function showModal() {
    contactModal.classList.remove('hidden');
    contactModal.classList.add('flex');
  }
  function hideModal() {
    contactModal.classList.add('hidden');
    contactModal.classList.remove('flex');
  }

  if (contactDeveloperBtn) contactDeveloperBtn.addEventListener('click', showModal);
  if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
  if (contactModal) contactModal.addEventListener('click', e => { if(e.target === contactModal) hideModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !contactModal.classList.contains('hidden')) hideModal(); });
</script>

<?php
include_template('footer');
?>

