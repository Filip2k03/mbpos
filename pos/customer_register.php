<?php
// pos/customer_register.php - Admin/Developer page to register new customers.

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

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Server-side Validation ---
    if (empty($username) || empty($phone) || empty($password)) {
        flash_message('error', 'Username, phone, and password are required.');
    } elseif ($password !== $confirm_password) {
        flash_message('error', 'Passwords do not match.');
    } else {
        // Check for existing username or phone
        $stmt_check = mysqli_prepare($connection, "SELECT id FROM users WHERE username = ? OR phone = ?");
        mysqli_stmt_bind_param($stmt_check, 'ss', $username, $phone);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            flash_message('error', 'A user with that username or phone number already exists.');
        } else {
            // Create the new customer user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_type = 'Customer'; // Set the user type specifically to Customer
            
            $stmt_insert = mysqli_prepare($connection, "INSERT INTO users (username, password, user_type, phone) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_insert, 'ssss', $username, $hashed_password, $user_type, $phone);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                flash_message('success', 'Customer account registered successfully!');
                redirect('index.php?page=customer_list');
            } else {
                flash_message('error', 'An error occurred during registration. Please try again.');
            }
        }
        mysqli_stmt_close($stmt_check);
    }
    // Redirect back to the form on validation failure
    redirect('index.php?page=customer_register');
}

include_template('header', ['page' => 'customer_register']);
?>

<div class="flex items-center justify-center min-h-screen -mt-20">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-lg border border-gray-100">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Register New Customer</h2>
        
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2" required>
        </div>
        <div>
            <label for="phone" class="form-label">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2" required>
            <p class="text-xs text-gray-500 mt-1">This links the customer to their vouchers.</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2" required>
        </div>
        <div>
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2" required>
        </div>
    </div>

    <div class="pt-4">
        <button type="submit" class="btn w-full bg-blue-500 text-white rounded-lg py-2 hover:bg-blue-600 transition duration-200">Register Customer</button>
    </div>
</form>

    </div>
</div>

<?php include_template('footer'); ?>
