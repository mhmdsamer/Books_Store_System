<?php
// Include database connection
require_once 'connection.php';

// Start session
session_start();

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$is_admin = $logged_in && $_SESSION['role'] === 'admin';

// Get search parameters
$search_query = isset($_GET['q']) ? $_GET['q'] : '';
$selected_category = isset($_GET['category']) ? urldecode($_GET['category']) : null;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Pagination
$items_per_page = 12;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $conditions[] = "(title LIKE ? OR author LIKE ? OR description LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($selected_category)) {
    $conditions[] = "category = ?";
    $params[] = $selected_category;
    $types .= 's';
}

// Combine conditions
$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Determine sort order
$sort_clause = '';
switch ($sort_by) {
    case 'price_low':
        $sort_clause = 'ORDER BY price ASC';
        break;
    case 'price_high':
        $sort_clause = 'ORDER BY price DESC';
        break;
    case 'name_asc':
        $sort_clause = 'ORDER BY title ASC';
        break;
    case 'name_desc':
        $sort_clause = 'ORDER BY title DESC';
        break;
    case 'oldest':
        $sort_clause = 'ORDER BY created_at ASC';
        break;
    case 'newest':
    default:
        $sort_clause = 'ORDER BY created_at DESC';
        break;
}

// Count total books for pagination
$count_query = "SELECT COUNT(*) as total FROM books $where_clause";
$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_books = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_books / $items_per_page);
$count_stmt->close();

// Get books
$books_query = "SELECT book_id, title, author, price, description, image_url, 
                       available_for_borrowing, borrowing_price_per_day, stock_quantity, 
                       category, publisher, published_date 
                FROM books 
                $where_clause 
                $sort_clause 
                LIMIT ? OFFSET ?";

$books_stmt = $conn->prepare($books_query);

if (!empty($params)) {
    $books_stmt->bind_param($types . 'ii', ...[...$params, $items_per_page, $offset]);
} else {
    $books_stmt->bind_param('ii', $items_per_page, $offset);
}

$books_stmt->execute();
$books_result = $books_stmt->get_result();
$books = [];

if ($books_result && $books_result->num_rows > 0) {
    while ($row = $books_result->fetch_assoc()) {
        $books[] = $row;
    }
}
$books_stmt->close();

// Get all categories for filter
$categories_query = "SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category";
$categories_result = $conn->query($categories_query);
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Handle add to cart (purchase)
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
    $redirect_url = "books.php?added=1";
    if (!empty($search_query)) $redirect_url .= "&q=" . urlencode($search_query);
    if (!empty($selected_category)) $redirect_url .= "&category=" . urlencode($selected_category);
    if ($sort_by != 'newest') $redirect_url .= "&sort=" . urlencode($sort_by);
    if ($current_page > 1) $redirect_url .= "&page=" . $current_page;
    
    header("Location: " . $redirect_url);
    exit();
}

