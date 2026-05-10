<?php
// customer/register.php - Registration page for new customers.

// require_once 'config.php';
// require_once 'includes/functions.php';

// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $username = trim($_POST['username']);
//     $phone = trim($_POST['phone']);
//     $password = $_POST['password'];
//     $confirm_password = $_POST['confirm_password'];

//     // Validation
//     if (empty($username) || empty($phone) || empty($password)) {
//         flash_customer_message('error', 'All fields are required.');
//     } elseif ($password !== $confirm_password) {
//         flash_customer_message('error', 'Passwords do not match.');
//     } else {
//         // Check for existing user
//         $stmt_check = mysqli_prepare($connection, "SELECT id FROM users WHERE username = ? OR phone = ?");
//         mysqli_stmt_bind_param($stmt_check, 'ss', $username, $phone);
//         mysqli_stmt_execute($stmt_check);
//         mysqli_stmt_store_result($stmt_check);

//         if (mysqli_stmt_num_rows($stmt_check) > 0) {
//             flash_customer_message('error', 'Username or phone number is already taken.');
//         } else {
//             // Create user
//             $hashed_password = password_hash($password, PASSWORD_DEFAULT);
//             $user_type = 'Customer';
//             $stmt_insert = mysqli_prepare($connection, "INSERT INTO users (username, password, user_type, phone) VALUES (?, ?, ?, ?)");
//             mysqli_stmt_bind_param($stmt_insert, 'ssss', $username, $hashed_password, $user_type, $phone);
            
//             if (mysqli_stmt_execute($stmt_insert)) {
//                 flash_customer_message('success', 'Registration successful! You can now log in.');
//                 redirect('index.php?page=login');
//             } else {
//                 flash_customer_message('error', 'An error occurred. Please try again.');
//             }
//         }
//     }
//     redirect('index.php?page=register');
// }


include_template('header', ['title' => 'Customer Registration']);
?>
<!--<div class="flex items-center justify-center min-h-screen">-->
<!--    <div class="w-full max-w-md">-->
<!--        <form action="index.php?page=register" method="POST" class="bg-white shadow-lg rounded-2xl px-8 pt-6 pb-8 mb-4">-->
<!--             <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Create Your Account</h2>-->
<!--            <?php display_customer_flash_messages(); ?>-->
<!--            <div class="mb-4">-->
<!--                <label class="form-label" for="username">Username</label>-->
<!--                <input class="form-input" id="username" name="username" type="text" required>-->
<!--            </div>-->
<!--             <div class="mb-4">-->
<!--                <label class="form-label" for="phone">Phone Number</label>-->
<!--                <input class="form-input" id="phone" name="phone" type="tel" required>-->
<!--                 <p class="text-xs text-gray-500 mt-1">Used to link you to your shipments.</p>-->
<!--            </div>-->
<!--            <div class="mb-4">-->
<!--                <label class="form-label" for="password">Password</label>-->
<!--                <input class="form-input" id="password" name="password" type="password" required>-->
<!--            </div>-->
<!--             <div class="mb-6">-->
<!--                <label class="form-label" for="confirm_password">Confirm Password</label>-->
<!--                <input class="form-input" id="confirm_password" name="confirm_password" type="password" required>-->
<!--            </div>-->
<!--            <div class="flex items-center justify-between">-->
<!--                <button class="btn w-full" type="submit">Register</button>-->
<!--            </div>-->
<!--             <p class="text-center text-gray-500 text-xs mt-6">-->
<!--                Already have an account? <a href="index.php?page=login" class="text-blue-500 hover:text-blue-700">Login here</a>.-->
<!--            </p>-->
<!--        </form>-->
<!--    </div>-->
<!--</div>-->
<?php
include_template('footer');
?>
