<?php
// Include database connection
require_once 'connection.php';

// Start session
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$logged_in = true;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$username = $_SESSION['username'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';

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

// Pagination settings
$borrowings_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $borrowings_per_page;

// Get total borrowings count for pagination
$count_query = "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_borrowings = $count_row['total'];
$total_pages = ceil($total_borrowings / $borrowings_per_page);
$count_stmt->close();

// Get borrowings for current page
$borrowings_query = "SELECT b.*, 
                           bk.title as book_title,
                           bk.author as book_author,
                           bk.image_url as book_image
                     FROM borrowings b
                     JOIN books bk ON b.book_id = bk.book_id
                     WHERE b.user_id = ?
                     ORDER BY b.borrow_date DESC
                     LIMIT ? OFFSET ?";
$borrowings_stmt = $conn->prepare($borrowings_query);
$borrowings_stmt->bind_param("iii", $user_id, $borrowings_per_page, $offset);
$borrowings_stmt->execute();
$borrowings_result = $borrowings_stmt->get_result();
$borrowings = [];

while ($borrowing = $borrowings_result->fetch_assoc()) {
    $borrowings[] = $borrowing;
}
$borrowings_stmt->close();

// Handle return request if submitted
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $borrowing_id = $_POST['borrowing_id'];
    
    // Check if borrowing belongs to user and is active
    $check_query = "SELECT * FROM borrowings WHERE borrowing_id = ? AND user_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $borrowing_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $borrowing_data = $check_result->fetch_assoc();
        $book_id = $borrowing_data['book_id'];
        $daily_rate = $borrowing_data['daily_rate'];
        $expected_return_date = new DateTime($borrowing_data['expected_return_date']);
        $today = new DateTime();
        
        // Calculate total days and fee
        $borrow_date = new DateTime($borrowing_data['borrow_date']);
        $days_borrowed = $today->diff($borrow_date)->days;
        $total_fee = $days_borrowed * $daily_rate;
        
        // Check if overdue
        $status = 'returned';
        if ($today > $expected_return_date) {
            $overdue_days = $today->diff($expected_return_date)->days;
            $overdue_fee = $overdue_days * ($daily_rate * 1.5); // 50% more for overdue days
            $total_fee += $overdue_fee;
        }
        
        // Update borrowing status
        $update_query = "UPDATE borrowings SET 
                         status = ?, 
                         actual_return_date = NOW(), 
                         total_fee = ? 
                         WHERE borrowing_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sdi", $status, $total_fee, $borrowing_id);
        
        if ($update_stmt->execute()) {
            // Update book stock
            $update_book_query = "UPDATE books SET stock_quantity = stock_quantity + 1 WHERE book_id = ?";
            $update_book_stmt = $conn->prepare($update_book_query);
            $update_book_stmt->bind_param("i", $book_id);
            $update_book_stmt->execute();
            $update_book_stmt->close();
            
            $success_message = "Book has been returned successfully. Total fee: $" . number_format($total_fee, 2);
            
            // Reload borrowings list
            header("Location: borrowings.php?success=" . urlencode($success_message));
            exit();
        } else {
            $error_message = "Error processing return: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $error_message = "Cannot return this book. It may already be returned or not belong to you.";
    }
    
    $check_stmt->close();
}

