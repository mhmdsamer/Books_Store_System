<?php
// Include database connection
require_once 'connection.php';

// Start session
session_start();

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$is_admin = $logged_in && $_SESSION['role'] === 'admin';

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

    // Validate data
    if (empty($name) || empty($email) || empty($message)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($rating < 1 || $rating > 5) {
        $error_message = "Please select a rating between 1 and 5.";
    } else {
        // Prepare data for insertion
        $user_id = $logged_in ? $_SESSION['user_id'] : NULL;
        
        // Insert into feedback table
        // Note: Since this is a contact form, we'll set book_id to NULL
        // and use the review field for the message
        $status = 'pending'; // Pending review by admin
        
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, book_id, rating, review, status, created_at) 
                              VALUES (?, NULL, ?, ?, ?, NOW())");
        
        $stmt->bind_param("idss", $user_id, $rating, $message, $status);
        
        if ($stmt->execute()) {
            $success_message = "Thank you for your message! We'll get back to you soon.";
            // Reset form data after successful submission
            $name = $email = $subject = $message = '';
            $rating = 0;
        } else {
            $error_message = "Sorry, there was an error sending your message. Please try again later.";
        }
        
        $stmt->close();
    }
}

// Count cart items for the user
$cart_count = 0;
if ($logged_in) {
    $cart_count_query = "SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ? AND is_purchase = 1";
    $cart_stmt = $conn->prepare($cart_count_query);
    $cart_stmt->bind_param("i", $_SESSION['user_id']);
    $cart_stmt->execute();
    $result = $cart_stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $cart_count = $row['total_items'] ?? 0;
    }
    
    $_SESSION['cart_count'] = $cart_count;
    $cart_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - BookStore</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        secondary: {
                            50: '#fdf2f8',
                            100: '#fce7f3',
                            200: '#fbcfe8',
                            300: '#f9a8d4',
                            400: '#f472b6',
                            500: '#ec4899',
                            600: '#db2777',
                            700: '#be185d',
                            800: '#9d174d',
                            900: '#831843',
                        },
                        accent: {
                            50: '#f5f3ff',
                            100: '#ede9fe',
                            200: '#ddd6fe',
                            300: '#c4b5fd',
                            400: '#a78bfa',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            800: '#5b21b6',
                            900: '#4c1d95',
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Poppins', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
                        'inner-soft': 'inset 0 2px 4px 0 rgba(0, 0, 0, 0.06)',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out forwards',
                        'slide-up': 'slideUp 0.5s ease-in-out forwards',
                        'slide-down': 'slideDown 0.5s ease-in-out forwards',
                        'bounce-slow': 'bounce 3s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        slideDown: {
                            '0%': { transform: 'translateY(-20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                    }
                },
            },
        }
    </script>
    <style>
        /* Rating stars */
        .rate {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        
        .rate > input {
            display: none;
        }
        
        .rate > label {
            position: relative;
            width: 1.1em;
            font-size: 30px;
            color: #e5e7eb;
            cursor: pointer;
        }
        
        .rate > label::before {
            content: "\2605";
            position: absolute;
            opacity: 0;
        }
        
        .rate > label:hover:before,
        .rate > label:hover ~ label:before {
            opacity: 1 !important;
            color: #f59e0b;
        }
        
        .rate > input:checked ~ label:before {
            opacity: 1;
            color: #f59e0b;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        
        /* Mobile menu animation */
        #mobile-menu {
            transition: transform 0.3s ease-in-out;
            transform: translateX(-100%);
        }
        
        #mobile-menu.open {
            transform: translateX(0);
        }
    </style>
