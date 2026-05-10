<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download MBLogistics App</title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios-filled/50/000000/shipping-container.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #FF7F00;
            --color-secondary: #FF4500;
            --color-text-light: #1f2937;
            --color-background-light: #ffffff;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
        .light { background-color: var(--color-background-light); color: var(--color-text-light); }
        .bg-primary { background-color: var(--color-primary); }
        .text-primary { color: var(--color-primary); }
        .hover\:bg-secondary:hover { background-color: var(--color-secondary); }
    </style>
</head>
<body class="antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 text-center p-6">
        <img src="bg.jpg" alt="mblogistics Logo" class="h-24 w-24 mb-4 rounded-full shadow-lg" />
        <h1 class="text-4xl md:text-5xl font-extrabold text-primary mb-4">MBLogistics Mobile App</h1>
        <p class="text-lg text-gray-600 max-w-md mx-auto mb-8">
            Click the button below to download the official Android application for managing and tracking your shipments on the go.
        </p>
        <a href="/app/Mblogistics.apk" download="mblogistics.apk" class="bg-primary hover:bg-secondary text-white font-bold py-4 px-10 rounded-full text-lg transition-all duration-300 shadow-lg transform hover:scale-110 focus:outline-none focus:ring-4 focus:ring-primary/50 inline-flex items-center">
            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Download APK
        </a>
         <p class="text-xs text-gray-400 mt-8 max-w-xs mx-auto">
            You may need to enable "Install from unknown sources" in your Android device's security settings.
        </p>
         <div class="mt-12 text-sm text-gray-500">
            <a href="/" class="hover:text-primary transition-colors">&larr; Back to main website</a>
        </div>
    </div>
</body>
</html>
