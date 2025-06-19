<?php
// Start the session to maintain user authentication
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../connection.php';

// Fetch summary statistics
// Total number of books
$booksQuery = "SELECT COUNT(*) as total_books FROM books";
$booksResult = $conn->query($booksQuery);
$totalBooks = $booksResult->fetch_assoc()['total_books'];

// Total number of users
$usersQuery = "SELECT COUNT(*) as total_users FROM users";
$usersResult = $conn->query($usersQuery);
$totalUsers = $usersResult->fetch_assoc()['total_users'];

// Total number of orders
$ordersQuery = "SELECT COUNT(*) as total_orders FROM orders";
$ordersResult = $conn->query($ordersQuery);
$totalOrders = $ordersResult->fetch_assoc()['total_orders'];

// Total number of active borrowings
$borrowingsQuery = "SELECT COUNT(*) as active_borrowings FROM borrowings WHERE status = 'active'";
$borrowingsResult = $conn->query($borrowingsQuery);
$activeBorrowings = $borrowingsResult->fetch_assoc()['active_borrowings'];

// Recent orders
$recentOrdersQuery = "SELECT o.order_id, o.order_date, o.total_amount, o.status, u.username
                      FROM orders o
                      JOIN users u ON o.user_id = u.user_id
                      ORDER BY o.order_date DESC
                      LIMIT 5";
$recentOrdersResult = $conn->query($recentOrdersQuery);

// Books with low stock
$lowStockQuery = "SELECT book_id, title, author, stock_quantity
                 FROM books
                 WHERE stock_quantity < 5
                 ORDER BY stock_quantity ASC
                 LIMIT 5";
$lowStockResult = $conn->query($lowStockQuery);

// Recent users
$recentUsersQuery = "SELECT user_id, username, email, created_at, role
                    FROM users
                    ORDER BY created_at DESC
                    LIMIT 5";