</head>
<body class="min-h-screen font-sans text-gray-800 bg-gray-50">
    <!-- Navigation -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-600 to-primary-800 flex items-center justify-center mr-3">
                            <i class="fas fa-book-open text-white text-xl"></i>
                        </div>
                        <span class="font-display font-bold text-2xl text-gray-800">Book<span class="text-primary-600">Store</span></span>
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="index.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">Home</a>
                    <a href="books.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">Books</a>
                    <a href="categories.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">Categories</a>
                    <a href="about.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">About</a>
                    <a href="contact.php" class="font-medium text-primary-600 border-b-2 border-primary-500">Contact</a>
                </nav>
                
                <!-- User Actions -->
                <div class="flex items-center space-x-4">
                    <!-- Search Button -->
                    <button type="button" id="search-toggle" class="text-gray-600 hover:text-primary-600 transition-colors p-1">
                        <i class="fas fa-search text-xl"></i>
                    </button>
                    
                    <!-- Cart -->
                    <a href="cart.php" class="text-gray-600 hover:text-primary-600 transition-colors p-1 relative">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if ($logged_in): ?>
                        <span class="absolute -top-2 -right-2 bg-primary-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : '0'; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- User Menu -->
                    <?php if ($logged_in): ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
                                <span class="text-primary-700 font-medium text-sm">
                                    <?php echo substr($_SESSION['first_name'] ?? $_SESSION['username'], 0, 1); ?>
                                </span>
                            </div>
                            <span class="hidden sm:inline-block font-medium text-gray-700">
                                <?php echo $_SESSION['first_name'] ?? $_SESSION['username']; ?>
                            </span>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50" style="display: none;">
                            <?php if ($is_admin): ?>
                            <a href="admin/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Dashboard</a>
                            <div class="border-b border-gray-200"></div>
                            <?php endif; ?>
                            
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                            <a href="orders.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Orders</a>
                            <a href="borrowings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Borrowings</a>
                            <div class="border-b border-gray-200"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Sign Out</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="space-x-2 flex items-center">
                        <a href="login.php" class="hidden sm:block text-primary-600 hover:text-primary-700 font-medium transition-colors">Sign In</a>
                        <a href="signup.php" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">Sign Up</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Mobile Menu Toggle -->
                    <button id="mobile-menu-toggle" class="md:hidden text-gray-600 hover:text-primary-600 transition-colors p-1">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Search Bar (Hidden by default) -->
            <div id="search-bar" class="pb-4 hidden">
                <form action="search.php" method="GET" class="relative">
                    <input type="text" name="q" placeholder="Search for books, authors, or categories..." class="w-full py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </form>
            </div>
        </div>
    </header>
    
    <!-- Mobile Menu (Off-canvas) -->
    <div id="mobile-menu" class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 md:hidden transform -translate-x-full">
        <div class="p-6">
            <div class="flex items-center justify-between mb-8">
                <div class="font-display font-bold text-xl text-gray-800">BookStore</div>
                <button id="close-mobile-menu" class="text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <nav class="space-y-4">
                <a href="index.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Home</a>
                <a href="books.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Books</a>
                <a href="categories.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Categories</a>
                <a href="about.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">About</a>
                <a href="contact.php" class="block font-medium text-primary-600">Contact</a>
                <?php if (!$logged_in): ?>
                <div class="border-t border-gray-200 my-4 pt-4">
                    <a href="login.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Sign In</a>
                    <a href="signup.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors mt-2">Sign Up</a>
                </div>
                <?php endif; ?>
            </nav>
        </div>
    </div>
    
    <!-- Page Header -->
    <section class="bg-gradient-to-r from-primary-900 to-primary-700 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl md:text-4xl font-bold font-display">Contact Us</h1>
            <div class="flex items-center mt-2">
                <a href="index.php" class="text-primary-100 hover:text-white transition-colors">Home</a>
                <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                <span class="text-white">Contact</span>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Contact Information -->
                <div class="animate-slide-up">
                    <h2 class="text-2xl font-bold font-display mb-6">Get In Touch</h2>
                    <p class="text-gray-600 mb-8">Have questions about our books, orders, or services? Our team is here to help! Fill out the form or use the contact information below to get in touch with us.</p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="bg-primary-100 rounded-full p-3 text-primary-600 mr-4">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-900 mb-1">Our Location</h3>
                                <p class="text-gray-600">123 Bookstore St, Literary City, LS 12345</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-primary-100 rounded-full p-3 text-primary-600 mr-4">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-900 mb-1">Email Us</h3>
                                <p class="text-gray-600">contact@bookstore.com</p>
                                <p class="text-gray-600">support@bookstore.com</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-primary-100 rounded-full p-3 text-primary-600 mr-4">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-900 mb-1">Call Us</h3>
                                <p class="text-gray-600">+1 (123) 456-7890</p>
                                <p class="text-gray-600">+1 (987) 654-3210</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-primary-100 rounded-full p-3 text-primary-600 mr-4">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-900 mb-1">Business Hours</h3>
                                <p class="text-gray-600">Monday-Friday: 9AM - 8PM</p>
                                <p class="text-gray-600">Saturday-Sunday: 10AM - 6PM</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-10">
                        <h3 class="font-medium text-gray-900 mb-3">Follow Us</h3>
                        <div class="flex space-x-4">
                            <a href="#" class="bg-primary-100 hover:bg-primary-200 text-primary-600 p-3 rounded-full transition-colors">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="bg-primary-100 hover:bg-primary-200 text-primary-600 p-3 rounded-full transition-colors">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="bg-primary-100 hover:bg-primary-200 text-primary-600 p-3 rounded-full transition-colors">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="bg-primary-100 hover:bg-primary-200 text-primary-600 p-3 rounded-full transition-colors">
                                <i class="fab fa-pinterest-p"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="bg-white rounded-xl shadow-soft p-8 animate-slide-up">
                    <h2 class="text-2xl font-bold font-display mb-6">Send Us a Message</h2>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle mt-1"></i>
                                </div>
                                <div class="ml-3">
                                    <p><?php echo $success_message; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle mt-1"></i>
                                </div>
                                <div class="ml-3">
                                    <p><?php echo $error_message; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="contact.php" method="POST">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-gray-700 font-medium mb-2">Your Name <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" required value="<?php echo isset($name) ? htmlspecialchars($name) : ($logged_in ? htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) : ''); ?>" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-gray-700 font-medium mb-2">Your Email <span class="text-red-500">*</span></label>
                                <input type="email" id="email" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ($logged_in && isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''); ?>" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="subject" class="block text-gray-700 font-medium mb-2">Subject</label>
                            <input type="text" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors">
                        </div>
                        
                        <div class="mb-6">
                            <label for="message" class="block text-gray-700 font-medium mb-2">Your Message <span class="text-red-500">*</span></label>
                            <textarea id="message" name="message" rows="5" required class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">Rate Your Experience <span class="text-red-500">*</span></label>
                            <div class="rate">
                                <input type="radio" id="star5" name="rating" value="5" <?php echo (isset($rating) && $rating == 5) ? 'checked' : ''; ?> required />
                                <label for="star5" title="5 stars">5 stars</label>
                                <input type="radio" id="star4" name="rating" value="4" <?php echo (isset($rating) && $rating == 4) ? 'checked' : ''; ?> />
                                <label for="star4" title="4 stars">4 stars</label>
                                <input type="radio" id="star3" name="rating" value="3" <?php echo (isset($rating) && $rating == 3) ? 'checked' : ''; ?> />
                                <label for="star3" title="3 stars">3 stars</label>
                                <input type="radio" id="star2" name="rating" value="2" <?php echo (isset($rating) && $rating == 2) ? 'checked' : ''; ?> />
                                <label for="star2" title="2 stars">2 stars</label>
                                <input type="radio" id="star1" name="rating" value="1" <?php echo (isset($rating) && $rating == 1) ? 'checked' : ''; ?> />
                                <label for="star1" title="1 star">1 star</label>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Please rate your overall experience with our BookStore</p>
                        </div>
                        
                        <button type="submit" name="submit_feedback" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-3 px-6 rounded-lg transition-colors">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Map Section -->
    <section class="py-12 bg-gray-100">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-soft overflow-hidden">
                <div class="aspect-w-16 aspect-h-9 w-full h-96">
                    <!-- Placeholder for a map (In a real implementation, this would be a Google Maps embed) -->
                    <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-map-marker-alt text-primary-500 text-4xl mb-3"></i>
                            <h3 class="font-medium text-gray-800 text-lg">Our Location</h3>
                            <p class="text-gray-600">123 Bookstore St, Literary City, LS 12345</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- FAQ Section -->
    <section class="py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold font-display mb-4">Frequently Asked Questions</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Find answers to common questions about our services, shipping, returns, and more.</p>
            </div>
            
            <div class="max-w-3xl mx-auto" x-data="{active: null}">
                <!-- FAQ Item 1 -->
                <div class="border-b border-gray-200 py-4">
                    <button @click="active = active === 1 ? null : 1" class="flex justify-between items-center w-full text-left">
                        <h3 class="font-medium text-lg text-gray-900">How long does shipping take?</h3>
                        <i class="fas" :class="active === 1 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                    <div x-show="active === 1" class="mt-3 text-gray-600" style="display: none;">
                        <p>Standard shipping typically takes 3-5 business days within the continental US. Express shipping options are available at checkout for 1-2 day delivery. International shipping can take 7-14 business days depending on the destination country.</p>
                    </div>
                </div>
                
                <!-- FAQ Item 2 -->
                <div class="border-b border-gray-200 py-4">
                    <button @click="active = active === 2 ? null : 2" class="flex justify-between items-center w-full text-left">
                        <h3 class="font-medium text-lg text-gray-900">What is your return policy?</h3>
                        <i class="fas" :class="active === 2 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                    <div x-show="active === 2" class="mt-3 text-gray-600" style="display: none;">
                        <p>We accept returns within 30 days of purchase for a full refund or exchange. Books must be in original condition with no damage beyond normal reading wear. Please include your order number and reason for return when sending items back to us.</p>
                    </div>
                </div>
                
                <!-- FAQ Item 3 -->
                <div class="border-b border-gray-200 py-4">
                    <button @click="active = active === 3 ? null : 3" class="flex justify-between items-center w-full text-left">
                        <h3 class="font-medium text-lg text-gray-900">How do I track my order?</h3>
                        <i class="fas" :class="active === 3 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                    <div x-show="active === 3" class="mt-3 text-gray-600" style="display: none;">
                        <p>Once your order ships, you will receive a shipping confirmation email with tracking information. You can also log into your account and view order status and tracking details under "Your Orders." Please allow 24-48 hours for tracking information to become active.</p>
                    </div>
                </div>
                
                <!-- FAQ Item 4 -->
                <div class="border-b border-gray-200 py-4">
                    <button @click="active = active === 4 ? null : 4" class="flex justify-between items-center w-full text-left">
                        <h3 class="font-medium text-lg text-gray-900">Do you offer international shipping?</h3>
                        <i class="fas" :class="active === 4 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                    <div x-show="active === 4" class="mt-3 text-gray-600" style="display: none;">
                        <p>Yes, we ship to most countries worldwide. International shipping rates vary by destination and package weight. Please note that customers are responsible for any customs fees, taxes, or import duties that may apply in your country.</p>
                    </div>
                </div>
                
                <!-- FAQ Item 5 -->
                <div class="border-b border-gray-200 py-4">
                    <button @click="active = active === 5 ? null : 5" class="flex justify-between items-center w-full text-left">
                        <h3 class="font-medium text-lg text-gray-900">How do I borrow a book instead of purchasing?</h3>
                        <i class="fas" :class="active === 5 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                    <div x-show="active === 5" class="mt-3 text-gray-600" style="display: none;">
                        <p>To borrow a book, you need to have a registered account with us. Simply navigate to the book's page and select the "Borrow" option instead of "Add to Cart." Borrowed books must be returned within the specified lending period to avoid late fees. You can manage all your current borrowings in the "Your Borrowings" section of your account.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Newsletter Section -->
    <section class="py-12 bg-primary-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-soft p-8 md:p-12">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <h2 class="text-2xl font-bold font-display mb-4">Subscribe to Our Newsletter</h2>
                        <p class="text-gray-600 mb-6">Stay updated with our latest releases, special offers, and literary events. Join our newsletter today!</p>
                        
                        <form class="flex flex-col sm:flex-row gap-3">
                            <input type="email" placeholder="Your email address" class="flex-grow px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors">
                            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-3 px-6 rounded-lg transition-colors whitespace-nowrap">
                                Subscribe
                            </button>
                        </form>
                        <p class="text-sm text-gray-500 mt-3">We respect your privacy and will never share your information with third parties.</p>
                    </div>
                    
                    <div class="hidden md:flex justify-end">
                        <div class="w-64 h-64 bg-primary-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-envelope-open-text text-primary-500 text-6xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white pt-12 pb-8">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div>
                    <h3 class="text-lg font-bold mb-4">BookStore</h3>
                    <p class="text-gray-400 mb-4">Your ultimate destination for books, with a vast selection of genres, formats, and authors to explore and enjoy.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-pinterest-p"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="books.php" class="text-gray-400 hover:text-white transition-colors">Books</a></li>
                        <li><a href="categories.php" class="text-gray-400 hover:text-white transition-colors">Categories</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition-colors">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                
                <!-- Customer Service -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">My Account</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Order History</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Shipping Information</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Returns & Refunds</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">FAQ</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Contact Info</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-3 text-gray-400"></i>
                            <span class="text-gray-400">123 Bookstore St, Literary City, LS 12345</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-envelope mt-1 mr-3 text-gray-400"></i>
                            <span class="text-gray-400">contact@bookstore.com</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-phone-alt mt-1 mr-3 text-gray-400"></i>
                            <span class="text-gray-400">+1 (123) 456-7890</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-clock mt-1 mr-3 text-gray-400"></i>
                            <span class="text-gray-400">Mon-Sun: 9AM - 9PM</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm mb-4 md:mb-0">&copy; 2025 BookStore. All rights reserved.</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Privacy Policy</a>
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Terms of Service</a>
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Shipping Policy</a>
                    <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const closeMenuButton = document.getElementById('close-mobile-menu');
            const mobileMenu = document.getElementById('mobile-menu');
            
            mobileMenuToggle.addEventListener('click', function() {
                mobileMenu.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
            
            closeMenuButton.addEventListener('click', function() {
                mobileMenu.classList.remove('open');
                document.body.style.overflow = '';
            });
            
            // Search toggle
            const searchToggle = document.getElementById('search-toggle');
            const searchBar = document.getElementById('search-bar');
            
            searchToggle.addEventListener('click', function() {
                searchBar.classList.toggle('hidden');
            });
        });
    </script>
</body>
</html>