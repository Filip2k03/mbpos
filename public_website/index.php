<?php
// public_website/index.php - The main public-facing homepage.

require_once 'config.php'; // Use the CMS config for DB connection

global $connection;

// Fetch all active routes from the database
$routes = [];
$result = mysqli_query($connection, "SELECT * FROM shipping_routes WHERE is_active = 1 ORDER BY id");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Decode the JSON rates into a PHP array
        $row['rates'] = json_decode($row['rates'], true);
        $routes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mblogistics - Your Global Shipping Partner</title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios-filled/50/000000/shipping-container.png">
    <meta name="description" content="mblogistics: Reliable global shipping services - Air, Ocean, Customs, Warehousing & Delivery. Connecting Myanmar to the world.">
    <meta property="og:title" content="mblogistics - Your Global Shipping Partner">
    <meta property="og:description" content="Fast & reliable air and ocean freight, customs clearance, warehousing, and last-mile delivery.">
    <meta property="og:image" content="https://mblogistics.express/bg.jpg">
    <meta property="og:type" content="website">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="google-site-verification" content="l8xCC6XYRhwxi8RKy1dknNZZNuB36vHcQqpBf0LSvIA">
    <style>
        :root {
            --color-primary: #FF7F00; /* A slightly softer, more modern orange */
            --color-secondary: #FF4500;
            --color-text-light: #1f2937; /* Dark gray for better readability */
            --color-text-dark: #e5e7eb;  /* Light gray for dark mode */
            --color-background-light: #ffffff;
            --color-background-dark: #111827; /* A deep, rich dark blue */
            --color-card-light: #f9fafb; /* Off-white for cards */
            --color-card-dark: #1f2937;  /* Matching text color for dark mode cards */
            --color-border-light: #e5e7eb;
            --color-border-dark: #374151;
        }
        /* Base styles */
        html {
            scroll-behavior: smooth;
        }
        body {
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        /* Light Theme */
        .light { background-color: var(--color-background-light); color: var(--color-text-light); }
        .light .bg-primary { background-color: var(--color-primary); }
        .light .text-primary { color: var(--color-primary); }
        .light .bg-secondary { background-color: var(--color-secondary); }
        .light .text-secondary { color: var(--color-secondary); }
        .light .bg-card { background-color: var(--color-card-light); }
        .light .border-custom { border-color: var(--color-border-light); }

        /* Dark Theme */
        .dark { background-color: var(--color-background-dark); color: var(--color-text-dark); }
        .dark .bg-primary { background-color: var(--color-primary); }
        .dark .text-primary { color: var(--color-primary); }
        .dark .bg-secondary { background-color: var(--color-secondary); }
        .dark .text-secondary { color: var(--color-secondary); }
        .dark .bg-card { background-color: var(--color-card-dark); }
        .dark .border-custom { border-color: var(--color-border-dark); }

        /* Component Styles */
        .section-heading {
            position: relative;
            padding-bottom: 1rem;
            margin-bottom: 3rem;
        }
        .section-heading::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background-color: var(--color-primary);
            border-radius: 2px;
        }
        .service-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        .dark .service-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        /* Marquee Animation */
        .marquee-container {
            overflow: hidden; white-space: nowrap; box-sizing: border-box; background-color: var(--color-primary); color: white; padding: 12px 0;
        }
        .marquee-content { display: inline-block; padding-left: 100%; animation: marquee 30s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
        
        /* Airplane Animation */
        .airplane {
            position: absolute; font-size: 2.5rem; opacity: 0; animation: fly-across 18s linear infinite;
            filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.5));
        }
        @keyframes fly-across {
            0% { transform: translateX(-10vw) scale(0.8); opacity: 0; }
            10% { opacity: 0.6; }
            90% { opacity: 0.6; }
            100% { transform: translateX(110vw) scale(1.2); opacity: 0; }
        }
    </style>
