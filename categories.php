<?php
// Include database connection
require_once 'connection.php';

// Start session
session_start();

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$is_admin = $logged_in && $_SESSION['role'] === 'admin';

// Get selected category if available
$selected_category = isset($_GET['category']) ? urldecode($_GET['category']) : null;

// Get all categories
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

// If database has no categories, use these defaults
if (empty($categories)) {
    $default_categories = ['Fiction', 'Science', 'History', 'Self-Help', 'Children', 'Romance', 'Mystery', 'Biography', 'Business', 'Technology'];
    foreach ($default_categories as $cat) {
        $categories[] = [
            'category_id' => urlencode($cat),
            'category_name' => $cat
        ];
    }
}

// Fetch books by category
$books = [];
if ($selected_category) {
    $books_query = "SELECT book_id, title, author, price, description, image_url, 
                           available_for_borrowing, stock_quantity, category, publisher, published_date
                    FROM books 
                    WHERE category = ?
                    ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($books_query);
    $stmt->bind_param("s", $selected_category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
    }
    $stmt->close();
} else {
    // If no category is selected, fetch a few books from each category
    foreach ($categories as $category) {
        $cat_name = $category['category_name'];
        $cat_books_query = "SELECT book_id, title, author, price, description, image_url, 
                                   available_for_borrowing, stock_quantity, category, publisher, published_date
                            FROM books 
                            WHERE category = ?
                            ORDER BY created_at DESC
                            LIMIT 4";
        
        $stmt = $conn->prepare($cat_books_query);
        $stmt->bind_param("s", $cat_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $category_books = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $category_books[] = $row;
            }
        }
        
        if (!empty($category_books)) {
            $books[$cat_name] = $category_books;
        }
        
        $stmt->close();
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
    $redirect_url = $selected_category ? 
        "categories.php?category=" . urlencode($selected_category) . "&added=1" : 
        "categories.php?added=1";
    
    header("Location: " . $redirect_url);
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
    <title><?php echo $selected_category ? htmlspecialchars($selected_category) . ' Books' : 'Browse Categories'; ?> - BookStore</title>
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
        
        /* Category badges */
        .category-badge {
            transition: all 0.2s ease;
        }
        
        .category-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* Active category styles */
        .category-badge.active {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* Star rating */
        .star-rating {
            color: #e5e7eb;
        }
        
        .star-rating .filled {
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
        
        /* Cart notification */
        .cart-notification {
            animation: fadeOut 3s forwards;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
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
                    <a href="categories.php" class="font-medium text-primary-600 border-b-2 border-primary-500">Categories</a>
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
                <a href="index.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Home</a>
                <a href="books.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Books</a>
                <a href="categories.php" class="block font-medium text-primary-600">Categories</a>
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
    
    <!-- Page Header -->
    <section class="bg-gradient-to-r from-primary-900 to-primary-700 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl md:text-4xl font-bold font-display">
                <?php echo $selected_category ? htmlspecialchars($selected_category) . ' Books' : 'Browse by Category'; ?>
            </h1>
            <div class="flex items-center mt-2">
                <a href="index.php" class="text-primary-100 hover:text-white transition-colors">Home</a>
                <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                <a href="categories.php" class="text-primary-100 hover:text-white transition-colors">Categories</a>
                <?php if ($selected_category): ?>
                <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                <span class="text-white"><?php echo htmlspecialchars($selected_category); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Categories Navigation -->
    <section class="py-8 border-b border-gray-200">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-xl font-bold mb-6">Categories</h2>
            
            <div class="flex flex-wrap gap-3">
                <a href="categories.php" class="category-badge <?php echo !$selected_category ? 'active bg-primary-100 text-primary-800' : 'bg-gray-100 text-gray-800 hover:bg-primary-50 hover:text-primary-700'; ?> px-4 py-2 rounded-full font-medium text-sm transition-all">
                    All Categories
                </a>
                
                <?php 
                $colors = [
                    'bg-primary-100 text-primary-800 hover:bg-primary-50 hover:text-primary-700',
                    'bg-secondary-100 text-secondary-800 hover:bg-secondary-50 hover:text-secondary-700',
                    'bg-accent-100 text-accent-800 hover:bg-accent-50 hover:text-accent-700',
                    'bg-green-100 text-green-800 hover:bg-green-50 hover:text-green-700',
                    'bg-yellow-100 text-yellow-800 hover:bg-yellow-50 hover:text-yellow-700',
                    'bg-red-100 text-red-800 hover:bg-red-50 hover:text-red-700',
                    'bg-purple-100 text-purple-800 hover:bg-purple-50 hover:text-purple-700',
                    'bg-blue-100 text-blue-800 hover:bg-blue-50 hover:text-blue-700',
                ];
                
                foreach ($categories as $index => $category):
                    $color_class = $colors[$index % count($colors)];
                    $is_active = $selected_category === $category['category_name'];
                ?>
                <a href="categories.php?category=<?php echo $category['category_id']; ?>" 
                   class="category-badge <?php echo $is_active ? 'active ' . $color_class : 'bg-gray-100 text-gray-800 hover:bg-gray-200'; ?> px-4 py-2 rounded-full font-medium text-sm transition-all">
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- Books Section -->
    <section class="py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <?php if ($selected_category): ?>
                <!-- Single category view -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold font-display">
                            <?php echo htmlspecialchars($selected_category); ?> Books
                        </h2>
                    </div>
                    
                    <?php if (empty($books)): ?>
                        <div class="bg-gray-100 rounded-lg p-8 text-center">
                            <i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-700 mb-2">No books found in this category</h3>
                            <p class="text-gray-600 mb-4">We're constantly updating our collection. Check back soon!</p>
                            <a href="categories.php" class="inline-flex items-center justify-center bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                                Browse Other Categories
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                            <?php foreach ($books as $book): ?>
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
                                                $random_rating = rand(3, 5);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo '<i class="fas fa-star ' . ($i <= $random_rating ? 'filled' : '') . ' text-sm"></i>';
                                                }
                                                ?>
                                            </div>
                                            <span class="text-sm text-gray-500">(<?php echo rand(1, 50); ?>)</span>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <p class="text-lg font-bold text-primary-700">
                                                $<?php echo number_format($book['price'], 2); ?>
                                            </p>
                                            
                                            <?php if ($logged_in): ?>
                                                <form action="categories.php?category=<?php echo urlencode($selected_category); ?>" method="POST">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                    <button type="submit" name="add_to_cart" class="bg-primary-100 hover:bg-primary-200 text-primary-800 p-2 rounded-full transition-colors">
                                                        <i class="fas fa-shopping-cart"></i>
                                                    </button>
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
            <?php else: ?>
                <!-- All categories view -->
                <?php 
                // If no books in database, show empty state
                $all_empty = true;
                if (is_array($books)) {
                    foreach ($books as $category_name => $category_books) {
                        if (!empty($category_books)) {
                            $all_empty = false;
                        }
                    }
                }
                
                if ($all_empty): 
                ?>
                    <div class="bg-gray-100 rounded-lg p-8 text-center">
                        <i class="fas fa-books text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-700 mb-2">No books available yet</h3>
                        <p class="text-gray-600 mb-4">Our catalog is being updated. Check back soon for exciting new titles!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($books as $category_name => $category_books): ?>
                        <?php if (!empty($category_books)): ?>
                            <div class="mb-12">
                                <div class="flex justify-between items-center mb-6">
                                    <h2 class="text-2xl font-bold font-display"><?php echo htmlspecialchars($category_name); ?></h2>
                                    <a href="categories.php?category=<?php echo urlencode($category_name); ?>" class="text-sm font-medium text-primary-600 hover:text-primary-800 flex items-center">
                                        View All <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                    <?php foreach ($category_books as $book): ?>
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
                                                        $random_rating = rand(3, 5);
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            echo '<i class="fas fa-star ' . ($i <= $random_rating ? 'filled' : '') . ' text-sm"></i>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <span class="text-sm text-gray-500">(<?php echo rand(1, 50); ?>)</span>
                                                </div>
                                                
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg font-bold text-primary-700">
                                                        $<?php echo number_format($book['price'], 2); ?>
                                                    </p>
                                                    
                                                    <?php if ($logged_in): ?>
                                                        <form action="categories.php" method="POST">
                                                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                            <button type="submit" name="add_to_cart" class="bg-primary-100 hover:bg-primary-200 text-primary-800 p-2 rounded-full transition-colors">
                                                                <i class="fas fa-shopping-cart"></i>
                                                            </button>
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
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    
    
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-600 to-primary-800 flex items-center justify-center mr-3">
                            <i class="fas fa-book-open text-white text-xl"></i>
                        </div>
                        <span class="font-display font-bold text-xl text-white">Book<span class="text-primary-500">Store</span></span>
                    </div>
                    
                    <p class="text-gray-400 mb-4">Your one-stop destination for all your reading needs. Quality books, competitive prices, and fast delivery.</p>
                    
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
                    <h3 class="text-white font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="books.php" class="text-gray-400 hover:text-white transition-colors">Books</a></li>
                        <li><a href="categories.php" class="text-gray-400 hover:text-white transition-colors">Categories</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition-colors">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                        <li><a href="faq.php" class="text-gray-400 hover:text-white transition-colors">FAQ</a></li>
                    </ul>
                </div>
                
                <!-- Customer Service -->
                <div>
                    <h3 class="text-white font-bold mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Shipping Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Return & Refund</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Payment Methods</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div>
                    <h3 class="text-white font-bold mb-4">Contact Us</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-3 text-primary-500"></i>
                            <span>123 Bookstore St, Literary City, LS 12345</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-envelope mt-1 mr-3 text-primary-500"></i>
                            <span>contact@bookstore.com</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-phone-alt mt-1 mr-3 text-primary-500"></i>
                            <span>+1 (123) 456-7890</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-clock mt-1 mr-3 text-primary-500"></i>
                            <span>Monday-Friday: 9AM - 8PM<br>Saturday-Sunday: 10AM - 6PM</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-12 pt-8 text-center">
                <p class="text-gray-500">© 2025 BookStore. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Alpine JS -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const closeMobileMenu = document.getElementById('close-mobile-menu');
            const mobileMenu = document.getElementById('mobile-menu');
            
            mobileMenuToggle.addEventListener('click', function() {
                mobileMenu.classList.add('open');
                mobileMenu.classList.remove('-translate-x-full');
            });
            
            closeMobileMenu.addEventListener('click', function() {
                mobileMenu.classList.remove('open');
                mobileMenu.classList.add('-translate-x-full');
            });
            
            // Search bar toggle
            const searchToggle = document.getElementById('search-toggle');
            const searchBar = document.getElementById('search-bar');
            
            searchToggle.addEventListener('click', function() {
                searchBar.classList.toggle('hidden');
                searchBar.querySelector('input').focus();
            });
        });
    </script>
</body>
</html>