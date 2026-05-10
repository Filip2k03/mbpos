<?php
// admin_dashboard.php - Admin-specific dashboard view.
require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access the admin dashboard.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Handle Maintenance Mode Toggle ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_maintenance'])) {
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'on') ? 'off' : 'on';

    $stmt = mysqli_prepare($connection, "UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
    mysqli_stmt_bind_param($stmt, 's', $new_status);

    if (mysqli_stmt_execute($stmt)) {
        flash_message('success', 'Maintenance mode has been turned ' . strtoupper($new_status) . '.');
    } else {
        flash_message('error', 'Failed to update maintenance mode.');
    }
    mysqli_stmt_close($stmt);
    redirect('index.php?page=admin_dashboard');
}


// --- Fetch Dashboard Data ---
$maintenance_mode = 'off';
$result = mysqli_query($connection, "SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
if ($result && mysqli_num_rows($result) > 0) {
    $maintenance_mode = mysqli_fetch_assoc($result)['setting_value'];
}


// Fetch other admin stats (example queries, adjust as needed)
$total_vouchers_result = mysqli_query($connection, "SELECT COUNT(id) AS total FROM vouchers");
$total_vouchers = mysqli_fetch_assoc($total_vouchers_result)['total'];

$total_users_result = mysqli_query($connection, "SELECT COUNT(id) AS total FROM users");
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$total_revenue_result = mysqli_query($connection, "SELECT SUM(total_amount) AS total FROM vouchers");
$total_revenue = mysqli_fetch_assoc($total_revenue_result)['total'] ?? 0;

include_template('header', ['page' => 'admin_dashboard']);
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Admin Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Total Vouchers</h2>
            <p class="text-3xl font-bold text-blue-600"><?php echo number_format($total_vouchers); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Total Users</h2>
            <p class="text-3xl font-bold text-green-600"><?php echo number_format($total_users); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Total Revenue</h2>
            <p class="text-3xl font-bold text-purple-600">$<?php echo number_format($total_revenue, 2); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Site Settings</h2>

        <div class="flex items-center justify-between p-4 border rounded-lg <?php echo $maintenance_mode === 'on' ? 'bg-yellow-100 border-yellow-400' : 'bg-gray-50'; ?>">
            <div>
                <h3 class="font-semibold text-lg">Maintenance Mode</h3>
                <p class="text-gray-600">When enabled, only Admins and Developers can access the site.</p>
            </div>
            <div class="text-right">
                <p class="mb-2">Current Status:
                    <span class="font-bold <?php echo $maintenance_mode === 'on' ? 'text-red-600' : 'text-green-600'; ?>">
                        <?php echo strtoupper($maintenance_mode); ?>
                    </span>
                </p>
                <form action="index.php?page=admin_dashboard" method="POST">
                    <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($maintenance_mode); ?>">
                    <button type="submit" name="toggle_maintenance" class="<?php echo $maintenance_mode === 'on' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'; ?> text-white font-bold py-2 px-4 rounded transition duration-300">
                        Turn <?php echo $maintenance_mode === 'on' ? 'OFF' : 'ON'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>