</head>
<body class="antialiased">

    <nav class="bg-card/80 backdrop-blur-lg shadow-sm py-3 sticky top-0 z-50 transition-colors duration-300 border-b border-custom" role="navigation" aria-label="Main navigation">
        <div class="container mx-auto flex justify-between items-center px-6">
            <a href="#" class="text-3xl font-bold flex items-center" aria-label="mblogistics Home">
                <img src="bg.jpg" alt="mblogistics Logo" class="h-10 w-10 mr-2 rounded-full" />
                <span class="text-primary">mblo</span><span class="text-secondary">gistics</span>
            </a>

            <ul class="hidden md:flex space-x-8 text-base font-medium items-center" role="menubar">
                <li role="none"><a href="#hero" class="hover:text-primary transition-colors" role="menuitem">Home</a></li>
                <li role="none"><a href="#services" class="hover:text-primary transition-colors" role="menuitem">Services</a></li>
                <li role="none"><a href="#rates-routes" class="hover:text-primary transition-colors" role="menuitem">Rates & Routes</a></li>
                <li role="none"><a href="#contact" class="hover:text-primary transition-colors" role="menuitem">Contact</a></li>
                <li role="none">
                    <a href="http://customer.mblogistics.express" target="_blank" rel="noopener noreferrer" class="bg-primary text-white px-5 py-2.5 rounded-full hover:bg-secondary font-semibold transition-transform duration-300 hover:scale-105" role="menuitem">
                        Customer Portal
                    </a>
                </li>
            </ul>

            <div class="flex items-center space-x-2">
                <button id="theme-toggle" aria-label="Toggle theme" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <svg id="sun-icon" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <svg id="moon-icon" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </button>
                <button id="mobile-menu-button" aria-label="Toggle mobile menu" class="md:hidden p-2 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden bg-card border-t border-custom" role="menu" aria-label="Mobile menu">
            <ul class="flex flex-col items-center py-4 space-y-4 text-base">
                <li role="none"><a href="#hero" role="menuitem">Home</a></li>
                <li role="none"><a href="#services" role="menuitem">Services</a></li>
                <li role="none"><a href="#rates-routes" role="menuitem">Rates & Routes</a></li>
                <li role="none"><a href="#contact" role="menuitem">Contact Us</a></li>
                <li role="none">
                    <a href="http://customer.mblogistics.express" target="_blank" rel="noopener noreferrer" class="bg-primary text-white px-5 py-2.5 rounded-full hover:bg-secondary font-semibold" role="menuitem">
                        Customer Portal
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <main>
        
        <section id="hero" class="relative h-screen flex items-center justify-center text-white overflow-hidden">
            <div class="absolute inset-0 bg-black/60 z-10"></div>
            <video autoplay loop muted playsinline class="absolute z-0 w-auto min-w-full min-h-full max-w-none">
                <source src="vd.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
            <div class="airplane" style="top: 15%; animation-delay: 0s;">✈️</div>
            <div class="airplane" style="top: 45%; animation-delay: 6s;">✈️</div>
            <div class="airplane" style="top: 75%; animation-delay: 12s;">✈️</div>

            <div class="relative z-20 text-center p-6 max-w-4xl mx-auto">
                <h1 class="text-5xl md:text-7xl font-extrabold mb-4 leading-tight" style="text-shadow: 2px 2px 8px rgba(0,0,0,0.7);">
                    <span class="text-primary">Global</span> Logistics, <span class="text-secondary">Local</span> Expertise
                </h1>
                <p class="text-xl md:text-2xl mb-10 text-gray-200 max-w-2xl mx-auto">
                    Connecting Myanmar to the World: Seamless, reliable, and efficient shipping solutions tailored for your business.
                </p>
                <a href="#contact" class="bg-primary hover:bg-secondary text-white font-bold py-4 px-10 rounded-full text-lg transition-all duration-300 shadow-lg transform hover:scale-110 focus:outline-none focus:ring-4 focus:ring-primary/50">
                    Get a Free Quote Today
                </a>
            </div>
        </section>
        
         <!-- New Download App Section -->
        <section id="download-app" class="py-20 md:py-2 bg-primary text-white">
            <div class="container mx-auto px-6 text-center">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">Get Our App!</h2>
                <p class="text-xl md:text-2xl mb-10 max-w-2xl mx-auto text-orange-100">
                    Manage your shipments on the go with our dedicated mobile application for Android.
                </p>
                <a href="download.php" class="bg-white text-primary font-bold py-4 px-10 rounded-full text-lg transition-all duration-300 shadow-lg transform hover:scale-110 focus:outline-none focus:ring-4 focus:ring-white/50 inline-block">
                    Download for Android
                </a>
            </div>
        </section>

        <section class="marquee-container shadow-md">
            <div class="marquee-content text-lg font-medium">
                <span class="mx-8">✈️ Fast & Reliable Air Freight</span>
                <span class="mx-8">🚢 Cost-Effective Ocean Freight</span>
                <span class="mx-8">✅ Expert Customs Clearance</span>
                <span class="mx-8">🌏 Connecting Myanmar to Malaysia, Australia, Canada & more!</span>
                <span class="mx-8">📦 Secure Warehousing & Distribution</span>
            </div>
             <div class="marquee-content text-lg font-medium" aria-hidden="true">
                <span class="mx-8">✈️ Fast & Reliable Air Freight</span>
                <span class="mx-8">🚢 Cost-Effective Ocean Freight</span>
                <span class="mx-8">✅ Expert Customs Clearance</span>
                <span class="mx-8">🌏 Connecting Myanmar to Malaysia, Australia, Canada & more!</span>
                <span class="mx-8">📦 Secure Warehousing & Distribution</span>
            </div>
        </section>

        <section id="services" class="py-20 md:py-28">
            <div class="container mx-auto px-6">
                <h2 class="text-4xl md:text-5xl font-bold text-center mb-16 section-heading">Our Core Services</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php
                    $services = [
                        ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>', 'title' => 'Air Freight', 'desc' => 'Fast and reliable air cargo services for your time-sensitive shipments across the globe.'],
                        ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>', 'title' => 'Ocean Freight', 'desc' => 'Cost-effective and secure sea freight solutions perfect for large volume shipments.'],
                        ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12 12 0 002.944 12a12 12 0 003.04 8.618A11.955 11.955 0 0112 21.056a11.955 11.955 0 018.618-3.04A12 12 0 0021.056 12a12 12 0 00-3.04-8.618z"/>', 'title' => 'Customs Clearance', 'desc' => 'Expert handling of all customs documentation and complex procedures for smooth transit.'],
                        ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>', 'title' => 'Warehousing', 'desc' => 'Secure, modern storage and efficient distribution services to optimize your supply chain.'],
                        ['icon' => '<path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8a1 1 0 001-1z" /><path stroke-linecap="round" stroke-linejoin="round" d="M18 17h-5v-4h5l2 4z" />', 'title' => 'Land Transport', 'desc' => 'Efficient road and rail freight for seamless domestic and cross-border movements.'],
                        ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />', 'title' => 'Last-Mile Delivery', 'desc' => 'Ensuring your goods reach their final destination quickly, safely, and with full visibility.']
                    ];
                    foreach ($services as $service) : ?>
                    <div class="bg-card p-8 rounded-xl shadow-lg border border-custom service-card text-center">
                        <div class="text-primary mb-5 inline-block bg-primary/10 p-4 rounded-full">
                            <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><?= $service['icon'] ?></svg>
                        </div>
                        <h3 class="text-2xl font-bold mb-3"><?= $service['title'] ?></h3>
                        <p class="text-base text-gray-600 dark:text-gray-300"><?= $service['desc'] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="rates-routes" class="py-20 md:py-28 bg-card border-y border-custom">
            <div class="container mx-auto px-6">
                <h2 class="text-4xl md:text-5xl font-bold text-center mb-16 section-heading">Rates & Popular Routes</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <?php if (empty($routes)): ?>
                        <p class="text-center lg:col-span-2 text-gray-500 text-lg">Shipping rates are currently unavailable. Please contact us for a custom quote.</p>
                    <?php else: ?>
                        <?php foreach ($routes as $route): ?>
                        <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-xl border border-custom flex flex-col">
                            <h3 class="text-3xl font-bold text-primary mb-2 text-center"><?= htmlspecialchars($route['origin_country']) ?> ✈️ <?= htmlspecialchars($route['destination_country']) ?></h3>
                            <p class="text-base mb-6 text-center text-gray-500 dark:text-gray-400">Schedule: <span class="font-semibold"><?= htmlspecialchars($route['schedule']) ?></span></p>
                            <div class="overflow-x-auto flex-grow">
                                <table class="w-full text-left">
                                    <thead class="bg-gray-100 dark:bg-gray-700">
                                        <tr>
                                            <th class="p-4 font-semibold text-sm uppercase">Item Type</th>
                                            <th class="p-4 font-semibold text-sm uppercase text-right">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                        <?php foreach ($route['rates'] as $rate): ?>
                                        <tr>
                                            <td class="p-4 text-gray-700 dark:text-gray-300"><?= htmlspecialchars($rate['item']) ?></td>
                                            <td class="p-4 font-semibold text-right text-primary"><?= htmlspecialchars($rate['price']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-8 pt-6 border-t border-custom text-center bg-primary/10 text-primary p-4 rounded-lg">
                                <p class="font-semibold">Estimated Delivery Time:</p>
                                <p class="text-lg font-bold"><?= htmlspecialchars($route['working_days']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
       

        <section id="contact" class="py-20 md:py-28">
            <div class="container mx-auto px-6">
                <h2 class="text-4xl md:text-5xl font-bold text-center mb-16 section-heading">Get in Touch</h2>
                <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
                    <div class="bg-card p-8 rounded-xl shadow-lg border border-custom">
                         <h3 class="text-3xl font-bold mb-1">Send us a Message</h3>
                         <p class="text-gray-600 dark:text-gray-400 mb-6">We'll get back to you within one business day.</p>
                        <form class="space-y-6">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium mb-1">Your Name</label>
                                    <input type="text" id="name" class="w-full p-3 border border-custom rounded-lg focus:ring-2 focus:ring-primary focus:border-primary bg-white dark:bg-gray-900" placeholder="Kyaw Kyaw" required>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium mb-1">Your Email</label>
                                    <input type="email" id="email" class="w-full p-3 border border-custom rounded-lg focus:ring-2 focus:ring-primary focus:border-primary bg-white dark:bg-gray-900" placeholder="you@example.com" required>
                                </div>
                            </div>
                            <div>
                                <label for="subject" class="block text-sm font-medium mb-1">Subject</label>
                                <input type="text" id="subject" class="w-full p-3 border border-custom rounded-lg focus:ring-2 focus:ring-primary focus:border-primary bg-white dark:bg-gray-900" placeholder="Shipping Inquiry">
                            </div>
                            <div>
                                <label for="message" class="block text-sm font-medium mb-1">Message</label>
                                <textarea id="message" rows="5" class="w-full p-3 border border-custom rounded-lg focus:ring-2 focus:ring-primary focus:border-primary bg-white dark:bg-gray-900" placeholder="Tell us about your shipping needs..." required></textarea>
                            </div>
                            <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-bold py-3.5 px-6 rounded-lg text-lg transition-transform duration-300 hover:scale-105 shadow-md">
                                Send Message
                            </button>
                        </form>
                    </div>

                    <div class="space-y-8">
                        <div class="bg-card p-8 rounded-xl shadow-lg border border-custom">
                            <h3 class="text-2xl font-bold text-primary mb-4">Yangon Office 🇲🇲</h3>
                            <p class="mb-2"><strong>Address:</strong> 53, 125St, Ground Floor, Mingalar Taung Nyunt, Yangon</p>
                            <p><strong>Phone:</strong> <a href="tel:+959263525212" class="hover:underline">09 263 5252 12</a></p>
                            <div class="mt-4 rounded-lg overflow-hidden border border-custom">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3819.57077759508!2d96.1718043153676!3d16.7979679237618!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30c1ed144c555555%3A0x892e5c6e85854611!2s125th%20St%2C%20Yangon%2C%20Myanmar%20(Burma)!5e0!3m2!1sen!2sus!4v1662058882571!5m2!1sen!2sus" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        </div>
                        <div class="bg-card p-8 rounded-xl shadow-lg border border-custom">
                            <h3 class="text-2xl font-bold text-primary mb-4">Malaysia Office 🇲🇾</h3>
                            <p class="mb-2"><strong>Address:</strong> 6-A, Jalan 4 Selayang Baru, 68100 Batu Caves Selangor, Malaysia</p>
                            <p><strong>Phone:</strong> <a href="tel:+60172800272" class="hover:underline">017 2800 272</a></p>
                            <div class="mt-4 rounded-lg overflow-hidden border border-custom">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3983.473180845348!2d101.68066531527712!3d3.232336953288924!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc461622340a6f%3A0x3676450f3b06bc9a!2sSelayang%20Baru%2C%2068100%20Batu%20Caves%2C%20Selangor%2C%20Malaysia!5e0!3m2!1sen!2sus!4v1662059012345!5m2!1sen!2sus" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-gray-900 dark:bg-black text-white">
        <div class="container mx-auto px-6 py-12">
             <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center md:text-left">
                <div>
                    <a href="#" class="text-3xl font-bold flex items-center justify-center md:justify-start mb-4">
                         <img src="bg.jpg" alt="mblogistics Logo" class="h-10 w-10 mr-2 rounded-full" />
                        <span class="text-primary">mblo</span><span class="text-secondary">gistics</span>
                    </a>
                    <p class="text-gray-400 max-w-md">Your trusted partner for seamless logistics solutions, connecting businesses globally.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4 uppercase tracking-wider">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#services" class="text-gray-300 hover:text-primary transition">Services</a></li>
                        <li><a href="#rates-routes" class="text-gray-300 hover:text-primary transition">Rates</a></li>
                        <li><a href="#contact" class="text-gray-300 hover:text-primary transition">Contact</a></li>
                        <li><a href="download.php" class="text-gray-300 hover:text-primary transition">Download App</a></li>
                    </ul>
                </div>
                 <div>
                    <h3 class="text-lg font-semibold mb-4 uppercase tracking-wider">Legal</h3>
                    <ul class="space-y-2">
                        <li><a href="javascript:void(0)" id="privacy-link" class="text-gray-300 hover:text-primary transition">Privacy Policy</a></li>
                        <li><a href="javascript:void(0)" id="terms-link" class="text-gray-300 hover:text-primary transition">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-12 border-t border-gray-800 pt-8 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500">
                <p>&copy; <?= date('Y') ?> mblogistics. All rights reserved.</p>
                <div class="flex items-center space-x-4 mt-4 sm:mt-0">
                    <span>Powered by <a href="https://payvia.asia" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-primary transition">PayVia</a></span>
                    <span class="text-gray-700">|</span>
                    <span>Developed by <a href="https://techyyfilip.vercel.app" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-primary transition">Filip</a></span>
                </div>
            </div>
        </div>
    </footer>
         <!-- Legal Modal -->
    <div id="legalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-card rounded-lg shadow-xl w-full max-w-2xl relative max-h-[80vh] flex flex-col">
            <div class="flex justify-between items-center p-6 border-b border-custom">
                <h2 id="legalModalTitle" class="text-2xl font-bold"></h2>
                <button id="closeModalBtn" class="text-2xl font-bold hover:text-primary">&times;</button>
            </div>
            <div id="legalModalContent" class="p-6 overflow-y-auto">
                <!-- Content will be injected here -->
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Legal Modal Logic ---
            const legalModal = document.getElementById('legalModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const privacyLink = document.getElementById('privacy-link');
            const termsLink = document.getElementById('terms-link');
            const modalTitle = document.getElementById('legalModalTitle');
            const modalContent = document.getElementById('legalModalContent');

            const privacyPolicyText = `
                <h3 class="font-bold text-lg mb-2">Information We Collect</h3>
                <p class="mb-4">We collect information you provide directly to us when you create an account, create or modify your profile, request on-demand services, contact customer support, or otherwise communicate with us. This information may include: name, email, phone number, postal address, payment method, items requested (for delivery services), delivery notes, and other information you choose to provide.</p>
                <h3 class="font-bold text-lg mb-2">Use of Information</h3>
                <p class="mb-4">We may use the information we collect about you to: provide, maintain, and improve our services, including, for example, to facilitate payments, send receipts, provide products and services you request (and send related information), develop new features, provide customer support to Users, develop safety features, authenticate users, and send product updates and administrative messages.</p>
            `;
            const termsOfServiceText = `
                <h3 class="font-bold text-lg mb-2">1. Prohibited Items</h3>
                <p class="mb-4">Users are strictly prohibited from shipping illegal items. It is the user's responsibility to ensure that their shipment does not contain any items that are prohibited by law in either the origin or destination country. We are not responsible for any undeclared or hidden items.</p>
                <h3 class="font-bold text-lg mb-2">2. Inspection</h3>
                <p class="mb-4">All shipments are subject to inspection for security purposes. By using our service, you consent to the inspection of your cargo by our staff and relevant authorities.</p>
                <h3 class="font-bold text-lg mb-2">3. Liability</h3>
                <p>We are not liable for damage to perishable or fragile goods. Users are advised to ensure proper packaging for such items. Our liability for any loss or damage to a shipment is limited. Please refer to our detailed liability terms for more information.</p>
            `;

            function openModal(title, content) {
                modalTitle.textContent = title;
                modalContent.innerHTML = content;
                legalModal.classList.remove('hidden');
            }

            function closeModal() {
                legalModal.classList.add('hidden');
            }

            privacyLink.addEventListener('click', (e) => {
                e.preventDefault();
                openModal('Privacy Policy', privacyPolicyText);
            });

            termsLink.addEventListener('click', (e) => {
                e.preventDefault();
                openModal('Terms of Service', termsOfServiceText);
            });

            closeModalBtn.addEventListener('click', closeModal);
            legalModal.addEventListener('click', (e) => {
                if (e.target === legalModal) {
                    closeModal();
                }
            });
            
            const themeToggle = document.getElementById('theme-toggle');
            const sunIcon = document.getElementById('sun-icon');
            const moonIcon = document.getElementById('moon-icon');
            const htmlElement = document.documentElement;

            // Function to apply theme
            const applyTheme = (theme) => {
                if (theme === 'dark') {
                    htmlElement.classList.add('dark');
                    htmlElement.classList.remove('light');
                    sunIcon.style.display = 'block';
                    moonIcon.style.display = 'none';
                } else {
                    htmlElement.classList.add('light');
                    htmlElement.classList.remove('dark');
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = 'block';
                }
            };

            // Set initial theme
            const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            applyTheme(savedTheme);

            // Theme toggle listener
            themeToggle.addEventListener('click', () => {
                const newTheme = htmlElement.classList.contains('dark') ? 'light' : 'dark';
                localStorage.setItem('theme', newTheme);
                applyTheme(newTheme);
            });

            // Mobile Menu Toggle
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuLinks = mobileMenu.querySelectorAll('a');

            mobileMenuButton.addEventListener('click', () => {
                const isHidden = mobileMenu.classList.toggle('hidden');
                mobileMenuButton.setAttribute('aria-expanded', !isHidden);
            });
            
            // Close mobile menu when a link is clicked
            mobileMenuLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                });
            });
        });
    </script>
</body>
</html>