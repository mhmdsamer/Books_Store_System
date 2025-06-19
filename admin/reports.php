<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../connection.php';

// Default time period (last 30 days)
$period = isset($_GET['period']) ? $_GET['period'] : '30days';

// Set start and end dates based on period
$end_date = date('Y-m-d');
$start_date = '';

switch ($period) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_text = 'Last 7 Days';
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_text = 'Last 30 Days';
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $period_text = 'Last 90 Days';
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $period_text = 'Last Year';
        break;
    case 'all':
        $start_date = '2000-01-01'; // Far in the past to get all records
        $period_text = 'All Time';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_text = 'Last 30 Days';
}

// Get date range from custom filter if provided
if (isset($_GET['custom']) && $_GET['custom'] == '1' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $period_text = 'Custom: ' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
    $period = 'custom';
}

// Initialize data arrays
$summary = [
    'total_sales' => 0,
    'total_orders' => 0,
    'avg_order_value' => 0,
    'books_sold' => 0,
    'new_customers' => 0,
    'total_borrowings' => 0,
    'active_borrowings' => 0,
    'overdue_borrowings' => 0
];

// Get summary data
// Total sales
$query = "SELECT COUNT(*) as total_orders, SUM(total_amount) as total_sales 
          FROM orders 
          WHERE order_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' 
          AND status != 'cancelled'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $summary['total_sales'] = $row['total_sales'] ?? 0;
    $summary['total_orders'] = $row['total_orders'] ?? 0;
    $summary['avg_order_value'] = $summary['total_orders'] > 0 ? 
                                 $summary['total_sales'] / $summary['total_orders'] : 0;
}

// Books sold
$query = "SELECT SUM(oi.quantity) as books_sold 
          FROM order_items oi 
          JOIN orders o ON oi.order_id = o.order_id 
          WHERE o.order_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' 
          AND o.status != 'cancelled'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $summary['books_sold'] = $row['books_sold'] ?? 0;
}

// New customers
$query = "SELECT COUNT(*) as new_customers 
          FROM users 
          WHERE role = 'customer' 
          AND created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $summary['new_customers'] = $row['new_customers'] ?? 0;
}

// Borrowings
$query = "SELECT 
            COUNT(*) as total_borrowings,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_borrowings,
            SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_borrowings
          FROM borrowings
          WHERE borrow_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $summary['total_borrowings'] = $row['total_borrowings'] ?? 0;
    $summary['active_borrowings'] = $row['active_borrowings'] ?? 0;
    $summary['overdue_borrowings'] = $row['overdue_borrowings'] ?? 0;
}

// Get daily sales data for the chart
$daily_sales = [];
$query = "SELECT 
            DATE(order_date) as date,
            SUM(total_amount) as total
          FROM orders 
          WHERE order_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
          AND status != 'cancelled'
          GROUP BY DATE(order_date)
          ORDER BY date";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $daily_sales[] = [
            'date' => $row['date'],
            'total' => (float)$row['total']
        ];
    }
}

// Get top selling books
$top_books = [];
$query = "SELECT 
            b.book_id,
            b.title,
            b.author,
            SUM(oi.quantity) as quantity_sold,
            SUM(oi.quantity * oi.price_per_unit) as total_revenue
          FROM order_items oi
          JOIN orders o ON oi.order_id = o.order_id
          JOIN books b ON oi.book_id = b.book_id
          WHERE o.order_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
          AND o.status != 'cancelled'
          GROUP BY b.book_id
          ORDER BY quantity_sold DESC
          LIMIT 10";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $top_books[] = $row;
    }
}

