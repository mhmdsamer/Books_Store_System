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

// Process actions if any
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    if ($action === 'approve') {
        // Update feedback status to published
        $updateQuery = "UPDATE feedback SET status = 'published', updated_at = NOW() WHERE feedback_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to avoid resubmission on refresh
        header("Location: feedback.php?status=approved");
        exit();
    } elseif ($action === 'reject') {
        // Update feedback status to rejected
        $updateQuery = "UPDATE feedback SET status = 'rejected', updated_at = NOW() WHERE feedback_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to avoid resubmission on refresh
        header("Location: feedback.php?status=rejected");
        exit();
    } elseif ($action === 'delete') {
        // Delete feedback
        $deleteQuery = "DELETE FROM feedback WHERE feedback_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to avoid resubmission on refresh
        header("Location: feedback.php?status=deleted");
        exit();
    }
}

// Handle status filtering
$statusFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$whereClause = '';

if ($statusFilter !== 'all') {
    $whereClause = "WHERE status = '" . $conn->real_escape_string($statusFilter) . "'";
}

// Fetch all feedback with user information
$feedbackQuery = "
    SELECT f.*, u.username, u.email, b.title AS book_title
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.user_id
    LEFT JOIN books b ON f.book_id = b.book_id
    $whereClause
    ORDER BY f.created_at DESC
";
$feedbackResult = $conn->query($feedbackQuery);

// Get counts for different status categories
$countQuery = "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
                FROM feedback";
