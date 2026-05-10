   <?php
// templates/footer.php
// Common header for all pages, including Tailwind CSS CDN

// The $page variable is passed to include_template function
// No redirect logic here. Redirects MUST happen before any HTML output.
// Ensure assets.php is included to load CSS/JS
require_once __DIR__ . '/../assets.php';

$page = $page ?? 'home'; // fallback page name
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo ucfirst(str_replace('_', ' ', $page)); ?></title>

</head>
<body class="min-h-screen flex flex-col">
    <footer class="bg-gray-900 text-gray-300 text-center py-6 mt-auto shadow-inner">
        <p class="text-lg font-semibold">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p class="text-sm mt-2">
            Powered by 
            <a href="https://payvia.asia" target="_blank" class="text-yellow-400 hover:text-yellow-300 transition-colors font-medium">
                Payvia
            </a>
        </p>
    </footer>
</body>
</html>