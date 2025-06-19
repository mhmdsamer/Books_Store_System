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

// Initialize variables for form data
$username = $_SESSION['username'] ?? '';
$email = '';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$success_message = '';
$error_message = '';

// Fetch current user data
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $username = $user_data['username'];
    $email = $user_data['email'];
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile information update
    if (isset($_POST['update_profile'])) {
        $new_first_name = trim($_POST['first_name']);
        $new_last_name = trim($_POST['last_name']);
        $new_email = trim($_POST['email']);
        
        // Validate email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format";
        } else {
            // Check if email already exists for another user
            $check_email_query = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            $check_stmt = $conn->prepare($check_email_query);
            $check_stmt->bind_param("si", $new_email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Email address is already in use";
            } else {
                // Update profile
                $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssi", $new_first_name, $new_last_name, $new_email, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Profile updated successfully";
                    // Update session data
                    $_SESSION['first_name'] = $new_first_name;
                    $_SESSION['last_name'] = $new_last_name;
                    
                    // Refresh page variables
                    $first_name = $new_first_name;
                    $last_name = $new_last_name;
                    $email = $new_email;
                } else {
                    $error_message = "Error updating profile: " . $conn->error;
                }
                
                $update_stmt->close();
            }
            
            $check_stmt->close();
        }
    }
    
    // Password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password
        if (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match";
        } else {
            // Verify current password
            $password_query = "SELECT password FROM users WHERE user_id = ?";
            $password_stmt = $conn->prepare($password_query);
            $password_stmt->bind_param("i", $user_id);
            $password_stmt->execute();
            $password_result = $password_stmt->get_result();
            
            if ($password_row = $password_result->fetch_assoc()) {
                if (password_verify($current_password, $password_row['password'])) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $update_password_query = "UPDATE users SET password = ? WHERE user_id = ?";
                    $update_password_stmt = $conn->prepare($update_password_query);
                    $update_password_stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($update_password_stmt->execute()) {
                        $success_message = "Password changed successfully";
                    } else {
                        $error_message = "Error changing password: " . $conn->error;
                    }
                    
                    $update_password_stmt->close();
                } else {
                    $error_message = "Current password is incorrect";
                }
            }
            
            $password_stmt->close();
        }
    }
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

// Get order statistics
$order_count = 0;
$order_query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_row = $order_result->fetch_assoc()) {
    $order_count = $order_row['order_count'];
}
$order_stmt->close();

// Get borrowing statistics
$active_borrowings = 0;
$borrowing_query = "SELECT COUNT(*) as active_count FROM borrowings WHERE user_id = ? AND status = 'active'";
$borrowing_stmt = $conn->prepare($borrowing_query);
$borrowing_stmt->bind_param("i", $user_id);
$borrowing_stmt->execute();
$borrowing_result = $borrowing_stmt->get_result();

if ($borrowing_row = $borrowing_result->fetch_assoc()) {
    $active_borrowings = $borrowing_row['active_count'];
}
$borrowing_stmt->close();

// Get account creation date
$account_date = '';
$date_query = "SELECT DATE_FORMAT(created_at, '%M %d, %Y') as join_date FROM users WHERE user_id = ?";
$date_stmt = $conn->prepare($date_query);
$date_stmt->bind_param("i", $user_id);
$date_stmt->execute();
$date_result = $date_stmt->get_result();

