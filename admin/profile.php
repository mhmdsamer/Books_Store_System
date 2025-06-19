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

// Initialize variables for form submission status
$updateSuccess = false;
$updateError = '';

// Get current admin info
$userId = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE user_id = ? AND role = 'admin'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found or not an admin
    header("Location: ../login.php");
    exit();
}

$adminData = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $updateError = 'Invalid email format';
        } else {
            // Check if email already exists and belongs to another user
            $checkEmailQuery = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            $checkStmt = $conn->prepare($checkEmailQuery);
            $checkStmt->bind_param("si", $email, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $updateError = 'Email address is already in use by another account';
            } else {
                // Update user profile
                $updateQuery = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("sssi", $firstName, $lastName, $email, $userId);
                
                if ($updateStmt->execute()) {
                    // Update session variables
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    $_SESSION['email'] = $email;
                    $_SESSION['full_name'] = $firstName . ' ' . $lastName;
                    
                    $updateSuccess = true;
                    
                    // Refresh admin data
                    $adminData['first_name'] = $firstName;
                    $adminData['last_name'] = $lastName;
                    $adminData['email'] = $email;
                } else {
                    $updateError = 'Failed to update profile: ' . $conn->error;
                }
                
                $updateStmt->close();
            }
            $checkStmt->close();
        }
    } elseif (isset($_POST['change_password'])) {
        // Handle password change
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate password match
        if ($newPassword !== $confirmPassword) {
            $updateError = 'New passwords do not match';
        } elseif (strlen($newPassword) < 8) {
            $updateError = 'Password must be at least 8 characters long';
        } else {
            // Verify current password
            if (password_verify($currentPassword, $adminData['password'])) {
                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password in database
                $updatePassQuery = "UPDATE users SET password = ? WHERE user_id = ?";
                $updatePassStmt = $conn->prepare($updatePassQuery);
                $updatePassStmt->bind_param("si", $hashedPassword, $userId);
                
                if ($updatePassStmt->execute()) {
                    $updateSuccess = true;
                } else {
                    $updateError = 'Failed to update password: ' . $conn->error;
                }
                
                $updatePassStmt->close();
            } else {
                $updateError = 'Current password is incorrect';
            }
        }
    }
}



