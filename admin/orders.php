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

// Initialize variables for actions and messages
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update order status
    if (isset($_POST['update_status'])) {
        $order_id = (int)$_POST['order_id'];
        $status = $conn->real_escape_string($_POST['status']);
        
        $updateQuery = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("si", $status, $order_id);
        
        if ($stmt->execute()) {
            $message = "Order status updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating order status: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// Default query for orders
$orderQuery = "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address, o.payment_method, 
               u.username, u.email, u.first_name, u.last_name
               FROM orders o
               JOIN users u ON o.user_id = u.user_id";

// Add filters and search functionality
$whereClause = [];
$searchTerm = '';

if (isset($_GET['status']) && $_GET['status'] !== 'all') {
    $status = $conn->real_escape_string($_GET['status']);
    $whereClause[] = "o.status = '$status'";
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $whereClause[] = "(u.username LIKE '%$searchTerm%' OR u.email LIKE '%$searchTerm%' OR u.first_name LIKE '%$searchTerm%' OR u.last_name LIKE '%$searchTerm%' OR o.order_id LIKE '%$searchTerm%')";
}

if (!empty($whereClause)) {
    $orderQuery .= " WHERE " . implode(" AND ", $whereClause);
}

// Add sorting
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort field to prevent SQL injection
$validSortFields = ['order_id', 'order_date', 'total_amount', 'status', 'username'];
if (!in_array($sortField, $validSortFields)) {
    $sortField = 'order_date';
}

// Validate sort order
if ($sortOrder !== 'ASC' && $sortOrder !== 'DESC') {
    $sortOrder = 'DESC';
}

$orderQuery .= " ORDER BY $sortField $sortOrder";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Get total number of records for pagination
$countQuery = str_replace("SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address, o.payment_method, 
               u.username, u.email, u.first_name, u.last_name", "SELECT COUNT(*) as total", $orderQuery);
$countQuery = preg_replace('/ORDER BY.*$/', '', $countQuery);
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Add LIMIT clause for pagination
$orderQuery .= " LIMIT $offset, $recordsPerPage";

// Execute the query
$result = $conn->query($orderQuery);

// View specific order details
if ($action === 'view' && $id > 0) {
    // Fetch order details
    $orderDetailsQuery = "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address, o.payment_method, 
                         u.username, u.email, u.first_name, u.last_name
                         FROM orders o
                         JOIN users u ON o.user_id = u.user_id
                         WHERE o.order_id = $id";
    $orderDetailsResult = $conn->query($orderDetailsQuery);
    $orderDetails = $orderDetailsResult->fetch_assoc();
    
    // Fetch order items
    $orderItemsQuery = "SELECT oi.order_item_id, oi.quantity, oi.price_per_unit, 
                       b.title, b.author, b.isbn, b.image_url
                       FROM order_items oi
                       JOIN books b ON oi.book_id = b.book_id
                       WHERE oi.order_id = $id";
    $orderItemsResult = $conn->query($orderItemsQuery);
}

// Get unique statuses for filtering
$statusQuery = "SELECT DISTINCT status FROM orders";
$statusResult = $conn->query($statusQuery);
$statuses = [];
while ($row = $statusResult->fetch_assoc()) {
    $statuses[] = $row['status'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - BookStore Admin</title>
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
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        
        .status-badge i {
            margin-right: 0.25rem;
        }
        
        /* Status-specific styles */
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-processing {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .status-shipped {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .status-delivered {
            background-color: #d1fae5;
            color: #047857;
        }
        
        .status-cancelled {
            background-color: #fee2e2;
            color: #b91c1c;
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
                    <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
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
                    <a href="orders.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <form class="flex-1 relative" action="orders.php" method="GET">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </span>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search orders, customers...">
                                <input type="hidden" name="status" value="<?php echo isset($_GET['status']) ? htmlspecialchars($_GET['status']) : 'all'; ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortField); ?>">
                                <input type="hidden" name="order" value="<?php echo htmlspecialchars($sortOrder); ?>">
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
                                        <h5 class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h5>
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
                            <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
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
                            <a href="orders.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                
                <?php if (!empty($message)): ?>
                    <div class="mb-4 p-4 border-l-4 <?php echo $messageType === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'view' && isset($orderDetails)): ?>
                    <!-- Order Details View -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 font-display">Order #<?php echo $orderDetails['order_id']; ?></h1>
                            <p class="mt-1 text-sm text-gray-600">Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($orderDetails['order_date'])); ?></p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <a href="orders.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Orders
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Customer Information -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Customer Information</h3>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Name</p>
                                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($orderDetails['first_name'] . ' ' . $orderDetails['last_name']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Email</p>
                                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($orderDetails['email']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Username</p>
                                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($orderDetails['username']); ?></p>
                                </div>
                                <div class="pt-2">
                                    
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Summary</h3>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Order ID</p>
                                    <p class="text-base text-gray-900">#<?php echo $orderDetails['order_id']; ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Order Date</p>
                                    <p class="text-base text-gray-900"><?php echo date('F j, Y \a\t g:i A', strtotime($orderDetails['order_date'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Total Amount</p>
                                    <p class="text-base text-gray-900 font-semibold">$<?php echo number_format($orderDetails['total_amount'], 2); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Payment Method</p>
                                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($orderDetails['payment_method']); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Order Status -->
                        <div class="bg-white rounded-lg shadow-soft border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Status</h3>
                            
                            <div class="mb-4">
                                <?php
                                $statusClass = '';
                                switch($orderDetails['status']) {
                                    case 'pending':
                                        $statusClass = 'status-pending';
                                        $statusIcon = 'fa-clock';
                                        break;
                                    case 'processing':
                                        $statusClass = 'status-processing';
                                        $statusIcon = 'fa-spinner';
                                        break;
                                    case 'shipped':
                                        $statusClass = 'status-shipped';
                                        $statusIcon = 'fa-shipping-fast';
                                        break;
                                    case 'delivered':
                                        $statusClass = 'status-delivered';
                                        $statusIcon = 'fa-check-circle';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'status-cancelled';
                                        $statusIcon = 'fa-times-circle';
                                        break;
                                    default:
                                    $statusClass = '';
                                    $statusIcon = 'fa-question-circle';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <i class="fas <?php echo $statusIcon; ?>"></i>
                                <?php echo ucfirst($orderDetails['status']); ?>
                            </span>
                        </div>
                        
                        <!-- Status Update Form -->
                        <form action="orders.php?action=view&id=<?php echo $orderDetails['order_id']; ?>" method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $orderDetails['order_id']; ?>">
                            <div class="mb-4">
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Update Status</label>
                                <select name="status" id="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50">
                                    <option value="pending" <?php echo $orderDetails['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $orderDetails['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $orderDetails['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $orderDetails['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $orderDetails['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Update Status
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="bg-white rounded-lg shadow-soft border border-gray-200 p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Shipping Information</h3>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p><?php echo nl2br(htmlspecialchars($orderDetails['shipping_address'])); ?></p>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow-soft border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Items</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ISBN</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $totalItems = 0;
                                if ($orderItemsResult->num_rows > 0):
                                    while ($item = $orderItemsResult->fetch_assoc()):
                                        $totalItems += $item['quantity'];
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <img class="h-10 w-10 rounded-sm object-cover" src="<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : '../assets/images/book-placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item['author']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($item['isbn']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        $<?php echo number_format($item['price_per_unit'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $item['quantity']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        $<?php echo number_format($item['price_per_unit'] * $item['quantity'], 2); ?>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No items found for this order.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-900 text-right">Total:</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 font-medium"><?php echo $totalItems; ?> item(s)</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 font-medium">$<?php echo number_format($orderDetails['total_amount'], 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- Orders List View -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 font-display">Orders Management</h1>
                        <p class="mt-1 text-sm text-gray-600">View and manage customer orders</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-soft border border-gray-200 p-6 mb-6">
                    <div class="flex flex-col md:flex-row md:items-end md:justify-between space-y-4 md:space-y-0">
                        <!-- Status Filter -->
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="orders.php" class="inline-flex items-center px-3 py-1 rounded-full text-sm <?php echo !isset($_GET['status']) || $_GET['status'] === 'all' ? 'bg-primary-100 text-primary-800 font-medium' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'; ?>">
                                All Orders
                            </a>
                            <?php foreach($statuses as $status): ?>
                            <a href="orders.php?status=<?php echo $status; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['sort']) ? '&sort='.urlencode($_GET['sort']) : ''; ?><?php echo isset($_GET['order']) ? '&order='.urlencode($_GET['order']) : ''; ?>" class="inline-flex items-center px-3 py-1 rounded-full text-sm <?php echo isset($_GET['status']) && $_GET['status'] === $status ? 'bg-primary-100 text-primary-800 font-medium' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'; ?>">
                                <?php echo ucfirst($status); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Search Form (Mobile) -->
                        <div class="md:hidden">
                            <form action="orders.php" method="GET" class="flex">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="flex-1 rounded-l-lg border border-r-0 border-gray-300 py-2 px-4 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search orders...">
                                <button type="submit" class="bg-primary-600 text-white rounded-r-lg px-4 py-2">
                                    <i class="fas fa-search"></i>
                                </button>
                                <input type="hidden" name="status" value="<?php echo isset($_GET['status']) ? htmlspecialchars($_GET['status']) : 'all'; ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortField); ?>">
                                <input type="hidden" name="order" value="<?php echo htmlspecialchars($sortOrder); ?>">
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="bg-white rounded-lg shadow-soft border border-gray-200 overflow-hidden">
                    <?php if ($result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="orders.php?sort=order_id&order=<?php echo $sortField === 'order_id' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo isset($_GET['status']) ? '&status='.urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="flex items-center hover:text-gray-700">
                                            Order ID
                                            <?php if ($sortField === 'order_id'): ?>
                                                <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ml-1 text-gray-400"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="orders.php?sort=order_date&order=<?php echo $sortField === 'order_date' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo isset($_GET['status']) ? '&status='.urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="flex items-center hover:text-gray-700">
                                            Date
                                            <?php if ($sortField === 'order_date'): ?>
                                                <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ml-1 text-gray-400"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="orders.php?sort=username&order=<?php echo $sortField === 'username' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo isset($_GET['status']) ? '&status='.urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="flex items-center hover:text-gray-700">
                                            Customer
                                            <?php if ($sortField === 'username'): ?>
                                                <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ml-1 text-gray-400"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="orders.php?sort=total_amount&order=<?php echo $sortField === 'total_amount' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo isset($_GET['status']) ? '&status='.urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="flex items-center hover:text-gray-700">
                                            Total
                                            <?php if ($sortField === 'total_amount'): ?>
                                                <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ml-1 text-gray-400"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="orders.php?sort=status&order=<?php echo $sortField === 'status' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo isset($_GET['status']) ? '&status='.urlencode($_GET['status']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="flex items-center hover:text-gray-700">
                                            Status
                                            <?php if ($sortField === 'status'): ?>
                                                <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort ml-1 text-gray-400"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">#<?php echo $row['order_id']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($row['order_date'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($row['order_date'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">$<?php echo number_format($row['total_amount'], 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = '';
                                        $statusIcon = '';
                                        switch($row['status']) {
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                $statusIcon = 'fa-clock';
                                                break;
                                            case 'processing':
                                                $statusClass = 'status-processing';
                                                $statusIcon = 'fa-spinner';
                                                break;
                                            case 'shipped':
                                                $statusClass = 'status-shipped';
                                                $statusIcon = 'fa-shipping-fast';
                                                break;
                                            case 'delivered':
                                                $statusClass = 'status-delivered';
                                                $statusIcon = 'fa-check-circle';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'status-cancelled';
                                                $statusIcon = 'fa-times-circle';
                                                break;
                                            default:
                                                $statusClass = '';
                                                $statusIcon = 'fa-question-circle';
                                                break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?>"></i>
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="orders.php?action=view&id=<?php echo $row['order_id']; ?>" class="text-primary-600 hover:text-primary-900 mr-4">View</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 bg-white border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo ($page - 1) * $recordsPerPage + 1; ?></span> to 
                                <span class="font-medium"><?php echo min($page * $recordsPerPage, $totalRecords); ?></span> of 
                                <span class="font-medium"><?php echo $totalRecords; ?></span> orders
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php if ($page > 1): ?>
                                <a href="orders.php?page=<?php echo $page - 1; ?>&status=<?php echo isset($_GET['status']) ? urlencode($_GET['status']) : 'all'; ?>&search=<?php echo urlencode($searchTerm); ?>&sort=<?php echo urlencode($sortField); ?>&order=<?php echo urlencode($sortOrder); ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left mr-2 text-xs"></i> Previous
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="orders.php?page=<?php echo $page + 1; ?>&status=<?php echo isset($_GET['status']) ? urlencode($_GET['status']) : 'all'; ?>&search=<?php echo urlencode($searchTerm); ?>&sort=<?php echo urlencode($sortField); ?>&order=<?php echo urlencode($sortOrder); ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Next <i class="fas fa-chevron-right ml-2 text-xs"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="text-center py-12 px-6">
                        <i class="fas fa-shopping-cart text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">No orders found</h3>
                        <p class="text-gray-500">
                            <?php if (!empty($searchTerm)): ?>
                                No orders match your search criteria. <a href="orders.php" class="text-primary-600 hover:text-primary-800">Clear filters</a>
                            <?php else: ?>
                                There are no orders in the system yet.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
    // Toggle mobile sidebar
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const mobileSidebarContainer = document.getElementById('mobile-sidebar-container');
    const closeSidebarButton = document.getElementById('close-sidebar');
    
    mobileMenuButton.addEventListener('click', () => {
        mobileSidebar.classList.remove('hidden');
        setTimeout(() => {
            mobileSidebarContainer.classList.remove('-translate-x-full');
        }, 10);
    });
    
    closeSidebarButton.addEventListener('click', () => {
        mobileSidebarContainer.classList.add('-translate-x-full');
        setTimeout(() => {
            mobileSidebar.classList.add('hidden');
        }, 300);
    });
    
    // Close sidebar when clicking outside
    mobileSidebar.addEventListener('click', (e) => {
        if (!mobileSidebarContainer.contains(e.target)) {
            mobileSidebarContainer.classList.add('-translate-x-full');
            setTimeout(() => {
                mobileSidebar.classList.add('hidden');
            }, 300);
        }
    });
    
    // User dropdown toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');
    
    userMenuButton.addEventListener('click', () => {
        userDropdown.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.classList.remove('show');
        }
    });
</script>
</body>
</html>