if ($date_row = $date_result->fetch_assoc()) {
    $account_date = $date_row['join_date'];
}
$date_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - BookStore</title>
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
        
        /* Tab transition */
        .tab-content {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        
        .tab-content.active {
            display: block;
            opacity: 1;
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
                            
                            <a href="profile.php" class="block px-4 py-2 text-sm text-primary-600 bg-gray-100">Your Profile</a>
                            <a href="orders.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Orders</a>
                            <a href="borrowings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Borrowings</a>
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
    <?php if (!empty($success_message)): ?>
    <div class="fixed top-20 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md notification z-50">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p>
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
            <h1 class="text-3xl md:text-4xl font-bold font-display">Your Profile</h1>
            <div class="flex items-center mt-2">
                <a href="index.php" class="text-primary-100 hover:text-white transition-colors">Home</a>
                <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                <span class="text-white">Profile</span>
            </div>
        </div>
    </section>
    
    <!-- Profile Section -->
    <section class="py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Sidebar -->
                <div class="lg:w-1/4">
                    <div class="bg-white rounded-lg shadow-soft p-6 mb-6">
                        <div class="flex flex-col items-center text-center mb-6">
                            <div class="w-24 h-24 bg-primary-100 rounded-full flex items-center justify-center mb-4">
                                <span class="text-primary-700 font-bold text-3xl">
                                    <?php echo substr($first_name ?? $username, 0, 1); ?>
                                </span>
                            </div>
                            <h2 class="text-xl font-bold"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h2>
                            <p class="text-gray-600"><?php echo htmlspecialchars($username); ?></p>
                            <p class="text-sm text-gray-500 mt-1">Member since <?php echo htmlspecialchars($account_date); ?></p>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex flex-wrap justify-center gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-primary-600"><?php echo $order_count; ?></div>
                                    <div class="text-xs text-gray-600">Orders</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-primary-600"><?php echo $active_borrowings; ?></div>
                                    <div class="text-xs text-gray-600">Active Borrowings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-soft overflow-hidden">
                        <nav class="profile-tabs">
                            <a href="#" class="tab-link active flex items-center py-3 px-6 border-l-4 border-primary-600 bg-primary-50 text-primary-700 font-medium" data-tab="account">
                                <i class="fas fa-user-circle mr-3"></i>
                                Account Information
                            </a>
                            <a href="#" class="tab-link flex items-center py-3 px-6 border-l-4 border-transparent hover:bg-gray-50 text-gray-700 hover:text-primary-600 transition-colors" data-tab="security">
                                <i class="fas fa-lock mr-3"></i>
                                Security
                            </a>
                            <a href="orders.php" class="flex items-center py-3 px-6 border-l-4 border-transparent hover:bg-gray-50 text-gray-700 hover:text-primary-600 transition-colors">
                                <i class="fas fa-shopping-bag mr-3"></i>
                                Order History
                            </a>
                            <a href="borrowings.php" class="flex items-center py-3 px-6 border-l-4 border-transparent hover:bg-gray-50 text-gray-700 hover:text-primary-600 transition-colors">
                                <i class="fas fa-book mr-3"></i>
                                Borrowings
                            </a>
                        </nav>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="lg:w-3/4">
                    <!-- Account Information Tab -->
                    <div id="account-tab" class="tab-content active bg-white rounded-lg shadow-soft p-6 mb-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold">Account Information</h3>
                        </div>
                        
                        <form action="profile.php" method="POST">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700" readonly>
                                    <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                </div>
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="update_profile" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Security Tab -->
                    <div id="security-tab" class="tab-content bg-white rounded-lg shadow-soft p-6 mb-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold">Security Settings</h3>
                        </div>
                        
                        <form action="profile.php" method="POST">
                            <div class="mb-6">
                                <h4 class="text-lg font-medium text-gray-800 mb-4">Change Password</h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    </div>
                                    <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                        <input type="password" id="new_password" name="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    </div>
                                    <div>
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                        <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="change_password" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                                    Update Password
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="text-lg font-medium text-gray-800 mb-4">Account Protection</h4>
                            
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-shield-alt text-yellow-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            We recommend using a strong, unique password for your BookStore account that you don't use elsewhere.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white pt-12 pb-8">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- About -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">About BookStore</h4>
                    <p class="text-gray-400 mb-4">We're passionate about books and dedicated to bringing you the best reading experience with a wide selection of titles and exceptional service.</p>
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
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="books.php" class="text-gray-400 hover:text-white transition-colors">Books</a></li>
                        <li><a href="categories.php" class="text-gray-400 hover:text-white transition-colors">Categories</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition-colors">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                
                <!-- Help -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Help</h4>
                    <ul class="space-y-2">
                        <li><a href="faq.php" class="text-gray-400 hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="shipping.php" class="text-gray-400 hover:text-white transition-colors">Shipping</a></li>
                        <li><a href="returns.php" class="text-gray-400 hover:text-white transition-colors">Returns</a></li>
                        <li><a href="order-tracking.php" class="text-gray-400 hover:text-white transition-colors">Order Tracking</a></li>
                        <li><a href="privacy-policy.php" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <!-- Newsletter -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Newsletter</h4>
                    <p class="text-gray-400 mb-4">Subscribe to our newsletter and get updates on new books and exclusive offers.</p>
                    <form action="subscribe.php" method="POST" class="space-y-2">
                        <input type="email" name="email" placeholder="Your email address" class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" required>
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm">&copy; 2025 BookStore. All rights reserved.</p>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    <a href="terms.php" class="text-gray-400 hover:text-white text-sm transition-colors">Terms of Service</a>
                    <a href="privacy-policy.php" class="text-gray-400 hover:text-white text-sm transition-colors">Privacy Policy</a>
                    <a href="cookie-policy.php" class="text-gray-400 hover:text-white text-sm transition-colors">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.add('open');
        });
        
        document.getElementById('close-mobile-menu').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.remove('open');
        });
        
        // Search bar toggle
        document.getElementById('search-toggle').addEventListener('click', function() {
            const searchBar = document.getElementById('search-bar');
            searchBar.classList.toggle('hidden');
            if (!searchBar.classList.contains('hidden')) {
                searchBar.querySelector('input').focus();
            }
        });
        
        // Tab navigation
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Deactivate all tabs
                tabLinks.forEach(item => item.classList.remove('active', 'border-primary-600', 'bg-primary-50', 'text-primary-700'));
                tabLinks.forEach(item => item.classList.add('border-transparent', 'text-gray-700'));
                
                // Activate clicked tab
                this.classList.add('active', 'border-primary-600', 'bg-primary-50', 'text-primary-700');
                this.classList.remove('border-transparent', 'text-gray-700');
                
                // Hide all tab contents
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Show related tab content
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId + '-tab').classList.add('active');
            });
        });
        
        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>sss