// Get last login information
$lastLoginQuery = "SELECT last_login FROM users WHERE user_id = ?";
$lastLoginStmt = $conn->prepare($lastLoginQuery);
$lastLoginStmt->bind_param("i", $userId);
$lastLoginStmt->execute();
$lastLoginResult = $lastLoginStmt->get_result();
$lastLogin = $lastLoginResult->fetch_assoc()['last_login'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - BookStore</title>
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
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
                    <a href="profile.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <a href="profile.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                <div class="max-w-4xl mx-auto">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 font-display">My Profile</h1>
                            <p class="mt-1 text-sm text-gray-600">Manage your account information and change your password</p>
                        </div>
                    </div>
                    
                    <!-- Status Messages -->
                    <?php if($updateSuccess): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p class="font-medium">Success!</p>
                        <p>Your profile has been updated successfully.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($updateError): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p class="font-medium">Error!</p>
                        <p><?php echo $updateError; ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Tabs -->
                    <div class="bg-white shadow-soft rounded-lg overflow-hidden mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="flex -mb-px">
                                <button id="tab-profile" class="tab-button text-primary-600 border-primary-500 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm focus:outline-none" data-tab="profile-content">
                                    <i class="fas fa-user mr-2"></i> Profile
                                </button>
                                <button id="tab-password" class="tab-button text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 border-transparent font-medium text-sm focus:outline-none" data-tab="password-content">
                                    <i class="fas fa-lock mr-2"></i> Password
                                </button>
                                
                            </nav>
                        </div>
                        
                        <!-- Profile Tab Content -->
                        <div id="profile-content" class="tab-content active p-6">
                            <div class="flex flex-col md:flex-row">
                                <!-- Avatar Section -->
                                <div class="md:w-1/3 md:pr-8 mb-6 md:mb-0">
                                    <div class="flex flex-col items-center">
                                        <div class="w-32 h-32 bg-primary-500 rounded-full flex items-center justify-center text-white mb-4">
                                            <span class="text-4xl font-medium uppercase"><?php echo substr($adminData['first_name'], 0, 1); ?></span>
                                        </div>
                                        <h4 class="text-lg font-medium"><?php echo htmlspecialchars($adminData['first_name'] . ' ' . $adminData['last_name']); ?></h4>
                                        <p class="text-gray-500 text-sm">Administrator</p>
                                        <p class="text-gray-500 text-sm mt-1">Member since <?php echo date('M d, Y', strtotime($adminData['created_at'])); ?></p>
                                        
                                        <button class="mt-4 px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium inline-flex items-center hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                            <i class="fas fa-camera mr-2"></i> Change Photo
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Profile Form -->
                                <div class="md:w-2/3">
                                    <form action="profile.php" method="post">
                                        <div class="grid grid-cols-1 gap-6">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                                    <input type="text" name="first_name" id="first_name" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo htmlspecialchars($adminData['first_name']); ?>" required>
                                                </div>
                                                <div>
                                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                                    <input type="text" name="last_name" id="last_name" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo htmlspecialchars($adminData['last_name']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                                <input type="email" name="email" id="email" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo htmlspecialchars($adminData['email']); ?>" required>
                                            </div>
                                            
                                            <div>
                                                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                                <input type="text" name="username" id="username" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" value="<?php echo htmlspecialchars($adminData['username']); ?>" readonly>
                                                <p class="mt-1 text-xs text-gray-500">Username cannot be changed</p>
                                            </div>
                                            
                                            <div>
                                                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                                <input type="text" name="role" id="role" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" value="<?php echo ucfirst(htmlspecialchars($adminData['role'])); ?>" readonly>
                                            </div>
                                            
                                            <div class="flex justify-end">
                                                <button type="submit" name="update_profile" class="px-6 py-2 bg-primary-600 text-white rounded-md text-sm font-medium hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                    Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Password Tab Content -->
                        <div id="password-content" class="tab-content p-6">
                            <form action="profile.php" method="post">
                                <div class="space-y-6">
                                    <div><div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                        <input type="password" name="current_password" id="current_password" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    </div>
                                    
                                    <div>
                                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                        <input type="password" name="new_password" id="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                        <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters long</p>
                                    </div>
                                    
                                    <div>
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                        <input type="password" name="confirm_password" id="confirm_password" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    </div>
                                    
                                    <div class="flex justify-end">
                                        <button type="submit" name="change_password" class="px-6 py-2 bg-primary-600 text-white rounded-md text-sm font-medium hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                            Update Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Activity Tab Content -->
                        <div id="activity-content" class="tab-content p-6">
                            <div class="space-y-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Login Activity</h3>
                                    <p class="mt-1 text-sm text-gray-500">See your recent login sessions</p>
                                </div>
                                
                                <div>
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                        <div class="flex items-start md:items-center justify-between flex-col md:flex-row">
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-900">Last login</h4>
                                                <p class="text-sm text-gray-500"><?php echo date('M d, Y h:i A', strtotime($lastLogin)); ?></p>
                                            </div>
                                            <div class="mt-2 md:mt-0">
                                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i> Current Session
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Account Security</h3>
                                    <p class="mt-1 text-sm text-gray-500">Manage your account security settings</p>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">Two-Factor Authentication</h4>
                                            <p class="text-sm text-gray-500">Add an extra layer of security to your account</p>
                                        </div>
                                        <div>
                                            <button class="px-4 py-2 bg-primary-600 text-white rounded-md text-sm font-medium hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                Enable 2FA
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">Account Sessions</h4>
                                            <p class="text-sm text-gray-500">Manage active sessions on your account</p>
                                        </div>
                                        <div>
                                            <button class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                Sign Out All Sessions
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
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
        window.addEventListener('click', function(e) {
            if (!document.getElementById('user-menu-button').contains(e.target)) {
                document.getElementById('user-dropdown').classList.remove('show');
            }
        });
        
        // Tab switching
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all tabs
                tabButtons.forEach(btn => {
                    btn.classList.remove('text-primary-600', 'border-primary-500');
                    btn.classList.add('text-gray-500', 'border-transparent');
                });
                
                // Hide all tab contents
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                // Add active class to clicked tab
                button.classList.remove('text-gray-500', 'border-transparent');
                button.classList.add('text-primary-600', 'border-primary-500');
                
                // Show corresponding tab content
                const tabId = button.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Password validation
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        if (newPasswordInput && confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== newPasswordInput.value) {
                    this.setCustomValidity("Passwords don't match");
                } else {
                    this.setCustomValidity('');
                }
            });
            
            newPasswordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value !== '' && this.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity("Passwords don't match");
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
                
                if (this.value.length < 8) {
                    this.setCustomValidity("Password must be at least 8 characters long");
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>