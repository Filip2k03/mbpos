<?php
// pos/ajax_search_customers.php - API for searching customers for Select2.

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security: Only logged-in users can search for customers.
if (!is_logged_in()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

global $connection;
$search = $_GET['q'] ?? '';
$results = ['results' => []];
$search = trim(mb_substr((string)$search, 0, 80));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=15, must-revalidate');

if ($search === '') {
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit();
}

$cache_key = mbpos_cache_key('customer-search', strtolower($search));
$cached_results = mbpos_cache_get($cache_key);
if (is_array($cached_results)) {
    echo json_encode($cached_results, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($search !== '') {
    $term = "%" . $search . "%";
    $query = "SELECT id, username as text, phone FROM users WHERE user_type = 'Customer' AND (username LIKE ? OR phone LIKE ?)";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'ss', $term, $term);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Format for Select2: id and text are required keys.
        $results['results'][] = [
            'id' => $row['id'],
            'text' => $row['text'] . ' (' . $row['phone'] . ')', // E.g., "John Doe (123-456-7890)"
            'phone' => $row['phone'] // Send back phone number to auto-fill
        ];
    }
    mysqli_stmt_close($stmt);
}

mbpos_cache_set($cache_key, $results, 30);
echo json_encode($results, JSON_UNESCAPED_UNICODE);
?>