$recentUsersResult = $conn->query($recentUsersQuery);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BookStore</title>
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
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f8fafc;
        }
        
        .dashboard-card {
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .nav-link {
            transition: all 0.2s ease;
        }
        
        .nav-link:hover {
            transform: translateX(5px);
        }
        
        .nav-link.active {
            color: #0ea5e9;
            border-left: 3px solid #0ea5e9;
        }
        
        .dropdown-menu {
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .chart-container {
            height: 300px;
        }
    </style>
</head>
<body class="font-sans min-h-screen">
    <div class="flex h-screen bg-gray-100">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-soft hidden md:block">
            <div class="p-6">
                <a href="../index.php" class="flex items-center space-x-2">
                    <i class="fas fa-book-open text-primary-600 text-2xl"></i>
                    <span class="text-xl font-bold text-gray-800 font-display">BookStore</span>
                </a>
                <p class="text-xs text-gray-500 mt-1">Admin Dashboard</p>
            </div>
            <nav class="mt-2">
                <div class="px-4 py-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Dashboard</h5>
                    <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Overview</span>
                    </a>
                </div>
                
                <div class="px-4 py-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Catalog</h5>
                    <a href="books.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-book"></i>
                        <span>Books</span>
                    </a>
                    <a href="categories.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </div>
                
                <div class="px-4 py-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sales</h5>
                    <a href="orders.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="borrowings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-hand-holding"></i>
                        <span>Borrowings</span>
                    </a>
                </div>
                
                <div class="px-4 py-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</h5>
                    <a href="users.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-users"></i>
                        <span>All Users</span>
                    </a>
                    <a href="feedback.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-star"></i>
                        <span>Feedback</span>
                    </a>
                </div>
                
                <div class="px-4 py-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Settings</h5>
                    <a href="profile.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-user-cog"></i>
                        <span>Profile</span>
                    </a>
                    <a href="settings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Store Settings</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-soft z-10">
                <div class="container mx-auto px-4 py-3">
                    <div class="flex items-center justify-between">
                        <!-- Mobile menu button -->
                        <button id="mobile-menu-button" class="md:hidden text-gray-500 hover:text-gray-700 focus:outline-none">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        
                        <!-- Search Bar -->
                        <div class="hidden md:flex items-center flex-1 max-w-md">
                            <form class="flex-1 relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </span>
                                <input type="text" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search for books, orders, users...">
                            </form>
                        </div>
                        
                        <!-- Right Navigation -->
                        <div class="flex items-center space-x-4">
                            <!-- Notifications -->
                            <div class="relative">
                                <button class="p-1 text-gray-500 rounded-full hover:bg-gray-100 focus:outline-none relative">
                                    <i class="fas fa-bell text-lg"></i>
                                    <span class="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full"></span>
                                </button>
                            </div>
                            
                            <!-- User Menu -->
                            <div class="relative" x-data="{ open: false }">
                                <button id="user-menu-button" class="flex items-center space-x-2 focus:outline-none">
                                    <div class="w-8 h-8 rounded-full bg-primary-500 flex items-center justify-center text-white">
                                        <span class="text-sm font-medium uppercase"><?php echo substr($_SESSION['first_name'], 0, 1); ?></span>
                                    </div>
                                    <div class="hidden md:block text-left">
                                        <h5 class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h5>
                                        <p class="text-xs text-gray-500">Administrator</p>
                                    </div>
                                    <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                                </button>
                                
                                <!-- Dropdown Menu -->
                                <div id="user-dropdown" class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-10">
                                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-user mr-2 text-gray-500"></i> Profile
                                    </a>
                                    <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-cog mr-2 text-gray-500"></i> Settings
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <a href="../logout.php" class="block px-4 py-2 text-sm text-red-500 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Mobile Sidebar -->
            <div id="mobile-sidebar" class="fixed inset-0 z-20 bg-black bg-opacity-50 hidden">
                <div class="absolute inset-y-0 left-0 w-64 bg-white shadow-lg transform -translate-x-full transition-transform duration-300 ease-in-out" id="mobile-sidebar-container">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <a href="../index.php" class="flex items-center space-x-2">
                                <i class="fas fa-book-open text-primary-600 text-2xl"></i>
                                <span class="text-xl font-bold text-gray-800 font-display">BookStore</span>
                            </a>
                            <button id="close-sidebar" class="text-gray-500 focus:outline-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Admin Dashboard</p>
                    </div>
                    
                    <!-- Mobile Nav Menu (same as desktop sidebar) -->
                    <nav class="mt-2">
                        <div class="px-4 py-2">
                            <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Dashboard</h5>
                            <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Overview</span>
                            </a>
                        </div>
                        
                        <div class="px-4 py-2">
                            <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Catalog</h5>
                            <a href="books.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-book"></i>
                                <span>Books</span>
                            </a>
                            <a href="categories.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-tags"></i>
                                <span>Categories</span>
                            </a>
                        </div>
                        
                        <div class="px-4 py-2">
                            <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sales</h5>
                            <a href="orders.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-shopping-cart"></i>
                                <span>Orders</span>
                            </a>
                            <a href="borrowings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-hand-holding"></i>
                                <span>Borrowings</span>
                            </a>
                        </div>
                        
                        <div class="px-4 py-2">
                            <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</h5>
                            <a href="users.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-users"></i>
                                <span>All Users</span>
                            </a>
                            <a href="feedback.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-star"></i>
                                <span>Feedback</span>
                            </a>
                        </div>
                        
                        <div class="px-4 py-2">
                            <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Settings</h5>
                            <a href="profile.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-user-cog"></i>
                                <span>Profile</span>
                            </a>
                            <a href="settings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-cog"></i>
                                <span>Store Settings</span>
                            </a>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-4 md:p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 font-display">Dashboard Overview</h1>
                            <p class="mt-1 text-sm text-gray-600">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! Here's what's happening with your bookstore today.</p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <a href="books.php?action=add" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-plus mr-2"></i> Add New Book
                            </a>
                            <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-download mr-2"></i> Export Report
                            </button>
                        </div>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Total Books Card -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 dashboard-card p-6">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full bg-primary-100 text-primary-600">
                                    <i class="fas fa-book text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">Total Books</h3>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalBooks; ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <a href="books.php" class="text-sm text-primary-600 hover:text-primary-700">View all books</a>
                                    <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> 12%
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Users Card -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 dashboard-card p-6">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full bg-accent-100 text-accent-600">
                                    <i class="fas fa-users text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">Total Users</h3>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalUsers; ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <a href="users.php" class="text-sm text-primary-600 hover:text-primary-700">View all users</a>
                                    <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> 8%
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Orders Card -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 dashboard-card p-6">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full bg-secondary-100 text-secondary-600">
                                    <i class="fas fa-shopping-cart text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">Total Orders</h3>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalOrders; ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <a href="orders.php" class="text-sm text-primary-600 hover:text-primary-700">View all orders</a>
                                    <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> 16%
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Borrowings Card -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 dashboard-card p-6">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full bg-yellow-100 text-yellow-600">
                                    <i class="fas fa-hand-holding text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">Active Borrowings</h3>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $activeBorrowings; ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <a href="borrowings.php" class="text-sm text-primary-600 hover:text-primary-700">View all borrowings</a>
                                    <span class="text-xs font-medium px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-arrow-right mr-1"></i> 0%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    <!-- Recent Data -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Recent Orders -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 overflow-hidden">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-semibold text-gray-800">Recent Orders</h3>
                                    <a href="orders.php" class="text-sm text-primary-600 hover:text-primary-700">View All</a>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if ($recentOrdersResult->num_rows > 0): ?>
                                            <?php while ($order = $recentOrdersResult->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $order['order_id']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($order['username']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
    <?php
    $statusClass = '';
    $statusIcon = '';
    
    switch($order['status']) {
        case 'completed':
            $statusClass = 'bg-green-100 text-green-800';
            $statusIcon = 'fa-check-circle';
            break;
        case 'processing':
            $statusClass = 'bg-blue-100 text-blue-800';
            $statusIcon = 'fa-spinner';
            break;
        case 'pending':
            $statusClass = 'bg-yellow-100 text-yellow-800';
            $statusIcon = 'fa-clock';
            break;
        case 'cancelled':
            $statusClass = 'bg-red-100 text-red-800';
            $statusIcon = 'fa-times-circle';
            break;
        default:
            $statusClass = 'bg-gray-100 text-gray-800';
            $statusIcon = 'fa-question-circle';
    }
    ?>
    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
        <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
        <?php echo ucfirst($order['status']); ?>
    </span>
</td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No recent orders found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Low Stock Books -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 overflow-hidden">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-semibold text-gray-800">Low Stock Books</h3>
                                    <a href="books.php?filter=low_stock" class="text-sm text-primary-600 hover:text-primary-700">View All</a>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if ($lowStockResult->num_rows > 0): ?>
                                            <?php while ($book = $lowStockResult->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $book['book_id']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($book['title']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($book['author']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $book['stock_quantity'] <= 0 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                            <?php echo $book['stock_quantity']; ?> left
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <a href="books.php?action=edit&id=<?php echo $book['book_id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="books.php?action=restock&id=<?php echo $book['book_id']; ?>" class="text-green-600 hover:text-green-900">
                                                            <i class="fas fa-plus-circle"></i> Restock
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No low stock items found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Users -->
                    <div class="mt-6 bg-white rounded-lg shadow-soft border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-gray-800">New Users</h3>
                                <a href="users.php" class="text-sm text-primary-600 hover:text-primary-700">View All</a>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($recentUsersResult->num_rows > 0): ?>
                                        <?php while ($user = $recentUsersResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $user['user_id']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <a href="users.php?action=view&id=<?php echo $user['user_id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="users.php?action=edit&id=<?php echo $user['user_id']; ?>" class="text-primary-600 hover:text-primary-900">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No recent users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Chart JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    
    <script>
        // Toggle mobile sidebar
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-sidebar').classList.remove('hidden');
            document.getElementById('mobile-sidebar-container').classList.remove('-translate-x-full');
        });
        
        document.getElementById('close-sidebar').addEventListener('click', function() {
            document.getElementById('mobile-sidebar-container').classList.add('-translate-x-full');
            setTimeout(function() {
                document.getElementById('mobile-sidebar').classList.add('hidden');
            }, 300);
        });
        
        // User dropdown toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            document.getElementById('user-dropdown').classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.closest('#user-menu-button')) {
                const dropdown = document.getElementById('user-dropdown');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
        
        // Charts
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Sales',
                    data: [1500, 2200, 1800, 2400, 2800, 3200, 3800, 3600, 4000, 4200, 4800, 5200],
                    backgroundColor: 'rgba(14, 165, 233, 0.2)',
                    borderColor: 'rgba(14, 165, 233, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(14, 165, 233, 1)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.05)',
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Categories Chart
        const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        const categoriesChart = new Chart(categoriesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Fiction', 'Non-Fiction', 'Science', 'History', 'Biography', 'Others'],
                datasets: [{
                    data: [35, 25, 15, 10, 10, 5],
                    backgroundColor: [
                        'rgba(14, 165, 233, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
                            padding: 15,
                        }
                    }
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>