// Handle add to borrowing cart
if ($logged_in && isset($_POST['add_to_borrow']) && isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if book is already in cart
    $check_query = "SELECT * FROM cart_items WHERE user_id = ? AND book_id = ? AND is_purchase = 0";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $user_id, $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Book already in borrowing cart - just show notification
    } else {
        // Insert new cart item for borrowing (quantity is always 1 for borrowing)
        $insert_query = "INSERT INTO cart_items (user_id, book_id, quantity, is_purchase) VALUES (?, ?, 1, 0)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $user_id, $book_id);
        $insert_stmt->execute();
        
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
    // Redirect to prevent form resubmission
    $redirect_url = "books.php?borrowed=1";
    if (!empty($search_query)) $redirect_url .= "&q=" . urlencode($search_query);
    if (!empty($selected_category)) $redirect_url .= "&category=" . urlencode($selected_category);
    if ($sort_by != 'newest') $redirect_url .= "&sort=" . urlencode($sort_by);
    if ($current_page > 1) $redirect_url .= "&page=" . $current_page;
    
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
    <title>Books - BookStore</title>
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
        
        /* Toggle switch for filters */
        .toggle-checkbox:checked {
            right: 0;
            border-color: #0ea5e9;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #0ea5e9;
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
                    <a href="books.php" class="font-medium text-primary-600 border-b-2 border-primary-500">Books</a>
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
                <form action="books.php" method="GET" class="relative">
                    <input type="text" name="q" placeholder="Search for books, authors, or categories..." 
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           class="w-full py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    <?php if (!empty($selected_category)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                    <?php endif; ?>
                    <?php if ($sort_by != 'newest'): ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <?php endif; ?>
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
                <a href="books.php" class="block font-medium text-primary-600">Books</a>
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
    
    <?php if (isset($_GET['borrowed']) && $_GET['borrowed'] == 1): ?>
    <!-- Borrow Notification -->
    <div class="fixed top-20 right-4 bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded shadow-md cart-notification z-50">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-blue-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm">Book added to your borrowing cart!</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <section class="bg-gradient-to-r from-primary-900 to-primary-700 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold font-display mb-2">
                        <?php if (!empty($search_query)): ?>
                            Search Results for "<?php echo htmlspecialchars($search_query); ?>"
                        <?php elseif (!empty($selected_category)): ?>
                            <?php echo htmlspecialchars($selected_category); ?> Books
                        <?php else: ?>
                            All Books
                        <?php endif; ?>
                    </h1>
                    <div class="flex items-center">
                        <a href="index.php" class="text-primary-100 hover:text-white transition-colors">Home</a>
                        <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                        <span class="text-white">Books</span>
                        <?php if (!empty($selected_category)): ?>
                        <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                        <span class="text-white"><?php echo htmlspecialchars($selected_category); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sort Options -->
                <div class="mt-4 md:mt-0">
                    <form action="books.php" method="GET" class="flex items-center space-x-2" id="sort-form">
                        <?php if (!empty($search_query)): ?>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                        <?php endif; ?>
                        <?php if (!empty($selected_category)): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                        <?php endif; ?>
                        
                        <label for="sort" class="text-primary-100 text-sm">Sort by:</label>
                        <select name="sort" id="sort" class="bg-primary-800 text-white border border-primary-600 rounded-md text-sm py-1 pr-8 pl-2 focus:outline-none focus:ring-2 focus:ring-primary-300" onchange="document.getElementById('sort-form').submit();">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                            <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                            <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Main Content -->
    <section class="py-8">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Sidebar Filters -->
                <div class="lg:w-1/4 xl:w-1/5">
                    <div class="bg-white rounded-lg shadow-md p-5 sticky top-24">
                        <h3 class="font-bold text-lg mb-4">Filters</h3>
                        
                        <!-- Categories Filter -->
                        <div class="mb-6">
                            <h4 class="font-medium text-gray-700 mb-3">Categories</h4>
                            <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                            <a href="books.php<?php echo !empty($search_query) ? '?q=' . urlencode($search_query) : ''; ?>" 
                                   class="block py-1 px-2 rounded <?php echo empty($selected_category) ? 'bg-primary-100 text-primary-700 font-medium' : 'text-gray-700 hover:bg-gray-100'; ?>">
                                   All Categories</a>
                                
                                <?php foreach ($categories as $category): ?>
                                <a href="books.php?<?php 
                                    $params = [];
                                    if (!empty($search_query)) $params[] = 'q=' . urlencode($search_query);
                                    $params[] = 'category=' . urlencode($category);
                                    if ($sort_by != 'newest') $params[] = 'sort=' . urlencode($sort_by);
                                    echo implode('&', $params);
                                ?>" 
                                   class="block py-1 px-2 rounded <?php echo $selected_category == $category ? 'bg-primary-100 text-primary-700 font-medium' : 'text-gray-700 hover:bg-gray-100'; ?>">
                                   <?php echo htmlspecialchars($category); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Price Range Filter (could be added in future iterations) -->
                        
                        <!-- Availability Filter -->
                        <div class="mb-6">
                            <h4 class="font-medium text-gray-700 mb-3">Availability</h4>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" class="form-checkbox h-4 w-4 text-primary-600">
                                    <span class="ml-2 text-gray-700">Available for Purchase</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" class="form-checkbox h-4 w-4 text-primary-600">
                                    <span class="ml-2 text-gray-700">Available for Borrowing</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Reset Filters -->
                        <div class="mt-6">
                            <a href="books.php" class="inline-flex items-center text-primary-600 hover:text-primary-700">
                                <i class="fas fa-redo-alt mr-2"></i> Reset Filters
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Books Grid -->
                <div class="lg:w-3/4 xl:w-4/5">
                    <!-- Results Summary -->
                    <div class="mb-6">
                        <p class="text-gray-600">
                            Showing <span class="font-medium"><?php echo count($books); ?></span> of <span class="font-medium"><?php echo $total_books; ?></span> books
                        </p>
                    </div>
                    
                    <?php if (count($books) > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($books as $book): ?>
                        <div class="book-card bg-white rounded-lg shadow-md overflow-hidden transition-transform duration-300">
                            <div class="relative overflow-hidden h-56">
                                <img src="<?php echo !empty($book['image_url']) ? htmlspecialchars($book['image_url']) : 'assets/images/book-placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                     class="book-image w-full h-full object-cover">
                                     
                                <?php if (!empty($book['category'])): ?>
                                <div class="absolute top-2 left-2">
                                    <span class="bg-primary-100 text-primary-800 text-xs px-2 py-1 rounded-full">
                                        <?php echo htmlspecialchars($book['category']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($book['available_for_borrowing']): ?>
                                <div class="absolute top-2 right-2">
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                        Borrowable
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-4">
                                <h3 class="font-medium text-lg mb-1 line-clamp-2">
                                    <a href="book-details.php?id=<?php echo $book['book_id']; ?>" class="text-gray-800 hover:text-primary-600 transition-colors">
                                        <?php echo htmlspecialchars($book['title']); ?>
                                    </a>
                                </h3>
                                
                                <p class="text-gray-600 text-sm mb-2"><?php echo htmlspecialchars($book['author']); ?></p>
                                
                                <div class="flex items-baseline mb-3">
                                    <span class="text-primary-600 font-bold text-lg">$<?php echo number_format($book['price'], 2); ?></span>
                                    <?php if ($book['available_for_borrowing']): ?>
                                    <span class="text-gray-500 text-xs ml-2">
                                        / Borrow: $<?php echo number_format($book['borrowing_price_per_day'], 2); ?>/day
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <form method="post" action="books.php" class="flex-1">
                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                        <button type="submit" name="add_to_cart" class="w-full py-2 px-3 bg-primary-600 hover:bg-primary-700 text-white rounded-md transition-colors text-sm flex items-center justify-center">
                                            <i class="fas fa-shopping-cart mr-2"></i> Buy
                                        </button>
                                    </form>
                                    
                                    <?php if ($book['available_for_borrowing']): ?>
                                    <form method="post" action="books.php" class="flex-1">
                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                        <button type="submit" name="add_to_borrow" class="w-full py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors text-sm flex items-center justify-center">
                                            <i class="fas fa-book-reader mr-2"></i> Borrow
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="mt-8 flex justify-center">
                        <nav class="inline-flex rounded-md shadow">
                            <?php if ($current_page > 1): ?>
                            <a href="books.php?<?php 
                                $params = [];
                                if (!empty($search_query)) $params[] = 'q=' . urlencode($search_query);
                                if (!empty($selected_category)) $params[] = 'category=' . urlencode($selected_category);
                                if ($sort_by != 'newest') $params[] = 'sort=' . urlencode($sort_by);
                                $params[] = 'page=' . ($current_page - 1);
                                echo implode('&', $params);
                            ?>" class="py-2 px-4 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-l-md">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="books.php?<?php 
                                $params = [];
                                if (!empty($search_query)) $params[] = 'q=' . urlencode($search_query);
                                if (!empty($selected_category)) $params[] = 'category=' . urlencode($selected_category);
                                if ($sort_by != 'newest') $params[] = 'sort=' . urlencode($sort_by);
                                if ($i > 1) $params[] = 'page=' . $i;
                                echo implode('&', $params);
                            ?>" class="py-2 px-4 <?php echo $i == $current_page ? 'bg-primary-50 text-primary-600 border border-primary-300 font-medium' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                            <a href="books.php?<?php 
                                $params = [];
                                if (!empty($search_query)) $params[] = 'q=' . urlencode($search_query);
                                if (!empty($selected_category)) $params[] = 'category=' . urlencode($selected_category);
                                if ($sort_by != 'newest') $params[] = 'sort=' . urlencode($sort_by);
                                $params[] = 'page=' . ($current_page + 1);
                                echo implode('&', $params);
                            ?>" class="py-2 px-4 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-r-md">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-search fa-3x"></i>
                        </div>
                        <h3 class="text-xl font-medium text-gray-700 mb-2">No books found</h3>
                        <p class="text-gray-600 mb-6">We couldn't find any books matching your search criteria.</p>
                        <a href="books.php" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md transition-colors">
                            <i class="fas fa-redo-alt mr-2"></i> Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div>
                    <h3 class="text-xl font-bold mb-4">BookStore</h3>
                    <p class="text-gray-400 mb-4">Your one-stop destination for all types of books. We believe in the power of reading and knowledge sharing.</p>
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
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-medium mb-4">Quick Links</h3>
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
                    <h3 class="text-lg font-medium mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Shipping Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Return Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Terms of Service</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div>
                    <h3 class="text-lg font-medium mb-4">Contact Us</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-2"></i>
                            <span>123 Book Street, Reading City, RC 12345</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt mr-2"></i>
                            <span>+1 (123) 456-7890</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2"></i>
                            <span>contact@bookstore.com</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            <span>Mon-Fri: 9AM - 6PM</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 BookStore. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- AlpineJS for Dropdowns -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
    
    <script>
        // Toggle Search Bar
        document.getElementById('search-toggle').addEventListener('click', function() {
            const searchBar = document.getElementById('search-bar');
            searchBar.classList.toggle('hidden');
            if (!searchBar.classList.contains('hidden')) {
                searchBar.querySelector('input').focus();
            }
        });
        
        // Mobile Menu Toggle
        document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.add('open');
        });
        
        document.getElementById('close-mobile-menu').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.remove('open');
        });
        
        // Fade out notifications after 3 seconds
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const notifications = document.querySelectorAll('.cart-notification');
                notifications.forEach(function(notification) {
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 500);
                });
            }, 3000);
        });
    </script>
</body>
</html>