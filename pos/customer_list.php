<?php
// pos/customer_list.php - Displays a list of all registered customers.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Fetch Customers ---
$customers = [];
$query = "SELECT id, username, phone, created_at FROM users WHERE user_type = 'Customer' ORDER BY created_at DESC";
$result = mysqli_query($connection, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
} else {
    flash_message('error', 'Could not retrieve customer list.');
}

include_template('header', ['page' => 'customer_list']);
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Customer List</h1>
        <a href="index.php?page=customer_register" class="btn px-5 py-2 bg-red-400 hover:bg-red-600 text-gray-700 font-medium rounded-lg shadow-sm transition">Register New Customer</a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="table-header">Username</th>
                    <th class="table-header">Phone Number</th>
                    <th class="table-header">Registration Date</th>
                    <th class="table-header">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-500">No customers have been registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td class="table-cell font-medium text-gray-900"><?= htmlspecialchars($customer['username']) ?></td>
                            <td class="table-cell"><?= htmlspecialchars($customer['phone']) ?></td>
                            <td class="table-cell"><?= date('F j, Y', strtotime($customer['created_at'])) ?></td>
                            <td class="table-cell">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                <!-- Add delete functionality later if needed -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_template('footer'); ?>
