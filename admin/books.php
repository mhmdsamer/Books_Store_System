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
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$message = '';
$error = '';

// Define available book categories
$categories = ['FICTION', 'NON-FICTION', 'SCIENCE', 'HISTORY', 'BIOGRAPHY', 'BUSINESS', 'CHILDREN', 'EDUCATION', 'TECHNOLOGY', 'ARTS', 'TRAVEL', 'HEALTH', 'ROMANCE', 'MYSTERY', 'HORROR', 'FANTASY', 'SCI-FI', 'COMICS', 'POETRY', 'RELIGION', 'COOKING', 'SPORTS', 'OTHER'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Validate input
                $title = trim($_POST['title']);
                $author = trim($_POST['author']);
                $isbn = trim($_POST['isbn']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $available_for_borrowing = isset($_POST['available_for_borrowing']) ? 1 : 0;
                $borrowing_price = $available_for_borrowing ? floatval($_POST['borrowing_price_per_day']) : null;
                $image_url = trim($_POST['image_url']);
                $published_date = $_POST['published_date'];
                $publisher = trim($_POST['publisher']);
                $category = trim($_POST['category']);

                // Basic validation
                if (empty($title) || empty($author) || empty($isbn) || $price <= 0) {
                    $error = "Please fill in all required fields properly!";
                } else {
                    // Check if ISBN already exists
                    $checkIsbn = $conn->prepare("SELECT book_id FROM books WHERE isbn = ?");
                    $checkIsbn->bind_param("s", $isbn);
                    $checkIsbn->execute();
                    $isbnResult = $checkIsbn->get_result();
                    
                    if ($isbnResult->num_rows > 0) {
                        $error = "This ISBN already exists in the database!";
                    } else {
                        // Insert new book
                        $insertSQL = "INSERT INTO books (title, author, isbn, description, price, stock_quantity, 
                                    available_for_borrowing, borrowing_price_per_day, image_url, published_date, 
                                    publisher, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($insertSQL);
                        $stmt->bind_param("ssssdiidssss", $title, $author, $isbn, $description, $price, 
                                        $stock_quantity, $available_for_borrowing, $borrowing_price, 
                                        $image_url, $published_date, $publisher, $category);
                        
                        if ($stmt->execute()) {
                            $message = "Book added successfully!";
                            // Redirect to book list after successful add
                            header("Location: books.php?message=" . urlencode($message));
                            exit();
                        } else {
                            $error = "Error adding book: " . $conn->error;
                        }
                    }
                }
                break;
                
            case 'edit':
                $book_id = intval($_POST['book_id']);
                $title = trim($_POST['title']);
                $author = trim($_POST['author']);
                $isbn = trim($_POST['isbn']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $available_for_borrowing = isset($_POST['available_for_borrowing']) ? 1 : 0;
                $borrowing_price = $available_for_borrowing ? floatval($_POST['borrowing_price_per_day']) : null;
                $image_url = trim($_POST['image_url']);
                $published_date = $_POST['published_date'];
                $publisher = trim($_POST['publisher']);
                $category = trim($_POST['category']);

                // Basic validation
                if (empty($title) || empty($author) || empty($isbn) || $price <= 0) {
                    $error = "Please fill in all required fields properly!";
                } else {
                    // Check if ISBN already exists (except for the current book)
                    $checkIsbn = $conn->prepare("SELECT book_id FROM books WHERE isbn = ? AND book_id != ?");
                    $checkIsbn->bind_param("si", $isbn, $book_id);
                    $checkIsbn->execute();
                    $isbnResult = $checkIsbn->get_result();
                    
                    if ($isbnResult->num_rows > 0) {
                        $error = "This ISBN already exists for another book!";
                    } else {
                        // Update book
                        $updateSQL = "UPDATE books SET 
                                    title = ?, 
                                    author = ?, 
                                    isbn = ?, 
                                    description = ?, 
                                    price = ?, 
                                    stock_quantity = ?, 
                                    available_for_borrowing = ?, 
                                    borrowing_price_per_day = ?, 
                                    image_url = ?, 
                                    published_date = ?, 
                                    publisher = ?, 
                                    category = ? 
                                WHERE book_id = ?";
                        
                        $stmt = $conn->prepare($updateSQL);
                        $stmt->bind_param("ssssdiiisssi", $title, $author, $isbn, $description, $price, 
                                        $stock_quantity, $available_for_borrowing, $borrowing_price, 
                                        $image_url, $published_date, $publisher, $category, $book_id);
                        
                        if ($stmt->execute()) {
                            $message = "Book updated successfully!";
                            // Redirect to book list after successful update
                            header("Location: books.php?message=" . urlencode($message));
                            exit();
                        } else {
                            $error = "Error updating book: " . $conn->error;
                        }
                    }
                }
                break;
                
            case 'delete':
                $book_id = intval($_POST['book_id']);
                
                // Check if the book is in any orders or borrowings
                $checkOrdersSQL = "SELECT COUNT(*) as count FROM order_items WHERE book_id = ?";
                $checkOrdersStmt = $conn->prepare($checkOrdersSQL);
                $checkOrdersStmt->bind_param("i", $book_id);
                $checkOrdersStmt->execute();
                $ordersResult = $checkOrdersStmt->get_result()->fetch_assoc();
                
                $checkBorrowingsSQL = "SELECT COUNT(*) as count FROM borrowings WHERE book_id = ?";
                $checkBorrowingsStmt = $conn->prepare($checkBorrowingsSQL);
                $checkBorrowingsStmt->bind_param("i", $book_id);
                $checkBorrowingsStmt->execute();
                $borrowingsResult = $checkBorrowingsStmt->get_result()->fetch_assoc();
                
                if ($ordersResult['count'] > 0 || $borrowingsResult['count'] > 0) {
                    $error = "Cannot delete this book as it is referenced in orders or borrowings.";
                } else {
                    // Delete the book
                    $deleteSQL = "DELETE FROM books WHERE book_id = ?";
                    $stmt = $conn->prepare($deleteSQL);
                    $stmt->bind_param("i", $book_id);
                    
                    if ($stmt->execute()) {
                        $message = "Book deleted successfully!";
                        // Redirect to book list after successful delete
                        header("Location: books.php?message=" . urlencode($message));
                        exit();
                    } else {
                        $error = "Error deleting book: " . $conn->error;
                    }
                }
                break;
                
            case 'restock':
                $book_id = intval($_POST['book_id']);
                $quantity = intval($_POST['quantity']);
                
                if ($quantity <= 0) {
                    $error = "Please provide a valid quantity greater than zero.";
                } else {
                    // Update stock quantity
                    $updateSQL = "UPDATE books SET stock_quantity = stock_quantity + ? WHERE book_id = ?";
                    $stmt = $conn->prepare($updateSQL);
                    $stmt->bind_param("ii", $quantity, $book_id);
                    
                    if ($stmt->execute()) {
                        $message = "Stock updated successfully!";
                        // Redirect to book list after successful update
                        header("Location: books.php?message=" . urlencode($message));
                        exit();
                    } else {
                        $error = "Error updating stock: " . $conn->error;
                    }
                }
                break;
        }
    }
}

