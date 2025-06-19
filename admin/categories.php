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
$errorMsg = '';
$successMsg = '';
$categoriesList = [];
$editCategory = '';
$editCategoryId = '';

// Process category actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new category
    if (isset($_POST['add_category']) && !empty($_POST['new_category'])) {
        $newCategory = trim($_POST['new_category']);
        
        // Check if category already exists
        $checkQuery = "SELECT DISTINCT category FROM books WHERE category = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $newCategory);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errorMsg = "Category '$newCategory' already exists.";
        } else {
            // Since categories are stored in the books table and not in a separate table,
            // we're not actually adding a category here, just showing success message
            // A new category will be created when a book with this category is added
            $successMsg = "Category '$newCategory' has been added. It will appear in the list when books are assigned to it.";
        }
    }
    
    // Update category
    if (isset($_POST['update_category']) && !empty($_POST['updated_category']) && isset($_POST['category_id'])) {
        $updatedCategory = trim($_POST['updated_category']);
        $oldCategory = trim($_POST['category_id']);
        
        // Check if the new category name already exists
        if ($updatedCategory !== $oldCategory) {
            $checkQuery = "SELECT DISTINCT category FROM books WHERE category = ? AND category != ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ss", $updatedCategory, $oldCategory);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errorMsg = "Category '$updatedCategory' already exists.";
            } else {
                // Update all books with the old category to the new category
                $updateQuery = "UPDATE books SET category = ? WHERE category = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ss", $updatedCategory, $oldCategory);
                
                if ($stmt->execute()) {
                    $successMsg = "Category '$oldCategory' has been updated to '$updatedCategory'.";
                } else {
                    $errorMsg = "Error updating category: " . $conn->error;
                }
            }
        }
    }
    
    // Delete category
    if (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
        $categoryToDelete = $_POST['category_id'];
        
        // Count books in this category
        $countQuery = "SELECT COUNT(*) as book_count FROM books WHERE category = ?";
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param("s", $categoryToDelete);
        $stmt->execute();
        $result = $stmt->get_result();
        $bookCount = $result->fetch_assoc()['book_count'];
        
        if ($bookCount > 0) {
            // If books exist in this category, we either need to:
            // 1. Move books to another category, or
            // 2. Set category to NULL
            
            if (isset($_POST['move_to_category']) && !empty($_POST['move_to_category'])) {
                $moveToCategory = $_POST['move_to_category'];
                $updateQuery = "UPDATE books SET category = ? WHERE category = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ss", $moveToCategory, $categoryToDelete);
                
                if ($stmt->execute()) {
                    $successMsg = "Category '$categoryToDelete' has been deleted and all books moved to '$moveToCategory'.";
                } else {
                    $errorMsg = "Error deleting category: " . $conn->error;
                }
            } else {
                // Set category to NULL
                $updateQuery = "UPDATE books SET category = NULL WHERE category = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("s", $categoryToDelete);
                
                if ($stmt->execute()) {
                    $successMsg = "Category '$categoryToDelete' has been deleted and books are now uncategorized.";
                } else {
                    $errorMsg = "Error deleting category: " . $conn->error;
                }
            }
        } else {
            // No books in this category, no action needed
            $successMsg = "Category '$categoryToDelete' has been deleted.";
        }
    }
}

// Edit action from GET request
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['category'])) {
    $editCategoryId = $_GET['category'];
    $editCategory = $editCategoryId; // Since we're using the category name as the ID
}

// Get list of distinct categories
$categoriesQuery = "SELECT DISTINCT category, COUNT(*) as book_count FROM books WHERE category IS NOT NULL GROUP BY category ORDER BY category";
$categoriesResult = $conn->query($categoriesQuery);

if ($categoriesResult) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categoriesList[] = $row;
    }
}

// Get total books with null category
$nullCategoryQuery = "SELECT COUNT(*) as null_count FROM books WHERE category IS NULL";
$nullCategoryResult = $conn->query($nullCategoryQuery);
$nullCategoryCount = 0;

