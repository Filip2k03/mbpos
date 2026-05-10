<?php
// public_website/cms/manage_routes.php - CRUD for shipping routes.
require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!is_cms_admin()) redirect('index.php?page=login');

global $connection;
$edit_route = null;

// --- Handle POST Requests (Add/Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_id = intval($_POST['route_id'] ?? 0);
    $origin = trim($_POST['origin_country']);
    $destination = trim($_POST['destination_country']);
    $schedule = trim($_POST['schedule']);
    $working_days = trim($_POST['working_days']);
    
    // Process rates from the form
    $rates = [];
    if (isset($_POST['rate_item']) && is_array($_POST['rate_item'])) {
        foreach ($_POST['rate_item'] as $key => $item) {
            if (!empty($item) && isset($_POST['rate_price'][$key])) {
                $rates[] = ['item' => trim($item), 'price' => trim($_POST['rate_price'][$key])];
            }
        }
    }
    $rates_json = json_encode($rates);

    if (empty($origin) || empty($destination) || empty($schedule) || empty($working_days) || empty($rates)) {
        flash_cms_message('error', 'All fields, including at least one rate, are required.');
    } else {
        if ($route_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE shipping_routes SET origin_country=?, destination_country=?, schedule=?, working_days=?, rates=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssssi', $origin, $destination, $schedule, $working_days, $rates_json, $route_id);
        } else { // Insert
            $stmt = mysqli_prepare($connection, "INSERT INTO shipping_routes (origin_country, destination_country, schedule, working_days, rates) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssss', $origin, $destination, $schedule, $working_days, $rates_json);
        }
        if (mysqli_stmt_execute($stmt)) {
            flash_cms_message('success', 'Route saved successfully.');
        } else {
            flash_cms_message('error', 'Failed to save route: ' . mysqli_stmt_error($stmt));
        }
    }
    redirect('index.php?page=manage_routes');
}

// --- Handle GET Requests (Delete/Edit) ---
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM shipping_routes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if(mysqli_stmt_execute($stmt)) flash_cms_message('success', 'Route deleted.');
        redirect('index.php?page=manage_routes');
    }
    if ($_GET['action'] === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM shipping_routes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $edit_route = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($edit_route) {
            $edit_route['rates'] = json_decode($edit_route['rates'], true);
        }
    }
}

// --- Fetch all routes for display ---
$routes = [];
$result = mysqli_query($connection, "SELECT * FROM shipping_routes ORDER BY id");
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $row['rates'] = json_decode($row['rates'], true);
        $routes[] = $row;
    }
}

include_template('header');
?>
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Manage Shipping Routes</h1>
    
    <!-- Add/Edit Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold mb-4"><?= $edit_route ? 'Edit Route' : 'Add New Route' ?></h2>
        <form action="index.php?page=manage_routes" method="POST">
            <input type="hidden" name="route_id" value="<?= $edit_route['id'] ?? '' ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <input name="origin_country" class="form-input" placeholder="Origin Country" value="<?= htmlspecialchars($edit_route['origin_country'] ?? '') ?>" required>
                <input name="destination_country" class="form-input" placeholder="Destination Country" value="<?= htmlspecialchars($edit_route['destination_country'] ?? '') ?>" required>
                <input name="schedule" class="form-input" placeholder="Schedule" value="<?= htmlspecialchars($edit_route['schedule'] ?? '') ?>" required>
                <input name="working_days" class="form-input" placeholder="Working Days" value="<?= htmlspecialchars($edit_route['working_days'] ?? '') ?>" required>
            </div>
            
            <h3 class="text-lg font-semibold mt-6 mb-2">Rates</h3>
            <div id="rates-container" class="space-y-2">
                <?php if (!empty($edit_route['rates'])): ?>
                    <?php foreach($edit_route['rates'] as $rate): ?>
                        <div class="flex items-center gap-2">
                            <input type="text" name="rate_item[]" class="form-input flex-grow" placeholder="Item Type" value="<?= htmlspecialchars($rate['item']) ?>">
                            <input type="text" name="rate_price[]" class="form-input w-32" placeholder="Price" value="<?= htmlspecialchars($rate['price']) ?>">
                            <button type="button" class="btn-danger remove-rate-btn">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="add-rate-btn" class="btn-secondary mt-2">Add Rate</button>

            <div class="mt-6">
                <button type="submit" class="btn"><?= $edit_route ? 'Update Route' : 'Save Route' ?></button>
            </div>
        </form>
    </div>

    <!-- Existing Routes Table -->
    <div class="bg-white p-6 rounded-lg shadow-md">
         <h2 class="text-2xl font-semibold mb-4">Existing Routes</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="table-header">Route</th>
                        <th class="table-header">Schedule</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($routes as $route): ?>
                        <tr>
                            <td class="table-cell"><?= htmlspecialchars($route['origin_country']) ?> &rarr; <?= htmlspecialchars($route['destination_country']) ?></td>
                            <td class="table-cell"><?= htmlspecialchars($route['schedule']) ?></td>
                            <td class="table-cell">
                                <a href="index.php?page=manage_routes&action=edit&id=<?= $route['id'] ?>" class="text-indigo-600">Edit</a>
                                <a href="index.php?page=manage_routes&action=delete&id=<?= $route['id'] ?>" class="text-red-600 ml-4" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    document.getElementById('add-rate-btn').addEventListener('click', function() {
        const container = document.getElementById('rates-container');
        const rateRow = document.createElement('div');
        rateRow.className = 'flex items-center gap-2';
        rateRow.innerHTML = `
            <input type="text" name="rate_item[]" class="form-input flex-grow" placeholder="Item Type">
            <input type="text" name="rate_price[]" class="form-input w-32" placeholder="Price">
            <button type="button" class="btn-danger remove-rate-btn">Remove</button>
        `;
        container.appendChild(rateRow);
    });
    document.getElementById('rates-container').addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-rate-btn')) {
            e.target.parentElement.remove();
        }
    });
</script>
<?php include_template('footer'); ?>

