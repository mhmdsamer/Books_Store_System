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

// Initialize variables
$successMessage = $errorMessage = '';
$user = [
    'user_id' => '',
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'role' => 'customer',
    'password' => '',
];

// Process form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle user actions (add, edit, delete)
    if (isset($_POST['action'])) {
        // Add new user
        if ($_POST['action'] === 'add') {
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
            $firstName = mysqli_real_escape_string($conn, $_POST['first_name']);
            $lastName = mysqli_real_escape_string($conn, $_POST['last_name']);
            $role = mysqli_real_escape_string($conn, $_POST['role']);
            
            // Check if username or email already exists
            $checkQuery = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
            $checkResult = $conn->query($checkQuery);
            
            if ($checkResult->num_rows > 0) {
                $errorMessage = "Username or email already exists.";
            } else {
                $insertQuery = "INSERT INTO users (username, email, password, first_name, last_name, role) 
                                VALUES ('$username', '$email', '$password', '$firstName', '$lastName', '$role')";
                
                if ($conn->query($insertQuery) === TRUE) {
                    $successMessage = "User added successfully!";
                } else {
                    $errorMessage = "Error adding user: " . $conn->error;
                }
            }
        }
        
        // Update existing user
        else if ($_POST['action'] === 'edit') {
            $userId = mysqli_real_escape_string($conn, $_POST['user_id']);
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $firstName = mysqli_real_escape_string($conn, $_POST['first_name']);
            $lastName = mysqli_real_escape_string($conn, $_POST['last_name']);
            $role = mysqli_real_escape_string($conn, $_POST['role']);
            
            // Check if username or email already exists for another user
            $checkQuery = "SELECT * FROM users WHERE (username = '$username' OR email = '$email') AND user_id != $userId";
            $checkResult = $conn->query($checkQuery);
            
            if ($checkResult->num_rows > 0) {
                $errorMessage = "Username or email already exists for another user.";
            } else {
                // Start with basic update query
                $updateQuery = "UPDATE users SET 
                                username = '$username', 
                                email = '$email', 
                                first_name = '$firstName', 
                                last_name = '$lastName', 
                                role = '$role'";
                
                // Add password update if provided
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $updateQuery .= ", password = '$password'";
                }
                
                $updateQuery .= " WHERE user_id = $userId";
                
                if ($conn->query($updateQuery) === TRUE) {
                    $successMessage = "User updated successfully!";
                } else {
                    $errorMessage = "Error updating user: " . $conn->error;
                }
            }
        }
        
        // Delete user
        else if ($_POST['action'] === 'delete') {
            $userId = mysqli_real_escape_string($conn, $_POST['user_id']);
            
            // Check for related records in other tables
            $checkOrdersQuery = "SELECT COUNT(*) as count FROM orders WHERE user_id = $userId";
            $ordersResult = $conn->query($checkOrdersQuery);
            $orderCount = $ordersResult->fetch_assoc()['count'];
            
            $checkBorrowingsQuery = "SELECT COUNT(*) as count FROM borrowings WHERE user_id = $userId";
            $borrowingsResult = $conn->query($checkBorrowingsQuery);
            $borrowingCount = $borrowingsResult->fetch_assoc()['count'];
            
            $checkFeedbackQuery = "SELECT COUNT(*) as count FROM feedback WHERE user_id = $userId";
            $feedbackResult = $conn->query($checkFeedbackQuery);
            $feedbackCount = $feedbackResult->fetch_assoc()['count'];
            
            $checkCartQuery = "SELECT COUNT(*) as count FROM cart_items WHERE user_id = $userId";
            $cartResult = $conn->query($checkCartQuery);
            $cartCount = $cartResult->fetch_assoc()['count'];
            
            if ($orderCount > 0 || $borrowingCount > 0 || $feedbackCount > 0 || $cartCount > 0) {
                $errorMessage = "Cannot delete user because they have related records in the system. Please remove their orders, borrowings, feedback, and cart items first.";
            } else {
                $deleteQuery = "DELETE FROM users WHERE user_id = $userId";
                
                if ($conn->query($deleteQuery) === TRUE) {
                    $successMessage = "User deleted successfully!";
                } else {
                    $errorMessage = "Error deleting user: " . $conn->error;
                }
            }
        }
    }
}

// Handle edit action from GET request
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $userId = mysqli_real_escape_string($conn, $_GET['id']);
    $userQuery = "SELECT * FROM users WHERE user_id = $userId";
    $userResult = $conn->query($userQuery);
    
    if ($userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
    }
}

// Fetch all users for the table display
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10; // Number of records per page
$offset = ($page - 1) * $limit;