// Handle extend request if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_borrowing'])) {
    $borrowing_id = $_POST['borrowing_id'];
    $extension_days = 7; // Default extension period (1 week)
    
    // Check if borrowing belongs to user and is active
    $check_query = "SELECT * FROM borrowings WHERE borrowing_id = ? AND user_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $borrowing_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update expected return date
        $update_query = "UPDATE borrowings SET 
                         expected_return_date = DATE_ADD(expected_return_date, INTERVAL ? DAY) 
                         WHERE borrowing_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $extension_days, $borrowing_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Borrowing period has been extended by " . $extension_days . " days.";
            
            // Reload borrowings list
            header("Location: borrowings.php?success=" . urlencode($success_message));
            exit();
        } else {
            $error_message = "Error extending borrowing period: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $error_message = "Cannot extend this borrowing. It may already be returned or not belong to you.";
    }
    
    $check_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Borrowings - BookStore</title>
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
        
        /* Notification animation */
        .notification {
            animation: fadeOut 4s forwards;
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
                        <span class="absolute -top-2 -right-2 bg-primary-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : '0'; ?>
                        </span>
                    </a>
                    
                    <!-- User Menu -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
                                <span class="text-primary-700 font-medium text-sm">
                                    <?php echo substr($first_name ?? $username, 0, 1); ?>
                                </span>
                            </div>
                            <span class="hidden sm:inline-block font-medium text-gray-700">
                                <?php echo $first_name ?? $username; ?>
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
                            <a href="borrowings.php" class="block px-4 py-2 text-sm text-primary-600 bg-gray-100">Your Borrowings</a>
                            <div class="border-b border-gray-200"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Sign Out</a>
                        </div>
                    </div>
                    
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
                <a href="contact.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Contact</a>
            </nav>
        </div>
    </div>
    
    <!-- Notification Messages -->
    <?php if (!empty($success_message) || isset($_GET['success'])): ?>
    <div class="fixed top-20 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md notification z-50">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm"><?php echo htmlspecialchars($success_message ?? $_GET['success']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="fixed top-20 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md notification z-50">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <section class="bg-gradient-to-r from-primary-900 to-primary-700 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl md:text-4xl font-bold font-display">Your Borrowings</h1>
            <div class="flex items-center mt-2">
                <a href="index.php" class="text-primary-100 hover:text-white transition-colors">Home</a>
                <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                <span class="text-white">Borrowings</span>
            </div>
        </div>
    </section>
    
    <!-- Borrowings Section -->
    <section class="py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <?php if (empty($borrowings)): ?>
            <div class="bg-white rounded-lg shadow-soft p-8 text-center">
                <div class="w-20 h-20 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-book text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">No Borrowings Yet</h3>
                <p class="text-gray-600 mb-6">You haven't borrowed any books yet. Explore our collection and borrow books today!</p>
                <a href="books.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                    Browse Books
                </a>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-lg shadow-soft overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Borrowing History</h2>
                    <p class="text-gray-600 text-sm mt-1">View and manage your borrowed books</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrowed On</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>

                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($borrowings as $borrowing): 
                                // Calculate days remaining or overdue
                                $today = new DateTime();
                                $expected_return = new DateTime($borrowing['expected_return_date']);
                                $days_diff = $today->diff($expected_return)->days;
                                $is_overdue = $today > $expected_return && $borrowing['status'] === 'active';
                                
                                // Update status to overdue if applicable
                                if ($is_overdue && $borrowing['status'] === 'active') {
                                    $borrowing['status'] = 'overdue';
                                }
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <?php if (!empty($borrowing['book_image'])): ?>
                                            <img class="h-10 w-10 rounded object-cover" src="<?php echo htmlspecialchars($borrowing['book_image']); ?>" alt="<?php echo htmlspecialchars($borrowing['book_title']); ?>">
                                            <?php else: ?>
                                            <div class="h-10 w-10 bg-gray-200 rounded flex items-center justify-center">
                                                <i class="fas fa-book text-gray-400"></i>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($borrowing['book_title']); ?></div>
                                            <div class="text-gray-500 text-sm"><?php echo htmlspecialchars($borrowing['book_author']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('M d, Y', strtotime($borrowing['borrow_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('M d, Y', strtotime($borrowing['expected_return_date'])); ?>
                                    <?php if ($borrowing['status'] === 'active'): ?>
                                        <?php if ($is_overdue): ?>
                                            <div class="text-red-600 text-xs mt-1">
                                                <?php echo $days_diff; ?> days overdue
                                            </div>
                                        <?php else: ?>
                                            <div class="text-gray-500 text-xs mt-1">
                                                <?php echo $days_diff; ?> days remaining
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo $borrowing['actual_return_date'] ? date('M d, Y', strtotime($borrowing['actual_return_date'])) : '-'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge status-<?php echo strtolower($borrowing['status']); ?>">
                                        <?php echo ucfirst($borrowing['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($borrowing['total_fee']): ?>
                                        $<?php echo number_format($borrowing['total_fee'], 2); ?>
                                    <?php else: ?>
                                        $<?php echo number_format($borrowing['daily_rate'], 2); ?>/day
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex space-x-2">
                                        <?php if ($borrowing['status'] === 'active' || $borrowing['status'] === 'overdue'): ?>
                                        <form action="borrowings.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to return this book?');">
                                            <input type="hidden" name="borrowing_id" value="<?php echo $borrowing['borrowing_id']; ?>">
                                            
                                        </form>
                                        
                                        <?php if ($borrowing['status'] === 'active'): ?>
                                        <form action="borrowings.php" method="POST" class="inline-block ml-4">
                                            <input type="hidden" name="borrowing_id" value="<?php echo $borrowing['borrowing_id']; ?>">
                                            
                                        </form>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo ($offset + 1); ?></span> to 
                            <span class="font-medium"><?php echo min($offset + $borrowings_per_page, $total_borrowings); ?></span> of 
                            <span class="font-medium"><?php echo $total_borrowings; ?></span> borrowings
                        </div>
                        <div class="flex space-x-1">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded bg-white text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-chevron-left mr-1"></i> Previous
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded bg-white text-gray-700 hover:bg-gray-50">
                                Next <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Information Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                <!-- How Borrowing Works -->
                <div class="bg-white rounded-lg shadow-soft p-6">
                    <div class="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center mb-4">
                        <i class="fas fa-info-circle text-primary-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">How Borrowing Works</h3>
                    <p class="text-gray-600 text-sm">
                        You can borrow books for up to 14 days. Return them before the due date to avoid late fees.
                        If you need more time, you can extend your borrowing period once for an additional 7 days.
                    </p>
                </div>
                
                <!-- Return Process -->
                <div class="bg-white rounded-lg shadow-soft p-6">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mb-4">
                        <i class="fas fa-undo text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Return Process</h3>
                    <p class="text-gray-600 text-sm">
                        Click the "Return" button when you're ready to return a book. The system will calculate any fees
                        based on the borrowing duration. Overdue books will incur additional charges.
                    </p>
                </div>
                
                <!-- Late Fees -->
                <div class="bg-white rounded-lg shadow-soft p-6">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-4">
                        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Late Fees</h3>
                    <p class="text-gray-600 text-sm">
                        Books returned after the due date will incur a late fee of 150% of the daily rate for each day overdue.
                        Please return your books on time to avoid additional charges.
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-white pt-12 pb-8">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- About -->
                <div>
                    <h3 class="text-lg font-bold mb-4">About BookStore</h3>
                    <p class="text-gray-400 text-sm">
                        BookStore is your premier destination for books of all genres. We offer both purchase and borrowing options to satisfy all readers.
                    </p>
                    <div class="flex space-x-4 mt-4">
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
                            <i class="fab fa-pinterest"></i>
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
                        <li><a href="faq.php" class="text-gray-400 hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="shipping.php" class="text-gray-400 hover:text-white transition-colors">Shipping Policy</a></li>
                        <li><a href="returns.php" class="text-gray-400 hover:text-white transition-colors">Returns & Refunds</a></li>
                        <li><a href="privacy.php" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="terms.php" class="text-gray-400 hover:text-white transition-colors">Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <!-- Newsletter -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Newsletter</h3>
                    <p class="text-gray-400 text-sm mb-4">
                        Subscribe to our newsletter and get notified about new books and exclusive offers.
                    </p>
                    <form action="#" method="POST" class="flex">
                        <input type="email" name="email" placeholder="Your email address" required class="px-4 py-2 rounded-l-lg w-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-r-lg transition-colors">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm">
                    &copy; <?php echo date('Y'); ?> BookStore. All rights reserved.
                </p>
                <div class="flex space-x-4 mt-4 md:mt-0">
                    <img src="assets/images/payment/visa.png" alt="Visa" class="h-8">
                    <img src="assets/images/payment/mastercard.png" alt="Mastercard" class="h-8">
                    <img src="assets/images/payment/paypal.png" alt="PayPal" class="h-8">
                    <img src="assets/images/payment/apple-pay.png" alt="Apple Pay" class="h-8">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- AlpineJS for Dropdowns -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile Menu Toggle
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const closeMobileMenu = document.getElementById('close-mobile-menu');
            
            mobileMenuToggle.addEventListener('click', function() {
                mobileMenu.classList.add('open');
            });
            
            closeMobileMenu.addEventListener('click', function() {
                mobileMenu.classList.remove('open');
            });
            
            // Search Bar Toggle
            const searchToggle = document.getElementById('search-toggle');
            const searchBar = document.getElementById('search-bar');
            
            searchToggle.addEventListener('click', function() {
                searchBar.classList.toggle('hidden');
            });
            
            // Auto-hide notifications after 5 seconds
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(function(notification) {
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 5000);
            });
        });
    </script>
</body>
</html>