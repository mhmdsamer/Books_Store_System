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

// Initialize message variable
$message = '';
$messageType = '';

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'return' && isset($_GET['id'])) {
        $borrowingId = $_GET['id'];
        
        // Mark the borrowing as returned
        $returnDate = date('Y-m-d H:i:s');
        $returnQuery = "UPDATE borrowings SET status = 'returned', actual_return_date = ? WHERE borrowing_id = ?";
        $stmt = $conn->prepare($returnQuery);
        $stmt->bind_param('si', $returnDate, $borrowingId);
        
        if ($stmt->execute()) {
            // Get book_id to update stock
            $bookQuery = "SELECT book_id FROM borrowings WHERE borrowing_id = ?";
            $bookStmt = $conn->prepare($bookQuery);
            $bookStmt->bind_param('i', $borrowingId);
            $bookStmt->execute();
            $result = $bookStmt->get_result();
            $book = $result->fetch_assoc();
            
            // Update the book stock
            if ($book) {
                $updateStockQuery = "UPDATE books SET stock_quantity = stock_quantity + 1 WHERE book_id = ?";
                $stockStmt = $conn->prepare($updateStockQuery);
                $stockStmt->bind_param('i', $book['book_id']);
                $stockStmt->execute();
            }
            
            $message = "Borrowing #$borrowingId has been marked as returned successfully.";
            $messageType = "success";
        } else {
            $message = "Error updating borrowing status: " . $conn->error;
            $messageType = "error";
        }
    } elseif ($action == 'lost' && isset($_GET['id'])) {
        $borrowingId = $_GET['id'];
        
        // Mark the borrowing as lost
        $updateQuery = "UPDATE borrowings SET status = 'lost' WHERE borrowing_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('i', $borrowingId);
        
        if ($stmt->execute()) {
            $message = "Borrowing #$borrowingId has been marked as lost.";
            $messageType = "success";
        } else {
            $message = "Error updating borrowing status: " . $conn->error;
            $messageType = "error";
        }
    } elseif ($action == 'extend' && isset($_GET['id']) && isset($_GET['days'])) {
        $borrowingId = $_GET['id'];
        $days = intval($_GET['days']);
        
        if ($days > 0) {
            // Extend the expected return date
            $updateQuery = "UPDATE borrowings 
                          SET expected_return_date = DATE_ADD(expected_return_date, INTERVAL ? DAY) 
                          WHERE borrowing_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('ii', $days, $borrowingId);
            
            if ($stmt->execute()) {
                $message = "Borrowing period for #$borrowingId has been extended by $days days.";
                $messageType = "success";
            } else {
                $message = "Error extending borrowing period: " . $conn->error;
                $messageType = "error";
            }
        }
    }
}

// Pagination setup
$recordsPerPage = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Filter setup
$status = isset($_GET['status']) ? $_GET['status'] : '';
$orderBy = isset($_GET['order']) ? $_GET['order'] : 'borrow_date';
$orderDirection = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query with filters
$whereClause = "";
$params = [];
$types = "";