$countQuery = "SELECT COUNT(*) as total FROM users";
$whereClause = "";

if (!empty($search)) {
    $whereClause = " WHERE username LIKE '%$search%' OR email LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%'";
    $countQuery .= $whereClause;
}

$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

$usersQuery = "SELECT * FROM users" . $whereClause . " ORDER BY created_at DESC LIMIT $offset, $limit";
$usersResult = $conn->query($usersQuery);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - BookStore Admin</title>
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
                    <a href="borrowings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-hand-holding"></i>
                        <span>Borrowings</span>
                    </a>
                </div>
                
                <div class="px-4 py-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</h5>
                    <a href="users.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <form class="flex-1 relative" action="users.php" method="GET">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </span>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search users...">
                                <button type="submit" class="absolute inset-y-0 right-0 pr-3 flex items-center text-primary-500">
                                    <i class="fas fa-arrow-right"></i>
                                </button>
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
                            <div class="relative">
                                <button id="user-menu-button" class="flex items-center space-x-2 focus:outline-none">
                                    <div class="w-8 h-8 rounded-full bg-primary-500 flex items-center justify-center text-white">
                                        <span class="text-sm font-medium uppercase"><?php echo substr($_SESSION['first_name'], 0, 1); ?></span>
                                    </div>
                                    <div class="hidden md:block text-left">
                                        <h5 class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h5>
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
                            <a href="borrowings.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                                <i class="fas fa-hand-holding"></i>
                                <span>Borrowings</span>
                            </a>
                        </div>
                        
                        <div class="px-4 py-2">
                            <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</h5>
                            <a href="users.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <h1 class="text-2xl font-bold text-gray-800 font-display">User Management</h1>
                            <p class="mt-1 text-sm text-gray-600">Manage all users in your bookstore system.</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <button id="add-user-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i class="fas fa-user-plus mr-2"></i> Add New User
                            </button>
                        </div>
                    </div>
                    
                    <!-- Notification Messages -->
                    <?php if (!empty($successMessage)): ?>
                        <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm" role="alert">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm"><?php echo $successMessage; ?></p>
                                </div>
                                <button class="ml-auto close-alert">
                                    <i class="fas fa-times text-green-500"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errorMessage)): ?>
                        <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm" role="alert">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm"><?php echo $errorMessage; ?></p>
                                </div>
                                <button class="ml-auto close-alert">
                                    <i class="fas fa-times text-red-500"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Mobile Search (visible only on mobile) -->
                    <div class="md:hidden mb-6">
                        <form class="relative" action="users.php" method="GET">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <i class="fas fa-search text-gray-400"></i>
                            </span>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search users...">
                            <button type="submit" class="absolute inset-y-0 right-0 pr-3 flex items-center text-primary-500">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Users Table -->
                    <div class="bg-white rounded-lg shadow-soft overflow-hidden mb-6">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-base font-semibold text-gray-800">All Users</h3>
                            <p class="mt-1 text-sm text-gray-500">A list of all users in your bookstore including their name, email, and role.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($usersResult->num_rows > 0): ?>
                                        <?php while ($row = $usersResult->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['user_id']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700">
                                                            <?php echo strtoupper(substr($row['first_name'] ?: $row['username'], 0, 1)); ?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                @<?php echo htmlspecialchars($row['username']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($row['email']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                                                        <?php echo ucfirst($row['role']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $row['last_login'] ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never'; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <a href="?action=edit&id=<?php echo $row['user_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="text-red-600 hover:text-red-900 delete-user-btn" data-id="<?php echo $row['user_id']; ?>" data-name="<?php echo htmlspecialchars($row['username']); ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No users found.
                                                <?php if (!empty($search)): ?>
                                                    <a href="users.php" class="text-primary-600 hover:underline">Clear search</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="px-6 py-4 border-t border-gray-200">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-500">
                                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                        <span class="font-medium"><?php echo min($offset + $limit, $totalRecords); ?></span> of 
                                        <span class="font-medium"><?php echo $totalRecords; ?></span> users
                                    </div>
                                    <div class="flex space-x-1">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                Previous
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium <?php echo $i === $page ? 'bg-primary-100 text-primary-700 border-primary-300' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                Next
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- User Form Modal -->
    <div id="user-modal" class="fixed inset-0 z-30 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900" id="modal-title">Add New User</h3>
                <button id="close-modal" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="user-form" method="POST" action="users.php" class="px-6 py-4">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="user_id" id="form-user-id" value="<?php echo $user['user_id']; ?>">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password <?php echo isset($_GET['action']) && $_GET['action'] === 'edit' ? '(Leave blank to keep current)' : ''; ?>
                        </label>
                        <input type="password" name="password" id="password" <?php echo isset($_GET['action']) && $_GET['action'] === 'edit' ? '' : 'required'; ?> class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="role" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="customer" <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="button" id="cancel-form" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Cancel
                    </button>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 z-30 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Delete User</h3>
                    <p class="text-gray-500">Are you sure you want to delete this user? This action cannot be undone.</p>
                    <p class="mt-2 font-medium" id="delete-user-name"></p>
                </div>
            </div>
            <form id="delete-form" method="POST" action="users.php" class="bg-gray-50 px-6 py-4 rounded-b-lg flex justify-end space-x-4">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete-user-id">
                <button type="button" id="cancel-delete" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Cancel
                </button>
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Delete
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle mobile menu
        const mobileMenuBtn = document.getElementById('mobile-menu-button');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const mobileSidebarContainer = document.getElementById('mobile-sidebar-container');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        
        if (mobileMenuBtn && mobileSidebar && closeSidebarBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileSidebar.classList.remove('hidden');
                setTimeout(() => {
                    mobileSidebarContainer.classList.remove('-translate-x-full');
                }, 50);
            });
            
            closeSidebarBtn.addEventListener('click', () => {
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
        }
        
        // User dropdown menu
        const userMenuBtn = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');
        
        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', () => {
                userDropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
        }
        
        // User Form Modal
        const addUserBtn = document.getElementById('add-user-btn');
        const userModal = document.getElementById('user-modal');
        const closeModalBtn = document.getElementById('close-modal');
        const cancelFormBtn = document.getElementById('cancel-form');
        const userForm = document.getElementById('user-form');
        const modalTitle = document.getElementById('modal-title');
        const formAction = document.getElementById('form-action');
        
        if (addUserBtn && userModal && closeModalBtn && cancelFormBtn) {
            // Open modal for adding a new user
            addUserBtn.addEventListener('click', () => {
                modalTitle.textContent = 'Add New User';
                formAction.value = 'add';
                userForm.reset();
                document.getElementById('form-user-id').value = '';
                userModal.classList.remove('hidden');
            });
            
            // Close modal functions
            const closeModal = () => {
                userModal.classList.add('hidden');
            };
            
            closeModalBtn.addEventListener('click', closeModal);
            cancelFormBtn.addEventListener('click', closeModal);
            
            // Close when clicking outside
            userModal.addEventListener('click', (e) => {
                if (e.target === userModal) {
                    closeModal();
                }
            });
        }
        
        // Delete User Modal
        const deleteModal = document.getElementById('delete-modal');
        const cancelDeleteBtn = document.getElementById('cancel-delete');
        const deleteUserBtns = document.querySelectorAll('.delete-user-btn');
        const deleteUserNameElement = document.getElementById('delete-user-name');
        const deleteUserIdInput = document.getElementById('delete-user-id');
        
        if (deleteModal && cancelDeleteBtn && deleteUserBtns.length) {
            // Open delete confirmation modal
            deleteUserBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const userId = btn.getAttribute('data-id');
                    const userName = btn.getAttribute('data-name');
                    
                    deleteUserIdInput.value = userId;
                    deleteUserNameElement.textContent = userName;
                    deleteModal.classList.remove('hidden');
                });
            });
            
            // Close delete modal
            const closeDeleteModal = () => {
                deleteModal.classList.add('hidden');
            };
            
            cancelDeleteBtn.addEventListener('click', closeDeleteModal);
            
            // Close when clicking outside
            deleteModal.addEventListener('click', (e) => {
                if (e.target === deleteModal) {
                    closeDeleteModal();
                }
            });
        }
        
        // Handle closing alerts
        const closeAlerts = document.querySelectorAll('.close-alert');
        if (closeAlerts.length) {
            closeAlerts.forEach(btn => {
                btn.addEventListener('click', () => {
                    const alert = btn.closest('[role="alert"]');
                    alert.style.display = 'none';
                });
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Handle edit user button clicks
        const editBtns = document.querySelectorAll('a[href^="?action=edit"]');
        if (editBtns.length && userModal) {
            editBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    if (userModal.classList.contains('hidden')) {
                        // Don't prevent the default action if the modal isn't already open
                        // This allows the page to reload with the user data
                        return;
                    }
                    
                    e.preventDefault(); // Prevent the link from navigating if modal is open
                });
            });
        }
        
        // If we're in edit mode, show the modal automatically
        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $userResult && $userResult->num_rows > 0): ?>
            if (userModal) {
                modalTitle.textContent = 'Edit User';
                formAction.value = 'edit';
                userModal.classList.remove('hidden');
            }
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>