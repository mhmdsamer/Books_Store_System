<?php
// Include database connection
require_once 'connection.php';

// Start session
session_start();

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$is_admin = $logged_in && $_SESSION['role'] === 'admin';

// Fetch featured books - Modified to match database schema
$featured_query = "SELECT b.book_id, b.title, b.author, b.price, b.description, b.image_url, 
                         b.available_for_borrowing, b.stock_quantity
                  FROM books b
                  ORDER BY b.created_at DESC
                  LIMIT 6";
                  
$featured_result = $conn->query($featured_query);
$featured_books = [];

if ($featured_result && $featured_result->num_rows > 0) {
    while ($row = $featured_result->fetch_assoc()) {
        $featured_books[] = $row;
    }
}

// Modified categories handling since there's no categories table
// We'll use the categories stored in the books table
$categories_query = "SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category";
$categories_result = $conn->query($categories_query);
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = [
            'category_id' => urlencode($row['category']),
            'category_name' => $row['category']
        ];
    }
}

// Add to cart functionality
if ($logged_in && isset($_POST['add_to_cart']) && isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if book is already in cart
    $check_query = "SELECT * FROM cart_items WHERE user_id = ? AND book_id = ? AND is_purchase = 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $user_id, $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update quantity
        $cart_item = $check_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + 1;
        
        $update_query = "UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['cart_item_id']);
        $update_stmt->execute();
        
        $update_stmt->close();
    } else {
        // Insert new cart item
        $insert_query = "INSERT INTO cart_items (user_id, book_id, quantity, is_purchase) VALUES (?, ?, 1, 1)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $user_id, $book_id);
        $insert_stmt->execute();
        
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
    // Redirect to prevent form resubmission
    header("Location: index.php?added=1#featured-books");
    exit();
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
    <title>BookStore - Your Online Bookshop & Library</title>
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
        /* Hero section stylings */
        .hero-gradient {
            background: linear-gradient(135deg, rgba(12, 74, 110, 0.8) 0%, rgba(14, 165, 233, 0.7) 100%);
        }
        
        .hero-image {
            background-image: url('/img/hero-books.jpg');
            background-size: cover;
            background-position: center;
        }
        
        /* Book card effects */
        .book-card {
            transition: all 0.3s ease;
        }
        
        .book-card:hover {
            transform: translateY(-8px);
        }
        
        .book-card .book-image {
            transition: transform 0.5s ease;
        }
        
        .book-card:hover .book-image {
            transform: scale(1.05);
        }
        
        /* 3D book effect */
        .book-3d {
            position: relative;
            transform-style: preserve-3d;
            transform: rotateY(-20deg) rotateX(5deg);
            transition: transform 0.6s ease;
        }
        
        .book-3d:hover {
            transform: rotateY(-15deg) rotateX(5deg) translateY(-5px);
        }
        
        .book-cover {
            position: relative;
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, #0ea5e9, #0369a1);
            border-radius: 2px;
            box-shadow: 
                5px 5px 20px rgba(0, 0, 0, 0.3),
                1px 1px 0 rgba(255, 255, 255, 0.2) inset;
        }
        
        .book-spine {
            position: absolute;
            width: 20px;
            height: 100%;
            transform: rotateY(90deg) translateZ(-10px) translateX(-10px);
            background: linear-gradient(90deg, #0c4a6e, #075985);
            border-radius: 2px 0 0 2px;
        }
        
        .book-pages {
            position: absolute;
            width: 97%;
            height: 97%;
            top: 1.5%;
            left: 1.5%;
            background: #fff;
            border-radius: 1px;
            transform: translateZ(-1px);
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
        
        /* Floating animation */
        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-15px);
            }
            100% {
                transform: translateY(0px);
            }
        }
        
        .float {
            animation: float 6s ease-in-out infinite;
        }
        
        /* Star rating */
        .star-rating {
            color: #e5e7eb;
        }
        
        .star-rating .filled {
            color: #f59e0b;
        }
        
        /* Cart notification */
        .cart-notification {
            animation: fadeOut 3s forwards;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        
        /* Category badges */
        .category-badge {
            transition: all 0.2s ease;
        }
        
        .category-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
                    <a href="index.php" class="font-medium text-primary-600 border-b-2 border-primary-500">Home</a>
                    <a href="books.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">Books</a>
                    <a href="categories.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">Categories</a>
                    <a href="about.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">About</a>
                    <a href="contact.php" class="font-medium text-gray-600 hover:text-primary-600 transition-colors">Contact</a>
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
                <a href="index.php" class="block font-medium text-primary-600">Home</a>
                <a href="books.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Books</a>
                <a href="categories.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Categories</a>
                <a href="about.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">About</a>
                <a href="contact.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Contact</a>
                <?php if (!$logged_in): ?>
                <div class="border-t border-gray-200 my-4 pt-4">
                    <a href="login.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Sign In</a>
                    <a href="signup.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors mt-2">Sign Up</a>
                </div>
                <?php endif; ?>
            </nav>
        </div>
    </div>
    
    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    <!-- Cart Notification -->
    <div class="fixed top-20 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md cart-notification z-50">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm">Book successfully added to your cart!</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Hero Section -->
    <section class="relative hero-image">
        <div class="hero-gradient absolute inset-0"></div>
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-col lg:flex-row items-center py-12 md:py-20">
                <!-- Hero Content -->
                <div class="lg:w-1/2 text-white mb-10 lg:mb-0">
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold font-display mb-4 leading-tight">Discover Your Next Favorite Book</h1>
                    <p class="text-lg md:text-xl opacity-90 mb-8 max-w-lg">Your one-stop destination for buying and borrowing books. Explore our vast collection of titles across all genres.</p>
                    
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="books.php" class="bg-white text-primary-700 hover:bg-primary-50 font-medium py-3 px-6 rounded-lg transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-book-open mr-2"></i>
                            Browse Collection
                        </a>
                        <a href="membership.php" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-3 px-6 rounded-lg transition-colors inline-flex items-center justify-center border border-primary-500">
                            <i class="fas fa-crown mr-2"></i>
                            Join Membership
                        </a>
                    </div>
                    
                    <div class="mt-8 flex items-center">
                        <div class="flex -space-x-2">
                            <img src="/api/placeholder/32/32" alt="User" class="w-8 h-8 rounded-full border-2 border-white">
                            <img src="/api/placeholder/32/32" alt="User" class="w-8 h-8 rounded-full border-2 border-white">
                            <img src="/api/placeholder/32/32" alt="User" class="w-8 h-8 rounded-full border-2 border-white">
                        </div>
                        <p class="ml-3 text-sm">Joined by 2,500+ book lovers</p>
                    </div>
                </div>
                
                <!-- Hero Image -->
                <div class="lg:w-1/2 lg:pl-12">
                    <div class="grid grid-cols-2 gap-4 md:gap-6">
                        <div class="space-y-4 md:space-y-6 mt-12">
                            <div class="h-48 md:h-64 bg-white rounded-lg overflow-hidden shadow-lg book-3d float relative">
                                <img src="/api/placeholder/300/400" alt="Book Cover" class="h-full w-full object-cover">
                            </div>
                            <div class="h-32 md:h-48 bg-white rounded-lg overflow-hidden shadow-lg book-3d relative">
                                <img src="/api/placeholder/300/400" alt="Book Cover" class="h-full w-full object-cover">
                            </div>
                        </div>
                        <div class="space-y-4 md:space-y-6">
                            <div class="h-32 md:h-48 bg-white rounded-lg overflow-hidden shadow-lg book-3d relative">
                                <img src="/api/placeholder/300/400" alt="Book Cover" class="h-full w-full object-cover">
                            </div>
                            <div class="h-48 md:h-64 bg-white rounded-lg overflow-hidden shadow-lg book-3d float relative">
                                <img src="/api/placeholder/300/400" alt="Book Cover" class="h-full w-full object-cover">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Categories Section -->
    <section class="py-12 bg-gray-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold font-display text-center mb-10">Browse by Category</h2>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php if (empty($categories)): ?>
                    <!-- Default categories if none found in database -->
                    <a href="categories.php?category=Fiction" class="category-badge bg-primary-100 text-primary-800 p-4 rounded-xl text-center hover:bg-primary-200 transition-all">
                        <i class="fas fa-book text-2xl mb-2"></i>
                        <p class="font-medium">Fiction</p>
                    </a>
                    <a href="categories.php?category=Science" class="category-badge bg-secondary-100 text-secondary-800 p-4 rounded-xl text-center hover:bg-secondary-200 transition-all">
                        <i class="fas fa-flask text-2xl mb-2"></i>
                        <p class="font-medium">Science</p>
                    </a>
                    <a href="categories.php?category=History" class="category-badge bg-accent-100 text-accent-800 p-4 rounded-xl text-center hover:bg-accent-200 transition-all">
                        <i class="fas fa-landmark text-2xl mb-2"></i>
                        <p class="font-medium">History</p>
                    </a>
                    <a href="categories.php?category=Self-Help" class="category-badge bg-green-100 text-green-800 p-4 rounded-xl text-center hover:bg-green-200 transition-all">
                        <i class="fas fa-brain text-2xl mb-2"></i>
                        <p class="font-medium">Self-Help</p>
                    </a>
                    <a href="categories.php?category=Children" class="category-badge bg-yellow-100 text-yellow-800 p-4 rounded-xl text-center hover:bg-yellow-200 transition-all">
                        <i class="fas fa-child text-2xl mb-2"></i>
                        <p class="font-medium">Children</p>
                    </a>
                    <a href="categories.php?category=Romance" class="category-badge bg-red-100 text-red-800 p-4 rounded-xl text-center hover:bg-red-200 transition-all">
                        <i class="fas fa-heart text-2xl mb-2"></i>
                        <p class="font-medium">Romance</p>
                    </a>
                <?php else: ?>
                    <?php 
                    $colors = [
                        'bg-primary-100 text-primary-800 hover:bg-primary-200',
                        'bg-secondary-100 text-secondary-800 hover:bg-secondary-200',
                        'bg-accent-100 text-accent-800 hover:bg-accent-200',
                        'bg-green-100 text-green-800 hover:bg-green-200',
                        'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
                        'bg-red-100 text-red-800 hover:bg-red-200',
                        'bg-purple-100 text-purple-800 hover:bg-purple-200',
                        'bg-blue-100 text-blue-800 hover:bg-blue-200',
                    ];
                    
                    $icons = [
                        'fas fa-book',
                        'fas fa-flask',
                        'fas fa-landmark',
                        'fas fa-brain',
                        'fas fa-child',
                        'fas fa-heart',
                        'fas fa-book-reader',
                        'fas fa-globe',
                        'fas fa-briefcase',
                        'fas fa-music'
                    ];
                    
                    foreach ($categories as $index => $category):
                        $color_class = $colors[$index % count($colors)];
                        $icon_class = $icons[$index % count($icons)];
                    ?>
                    <a href="categories.php?category=<?php echo $category['category_id']; ?>" class="category-badge <?php echo $color_class; ?> p-4 rounded-xl text-center transition-all">
                        <i class="<?php echo $icon_class; ?> text-2xl mb-2"></i>
                        <p class="font-medium"><?php echo $category['category_name']; ?></p>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <a href="categories.php" class="category-badge bg-gray-100 text-gray-800 p-4 rounded-xl text-center hover:bg-gray-200 transition-all">
                    <i class="fas fa-ellipsis-h text-2xl mb-2"></i>
                    <p class="font-medium">View All</p>
                </a>
            </div>
        </div>
    </section>
    
    <!-- Featured Books Section -->
    <section class="py-12" id="featured-books">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold font-display">Featured Books</h2>
                <a href="books.php" class="text-primary-600 hover:text-primary-700 font-medium flex items-center">
                    View All
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
            
            <?php if (empty($featured_books)): ?>
                <div class="text-center py-12">
                    <p class="text-gray-600">No featured books available at the moment. Check back soon!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
                    <?php foreach ($featured_books as $book): ?>
                        <div class="book-card bg-white rounded-lg overflow-hidden shadow-soft hover:shadow-lg transition-all">
                            <div class="h-48 overflow-hidden relative">
                                <img src="<?php echo !empty($book['image_url']) ? htmlspecialchars($book['image_url']) : '/api/placeholder/300/400'; ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                     class="book-image w-full h-full object-cover">
                                
                                <?php if ($book['available_for_borrowing']): ?>
                                    <div class="absolute top-2 left-2 bg-accent-500 text-white text-xs px-2 py-1 rounded-full">
                                        Borrowable
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-4">
                                <h3 class="font-medium text-gray-900 mb-1 truncate" title="<?php echo htmlspecialchars($book['title']); ?>">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h3>
                                <p class="text-gray-600 text-sm mb-2">
                                    <?php echo htmlspecialchars($book['author']); ?>
                                </p>
                                
                                <div class="flex items-center justify-between mb-3">
                                    <div class="star-rating inline-flex">
                                        <?php
                                        // Since we don't have ratings in this schema, display a default rating
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo '<i class="fas fa-star ' . ($i <= 4 ? 'filled' : '') . ' text-sm"></i>';
                                        }
                                        ?>
                                    </div>
                                    <span class="text-sm text-gray-500">(4.0)</span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <p class="text-lg font-bold text-primary-700">
                                        $<?php echo number_format($book['price'], 2); ?>
                                    </p>
                                    
                                    <?php if ($logged_in): ?>
                                        <form action="index.php#featured-books" method="POST">
                                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                            
                                        </form>
                                    <?php else: ?>
                                        <a href="login.php" class="bg-primary-100 hover:bg-primary-200 text-primary-800 p-2 rounded-full transition-colors">
                                            <i class="fas fa-shopping-cart"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Services Section -->
    <section class="py-12 bg-gradient-to-br from-primary-900 to-primary-800 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold font-display text-center mb-10">Our Services</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white/10 p-6 rounded-xl backdrop-blur-sm">
                    <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shopping-bag text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Buy Books</h3>
                    <p class="text-white/80">Purchase your favorite books and build your personal collection. We offer competitive prices and frequent discounts.</p>
                </div>
                
                <div class="bg-white/10 p-6 rounded-xl backdrop-blur-sm">
                    <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-book-reader text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Borrow Books</h3>
                    <p class="text-white/80">Join our lending library and borrow books for a fraction of the cost. Perfect for avid readers who like variety.</p>
                </div>
                
                <div class="bg-white/10 p-6 rounded-xl backdrop-blur-sm">
                    <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-exchange-alt text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Exchange Books</h3>
                    <p class="text-white/80">Trade your previously read books for new ones. An eco-friendly way to refresh your reading material.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Join Membership -->
    <section class="py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-accent-50 rounded-2xl p-8 md:p-12">
                <div class="flex flex-col md:flex-row items-center">
                    <div class="md:w-2/3 mb-6 md:mb-0 md:pr-8">
                        <h2 class="text-3xl font-bold font-display text-gray-900 mb-4">Join Our Membership Program</h2>
                        <p class="text-gray-700 mb-6">Become a member today and enjoy exclusive benefits such as discounted prices, priority access to new releases, free borrowing, and invitations to special events.</p>
                        
                        <div class="flex flex-wrap gap-4">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-accent-200 flex items-center justify-center mr-2">
                                    <i class="fas fa-tag text-accent-700"></i>
                                </div>
                                <span class="text-gray-700">Special Discounts</span>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-accent-200 flex items-center justify-center mr-2">
                                    <i class="fas fa-truck text-accent-700"></i>
                                </div>
                                <span class="text-gray-700">Free Delivery</span>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-accent-200 flex items-center justify-center mr-2">
                                    <i class="fas fa-calendar-alt text-accent-700"></i>
                                </div>
                                <span class="text-gray-700">Early Access</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:w-1/3 flex justify-center">
                        <a href="membership.php" class="bg-accent-600 hover:bg-accent-700 text-white font-medium py-3 px-6 rounded-lg transition-colors inline-flex items-center">
                            <i class="fas fa-crown mr-2"></i>
                            Learn More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Testimonials -->
    <section class="py-12 bg-gray-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold font-display text-center mb-10">What Our Customers Say</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-soft">
                    <div class="star-rating inline-flex mb-4">
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                    </div>
                    <p class="text-gray-700 mb-4">"I've been a member for over a year now and the service is exceptional. The borrowing feature saves me so much money and lets me read more books than I could have afforded to buy."</p>
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gray-200 mr-3">
                            <img src="/api/placeholder/40/40" alt="Customer" class="w-full h-full object-cover rounded-full">
                        </div>
                        <div>
                            <h4 class="font-medium">Sarah Johnson</h4>
                            <p class="text-sm text-gray-500">Premium Member</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-soft">
                    <div class="star-rating inline-flex mb-4">
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-700 mb-4">"The book exchange program is brilliant! I've discovered so many new authors and genres that I wouldn't have tried otherwise. The staff recommendations are always spot on."</p>
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gray-200 mr-3">
                            <img src="/api/placeholder/40/40" alt="Customer" class="w-full h-full object-cover rounded-full">
                        </div>
                        <div>
                            <h4 class="font-medium">Michael Thompson</h4>
                            <p class="text-sm text-gray-500">Regular Customer</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-soft">
                    <div class="star-rating inline-flex mb-4">
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                        <i class="fas fa-star filled"></i>
                    </div>
                    <p class="text-gray-700 mb-4">"Fast delivery, great selection, and excellent customer service. What more could you ask for? I appreciate how they carefully package the books to prevent any damage during shipping."</p>
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gray-200 mr-3">
                            <img src="/api/placeholder/40/40" alt="Customer" class="w-full h-full object-cover rounded-full">
                        </div>
                        <div>
                            <h4 class="font-medium">Emily Rodriguez</h4>
                            <p class="text-sm text-gray-500">New Member</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Newsletter -->
    <section class="py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-primary-50 rounded-2xl p-8 md:p-12">
                <div class="flex flex-col md:flex-row items-center">
                    <div class="md:w-2/3 mb-6 md:mb-0">
                        <h2 class="text-2xl font-bold font-display text-gray-900 mb-2">Subscribe to our Newsletter</h2>
                        <p class="text-gray-700">Stay updated on new releases, upcoming events, and exclusive offers.</p>
                    </div>
                    
                    <div class="md:w-1/3 w-full">
                        <form action="subscribe.php" method="POST" class="flex w-full">
                            <input type="email" name="email" placeholder="Your email address" required class="flex-grow px-4 py-3 rounded-l-lg border border-r-0 border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-3 rounded-r-lg transition-colors">
                                Subscribe
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-white pt-12 pb-6">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center mr-3">
                            <i class="fas fa-book-open text-white text-xl"></i>
                        </div>
                        <span class="font-display font-bold text-2xl text-white">Book<span class="text-primary-400">Store</span></span>
                    </div>
                    <p class="text-gray-400 mb-4">Your one-stop destination for buying and borrowing books. We're passionate about literature and dedicated to providing the best service to book lovers.</p>
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
                
                <div>
                    <h3 class="text-lg font-bold mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="faq.php" class="text-gray-400 hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="shipping.php" class="text-gray-400 hover:text-white transition-colors">Shipping Policy</a></li>
                        <li><a href="returns.php" class="text-gray-400 hover:text-white transition-colors">Returns & Refunds</a></li>
                        <li><a href="privacy.php" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="terms.php" class="text-gray-400 hover:text-white transition-colors">Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4">Contact Us</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt text-primary-400 mt-1 mr-3"></i>
                            <span class="text-gray-400">123 Bookstore Street, Reading City, 10001</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt text-primary-400 mr-3"></i>
                            <span class="text-gray-400">(555) 123-4567</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope text-primary-400 mr-3"></i>
                            <span class="text-gray-400">info@bookstore.com</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-clock text-primary-400 mr-3"></i>
                            <span class="text-gray-400">Mon-Fri: 9AM - 6PM</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-500 text-sm mb-4 md:mb-0">&copy; <?php echo date('Y'); ?> BookStore. All rights reserved.</p>
                    <div class="flex items-center space-x-4">
                        <a href="#" class="text-gray-500 hover:text-white transition-colors text-sm">Privacy Policy</a>
                        <span class="text-gray-700">|</span>
                        <a href="#" class="text-gray-500 hover:text-white transition-colors text-sm">Terms of Service</a>
                        <span class="text-gray-700">|</span>
                        <a href="#" class="text-gray-500 hover:text-white transition-colors text-sm">Sitemap</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top Button -->
    <button id="back-to-top" class="fixed bottom-6 right-6 bg-primary-600 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg z-50 transition-all opacity-0 invisible hover:bg-primary-700">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Alpine.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.10.2/cdn.min.js" defer></script>
    
    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const closeMobileMenu = document.getElementById('close-mobile-menu');
            const mobileMenu = document.getElementById('mobile-menu');
            
            mobileMenuToggle.addEventListener('click', function() {
                mobileMenu.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
            
            closeMobileMenu.addEventListener('click', function() {
                mobileMenu.classList.remove('open');
                document.body.style.overflow = '';
            });
            
            // Search toggle
            const searchToggle = document.getElementById('search-toggle');
            const searchBar = document.getElementById('search-bar');
            
            searchToggle.addEventListener('click', function() {
                searchBar.classList.toggle('hidden');
                if (!searchBar.classList.contains('hidden')) {
                    searchBar.querySelector('input').focus();
                }
            });
            
            // Back to top functionality
            const backToTopButton = document.getElementById('back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopButton.classList.remove('opacity-0', 'invisible');
                    backToTopButton.classList.add('opacity-100', 'visible');
                } else {
                    backToTopButton.classList.add('opacity-0', 'invisible');
                    backToTopButton.classList.remove('opacity-100', 'visible');
                }
            });
            
            backToTopButton.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>