// Get category performance
$category_data = [];
$query = "SELECT 
            c.category_id,
            c.category_name,
            COUNT(DISTINCT o.order_id) as order_count,
            SUM(oi.quantity) as quantity_sold,
            SUM(oi.quantity * oi.price_per_unit) as total_revenue
          FROM categories c
          JOIN book_categories bc ON c.category_id = bc.category_id
          JOIN books b ON bc.book_id = b.book_id
          JOIN order_items oi ON b.book_id = oi.book_id
          JOIN orders o ON oi.order_id = o.order_id
          WHERE o.order_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
          AND o.status != 'cancelled'
          GROUP BY c.category_id
          ORDER BY total_revenue DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $category_data[] = $row;
    }
}

// Get user purchasing stats
$user_stats = [];
$query = "SELECT 
            u.user_id,
            u.username,
            u.full_name,
            COUNT(DISTINCT o.order_id) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.order_date) as last_order_date
          FROM users u
          JOIN orders o ON u.user_id = o.user_id
          WHERE o.order_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
          AND o.status != 'cancelled'
          GROUP BY u.user_id
          ORDER BY total_spent DESC
          LIMIT 10";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $user_stats[] = $row;
    }
}

// Get inventory status
$inventory_stats = [];
$query = "SELECT 
            COUNT(*) as total_books,
            SUM(CASE WHEN quantity_available = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN quantity_available < 5 AND quantity_available > 0 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN quantity_available >= 5 THEN 1 ELSE 0 END) as sufficient_stock
          FROM books
          WHERE can_be_bought = 1";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $inventory_stats = $row;
}

// Close database connection
$conn->close();