// Get the requested book data if editing, restocking, or viewing
$bookData = null;
if (in_array($action, ['edit', 'view', 'restock']) && $book_id > 0) {
    $bookQuery = "SELECT * FROM books WHERE book_id = ?";
    $stmt = $conn->prepare($bookQuery);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $bookData = $result->fetch_assoc();
    } else {
        $error = "Book not found!";
        $action = 'list'; // Revert to list view if book not found
    }
}

// Prepare books query based on filter and search
$whereClause = "";
$params = [];
$paramTypes = "";

if ($filter === 'low_stock') {
    $whereClause = "WHERE stock_quantity < 5";
} elseif (!empty($search)) {
    $whereClause = "WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?";
    $searchTerm = "%" . $search . "%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $paramTypes = "sss";
}

// Get total number of books (for pagination)
$countSQL = "SELECT COUNT(*) as total FROM books " . $whereClause;
$totalBooks = 0;

if (empty($params)) {
    $countResult = $conn->query($countSQL);
} else {
    $countStmt = $conn->prepare($countSQL);
    $countStmt->bind_param($paramTypes, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
}

if ($countResult) {
    $totalBooks = $countResult->fetch_assoc()['total'];
}

// Pagination
$booksPerPage = 10;
$totalPages = ceil($totalBooks / $booksPerPage);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $booksPerPage;

// Get books for current page
$booksSQL = "SELECT * FROM books " . $whereClause . " ORDER BY title ASC LIMIT ?, ?";
$params[] = $offset;
$params[] = $booksPerPage;
$paramTypes .= "ii";

$stmt = $conn->prepare($booksSQL);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$booksResult = $stmt->get_result();

// Get message from URL if redirected
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Management - BookStore</title>
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
                    <a href="books.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <form action="books.php" method="get" class="flex-1 relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </span>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search for books...">
                                <button type="submit" class="absolute inset-y-0 right-0 px-4 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-search"></i>
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
                                        <span class="text-sm font-medium uppercase"><?php echo isset($_SESSION['first_name']) ? substr($_SESSION['first_name'], 0, 1) : 'A'; ?></span>
                                    </div>
                                    <div class="hidden md:block text-left">
                                        <h5 class="text-sm font-medium text-gray-700"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin User'; ?></h5>
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
                            <a href="books.php" class="flex items-center space-x-2 px-4 py-3 text-gray-700 nav-link active">
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
                            <?php if ($action === 'list'): ?>
                                <h1 class="text-2xl font-bold text-gray-800 font-display">Books Management</h1>
                                <p class="mt-1 text-sm text-gray-600">Manage your bookstore inventory</p>
                            <?php elseif ($action === 'add'): ?>
                                <h1 class="text-2xl font-bold text-gray-800 font-display">Add New Book</h1>
                                <p class="mt-1 text-sm text-gray-600">Add a new book to your inventory</p>
                            <?php elseif ($action === 'edit'): ?>
                                <h1 class="text-2xl font-bold text-gray-800 font-display">Edit Book</h1>
                                <p class="mt-1 text-sm text-gray-600">Update book information</p>
                            <?php elseif ($action === 'view'): ?>
                                <h1 class="text-2xl font-bold text-gray-800 font-display">Book Details</h1>
                                <p class="mt-1 text-sm text-gray-600">View comprehensive book information</p>
                            <?php elseif ($action === 'restock'): ?>
                                <h1 class="text-2xl font-bold text-gray-800 font-display">Restock Book</h1>
                                <p class="mt-1 text-sm text-gray-600">Add inventory to existing book</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <?php if ($action === 'list'): ?>
                                <a href="books.php?action=add" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-300 flex items-center">
                                    <i class="fas fa-plus mr-2"></i> Add New Book
                                </a>
                                <div class="relative">
                                    <button id="filter-button" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-300 flex items-center">
                                        <i class="fas fa-filter mr-2 text-gray-600"></i> Filter
                                        <i class="fas fa-chevron-down ml-2 text-xs text-gray-500"></i>
                                    </button>
                                    <div id="filter-dropdown" class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-10">
                                        <a href="books.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            All Books
                                        </a>
                                        <a href="books.php?filter=low_stock" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Low Stock (<5)
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <a href="books.php" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-300 flex items-center">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Mobile Search -->
                    <div class="md:hidden mb-6">
                        <form action="books.php" method="get" class="flex">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Search for books...">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-r-lg hover:bg-primary-700">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Notifications -->
                    <?php if (!empty($message)): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-3 text-green-500"></i>
                                <p><?php echo $message; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
                                <p><?php echo $error; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Main Content -->
                    <?php if ($action === 'list'): ?>
                        <!-- Books List -->
                        <div class="bg-white rounded-lg shadow-soft overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if ($booksResult->num_rows > 0): ?>
                                            <?php while($book = $booksResult->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="h-12 w-10 flex-shrink-0 mr-3">
                                                                <?php if (!empty($book['image_url'])): ?>
                                                                    <img class="h-12 object-cover rounded" src="<?php echo htmlspecialchars($book['image_url']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                                                <?php else: ?>
                                                                    <div class="h-12 w-10 flex items-center justify-center bg-gray-200 rounded">
                                                                        <i class="fas fa-book text-gray-400"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($book['title']); ?></div>
                                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($book['author']); ?></div>
                                                                <div class="text-xs text-gray-400">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-accent-100 text-accent-800">
                                                            <?php echo htmlspecialchars($book['category']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">$<?php echo number_format($book['price'], 2); ?></div>
                                                        <?php if ($book['available_for_borrowing']): ?>
                                                            <div class="text-xs text-gray-500">Borrow: $<?php echo number_format($book['borrowing_price_per_day'], 2); ?>/day</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($book['stock_quantity'] < 5): ?>
                                                            <span class="text-sm font-medium text-red-500"><?php echo $book['stock_quantity']; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-sm text-gray-900"><?php echo $book['stock_quantity']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($book['stock_quantity'] > 0): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                In Stock
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                                Out of Stock
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <div class="flex justify-end space-x-2">
                                                            <a href="books.php?action=view&id=<?php echo $book['book_id']; ?>" class="text-gray-500 hover:text-gray-700" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="books.php?action=edit&id=<?php echo $book['book_id']; ?>" class="text-blue-500 hover:text-blue-700" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="books.php?action=restock&id=<?php echo $book['book_id']; ?>" class="text-green-500 hover:text-green-700" title="Restock">
                                                                <i class="fas fa-plus-circle"></i>
                                                            </a>
                                                            <form action="books.php" method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                                <button type="submit" class="text-red-500 hover:text-red-700" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                                    <div class="flex flex-col items-center justify-center py-8">
                                                        <i class="fas fa-book-open text-4xl text-gray-300 mb-3"></i>
                                                        <p class="text-lg font-medium">No books found</p>
                                                        <?php if (!empty($search)): ?>
                                                            <p class="text-sm text-gray-500 mt-1">Try adjusting your search or filter to find what you're looking for.</p>
                                                            <a href="books.php" class="mt-3 text-primary-600 hover:text-primary-700">Clear search</a>
                                                        <?php else: ?>
                                                            <p class="text-sm text-gray-500 mt-1">Get started by adding books to your inventory.</p>
                                                            <a href="books.php?action=add" class="mt-3 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-300">
                                                                <i class="fas fa-plus mr-2"></i> Add New Book
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="flex items-center justify-between mt-6">
                                <div class="text-sm text-gray-500">
                                    Showing <span class="font-medium"><?php echo ($page - 1) * $booksPerPage + 1; ?></span> to 
                                    <span class="font-medium"><?php echo min($page * $booksPerPage, $totalBooks); ?></span> of 
                                    <span class="font-medium"><?php echo $totalBooks; ?></span> books
                                </div>
                                <div class="flex space-x-1">
                                    <?php if ($page > 1): ?>
                                        <a href="books.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<span class="px-3 py-1 text-gray-500">...</span>';
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $activeClass = ($i == $page) ? 'bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
                                        echo '<a href="books.php?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($filter) ? '&filter=' . urlencode($filter) : '') . '" class="px-3 py-1 border rounded-md text-sm font-medium ' . $activeClass . '">' . $i . '</a>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        echo '<span class="px-3 py-1 text-gray-500">...</span>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="books.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            Next
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    
                    <?php elseif ($action === 'add' || $action === 'edit'): ?>
                        <!-- Add/Edit Book Form -->
                        <div class="bg-white rounded-lg shadow-soft p-6">
                            <form action="books.php" method="post" class="space-y-6">
                                <input type="hidden" name="action" value="<?php echo $action === 'add' ? 'add' : 'edit'; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="book_id" value="<?php echo $bookData['book_id']; ?>">
                                <?php endif; ?>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Book Title -->
                                    <div>
                                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Book Title *</label>
                                        <input type="text" name="title" id="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['title']) ? htmlspecialchars($bookData['title']) : ''; ?>">
                                    </div>
                                    
                                    <!-- Author -->
                                    <div>
                                        <label for="author" class="block text-sm font-medium text-gray-700 mb-1">Author *</label>
                                        <input type="text" name="author" id="author" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['author']) ? htmlspecialchars($bookData['author']) : ''; ?>">
                                    </div>
                                    
                                    <!-- ISBN -->
                                    <div>
                                        <label for="isbn" class="block text-sm font-medium text-gray-700 mb-1">ISBN *</label>
                                        <input type="text" name="isbn" id="isbn" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['isbn']) ? htmlspecialchars($bookData['isbn']) : ''; ?>">
                                    </div>
                                    
                                    <!-- Category -->
                                    <div>
                                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                                        <select name="category" id="category" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category; ?>" <?php echo (isset($bookData['category']) && $bookData['category'] === $category) ? 'selected' : ''; ?>>
                                                    <?php echo $category; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div>
                                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price ($) *</label>
                                        <input type="number" name="price" id="price" step="0.01" min="0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['price']) ? htmlspecialchars($bookData['price']) : ''; ?>">
                                    </div>
                                    
                                    <!-- Stock Quantity -->
                                    <div>
                                        <label for="stock_quantity" class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity *</label>
                                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['stock_quantity']) ? htmlspecialchars($bookData['stock_quantity']) : ''; ?>">
                                    </div>
                                    
                                    <!-- Publisher -->
                                    <div>
                                        <label for="publisher" class="block text-sm font-medium text-gray-700 mb-1">Publisher</label>
                                        <input type="text" name="publisher" id="publisher" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['publisher']) ? htmlspecialchars($bookData['publisher']) : ''; ?>">
                                    </div>
                                    
                                    <!-- Published Date -->
                                    <div>
                                        <label for="published_date" class="block text-sm font-medium text-gray-700 mb-1">Publication Date</label>
                                        <input type="date" name="published_date" id="published_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['published_date']) ? htmlspecialchars($bookData['published_date']) : ''; ?>">
                                    </div>
                                    
                                    <!-- Image URL -->
                                    <div>
                                        <label for="image_url" class="block text-sm font-medium text-gray-700 mb-1">Cover Image URL</label>
                                        <input type="url" name="image_url" id="image_url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['image_url']) ? htmlspecialchars($bookData['image_url']) : ''; ?>">
                                    </div>
                                    
                                    <!-- Available for Borrowing -->
                                    <div>
                                        <div class="flex items-center">
                                            <input type="checkbox" name="available_for_borrowing" id="available_for_borrowing" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded" <?php echo (isset($bookData['available_for_borrowing']) && $bookData['available_for_borrowing']) ? 'checked' : ''; ?>>
                                            <label for="available_for_borrowing" class="ml-2 block text-sm font-medium text-gray-700">Available for Borrowing</label>
                                        </div>
                                    </div>
                                    
                                    <!-- Borrowing Price -->
                                    <div id="borrowing_price_container" class="<?php echo (isset($bookData['available_for_borrowing']) && !$bookData['available_for_borrowing']) ? 'hidden' : ''; ?>">
                                        <label for="borrowing_price_per_day" class="block text-sm font-medium text-gray-700 mb-1">Borrowing Price Per Day ($)</label>
                                        <input type="number" name="borrowing_price_per_day" id="borrowing_price_per_day" step="0.01" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="<?php echo isset($bookData['borrowing_price_per_day']) ? htmlspecialchars($bookData['borrowing_price_per_day']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <!-- Description -->
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea name="description" id="description" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"><?php echo isset($bookData['description']) ? htmlspecialchars($bookData['description']) : ''; ?></textarea>
                                </div>
                                
                                <div class="flex justify-end space-x-3">
                                    <a href="books.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
                                        Cancel
                                    </a>
                                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-300">
                                        <?php echo $action === 'add' ? 'Add Book' : 'Save Changes'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    
                    <?php elseif ($action === 'view'): ?>
                        <!-- View Book Details -->
                        <div class="bg-white rounded-lg shadow-soft p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                <!-- Book Cover -->
                                <div class="flex flex-col items-center">
                                    <?php if (!empty($bookData['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($bookData['image_url']); ?>" alt="<?php echo htmlspecialchars($bookData['title']); ?>" class="w-full max-w-[200px] h-auto rounded-lg shadow-soft">
                                    <?php else: ?>
                                        <div class="w-full max-w-[200px] h-[300px] bg-gray-200 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-book text-5xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-6 flex space-x-3">
                                        <a href="books.php?action=edit&id=<?php echo $bookData['book_id']; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-300 flex items-center">
                                            <i class="fas fa-edit mr-2"></i> Edit
                                        </a>
                                        <a href="books.php?action=restock&id=<?php echo $bookData['book_id']; ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300 flex items-center">
                                            <i class="fas fa-plus-circle mr-2"></i> Restock
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Book Details -->
                                <div class="md:col-span-2">
                                    <h2 class="text-2xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($bookData['title']); ?></h2>
                                    <p class="text-lg text-gray-600 mb-4">by <?php echo htmlspecialchars($bookData['author']); ?></p>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                        <div>
                                            <p class="text-sm text-gray-500">ISBN</p>
                                            <p class="text-base text-gray-800"><?php echo htmlspecialchars($bookData['isbn']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Category</p>
                                            <p class="text-base text-gray-800">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-accent-100 text-accent-800">
                                                    <?php echo htmlspecialchars($bookData['category']); ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Publisher</p>
                                            <p class="text-base text-gray-800"><?php echo !empty($bookData['publisher']) ? htmlspecialchars($bookData['publisher']) : 'N/A'; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Publication Date</p>
                                            <p class="text-base text-gray-800"><?php echo !empty($bookData['published_date']) ? date('F j, Y', strtotime($bookData['published_date'])) : 'N/A'; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Price</p>
                                            <p class="text-base text-gray-800 font-semibold">$<?php echo number_format($bookData['price'], 2); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Stock</p>
                                            <p class="text-base <?php echo $bookData['stock_quantity'] < 5 ? 'text-red-600 font-semibold' : 'text-gray-800'; ?>">
                                            <?php echo $bookData['stock_quantity']; ?> units
                                                <?php if ($bookData['stock_quantity'] < 5): ?>
                                                    <span class="text-xs text-red-600">(Low stock)</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Borrowing</p>
                                            <p class="text-base text-gray-800">
                                                <?php if ($bookData['available_for_borrowing']): ?>
                                                    Available ($<?php echo number_format($bookData['borrowing_price_per_day'], 2); ?>/day)
                                                <?php else: ?>
                                                    Not available for borrowing
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Status</p>
                                            <p class="text-base text-gray-800">
                                                <?php if ($bookData['stock_quantity'] > 0): ?>
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        In Stock
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        Out of Stock
                                                    </span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-6">
                                        <p class="text-sm text-gray-500 mb-1">Description</p>
                                        <div class="p-4 bg-gray-50 rounded-lg">
                                            <?php echo !empty($bookData['description']) ? nl2br(htmlspecialchars($bookData['description'])) : 'No description available.'; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="border-t border-gray-200 pt-4">
                                        <div class="flex justify-between items-center text-sm text-gray-500">
                                            <div>Book ID: <?php echo $bookData['book_id']; ?></div>
                                            <div>Last updated: <?php echo date('M j, Y', strtotime($bookData['updated_at'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                    <?php elseif ($action === 'restock'): ?>
                        <!-- Restock Book Form -->
                        <div class="bg-white rounded-lg shadow-soft p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                <!-- Book Info -->
                                <div class="flex flex-col items-center">
                                    <?php if (!empty($bookData['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($bookData['image_url']); ?>" alt="<?php echo htmlspecialchars($bookData['title']); ?>" class="w-full max-w-[150px] h-auto rounded-lg shadow-soft">
                                    <?php else: ?>
                                        <div class="w-full max-w-[150px] h-[225px] bg-gray-200 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-book text-4xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-4 text-center">
                                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($bookData['title']); ?></h3>
                                        <p class="text-sm text-gray-600">by <?php echo htmlspecialchars($bookData['author']); ?></p>
                                    </div>
                                </div>
                                
                                <!-- Restock Form -->
                                <div class="md:col-span-2">
                                    <div class="mb-6">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-lg font-semibold text-gray-800">Current Stock</h3>
                                            <span class="<?php echo $bookData['stock_quantity'] < 5 ? 'text-red-600 font-semibold' : 'text-gray-700'; ?>">
                                                <?php echo $bookData['stock_quantity']; ?> units
                                            </span>
                                        </div>
                                        <div class="mt-2 h-3 bg-gray-200 rounded-full overflow-hidden">
                                            <?php
                                            // Calculate percentage (assuming 100 is full stock)
                                            $stockPercentage = min(100, ($bookData['stock_quantity'] / 20) * 100);
                                            $barColor = $bookData['stock_quantity'] < 5 ? 'bg-red-500' : 'bg-green-500';
                                            ?>
                                            <div class="<?php echo $barColor; ?> h-full" style="width: <?php echo $stockPercentage; ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <form action="books.php" method="post" class="space-y-6">
                                        <input type="hidden" name="action" value="restock">
                                        <input type="hidden" name="book_id" value="<?php echo $bookData['book_id']; ?>">
                                        
                                        <div>
                                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Add Quantity *</label>
                                            <input type="number" name="quantity" id="quantity" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" value="1">
                                            <p class="mt-1 text-xs text-gray-500">Enter the number of units to add to the current stock.</p>
                                        </div>
                                        
                                        <div class="flex justify-end space-x-3">
                                            <a href="books.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
                                                Cancel
                                            </a>
                                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
                                                <i class="fas fa-plus-circle mr-2"></i> Add to Stock
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle
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
        
        // User Dropdown Toggle
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
        
        // Filter Dropdown Toggle
        const filterButton = document.getElementById('filter-button');
        const filterDropdown = document.getElementById('filter-dropdown');
        
        if (filterButton) {
            filterButton.addEventListener('click', () => {
                filterDropdown.classList.toggle('show');
            });
            
            // Close filter dropdown when clicking outside
            document.addEventListener('click', (event) => {
                if (!filterButton.contains(event.target) && !filterDropdown.contains(event.target)) {
                    filterDropdown.classList.remove('show');
                }
            });
        }
        
        // Toggle borrowing price field based on checkbox
        const availableForBorrowingCheckbox = document.getElementById('available_for_borrowing');
        const borrowingPriceContainer = document.getElementById('borrowing_price_container');
        
        if (availableForBorrowingCheckbox && borrowingPriceContainer) {
            availableForBorrowingCheckbox.addEventListener('change', () => {
                if (availableForBorrowingCheckbox.checked) {
                    borrowingPriceContainer.classList.remove('hidden');
                } else {
                    borrowingPriceContainer.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>