if ($status) {
    $whereClause .= " WHERE b.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($search) {
    $whereClause = $whereClause ? "$whereClause AND " : " WHERE ";
    $whereClause .= "(u.username LIKE ? OR u.email LIKE ? OR bk.title LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Valid order by columns
$validOrderColumns = ['borrowing_id', 'username', 'title', 'borrow_date', 'expected_return_date', 'status', 'daily_rate', 'total_fee'];
$orderBy = in_array($orderBy, $validOrderColumns) ? $orderBy : 'borrow_date';

// Valid order directions
$orderDirection = $orderDirection === 'ASC' ? 'ASC' : 'DESC';

// Query to count total records
$countQuery = "SELECT COUNT(*) as total FROM borrowings b 
               JOIN users u ON b.user_id = u.user_id 
               JOIN books bk ON b.book_id = bk.book_id
               $whereClause";

$countStmt = $conn->prepare($countQuery);
if (!empty($types)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Query to fetch borrowings with pagination
$query = "SELECT b.borrowing_id, b.borrow_date, b.expected_return_date, b.actual_return_date, 
          b.status, b.daily_rate, b.total_fee, u.user_id, u.username, u.email,
          bk.book_id, bk.title, bk.author
          FROM borrowings b
          JOIN users u ON b.user_id = u.user_id
          JOIN books bk ON b.book_id = bk.book_id
          $whereClause
          ORDER BY $orderBy $orderDirection
          LIMIT ?, ?";

// Add pagination parameters
$params[] = $offset;
$params[] = $recordsPerPage;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Count overdue borrowings
$overdueQuery = "SELECT COUNT(*) as overdue_count FROM borrowings 
                WHERE status = 'active' AND expected_return_date < NOW()";
$overdueResult = $conn->query($overdueQuery);
$overdueCount = $overdueResult->fetch_assoc()['overdue_count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Borrowings - BookStore Admin</title>
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
                    <a href="orders.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="borrowings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <form class="flex-1 relative" action="borrowings.php" method="GET">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </span>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search by user or book...">
                                <?php if ($status): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                                <?php endif; ?>
                                <button type="submit" class="hidden">Search</button>
                            </form>
                        </div>
                        
                        <!-- Right Navigation -->
                        <div class="flex items-center space-x-4">
                            <!-- Notifications -->
                            <div class="relative">
                                <button class="p-1 text-gray-500 rounded-full hover:bg-gray-100 focus:outline-none relative">
                                    <i class="fas fa-bell text-lg"></i>
                                    <?php if ($overdueCount > 0): ?>
                                    <span class="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full"></span>
                                    <?php endif; ?>
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
                            <a href="orders.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-shopping-cart"></i>
                                <span>Orders</span>
                            </a>
                            <a href="borrowings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 font-display">Manage Borrowings</h1>
                            <p class="mt-1 text-sm text-gray-600">View and manage all book borrowings</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <?php if ($overdueCount > 0): ?>
                            <a href="borrowings.php?status=active&order=expected_return_date&dir=ASC" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $overdueCount; ?> Overdue
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Alert Message -->
                    <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?> flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3"></i>
                            <span><?php echo $message; ?></span>
                        </div>
                        <button class="text-sm font-medium focus:outline-none" onclick="this.parentElement.style.display='none'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Filter Controls -->
                    <div class="bg-white rounded-lg shadow-soft p-4 mb-6">
                        <div class="flex flex-wrap items-center justify-between">
                            <div class="flex flex-wrap items-center space-x-2">
                                <a href="borrowings.php" class="px-3 py-2 text-sm font-medium rounded-md <?php echo !$status ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    All
                                </a>
                                <a href="borrowings.php?status=active" class="px-3 py-2 text-sm font-medium rounded-md <?php echo $status === 'active' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    Active
                                </a>
                                <a href="borrowings.php?status=returned" class="px-3 py-2 text-sm font-medium rounded-md <?php echo $status === 'returned' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    Returned
                                </a>
                                <a href="borrowings.php?status=overdue" class="px-3 py-2 text-sm font-medium rounded-md <?php echo $status === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    Overdue
                                </a>
                                <a href="borrowings.php?status=lost" class="px-3 py-2 text-sm font-medium rounded-md <?php echo $status === 'lost' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    Lost
                                </a>
                            </div>
                            <div class="mt-3 md:mt-0 flex items-center space-x-2">
                                <span class="text-sm text-gray-500">Sort by:</span>
                                <select id="sort-by" class="text-sm border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="borrow_date" <?php echo $orderBy === 'borrow_date' ? 'selected' : ''; ?>>Borrow Date</option>
                                    <option value="expected_return_date" <?php echo $orderBy === 'expected_return_date' ? 'selected' : ''; ?>>Return Date</option>
                                    <option value="status" <?php echo $orderBy === 'status' ? 'selected' : ''; ?>>Status</option>
                                    <option value="daily_rate" <?php echo $orderBy === 'daily_rate' ? 'selected' : ''; ?>>Daily Rate</option>
                                    <option value="total_fee" <?php echo $orderBy === 'total_fee' ? 'selected' : ''; ?>>Total Fee</option>
                                </select>
                                <button id="sort-direction" class="p-2 border border-gray-300 rounded-md focus:outline-none">
                                    <i class="fas fa-sort-<?php echo $orderDirection === 'ASC' ? 'up' : 'down'; ?>"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Borrowings Table -->
                    <div class="bg-white rounded-lg shadow-soft overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrow Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Rate</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Fee</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <?php 
                                            // Determine status display
                                            $statusClass = "";
                                            $statusText = $row['status'];
                                            
                                            switch ($row['status']) {
                                                case 'active':
                                                    // Check if overdue
                                                    $expected = new DateTime($row['expected_return_date']);
                                                    $now = new DateTime();
                                                    if ($expected < $now) {
                                                        $statusClass = "bg-red-100 text-red-800";
                                                        $statusText = "Overdue";
                                                    } else {
                                                        $statusClass = "bg-blue-100 text-blue-800";
                                                    }
                                                    break;
                                                case 'returned':
                                                    $statusClass = "bg-green-100 text-green-800";
                                                    break;
                                                case 'lost':
                                                    $statusClass = "bg-yellow-100 text-yellow-800";
                                                    break;
                                                default:
                                                    $statusClass = "bg-gray-100 text-gray-800";
                                            }
                                            ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    #<?php echo $row['borrowing_id']; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex items-center">
                                                        <div class="h-8 w-8 rounded-full bg-primary-200 flex items-center justify-center">
                                                            <span class="font-medium text-primary-700"><?php echo substr($row['username'], 0, 1); ?></span>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['username']); ?></div>
                                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['email']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($row['title']); ?></div>
                                                    <div class="text-xs text-gray-500">by <?php echo htmlspecialchars($row['author']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($row['borrow_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php if ($row['status'] === 'returned' && $row['actual_return_date']): ?>
                                                        <span class="text-green-600"><?php echo date('M d, Y', strtotime($row['actual_return_date'])); ?></span>
                                                    <?php else: ?>
                                                        <span class="<?php echo strtotime($row['expected_return_date']) < time() ? 'text-red-600 font-medium' : ''; ?>">
                                                            <?php echo date('M d, Y', strtotime($row['expected_return_date'])); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($statusText); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    $<?php echo number_format($row['daily_rate'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    $<?php echo number_format($row['total_fee'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <?php if ($row['status'] === 'active'): ?>
                                                        <div class="flex items-center justify-end space-x-3">
                                                            <a href="borrowings.php?action=return&id=<?php echo $row['borrowing_id']; ?>" class="text-green-600 hover:text-green-900" title="Mark as Returned">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <div class="relative group">
                                                                <button class="text-blue-600 hover:text-blue-900" title="Extend Borrowing Period">
                                                                    <i class="fas fa-calendar-plus"></i>
                                                                </button>
                                                                <div class="absolute right-0 z-10 w-48 mt-2 bg-white rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                                                                    <div class="py-1">
                                                                        <a href="borrowings.php?action=extend&id=<?php echo $row['borrowing_id']; ?>&days=7" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Extend 7 days</a>
                                                                        <a href="borrowings.php?action=extend&id=<?php echo $row['borrowing_id']; ?>&days=14" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Extend 14 days</a>
                                                                        <a href="borrowings.php?action=extend&id=<?php echo $row['borrowing_id']; ?>&days=30" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Extend 30 days</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <a href="borrowings.php?action=lost&id=<?php echo $row['borrowing_id']; ?>" class="text-yellow-600 hover:text-yellow-900" title="Mark as Lost">
                                                                <i class="fas fa-exclamation-triangle"></i>
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">No actions</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No borrowings found matching your criteria.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4 rounded-lg shadow-soft">
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo min(($page - 1) * $recordsPerPage + 1, $totalRecords); ?></span> to <span class="font-medium"><?php echo min($page * $recordsPerPage, $totalRecords); ?></span> of <span class="font-medium"><?php echo $totalRecords; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                    <a href="borrowings.php?page=<?php echo ($page - 1); ?>&status=<?php echo urlencode($status); ?>&order=<?php echo urlencode($orderBy); ?>&dir=<?php echo urlencode($orderDirection); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, min($page - 2, $totalPages - 4));
                                    $endPage = min($totalPages, max($page + 2, 5));
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <a href="borrowings.php?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&order=<?php echo urlencode($orderBy); ?>&dir=<?php echo urlencode($orderDirection); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-primary-600 bg-primary-50 z-10' : 'text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                    <a href="borrowings.php?page=<?php echo ($page + 1); ?>&status=<?php echo urlencode($status); ?>&order=<?php echo urlencode($orderBy); ?>&dir=<?php echo urlencode($orderDirection); ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile menu toggle
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
        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');
        
        userMenuButton.addEventListener('click', function() {
            userDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking elsewhere
        window.addEventListener('click', function(event) {
            if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('show');
            }
        });
        
        // Sort functionality
        document.getElementById('sort-by').addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('order', this.value);
            window.location.href = currentUrl.toString();
        });
        
        document.getElementById('sort-direction').addEventListener('click', function() {
            const currentUrl = new URL(window.location.href);
            const currentDir = currentUrl.searchParams.get('dir') || 'DESC';
            currentUrl.searchParams.set('dir', currentDir === 'ASC' ? 'DESC' : 'ASC');
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html>