<?php
// developer_dashboard.php - Dashboard for users with the 'Developer' role.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization Check: Only Developers can access this page ---
if (!is_logged_in() || !is_developer()) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
    exit();
}

global $connection,$database;

$query_result = null;
$query_error = '';
$affected_rows = null;

// --- Fetch some developer-relevant stats (examples) ---
$active_maintenance_modes = 0;
$result = mysqli_query($connection, "SELECT COUNT(*) as count FROM maintenance WHERE is_active = 1");
if ($result) {
    $active_maintenance_modes = mysqli_fetch_assoc($result)['count'];
}

$total_users = 0;
$result_users = mysqli_query($connection, "SELECT COUNT(*) as count FROM users");
if ($result_users) {
    $total_users = mysqli_fetch_assoc($result_users)['count'];
}

// --- Handle SQL Query Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_query'])) {
    $sql_query = trim($_POST['sql_query']);

    if (!empty($sql_query)) {
        // For security, only allow SELECT, SHOW, DESCRIBE, and EXPLAIN queries
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $sql_query)) {
            $result = mysqli_query($connection, $sql_query);

            if ($result) {
                if ($result instanceof mysqli_result) {
                    $query_result = mysqli_fetch_all($result, MYSQLI_ASSOC);
                }
                $affected_rows = mysqli_affected_rows($connection);
                flash_message('success', 'Query executed successfully.');
            } else {
                $query_error = mysqli_error($connection);
            }
        } else {
            $query_error = "For security reasons, only SELECT, SHOW, DESCRIBE, and EXPLAIN queries are allowed.";
        }
    } else {
        $query_error = "SQL query cannot be empty.";
    }
}

// --- Read recent error log entries ---
$log_content = 'Log file not found or is empty.';
$log_file = __DIR__ . '/error_log'; 
if (file_exists($log_file) && filesize($log_file) > 0) {
    // Read the last 200 lines for performance
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_content = implode("\n", array_slice($lines, -200));
}


include_template('header', ['page' => 'developer_dashboard']);
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Developer Dashboard</h1>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Active Maintenance Modes</h2>
            <p class="text-3xl font-bold text-red-600"><?php echo $active_maintenance_modes; ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Total Users</h2>
            <p class="text-3xl font-bold text-blue-600"><?php echo $total_users; ?></p>
        </div>
        <!-- Add more stats cards as needed -->
    </div>
    
   <!-- System Status Section -->
    <div class="mb-8">
        <h2 class="text-2xl font-semibold mb-4 text-gray-700">System Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" id="db-status-cards">
            <!-- Status cards will be loaded here by JavaScript -->
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- SQL Query Runner -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold mb-4">Database Management</h2>
            <form action="index.php?page=developer_dashboard" method="POST">
                <div class="mb-4">
                    <label for="sql_query" class="form-label">SQL Query (Read-only)</label>
                    <textarea id="sql_query" name="sql_query" rows="5" class="form-input font-mono" placeholder="e.g., SELECT * FROM users LIMIT 10;"></textarea>
                </div>
                <button type="submit" class="btn">Execute Query</button>
            </form>
        </div>

        <!-- System Log Viewer -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold">System Log Viewer</h2>
                <!--<a href="index.php?page=clear_log" class="btn-danger text-sm" onclick="return confirm('Are you sure you want to clear the error log? This action cannot be undone.')">Clear Log</a>-->
            </div>
            <div class="bg-gray-800 text-white font-mono text-sm p-4 rounded-lg overflow-auto h-64">
                <pre><code><?= htmlspecialchars($log_content) ?></code></pre>
            </div>
        </div>
    </div>
    
    <!-- Query Results -->
    <?php if ($query_result !== null || $query_error): ?>
    <div class="bg-white p-6 rounded-lg shadow-md mt-8">
        <h3 class="text-xl font-semibold mb-2">Query Results</h3>
        <?php if ($query_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <p><?= htmlspecialchars($query_error) ?></p>
            </div>
        <?php elseif (empty($query_result)): ?>
            <p class="text-gray-500">Query executed successfully. <?= $affected_rows ?? 0 ?> rows affected.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php foreach (array_keys($query_result[0]) as $header): ?>
                                <th class="table-header"><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($query_result as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td class="table-cell"><?= htmlspecialchars($cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">System Management</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="index.php?page=maintenance" class="bg-red-500 hover:bg-red-600 text-white font-bold py-4 px-6 rounded-lg text-center transition duration-300">
                Manage Maintenance
            </a>
            <a href="index.php?page=branches" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-4 px-6 rounded-lg text-center transition duration-300">
                Manage Branches
            </a>
            <a href="index.php?page=register" class="bg-green-500 hover:bg-green-600 text-white font-bold py-4 px-6 rounded-lg text-center transition duration-300">
                Register New User
            </a>
            <a href="index.php?page=error_log_viewer" class="bg-cyan-500 hover:bg-cyan-600 text-white font-bold py-4 px-6 rounded-lg text-center transition duration-300">
               View System Logs 
            </a>
            <!-- Placeholder for future developer tools -->
            
             <div class="bg-gray-200 p-4 rounded-lg text-center text-gray-500">
                Database Status (Coming Soon)
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch and display database status
    fetch('index.php?page=ajax_db_status')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('db-status-cards');
            container.innerHTML = `
                <div class="info-card"><h3 class="section-title">Database Size</h3><p class="text-2xl font-bold">${data.size}</p></div>
                <div class="info-card"><h3 class="section-title">Total Tables</h3><p class="text-2xl font-bold">${data.tables}</p></div>
                <div class="info-card"><h3 class="section-title">MySQL Version</h3><p class="text-2xl font-bold">${data.version}</p></div>
                <div class="info-card"><h3 class="section-title">Status</h3><p class="text-2xl font-bold text-green-500">Online</p></div>
            `;
        });
});
</script>

<?php include_template('footer'); ?>
