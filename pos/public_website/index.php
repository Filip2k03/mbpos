<?php
// public_website/index.php - The main public-facing homepage.

require_once 'cms/config.php'; // Use the CMS config for DB connection

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
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mblogistics - Your Global Shipping Partner</title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios-filled/50/000000/shipping-container.png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <meta name="google-site-verification" content="l8xCC6XYRhwxi8RKy1dknNZZNuB36vHcQqpBf0LSvIA">
    <style>
        :root {
            --color-primary: #FF8C00; 
            --color-secondary: #FF4500;
            --color-text-light: #333333;
            --color-text-dark: #f0f0f0;
            --color-background-light: #ffffff;
            --color-background-dark: #1a1a1a;
            --color-card-light: #f9f9f9;
            --color-card-dark: #2a2a2a;
            --color-border-light: #e0e0e0;
            --color-border-dark: #444444;
        }
        .light { background-color: var(--color-background-light); color: var(--color-text-light); }
        .light .bg-primary { background-color: var(--color-primary); }
        .light .text-primary { color: var(--color-primary); }
        .light .bg-secondary { background-color: var(--color-secondary); }
        .light .text-secondary { color: var(--color-secondary); }
        .light .bg-card { background-color: var(--color-card-light); }
        .light .border-custom { border-color: var(--color-border-light); }
        .dark { background-color: var(--color-background-dark); color: var(--color-text-dark); }
        .dark .bg-primary { background-color: var(--color-primary); }
        .dark .text-primary { color: var(--color-primary); }
        .dark .bg-secondary { background-color: var(--color-secondary); }
        .dark .text-secondary { color: var(--color-secondary); }
        .dark .bg-card { background-color: var(--color-card-dark); }
        .dark .border-custom { border-color: var(--color-border-dark); }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; transition: background-color 0.3s ease, color 0.3s ease; }
        .container { max-width: 1200px; }
        .section-heading { position: relative; padding-bottom: 10px; margin-bottom: 30px; }
        .section-heading::after { content: ''; position: absolute; left: 50%; bottom: 0; transform: translateX(-50%); width: 60px; height: 3px; background-color: var(--color-primary); border-radius: 5px; }
        .service-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .dark .service-card:hover { box-shadow: 0 10px 20px rgba(255, 255, 255, 0.05); }
        .price-table th, .price-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--color-border-light); }
        .dark .price-table th, .dark .price-table td { border-bottom: 1px solid var(--color-border-dark); }
        .price-table th { background-color: var(--color-card-light); }
        .dark .price-table th { background-color: var(--color-card-dark); }
        .marquee-container { overflow: hidden; white-space: nowrap; box-sizing: border-box; background-color: var(--color-primary); color: white; padding: 10px 0; }
        .marquee-content { display: inline-block; padding-left: 100%; animation: marquee 20s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
        .airplane { position: absolute; font-size: 3rem; opacity: 0.7; animation: fly-across 20s linear infinite; z-index: 15; top: 10%; left: -10%; filter: drop-shadow(0 0 5px rgba(0, 0, 0, 0.5)); }
        @keyframes fly-across { 0% { transform: translateX(0vw) translateY(0); opacity: 0; } 10% { opacity: 0.7; } 90% { opacity: 0.7; } 100% { transform: translateX(110vw) translateY(10vh); opacity: 0; } }
        @media (max-width: 768px) { .airplane { font-size: 2rem; animation: fly-across 15s linear infinite; } @keyframes fly-across { 0% { transform: translateX(0vw) translateY(0); opacity: 0; } 10% { opacity: 0.7; } 90% { opacity: 0.7; } 100% { transform: translateX(120vw) translateY(5vh); opacity: 0; } } }
    </style>
</head>
<body class="transition-colors duration-300">
    <nav class="bg-card shadow-md py-4 sticky top-0 z-50 transition-colors duration-300">
        <div class="container mx-auto flex justify-between items-center px-4">
            <a href="#" class="text-2xl font-bold flex items-center">
                <img src="bg.jpg" alt="mblogistics Logo" class="h-8 w-8 mr-2 rounded-full">
                <span class="text-primary">mblo</span><span class="text-secondary">gistics</span>
            </a>
            <ul class="hidden md:flex space-x-8 text-lg font-medium items-center">
                <li><a href="#hero" class="hover:text-primary">Home</a></li>
                <li><a href="#services" class="hover:text-primary">Services</a></li>
                <li><a href="#rates-routes" class="hover:text-primary">Rates & Routes</a></li>
                <li><a href="#contact" class="hover:text-primary">Contact Us</a></li>
                <li><a href="../customer/" target="_blank" class="bg-primary text-white px-4 py-2 rounded-full hover:bg-secondary transition-colors">Customer Portal</a></li>
            </ul>
            <div class="flex items-center">
                <button id="theme-toggle" class="p-2 rounded-full"><svg id="sun-icon" class="h-6 w-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" style="display: none;"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 00-1.414-1.414L13.5 4.5v.001l.707.707a1 1 0 101.414-1.414zM10 15a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4 0a1 1 0 01-1 1H4a1 1 0 110-2h1a1 1 0 011 1zm-3.536-1.95l-.707-.707a1 1 0 00-1.414 1.414l.707.707a1 1 0 001.414-1.414zM4.5 4.5l-.707.707a1 1 0 101.414 1.414L5.5 5.5v-.001a1 1 0 00-1.414-1.414z"/></svg><svg id="moon-icon" class="h-6 w-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20" style="display: block;"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg></button>
                <button id="mobile-menu-button" class="md:hidden ml-4 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></button>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden">
            <ul class="flex flex-col items-center py-4 space-y-4 text-lg">
                <li><a href="#hero">Home</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#rates-routes">Rates & Routes</a></li>
                <li><a href="#contact">Contact Us</a></li>
                <li><a href="../customer/" target="_blank" class="bg-primary text-white px-4 py-2 rounded-full hover:bg-secondary">Customer Portal</a></li>
            </ul>
        </div>
    </nav>

       <!-- Hero Section -->
    <section id="hero" class="relative bg-black h-screen flex items-center justify-center text-white overflow-hidden">
        <video autoplay loop muted class="absolute z-10 w-auto min-w-full min-h-full max-w-none opacity-50">
            <source src="vd.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <!-- Flying Airplane Element -->
        <div class="airplane" style="top: 20%; animation-delay: 0s;">✈️</div>
        <div class="airplane" style="top: 50%; animation-delay: 5s;">✈️</div>
        <div class="airplane" style="top: 80%; animation-delay: 10s;">✈️</div>

        <div class="relative z-20 text-center p-6 bg-black bg-opacity-70 rounded-lg max-w-3xl mx-auto">
            <h1 class="text-5xl md:text-6xl font-extrabold mb-4 leading-tight">
                <span class="text-primary">Global</span> <span class="text-secondary">Logistics</span>, Local <span class="text-white">Expertise</span>
            </h1>
            <p class="text-xl md:text-2xl mb-8 text-gray-200">
                Connecting Myanmar to the World: Seamless Shipping Solutions for Your Business.
            </p>
            <a href="#contact" class="bg-primary hover:bg-secondary text-white font-bold py-3 px-8 rounded-full text-lg transition-all duration-300 shadow-lg">
                Get a Free Quote
            </a>
        </div>
    </section>

    <!-- Marquee Section -->
    <section class="marquee-container shadow-inner">
        <div class="marquee-content text-xl font-semibold">
            &bull; Your trusted partner for seamless logistics solutions &bull; Fast, reliable, and secure shipping worldwide &bull; Connecting Myanmar to Malaysia, Australia, New Zealand, Canada, and Thailand &bull; Expert customs clearance and last-mile delivery &bull; Get your cargo moving with mblogistics today!
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-16 md:py-24 transition-colors duration-300">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center mb-12 section-heading">Our Services</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Service Card 1: Air Freight -->
                <div class="bg-card p-6 rounded-lg shadow-md border-custom service-card">
                    <div class="text-primary mb-4">
                        <svg class="h-12 w-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0zM19 12H5"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-center mb-3">Air Freight</h3>
                    <p class="text-center">Fast and reliable air cargo services for urgent shipments across continents.</p>
                </div>
                <!-- Service Card 2: Ocean Freight -->
                <div class="bg-card p-6 rounded-lg shadow-md border-custom service-card">
                    <div class="text-primary mb-4">
                        <svg class="h-12 w-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4m0-10.457l4-2.285"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-center mb-3">Ocean Freight</h3>
                    <p class="text-center">Cost-effective and secure sea freight solutions for large volume shipments.</p>
                </div>
                <!-- Service Card 3: Land Transport -->
                <div class="bg-card p-6 rounded-lg shadow-md border-custom service-card">
                    <div class="text-primary mb-4">
                        <svg class="h-12 w-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-center mb-3">Land Transport</h3>
                    <p class="text-center">Efficient road and rail transportation for seamless domestic and cross-border movements.</p>
                </div>
                <!-- Service Card 4: Customs Clearance -->
                <div class="bg-card p-6 rounded-lg shadow-md border-custom service-card">
                    <div class="text-primary mb-4">
                        <svg class="h-12 w-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.003 12.003 0 002.944 12a12.003 12.003 0 003.04 8.618A11.955 11.955 0 0112 21.056a11.955 11.955 0 018.618-3.04A12.003 12.003 0 0021.056 12a12.003 12.003 0 00-3.04-8.618z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-center mb-3">Customs Clearance</h3>
                    <p class="text-center">Expert handling of all customs documentation and procedures for smooth transit.</p>
                </div>
                <!-- Service Card 5: Warehousing & Distribution -->
                <div class="bg-card p-6 rounded-lg shadow-md border-custom service-card">
                    <div class="text-primary mb-4">
                        <svg class="h-12 w-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-center mb-3">Warehousing & Distribution</h3>
                    <p class="text-center">Secure storage and efficient distribution services to optimize your supply chain.</p>
                </div>
                <!-- Service Card 6: Last-Mile Delivery -->
                <div class="bg-card p-6 rounded-lg shadow-md border-custom service-card">
                    <div class="text-primary mb-4">
                        <svg class="h-12 w-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-center mb-3">Last-Mile Delivery</h3>
                    <p class="text-center">Ensuring your goods reach their final destination quickly and safely.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="rates-routes" class="py-16 md:py-24 transition-colors duration-300 bg-card">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center mb-12 section-heading">Shipping Rates & Routes</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <?php if (empty($routes)): ?>
                    <p class="text-center lg:col-span-2 text-gray-500">Shipping rates are currently unavailable. Please check back later.</p>
                <?php else: ?>
                    <?php foreach ($routes as $route): ?>
                    <div class="bg-card p-6 rounded-lg shadow-md border-custom">
                        <h3 class="text-3xl font-semibold text-primary mb-4 text-center"><?= htmlspecialchars($route['origin_country']) ?> to <?= htmlspecialchars($route['destination_country']) ?></h3>
                        <p class="text-lg mb-4 text-center">Schedule: <span class="font-bold"><?= htmlspecialchars($route['schedule']) ?></span></p>
                        <div class="overflow-x-auto">
                            <table class="w-full price-table text-lg">
                                <thead><tr><th>Item Type</th><th>Price</th></tr></thead>
                                <tbody>
                                    <?php if (!empty($route['rates'])): ?>
                                        <?php foreach ($route['rates'] as $rate): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rate['item']) ?></td>
                                            <td><?= htmlspecialchars($rate['price']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-6 text-lg">
                            <p class="font-semibold mb-2">Working Days:</p>
                            <p><?= htmlspecialchars($route['working_days']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Locations Section -->
    <section id="locations" class="py-16 md:py-24 bg-card transition-colors duration-300">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center mb-12 section-heading">Our Global Reach</h2>
            <div class="flex flex-wrap justify-center items-center gap-6 md:gap-10 text-xl font-semibold">
                <span class="text-primary text-2xl">Myanmar</span>
                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                <span class="text-gray-700 dark:text-gray-300">Malaysia</span>
                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                <span class="text-gray-700 dark:text-gray-300">Australia</span>
                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                <span class="text-gray-700 dark:text-gray-300">New Zealand</span>
                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                <span class="text-gray-700 dark:text-gray-300">Canada</span>
                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                <span class="text-gray-700 dark:text-gray-300">Thailand</span>
                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                <span class="text-primary text-2xl">...and back!</span>
            </div>
            <p class="text-center text-lg mt-8 text-gray-600 dark:text-gray-400">
                We provide comprehensive import and export services connecting these key regions, ensuring your cargo moves efficiently and reliably.
            </p>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class="py-16 md:py-24 transition-colors duration-300">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center mb-12 section-heading">About mblogistics</h2>
            <div class="flex flex-col md:flex-row items-center justify-between gap-10">
                <div class="md:w-1/2">
                    <p class="text-lg leading-relaxed mb-4">
                        At <span class="font-semibold text-primary">mblogistics</span>, we are dedicated to providing top-tier logistics solutions connecting Myanmar with major global markets including Malaysia, Australia, New Zealand, Canada, and Thailand. With years of experience and a deep understanding of international trade, we ensure your cargo reaches its destination safely, on time, and within budget.
                    </p>
                    <p class="text-lg leading-relaxed">
                        Our commitment to excellence, customer satisfaction, and innovative shipping strategies sets us apart. We leverage advanced technology and a strong network of partners to offer seamless door-to-door services, tailored to meet the unique needs of your business. Trust us to be your reliable partner in global logistics.
                    </p>
                    <ul class="list-disc list-inside mt-6 space-y-2 text-lg">
                        <li>Extensive global network</li>
                        <li>Experienced and dedicated team</li>
                        <li>Customized shipping solutions</li>
                        <li>Competitive pricing</li>
                        <li>Real-time tracking and support</li>
                    </ul>
                </div>
                <div class="md:w-1/2 flex justify-center">
                    <img src="https://via.placeholder.com/600x400/FF8C00/FFFFFF?text=Team+at+mblogistics" alt="mblogistics team" class="rounded-lg shadow-xl border border-custom">
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Us Section -->
    <section id="contact" class="py-16 md:py-24 bg-card transition-colors duration-300">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center mb-12 section-heading">Contact Us</h2>
            <div class="max-w-3xl mx-auto bg-card p-8 rounded-lg shadow-lg border border-custom">
                <p class="text-center text-lg mb-8">
                    Have a question or need a quote? Fill out the form below or reach out to us directly.
                </p>

                <form class="space-y-6 mb-10">
                    <div>
                        <label for="name" class="block text-lg font-medium mb-2">Your Name</label>
                        <input type="text" id="name" name="name" class="w-full p-3 border border-gray-300 rounded-md focus:ring-primary focus:border-primary bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="John Doe" required>
                    </div>
                    <div>
                        <label for="email" class="block text-lg font-medium mb-2">Your Email</label>
                        <input type="email" id="email" name="email" class="w-full p-3 border border-gray-300 rounded-md focus:ring-primary focus:border-primary bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="john.doe@example.com" required>
                    </div>
                    <div>
                        <label for="subject" class="block text-lg font-medium mb-2">Subject</label>
                        <input type="text" id="subject" name="subject" class="w-full p-3 border border-gray-300 rounded-md focus:ring-primary focus:border-primary bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Inquiry about shipping services">
                    </div>
                    <div>
                        <label for="message" class="block text-lg font-medium mb-2">Your Message</label>
                        <textarea id="message" name="message" rows="5" class="w-full p-3 border border-gray-300 rounded-md focus:ring-primary focus:border-primary bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Tell us more about your shipping needs..." required></textarea>
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-bold py-3 px-6 rounded-md text-lg transition-all duration-300 shadow-md">
                        Send Message
                    </button>
                </form>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-10">
                    <!-- Yangon Address & Map -->
                    <div>
                        <h3 class="text-2xl font-semibold text-primary mb-4">Yangon Office</h3>
                        <p class="text-lg mb-2"><strong>Address:</strong> 53, 125St, Ground Floor, Mingalar Taung Nyunt, Yangon</p>
                        <p class="text-lg mb-4"><strong>Phone:</strong> <a href="tel:+959263525212" class="text-primary hover:underline">09 263 5252 12</a>, <a href="tel:+959785380019" class="text-primary hover:underline">09 785 3800 19</a></p>
                        <div class="mb-4 rounded-lg overflow-hidden shadow-md">
                            
                            <iframe
                                src="https://www.google.com/maps/embed?pb=!1m10!1m8!1m3!1d238.73221351838356!2d96.17128722209017!3d16.790828399499045!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2smm!4v1749614000735!5m2!1sen!2smm"
                                width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>

                    <!-- Malaysia Address & Map -->
                    <div>
                        <h3 class="text-2xl font-semibold text-primary mb-4">Malaysia Office</h3>
                        <p class="text-lg mb-2"><strong>Address:</strong> 6-A,Jalan 4 Selayang Baru, 68100 Batu Caves Selangor, Malaysia</p>
                        <p class="text-lg mb-4"><strong>Phone:</strong> <a href="tel:+60172800272" class="text-primary hover:underline">017 2800 272</a>, <a href="tel:+60173055327" class="text-primary hover:underline">017 3055 327</a></p>
                        <div class="mb-4 rounded-lg overflow-hidden shadow-md">
                            <iframe
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3983.4523324700244!2d101.66990677585932!3d3.237067352604065!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc4705899de683%3A0x300e1c5a1fab5f26!2s93%2C%20Jalan%201%2C%20Taman%20Sri%20Selayang%2C%2068100%20Batu%20Caves%2C%20Selangor%2C%20Malaysia!5e0!3m2!1sen!2smm!4v1749614172194!5m2!1sen!2smm"
                                width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-black text-white py-8 transition-colors duration-300">
        <div class="container mx-auto px-4 text-center">
            <div class="flex justify-center items-center mb-4">
                <!-- Logo in footer with circular border-radius -->
                <img src="https://placehold.co/50x50/FF8C00/FFFFFF?text=ML" alt="mblogistics Logo" class="h-8 w-8 mr-2 rounded-full light:invert-0 dark:invert">
                <span class="text-2xl font-bold text-primary">mblo</span><span class="text-2xl font-bold text-secondary">gistics</span>
            </div>
            <p class="text-sm mb-4">&copy; 2025 mblogistics. All rights reserved.</p>
            <div class="flex justify-center space-x-6">
                <a href="#" class="text-gray-400 hover:text-white transition-colors duration-200">Privacy Policy</a>
                <a href="#" class="text-gray-400 hover:text-white transition-colors duration-200">Terms of Service</a>
            </div>
        </div>
    </footer>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
        const sunIcon = document.getElementById('sun-icon');
        const moonIcon = document.getElementById('moon-icon');
        const htmlElement = document.documentElement;

        // Set initial theme based on localStorage or system preference
        const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (currentTheme === 'dark') {
            htmlElement.classList.add('dark');
            sunIcon.style.display = 'block';
            moonIcon.style.display = 'none';
        } else {
            htmlElement.classList.add('light');
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'block';
        }

        themeToggle.addEventListener('click', () => {
            if (htmlElement.classList.contains('light')) {
                htmlElement.classList.remove('light');
                htmlElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                htmlElement.classList.remove('dark');
                htmlElement.classList.add('light');
                localStorage.setItem('theme', 'light');
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }
        });

        // Mobile Menu Toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        // Close mobile menu when a link is clicked
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
            });
        });
    </script>
</body>
</html>