// Format data for charts (JSON)
$daily_sales_json = json_encode($daily_sales);
$category_labels = array_column($category_data, 'category_name');
$category_revenue = array_column($category_data, 'total_revenue');
$inventory_labels = ['Out of Stock', 'Low Stock', 'Sufficient Stock'];
$inventory_data = [
    $inventory_stats['out_of_stock'] ?? 0,
    $inventory_stats['low_stock'] ?? 0,
    $inventory_stats['sufficient_stock'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - BookStore</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <!-- DatePicker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
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
                },
            },
        }
    </script>
    
    <style>
        .dashboard-card {
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-item {
            transition: all 0.2s ease;
        }
        
        .sidebar-item:hover {
            background-color: rgba(14, 165, 233, 0.1);
        }
        
        .sidebar-item.active {
            background-color: #0ea5e9;
            color: white;
        }
        
        .sidebar-item.active:hover {
            background-color: #0284c7;
        }
        
        /* Mobile sidebar animation */
        .sidebar-mobile {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar-mobile.active {
            transform: translateX(0);
        }
        
        .overlay {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive table adjustments for small screens */
        @media (max-width: 640px) {
            .responsive-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .responsive-table {
                min-width: 500px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex flex-col lg:flex-row min-h-screen">
        <!-- Mobile Menu Button -->
        <div class="block lg:hidden fixed top-0 left-0 z-20 m-4">
            <button id="mobileMenuBtn" class="p-2 bg-primary-600 rounded-md text-white shadow-md">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 overlay lg:hidden"></div>
        
        <!-- Sidebar (Mobile & Desktop) -->
        <div id="sidebar" class="sidebar-mobile lg:sidebar-desktop fixed lg:relative z-30 w-64 bg-white shadow-md flex flex-col h-screen lg:transform-none">
            <div class="p-4 bg-primary-600 flex items-center space-x-3">
                <i class="fas fa-book-open text-2xl text-white"></i>
                <h1 class="text-xl font-bold text-white font-display">BookStore Admin</h1>
                <!-- Close button for mobile -->
                <button id="closeSidebarBtn" class="ml-auto text-white lg:hidden">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Admin Profile -->
            <div class="p-4 border-b flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-primary-200 flex items-center justify-center">
                    <i class="fas fa-user text-primary-600"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?></p>
                    <p class="text-xs text-gray-500">Administrator</p>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg">
                    <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="books.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg">
                    <i class="fas fa-book w-5 h-5 mr-3"></i>
                    <span>Books</span>
                </a>
                
                <a href="users.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg">
                    <i class="fas fa-users w-5 h-5 mr-3"></i>
                    <span>Users</span>
                </a>
                
                <a href="orders.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg">
                    <i class="fas fa-shopping-cart w-5 h-5 mr-3"></i>
                    <span>Orders</span>
                </a>
                
                <a href="borrowings.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg">
                    <i class="fas fa-exchange-alt w-5 h-5 mr-3"></i>
                    <span>Borrowings</span>
                </a>
                
                <a href="categories.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg">
                    <i class="fas fa-tags w-5 h-5 mr-3"></i>
                    <span>Categories</span>
                </a>
                
                <a href="reports.php" class="sidebar-item active flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                    <i class="fas fa-chart-line w-5 h-5 mr-3"></i>
                    <span>Reports</span>
                </a>
                
                <a href="settings.php" class="sidebar-item flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg">
                    <i class="fas fa-cog w-5 h-5 mr-3"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <!-- Logout Section -->
            <div class="p-4 border-t">
                <a href="../logout.php" class="flex items-center px-4 py-3 text-sm font-medium text-gray-700 rounded-lg hover:bg-red-50 hover:text-red-700 transition-colors">
                    <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col lg:ml-0 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10 sticky top-0">
                <div class="flex items-center justify-between px-4 md:px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-xl font-semibold text-gray-800">Reports</h2>
                    </div>
                    
                    <div class="flex items-center space-x-2 md:space-x-4">
                        <a href="../index.php" class="text-xs md:text-sm text-primary-600 hover:underline">
                            <i class="fas fa-external-link-alt mr-1"></i>
                            <span class="hidden sm:inline">View Store</span>
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Main Reports Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 md:p-6 pt-4 mt-2 lg:mt-0">
                <div class="mb-6 md:mb-8">
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 font-display">Sales & Performance Reports</h1>
                    <p class="text-sm md:text-base text-gray-600">Analyze your bookstore's performance metrics and trends.</p>
                </div>
                
                <!-- Date Range Filter -->
                <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0">
                        <h3 class="text-base md:text-lg font-medium text-gray-800">Filter by Time Period</h3>
                        
                        <div class="flex flex-wrap gap-2">
                            <a href="?period=7days" class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $period == '7days' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                Last 7 Days
                            </a>
                            <a href="?period=30days" class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $period == '30days' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                Last 30 Days
                            </a>
                            <a href="?period=90days" class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $period == '90days' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                Last 90 Days
                            </a>
                            <a href="?period=year" class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $period == 'year' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                Last Year
                            </a>
                            <a href="?period=all" class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $period == 'all' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                All Time
                            </a>
                        </div>
                    </div>
                    
                    <!-- Custom Date Range -->
                    <div class="mt-6 border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-800 mb-3">Custom Date Range</h4>
                        <form action="" method="GET" class="flex flex-col sm:flex-row sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                            <div class="flex-1">
                                <label for="start_date" class="block text-xs text-gray-500 mb-1">Start Date</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo $period == 'custom' ? $start_date : ''; ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            </div>
                            <div class="flex-1">
                                <label for="end_date" class="block text-xs text-gray-500 mb-1">End Date</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo $period == 'custom' ? $end_date : ''; ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            </div>
                            <input type="hidden" name="custom" value="1">
                            <div class="flex items-end">
                                <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                                    Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="mt-4 text-sm">
                        <p class="text-gray-600">Currently showing data for: <span class="font-medium text-gray-800"><?php echo $period_text; ?></span></p>
                    </div>
                </div>
                
                <!-- Summary Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Total Sales -->
                    <div class="dashboard-card bg-white rounded-xl shadow-sm p-4 md:p-5 border border-gray-100">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-gray-500 text-xs md:text-sm font-medium">Total Sales</h3>
                            <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-green-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl md:text-3xl font-bold text-gray-800">$<?php echo number_format($summary['total_sales'], 2); ?></p>
                        <p class="text-xs text-gray-500 mt-1">From <?php echo $summary['total_orders']; ?> orders</p>
                    </div>
                    
                    <!-- Average Order Value -->
                    <div class="dashboard-card bg-white rounded-xl shadow-sm p-4 md:p-5 border border-gray-100">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-gray-500 text-xs md:text-sm font-medium">Avg. Order Value</h3>
                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-chart-line text-blue-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl md:text-3xl font-bold text-gray-800">$<?php echo number_format($summary['avg_order_value'], 2); ?></p>
                    </div>
                    
                    <!-- Books Sold -->
                    <div class="dashboard-card bg-white rounded-xl shadow-sm p-4 md:p-5 border border-gray-100">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-gray-500 text-xs md:text-sm font-medium">Books Sold</h3>
                            <div class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-book text-purple-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo number_format($summary['books_sold']); ?></p>
                    </div>
                    
                    <!-- New Customers -->
                    <div class="dashboard-card bg-white rounded-xl shadow-sm p-4 md:p-5 border border-gray-100">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-gray-500 text-xs md:text-sm font-medium">New Customers</h3>
                            <div class="h-8 w-8 rounded-full bg-yellow-100 flex items-center justify-center">
                                <i class="fas fa-user-plus text-yellow-600"></i>
                            </div>
                        </div>
                        <p class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo number_format($summary['new_customers']); ?></p>
                    </div>
                </div>
                
                <!-- Sales Chart -->
                <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 mb-6">
                    <h3 class="text-base md:text-lg font-medium text-gray-800 mb-4">Sales Trend</h3>
                    <div class="h-60 md:h-80">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
                
                <!-- Two Column Layout for Analysis -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Selling Books -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
                        <h3 class="text-base md:text-lg font-medium text-gray-800 mb-4">Top Selling Books</h3>
                        
                        <?php if (empty($top_books)): ?>
                        <div class="py-4 text-center">
                            <p class="text-gray-500 text-sm">No sales data available for this period.</p>
                        </div>
                        <?php else: ?>
                        <div class="responsive-table-container">
                            <table class="w-full responsive-table">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="pb-2 text-left text-xs font-medium text-gray-500">Book Title</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Sold</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_books as $book): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 text-sm">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($book['title']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($book['author']); ?></div>
                                        </td>
                                        <td class="py-3 text-right text-sm font-medium text-gray-900"><?php echo number_format($book['quantity_sold']); ?></td>
                                        <td class="py-3 text-right text-sm font-medium text-gray-900">$<?php echo number_format($book['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Category Performance -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
                        <h3 class="text-base md:text-lg font-medium text-gray-800 mb-4">Category Performance</h3>
                        
                        <?php if (empty($category_data)): ?>
                        <div class="py-4 text-center">
                            <p class="text-gray-500 text-sm">No category data available for this period.</p>
                        </div>
                        <?php else: ?>
                        <div class="h-60 md:h-72">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="mt-4 responsive-table-container max-h-64 overflow-y-auto">
                            <table class="w-full responsive-table">
                                <thead class="sticky top-0 bg-white">
                                    <tr class="border-b border-gray-200">
                                        <th class="pb-2 text-left text-xs font-medium text-gray-500">Category</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Orders</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Sold</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_data as $category): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-2 text-sm text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></td>
                                        <td class="py-2 text-right text-sm text-gray-700"><?php echo number_format($category['order_count']); ?></td>
                                        <td class="py-2 text-right text-sm text-gray-700"><?php echo number_format($category['quantity_sold']); ?></td>
                                        <td class="py-2 text-right text-sm font-medium text-gray-900">$<?php echo number_format($category['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Second Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Customers -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
                        <h3 class="text-base md:text-lg font-medium text-gray-800 mb-4">Top Customers</h3>
                        
                        <?php if (empty($user_stats)): ?>
                        <div class="py-4 text-center">
                            <p class="text-gray-500 text-sm">No customer data available for this period.</p>
                        </div>
                        <?php else: ?>
                        <div class="responsive-table-container">
                            <table class="w-full responsive-table">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="pb-2 text-left text-xs font-medium text-gray-500">Customer</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Orders</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Total Spent</th>
                                        <th class="pb-2 text-right text-xs font-medium text-gray-500">Last Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_stats as $user): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 text-sm">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['username']); ?></div>
                                        </td>
                                        <td class="py-3 text-right text-sm text-gray-700"><?php echo number_format($user['order_count']); ?></td>
                                        <td class="py-3 text-right text-sm font-medium text-gray-900">$<?php echo number_format($user['total_spent'], 2); ?></td>
                                        <td class="py-3 text-right text-xs text-gray-500"><?php echo date('M j, Y', strtotime($user['last_order_date'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Inventory Status -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
                        <h3 class="text-base md:text-lg font-medium text-gray-800 mb-4">Inventory Status</h3>
                        
                        <?php if (empty($inventory_stats) || $inventory_stats['total_books'] == 0): ?>
                        <div class="py-4 text-center">
                            <p class="text-gray-500 text-sm">No inventory data available.</p>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="p-3 bg-red-50 rounded-lg text-center">
                                <p class="text-xs text-red-600 font-medium">Out of Stock</p>
                                <p class="text-2xl font-bold text-red-700 mt-1"><?php echo number_format($inventory_stats['out_of_stock']); ?></p>
                                <p class="text-xs text-red-500 mt-1"><?php echo number_format(($inventory_stats['out_of_stock'] / $inventory_stats['total_books']) * 100, 1); ?>%</p>
                            </div>
                            <div class="p-3 bg-yellow-50 rounded-lg text-center">
                                <p class="text-xs text-yellow-600 font-medium">Low Stock</p>
                                <p class="text-2xl font-bold text-yellow-700 mt-1"><?php echo number_format($inventory_stats['low_stock']); ?></p>
                                <p class="text-xs text-yellow-500 mt-1"><?php echo number_format(($inventory_stats['low_stock'] / $inventory_stats['total_books']) * 100, 1); ?>%</p>
                            </div>
                            <div class="p-3 bg-green-50 rounded-lg text-center">
                                <p class="text-xs text-green-600 font-medium">Sufficient</p>
                                <p class="text-2xl font-bold text-green-700 mt-1"><?php echo number_format($inventory_stats['sufficient_stock']); ?></p>
                                <p class="text-xs text-green-500 mt-1"><?php echo number_format(($inventory_stats['sufficient_stock'] / $inventory_stats['total_books']) * 100, 1); ?>%</p>
                            </div>
                        </div>
                        <div class="h-48 md:h-64">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Borrowing Statistics -->
                <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 mb-6">
                    <h3 class="text-base md:text-lg font-medium text-gray-800 mb-4">Borrowing Statistics</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Total Borrowings -->
                        <div class="p-4 border border-gray-100 rounded-lg bg-blue-50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-medium text-blue-700">Total Borrowings</h4>
                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-book-reader text-blue-600"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-blue-800"><?php echo number_format($summary['total_borrowings']); ?></p>
                        </div>
                        
                        <!-- Active Borrowings -->
                        <div class="p-4 border border-gray-100 rounded-lg bg-green-50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-medium text-green-700">Active Borrowings</h4>
                                <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-clock text-green-600"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-green-800"><?php echo number_format($summary['active_borrowings']); ?></p>
                        </div>
                        
                        <!-- Overdue Borrowings -->
                        <div class="p-4 border border-gray-100 rounded-lg bg-red-50">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-medium text-red-700">Overdue Borrowings</h4>
                                <div class="h-8 w-8 rounded-full bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-exclamation-circle text-red-600"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-red-800"><?php echo number_format($summary['overdue_borrowings']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Export Options -->
                <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 mb-6">
                    <h3 class="text-base md:text-lg font-medium text-gray-800 mb-4">Export Reports</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <a href="export.php?type=sales&period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex items-center justify-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-file-csv text-primary-600 mr-2"></i>
                            <span class="text-sm font-medium text-gray-700">Export Sales (CSV)</span>
                        </a>
                        
                        <a href="export.php?type=inventory&period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex items-center justify-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-file-excel text-primary-600 mr-2"></i>
                            <span class="text-sm font-medium text-gray-700">Export Inventory (Excel)</span>
                        </a>
                        
                        <a href="export.php?type=customers&period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex items-center justify-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-file-pdf text-primary-600 mr-2"></i>
                            <span class="text-sm font-medium text-gray-700">Export Customers (PDF)</span>
                        </a>
                    </div>
                </div>
            </main>
            
            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 py-4 px-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-gray-600">
                        &copy; <?php echo date('Y'); ?> BookStore Admin Panel. All rights reserved.
                    </div>
                    <div class="mt-2 md:mt-0 text-sm text-gray-500">
                        Version 1.2.0
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- JavaScript for Charts -->
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        });
        
        document.getElementById('closeSidebarBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        });
        
        document.getElementById('overlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        });
        
        // Initialize date pickers
        flatpickr('#start_date', {
            dateFormat: 'Y-m-d',
            maxDate: new Date()
        });
        
        flatpickr('#end_date', {
            dateFormat: 'Y-m-d',
            maxDate: new Date()
        });
        
        // Sales Trend Chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendData = <?php echo $daily_sales_json; ?>;
        const salesDates = salesTrendData.map(item => item.date);
        const salesValues = salesTrendData.map(item => item.total);
        
        new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: salesDates,
                datasets: [{
                    label: 'Daily Sales ($)',
                    data: salesValues,
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    borderColor: 'rgba(14, 165, 233, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: 'rgba(14, 165, 233, 1)',
                    pointBorderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxTicksLimit: 10
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        bodyFont: {
                            size: 13
                        },
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                return 'Sales: $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            const categoryLabels = <?php echo json_encode($category_labels); ?>;
            const categoryRevenue = <?php echo json_encode($category_revenue); ?>;
            
            new Chart(categoryCtx, {
                type: 'bar',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: categoryRevenue,
                        backgroundColor: [
                            'rgba(14, 165, 233, 0.7)',
                            'rgba(236, 72, 153, 0.7)',
                            'rgba(139, 92, 246, 0.7)',
                            'rgba(16, 185, 129, 0.7)',
                            'rgba(245, 158, 11, 0.7)',
                            'rgba(239, 68, 68, 0.7)',
                            'rgba(37, 99, 235, 0.7)',
                            'rgba(217, 70, 239, 0.7)',
                            'rgba(5, 150, 105, 0.7)',
                            'rgba(234, 88, 12, 0.7)'
                        ],
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            bodyFont: {
                                size: 13
                            },
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: $' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Inventory Chart
        const inventoryCtx = document.getElementById('inventoryChart');
        if (inventoryCtx) {
            const inventoryLabels = <?php echo json_encode($inventory_labels); ?>;
            const inventoryData = <?php echo json_encode($inventory_data); ?>;
            
            new Chart(inventoryCtx, {
                type: 'doughnut',
                data: {
                    labels: inventoryLabels,
                    datasets: [{
                        data: inventoryData,
                        backgroundColor: [
                            'rgba(239, 68, 68, 0.7)',    // Red for Out of Stock
                            'rgba(245, 158, 11, 0.7)',   // Yellow for Low Stock
                            'rgba(16, 185, 129, 0.7)'    // Green for Sufficient Stock
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            bodyFont: {
                                size: 13
                            },
                            padding: 10
                        }
                    },
                    cutout: '60%'
                }
            });
        }
    </script>
</body>
</html>