if ($nullCategoryResult) {
    $nullCategoryCount = $nullCategoryResult->fetch_assoc()['null_count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - BookStore</title>
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
                    <a href="categories.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                                <input type="text" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search for categories...">
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
                            <a href="categories.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <h1 class="text-2xl font-bold text-gray-800 font-display">Book Categories</h1>
                            <p class="mt-1 text-sm text-gray-600">Manage your book categories and organize your inventory.</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            
                        </div>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if (!empty($errorMsg)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm"><?php echo $errorMsg; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($successMsg)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm"><?php echo $successMsg; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Categories Overview -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Total Categories Card -->
                        <div class="bg-white rounded-lg shadow-soft p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-primary-100 text-primary-600">
                                    <i class="fas fa-tags text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">Total Categories</h3>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count($categoriesList); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Books without Category -->
                        <div class="bg-white rounded-lg shadow-soft p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                    <i class="fas fa-question-circle text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">Uncategorized Books</h3>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $nullCategoryCount; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Most Popular Category -->
                        <?php
                        $mostPopularCategory = null;
                        $maxBookCount = 0;
                        
                        foreach ($categoriesList as $category) {
                            if ($category['book_count'] > $maxBookCount) {
                                $maxBookCount = $category['book_count'];
                                $mostPopularCategory = $category;
                            }
                        }
                        ?>
                        
                        <?php if ($mostPopularCategory): ?>
                        <div class="bg-white rounded-lg shadow-soft p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i class="fas fa-crown text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">Most Popular Category</h3>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($mostPopularCategory['category']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $mostPopularCategory['book_count']; ?> books</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Category Distribution Chart -->
                    <div class="bg-white rounded-lg shadow-soft border border-gray-200 p-6 mb-8">
                        <h3 class="text-base font-semibold text-gray-800 mb-4">Category Distribution</h3>
                        <div class="h-80">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Categories Table -->
                    <div class="bg-white rounded-lg shadow-soft border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-base font-semibold text-gray-800">All Categories</h3>
                            <p class="mt-1 text-sm text-gray-600">A list of all book categories in your store.</p>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Books Count</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (count($categoriesList) > 0): ?>
                                        <?php foreach ($categoriesList as $category): ?>
                                            <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['category']); ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
    <div class="text-sm text-gray-500"><?php echo $category['book_count']; ?> books</div>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm">
    <div class="flex space-x-2">
        <a href="?action=edit&category=<?php echo urlencode($category['category']); ?>" class="text-primary-600 hover:text-primary-900">
            <i class="fas fa-edit"></i> Edit
        </a>
        <button type="button" class="text-red-600 hover:text-red-900" 
                onclick="confirmDelete('<?php echo htmlspecialchars($category['category']); ?>')">
            <i class="fas fa-trash"></i> Delete
        </button>
    </div>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
    <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
        No categories found. Add a new category to get started.
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<!-- Add Category Modal -->
<div id="add-category-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        
        <!-- Modal content -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="categories.php" method="POST">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-tag text-primary-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Add New Category
                            </h3>
                            <div class="mt-4">
                                <label for="new_category" class="block text-sm font-medium text-gray-700">Category Name</label>
                                <input type="text" name="new_category" id="new_category" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="add_category" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Add Category
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm close-modal">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<?php if (!empty($editCategory)): ?>
<div id="edit-category-modal" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        
        <!-- Modal content -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="categories.php" method="POST">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-edit text-primary-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Edit Category
                            </h3>
                            <div class="mt-4">
                                <label for="updated_category" class="block text-sm font-medium text-gray-700">Category Name</label>
                                <input type="text" name="updated_category" id="updated_category" value="<?php echo htmlspecialchars($editCategory); ?>" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($editCategoryId); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_category" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Update Category
                    </button>
                    <a href="categories.php" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Category Modal -->
<div id="delete-category-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        
        <!-- Modal content -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="categories.php" method="POST" id="delete-form">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Delete Category
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to delete this category? This action cannot be undone.
                                </p>
                                <p class="text-sm font-medium text-gray-900 mt-2">
                                    Category: <span id="category-to-delete"></span>
                                </p>
                                <input type="hidden" name="category_id" id="delete-category-id">
                                
                                <div class="mt-4 border-t border-gray-200 pt-4">
                                    <label class="block text-sm font-medium text-gray-700">How to handle books in this category?</label>
                                    <div class="mt-2">
                                        <div class="relative flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="set-uncategorized" name="handle_books" type="radio" value="uncategorized" checked class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="set-uncategorized" class="font-medium text-gray-700">Set as uncategorized</label>
                                            </div>
                                        </div>
                                        
                                        <div class="relative flex items-start mt-2">
                                            <div class="flex items-center h-5">
                                                <input id="move-to-category" name="handle_books" type="radio" value="move" class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="move-to-category" class="font-medium text-gray-700">Move to another category</label>
                                            </div>
                                        </div>
                                        
                                        <div class="ml-7 mt-2" id="move-category-select-container">
                                            <select name="move_to_category" id="move-category-select" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                                                <?php foreach ($categoriesList as $cat): ?>
                                                    <?php if ($cat['category'] !== $editCategoryId): ?>
                                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="delete_category" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Delete
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm close-modal">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js for category distribution chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Main JavaScript -->
<script>
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
    
    // Mobile sidebar toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const closeSidebarButton = document.getElementById('close-sidebar');
    const mobileSidebarContainer = document.getElementById('mobile-sidebar-container');
    
    mobileMenuButton.addEventListener('click', () => {
        mobileSidebar.classList.remove('hidden');
        setTimeout(() => {
            mobileSidebarContainer.classList.remove('-translate-x-full');
        }, 50);
    });
    
    function closeMobileSidebar() {
        mobileSidebarContainer.classList.add('-translate-x-full');
        setTimeout(() => {
            mobileSidebar.classList.add('hidden');
        }, 300);
    }
    
    closeSidebarButton.addEventListener('click', closeMobileSidebar);
    mobileSidebar.addEventListener('click', (event) => {
        if (event.target === mobileSidebar) {
            closeMobileSidebar();
        }
    });
    
    // Modal functionality
    const modalToggles = document.querySelectorAll('[data-modal-toggle]');
    const closeModalButtons = document.querySelectorAll('.close-modal');
    
    modalToggles.forEach(button => {
        button.addEventListener('click', () => {
            const targetModal = document.getElementById(button.getAttribute('data-modal-toggle'));
            targetModal.classList.remove('hidden');
        });
    });
    
    closeModalButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('[id$="-modal"]');
            modal.classList.add('hidden');
        });
    });
    
    // Delete category confirmation
    function confirmDelete(categoryName) {
        const modal = document.getElementById('delete-category-modal');
        const categoryToDeleteSpan = document.getElementById('category-to-delete');
        const deleteCategoryIdInput = document.getElementById('delete-category-id');
        
        categoryToDeleteSpan.textContent = categoryName;
        deleteCategoryIdInput.value = categoryName;
        modal.classList.remove('hidden');
    }
    
    // Show/hide move category select based on radio selection
    const moveToCategory = document.getElementById('move-to-category');
    const moveCategorySelectContainer = document.getElementById('move-category-select-container');
    
    // Initially hide the select container
    moveCategorySelectContainer.style.display = 'none';
    
    document.querySelectorAll('input[name="handle_books"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (moveToCategory.checked) {
                moveCategorySelectContainer.style.display = 'block';
            } else {
                moveCategorySelectContainer.style.display = 'none';
            }
        });
    });
    
    // Category distribution chart
    const ctx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($categoriesList as $category): ?>
                    "<?php echo addslashes($category['category']); ?>",
                <?php endforeach; ?>
                "Uncategorized"
            ],
            datasets: [{
                label: 'Number of Books',
                data: [
                    <?php foreach ($categoriesList as $category): ?>
                        <?php echo $category['book_count']; ?>,
                    <?php endforeach; ?>
                    <?php echo $nullCategoryCount; ?>
                ],
                backgroundColor: [
                    <?php foreach ($categoriesList as $index => $category): ?>
                        'rgba(<?php echo 14 + ($index * 30) % 220; ?>, <?php echo 165 - ($index * 15) % 150; ?>, <?php echo 233 - ($index * 25) % 200; ?>, 0.8)',
                    <?php endforeach; ?>
                    'rgba(200, 200, 200, 0.8)'
                ],
                borderColor: [
                    <?php foreach ($categoriesList as $index => $category): ?>
                        'rgba(<?php echo 14 + ($index * 30) % 220; ?>, <?php echo 165 - ($index * 15) % 150; ?>, <?php echo 233 - ($index * 25) % 200; ?>, 1)',
                    <?php endforeach; ?>
                    'rgba(150, 150, 150, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>
</main>
</div>
</div>
</body>
</html>