$countResult = $conn->query($countQuery);
$counts = $countResult->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - BookStore Admin</title>
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
                    <a href="users.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link">
                        <i class="fas fa-users"></i>
                        <span>All Users</span>
                    </a>
                    <a href="feedback.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                                <input type="text" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search feedback...">
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
                                        <span class="text-sm font-medium uppercase"><?php echo substr($_SESSION['first_name'] ?? 'A', 0, 1); ?></span>
                                    </div>
                                    <div class="hidden md:block text-left">
                                        <h5 class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?></h5>
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
                            <a href="feedback.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <h1 class="text-2xl font-bold text-gray-800 font-display">Feedback Management</h1>
                            <p class="mt-1 text-sm text-gray-600">View and manage customer feedback and reviews for your bookstore.</p>
                        </div>
                    </div>
                    
                    <!-- Status message -->
                    <?php if (isset($_GET['status'])): ?>
                        <div class="mb-6">
                            <?php if ($_GET['status'] === 'approved'): ?>
                                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded" role="alert">
                                    <p class="font-medium">Success!</p>
                                    <p>Feedback has been approved and published successfully.</p>
                                </div>
                            <?php elseif ($_GET['status'] === 'rejected'): ?>
                                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded" role="alert">
                                    <p class="font-medium">Success!</p>
                                    <p>Feedback has been rejected successfully.</p>
                                </div>
                            <?php elseif ($_GET['status'] === 'deleted'): ?>
                                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                                    <p class="font-medium">Success!</p>
                                    <p>Feedback has been deleted successfully.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filter Tabs -->
                    <div class="bg-white rounded-lg shadow-soft mb-6">
                        <div class="sm:hidden">
                            <label for="tabs" class="sr-only">Select a tab</label>
                            <select id="tabs" name="tabs" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md" onchange="window.location.href=this.value">
                                <option value="feedback.php?filter=all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Feedback (<?php echo $counts['total']; ?>)</option>
                                <option value="feedback.php?filter=published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>Published (<?php echo $counts['published']; ?>)</option>
                                <option value="feedback.php?filter=pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending (<?php echo $counts['pending']; ?>)</option>
                                <option value="feedback.php?filter=rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected (<?php echo $counts['rejected']; ?>)</option>
                            </select>
                        </div>
                        <div class="hidden sm:block">
                            <div class="border-b border-gray-200">
                                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                                    <a href="feedback.php?filter=all" class="<?php echo $statusFilter === 'all' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        All Feedback
                                        <span class="ml-1 px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600"><?php echo $counts['total']; ?></span>
                                    </a>
                                    <a href="feedback.php?filter=published" class="<?php echo $statusFilter === 'published' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Published
                                        <span class="ml-1 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-600"><?php echo $counts['published']; ?></span>
                                    </a>
                                    <a href="feedback.php?filter=pending" class="<?php echo $statusFilter === 'pending' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Pending
                                        <span class="ml-1 px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-600"><?php echo $counts['pending']; ?></span>
                                    </a>
                                    <a href="feedback.php?filter=rejected" class="<?php echo $statusFilter === 'rejected' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Rejected
                                        <span class="ml-1 px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-600"><?php echo $counts['rejected']; ?></span>
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Feedback Table -->
                    <div class="bg-white rounded-lg shadow-soft overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($feedbackResult && $feedbackResult->num_rows > 0): ?>
                                        <?php while ($feedback = $feedbackResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?php echo $feedback['feedback_id']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                            <span class="text-xs font-medium uppercase text-gray-600"><?php echo substr($feedback['username'] ?? 'U', 0, 1); ?></span>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($feedback['username'] ?? 'Unknown User'); ?></div>
                                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($feedback['email'] ?? ''); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $feedback['book_title'] ? htmlspecialchars($feedback['book_title']) : 'General Feedback'; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                    <span class="text-sm font-medium text-gray-700 mr-1"><?php echo $feedback['rating']; ?></span>
                                                        <div class="flex text-yellow-400">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <?php if ($i <= $feedback['rating']): ?>
                                                                    <i class="fas fa-star text-xs"></i>
                                                                <?php elseif ($i - 0.5 <= $feedback['rating']): ?>
                                                                    <i class="fas fa-star-half-alt text-xs"></i>
                                                                <?php else: ?>
                                                                    <i class="far fa-star text-xs"></i>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                                        <?php echo htmlspecialchars($feedback['review'] ?? 'No review text'); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($feedback['status'] === 'published'): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            Published
                                                        </span>
                                                    <?php elseif ($feedback['status'] === 'pending'): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            Pending
                                                        </span>
                                                    <?php elseif ($feedback['status'] === 'rejected'): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                            Rejected
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex space-x-2">
                                                        <?php if ($feedback['status'] === 'pending'): ?>
                                                            <a href="feedback.php?action=approve&id=<?php echo $feedback['feedback_id']; ?>" class="text-green-600 hover:text-green-900" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="feedback.php?action=reject&id=<?php echo $feedback['feedback_id']; ?>" class="text-yellow-600 hover:text-yellow-900" title="Reject">
                                                                <i class="fas fa-ban"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="#" class="text-primary-600 hover:text-primary-900 view-details" data-id="<?php echo $feedback['feedback_id']; ?>" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="feedback.php?action=delete&id=<?php echo $feedback['feedback_id']; ?>" class="text-red-600 hover:text-red-900 delete-feedback" data-id="<?php echo $feedback['feedback_id']; ?>" title="Delete" onclick="return confirm('Are you sure you want to delete this feedback?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No feedback found in this category.
                                            </td>
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

    <!-- View Details Modal (hidden by default) -->
    <div id="details-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Feedback Details</h3>
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">User Information</h4>
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                            <span id="modal-user-initial" class="text-sm font-medium uppercase text-gray-600">U</span>
                        </div>
                        <div class="ml-3">
                            <div id="modal-username" class="text-sm font-medium text-gray-900">Username</div>
                            <div id="modal-email" class="text-xs text-gray-500">email@example.com</div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Book Information</h4>
                    <p id="modal-book-title" class="text-sm text-gray-900">Book Title</p>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Rating</h4>
                    <div class="flex items-center">
                        <span id="modal-rating" class="text-sm font-medium text-gray-700 mr-2">5.0</span>
                        <div id="modal-stars" class="flex text-yellow-400">
                            <!-- Stars will be added here dynamically -->
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Review</h4>
                    <p id="modal-review" class="text-sm text-gray-900 whitespace-pre-line">Review content will appear here.</p>
                </div>

                <div class="mb-4 grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Date Submitted</h4>
                        <p id="modal-date" class="text-sm text-gray-900">Apr 21, 2025</p>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Status</h4>
                        <span id="modal-status" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Published</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button id="modal-close-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Close
                    </button>
                    <div id="modal-action-btns">
                        <!-- Action buttons will be added here dynamically based on status -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Interactions -->
    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const mobileSidebarContainer = document.getElementById('mobile-sidebar-container');
        const closeSidebar = document.getElementById('close-sidebar');

        mobileMenuButton.addEventListener('click', () => {
            mobileSidebar.classList.remove('hidden');
            setTimeout(() => {
                mobileSidebarContainer.classList.remove('-translate-x-full');
            }, 10);
        });

        closeSidebar.addEventListener('click', () => {
            mobileSidebarContainer.classList.add('-translate-x-full');
            setTimeout(() => {
                mobileSidebar.classList.add('hidden');
            }, 300);
        });

        // User dropdown toggle
        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');

        userMenuButton.addEventListener('click', () => {
            userDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('show');
            }
        });

        // View Details Modal
        const viewDetailsButtons = document.querySelectorAll('.view-details');
        const detailsModal = document.getElementById('details-modal');
        const closeModal = document.getElementById('close-modal');
        const modalCloseBtn = document.getElementById('modal-close-btn');

        // Sample data for demo purposes - in a real application, you'd fetch this data from the server
        const feedbackData = <?php
            $feedbackArray = [];
            if ($feedbackResult) {
                $feedbackResult->data_seek(0); // Reset pointer to start
                while ($row = $feedbackResult->fetch_assoc()) {
                    $feedbackArray[] = $row;
                }
            }
            echo json_encode($feedbackArray);
        ?>;

        viewDetailsButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const feedbackId = button.getAttribute('data-id');
                const feedback = feedbackData.find(item => item.feedback_id == feedbackId);
                
                if (feedback) {
                    // Fill modal with data
                    document.getElementById('modal-user-initial').textContent = feedback.username ? feedback.username.charAt(0).toUpperCase() : 'U';
                    document.getElementById('modal-username').textContent = feedback.username || 'Unknown User';
                    document.getElementById('modal-email').textContent = feedback.email || 'No email available';
                    document.getElementById('modal-book-title').textContent = feedback.book_title || 'General Feedback';
                    document.getElementById('modal-rating').textContent = feedback.rating;
                    document.getElementById('modal-review').textContent = feedback.review || 'No review provided';
                    document.getElementById('modal-date').textContent = new Date(feedback.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    
                    // Set status
                    const statusEl = document.getElementById('modal-status');
                    statusEl.textContent = feedback.status.charAt(0).toUpperCase() + feedback.status.slice(1);
                    statusEl.className = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full';
                    
                    if (feedback.status === 'published') {
                        statusEl.classList.add('bg-green-100', 'text-green-800');
                    } else if (feedback.status === 'pending') {
                        statusEl.classList.add('bg-yellow-100', 'text-yellow-800');
                    } else if (feedback.status === 'rejected') {
                        statusEl.classList.add('bg-red-100', 'text-red-800');
                    }
                    
                    // Display stars
                    const starsContainer = document.getElementById('modal-stars');
                    starsContainer.innerHTML = '';
                    for (let i = 1; i <= 5; i++) {
                        const star = document.createElement('i');
                        star.classList.add('text-sm');
                        if (i <= feedback.rating) {
                            star.classList.add('fas', 'fa-star');
                        } else if (i - 0.5 <= feedback.rating) {
                            star.classList.add('fas', 'fa-star-half-alt');
                        } else {
                            star.classList.add('far', 'fa-star');
                        }
                        starsContainer.appendChild(star);
                    }
                    
                    // Add action buttons based on status
                    const actionBtnsContainer = document.getElementById('modal-action-btns');
                    actionBtnsContainer.innerHTML = '';
                    
                    if (feedback.status === 'pending') {
                        const approveBtn = document.createElement('a');
                        approveBtn.href = `feedback.php?action=approve&id=${feedback.feedback_id}`;
                        approveBtn.className = 'px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500';
                        approveBtn.textContent = 'Approve';
                        
                        const rejectBtn = document.createElement('a');
                        rejectBtn.href = `feedback.php?action=reject&id=${feedback.feedback_id}`;
                        rejectBtn.className = 'ml-2 px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500';
                        rejectBtn.textContent = 'Reject';
                        
                        actionBtnsContainer.appendChild(approveBtn);
                        actionBtnsContainer.appendChild(rejectBtn);
                    }
                    
                    const deleteBtn = document.createElement('a');
                    deleteBtn.href = `feedback.php?action=delete&id=${feedback.feedback_id}`;
                    deleteBtn.className = 'ml-2 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500';
                    deleteBtn.textContent = 'Delete';
                    deleteBtn.onclick = () => confirm('Are you sure you want to delete this feedback?');
                    
                    actionBtnsContainer.appendChild(deleteBtn);
                    
                    // Show modal
                    detailsModal.classList.remove('hidden');
                }
            });
        });

        // Close modal events
        [closeModal, modalCloseBtn].forEach(el => {
            el.addEventListener('click', () => {
                detailsModal.classList.add('hidden');
            });
        });

        // Close modal when clicking outside of it
        detailsModal.addEventListener('click', (e) => {
            if (e.target === detailsModal) {
                detailsModal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>