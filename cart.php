<?php
// Include database connection
require_once 'connection.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php?redirect=cart.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle quantity updates
if (isset($_POST['update_quantity']) && isset($_POST['cart_item_id']) && isset($_POST['quantity'])) {
    $cart_item_id = (int)$_POST['cart_item_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0) {
        // Update quantity
        $update_query = "UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("iii", $quantity, $cart_item_id, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: cart.php?updated=1");
    exit();
}

// Handle item removal
if (isset($_GET['remove']) && !empty($_GET['remove'])) {
    $cart_item_id = (int)$_GET['remove'];
    
    // Delete cart item
    $delete_query = "DELETE FROM cart_items WHERE cart_item_id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $cart_item_id, $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Redirect to prevent double deletion
    header("Location: cart.php?removed=1");
    exit();
}

// Get purchase cart items (is_purchase = 1)
$purchase_query = "SELECT ci.cart_item_id, ci.quantity, b.book_id, b.title, b.author, b.price, b.image_url, b.stock_quantity 
                  FROM cart_items ci 
                  JOIN books b ON ci.book_id = b.book_id 
                  WHERE ci.user_id = ? AND ci.is_purchase = 1";
$purchase_stmt = $conn->prepare($purchase_query);
$purchase_stmt->bind_param("i", $user_id);
$purchase_stmt->execute();
$purchase_result = $purchase_stmt->get_result();

$purchase_items = [];
$purchase_total = 0;

if ($purchase_result && $purchase_result->num_rows > 0) {
    while ($row = $purchase_result->fetch_assoc()) {
        $purchase_items[] = $row;
        $purchase_total += $row['price'] * $row['quantity'];
    }
}
$purchase_stmt->close();

// Get borrowing cart items (is_purchase = 0)
$borrow_query = "SELECT ci.cart_item_id, ci.quantity, b.book_id, b.title, b.author, 
                b.borrowing_price_per_day, b.image_url, b.available_for_borrowing 
                FROM cart_items ci 
                JOIN books b ON ci.book_id = b.book_id 
                WHERE ci.user_id = ? AND ci.is_purchase = 0";
$borrow_stmt = $conn->prepare($borrow_query);
$borrow_stmt->bind_param("i", $user_id);
$borrow_stmt->execute();
$borrow_result = $borrow_stmt->get_result();

$borrow_items = [];
$borrowing_days = isset($_SESSION['borrowing_days']) ? $_SESSION['borrowing_days'] : 7; // Default to 7 days

if ($borrow_result && $borrow_result->num_rows > 0) {
    while ($row = $borrow_result->fetch_assoc()) {
        $row['borrowing_days'] = $borrowing_days;
        $row['total_borrowing_fee'] = $row['borrowing_price_per_day'] * $borrowing_days;
        $borrow_items[] = $row;
    }
}
$borrow_stmt->close();

// Calculate borrowing total
$borrowing_total = 0;
foreach ($borrow_items as $item) {
    $borrowing_total += $item['total_borrowing_fee'];
}

// Update borrowing days if changed
if (isset($_POST['update_borrowing_days']) && isset($_POST['borrowing_days'])) {
    $borrowing_days = (int)$_POST['borrowing_days'];
    if ($borrowing_days >= 1) {
        $_SESSION['borrowing_days'] = $borrowing_days;
        
        // Redirect to refresh page with new calculations
        header("Location: cart.php?updated=1");
        exit();
    }
}

// Proceed to purchase checkout
if (isset($_POST['checkout']) && count($purchase_items) > 0) {
    // Create a new order
    $shipping_address = "Default Address"; // This would normally come from a form or user profile
    $payment_method = "Credit Card"; // This would normally come from a form
    
    // Insert order into the orders table
    $order_query = "INSERT INTO orders (user_id, total_amount, status, shipping_address, payment_method) 
                    VALUES (?, ?, 'pending', ?, ?)";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("idss", $user_id, $purchase_total, $shipping_address, $payment_method);
    
    if ($order_stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Insert each item into order_items
        $item_query = "INSERT INTO order_items (order_id, book_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)";
        $item_stmt = $conn->prepare($item_query);
        
        $success = true;
        foreach ($purchase_items as $item) {
            $item_stmt->bind_param("iiid", $order_id, $item['book_id'], $item['quantity'], $item['price']);
            if (!$item_stmt->execute()) {
                $success = false;
                break;
            }
            
            // Update book stock quantity
            $update_stock_query = "UPDATE books SET stock_quantity = stock_quantity - ? WHERE book_id = ? AND stock_quantity >= ?";
            $update_stock_stmt = $conn->prepare($update_stock_query);
            $update_stock_stmt->bind_param("iii", $item['quantity'], $item['book_id'], $item['quantity']);
            $update_stock_stmt->execute();
            $update_stock_stmt->close();
        }
        
        $item_stmt->close();
        
        if ($success) {
            // Remove items from cart
            $remove_cart_query = "DELETE FROM cart_items WHERE user_id = ? AND is_purchase = 1";
            $remove_cart_stmt = $conn->prepare($remove_cart_query);
            $remove_cart_stmt->bind_param("i", $user_id);
            $remove_cart_stmt->execute();
            $remove_cart_stmt->close();
            
            // Redirect to order confirmation page
            $_SESSION['order_id'] = $order_id;
            header("Location: checkout.php?order_id=".$order_id);
            exit();
        } else {
            // Handle error
            header("Location: cart.php?error=order_items");
            exit();
        }
    } else {
        // Handle error
        header("Location: cart.php?error=order");
        exit();
    }
    
    $order_stmt->close();
}

// Proceed to borrowing checkout
if (isset($_POST['borrow_checkout']) && count($borrow_items) > 0) {
    // Set borrowing days in session for checkout process
    $_SESSION['borrowing_days'] = $borrowing_days;
    
    // Current timestamp
    $current_time = date('Y-m-d H:i:s');
    
    // Calculate expected return date
    $expected_return_date = date('Y-m-d H:i:s', strtotime('+' . $borrowing_days . ' days'));
    
    // Transaction success flag
    $success = true;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        foreach ($borrow_items as $item) {
            // Check if book is available for borrowing
            $check_query = "SELECT available_for_borrowing FROM books WHERE book_id = ? AND available_for_borrowing >= ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ii", $item['book_id'], $item['quantity']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_stmt->close();
            
            if ($check_result->num_rows == 0) {
                throw new Exception("Book not available for borrowing");
            }
            
            // Insert into borrowings table
            $borrowing_query = "INSERT INTO borrowings (user_id, book_id, borrow_date, expected_return_date, status, daily_rate, total_fee) 
                               VALUES (?, ?, ?, ?, 'active', ?, ?)";
            $borrowing_stmt = $conn->prepare($borrowing_query);
            $daily_rate = $item['borrowing_price_per_day'];
            $total_fee = $item['total_borrowing_fee'];
            
            $borrowing_stmt->bind_param("iissdd", $user_id, $item['book_id'], $current_time, $expected_return_date, $daily_rate, $total_fee);
            if (!$borrowing_stmt->execute()) {
                throw new Exception("Failed to create borrowing record");
            }
            $borrowing_stmt->close();
            
            // Update book available_for_borrowing count
            $update_query = "UPDATE books SET available_for_borrowing = available_for_borrowing - ? WHERE book_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $item['quantity'], $item['book_id']);
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update book availability");
            }
            $update_stmt->close();
        }
        
        // Remove borrowing items from cart
        $remove_cart_query = "DELETE FROM cart_items WHERE user_id = ? AND is_purchase = 0";
        $remove_cart_stmt = $conn->prepare($remove_cart_query);
        $remove_cart_stmt->bind_param("i", $user_id);
        if (!$remove_cart_stmt->execute()) {
            throw new Exception("Failed to clear borrowing items from cart");
        }
        $remove_cart_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to borrowing confirmation page
        header("Location: borrowing-checkout.php?success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        header("Location: cart.php?error=borrowing&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// Update cart count in session
$_SESSION['cart_count'] = count($purchase_items);

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - BookStore</title>
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
                }
            }
        }
    </script>
    <style>
        .quantity-input::-webkit-inner-spin-button,
        .quantity-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .quantity-input {
            -moz-appearance: textfield;
        }
        
        .cart-animation {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .cart-item {
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            background-color: rgba(240, 249, 255, 0.5);
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
                    <a href="cart.php" class="text-primary-600 p-1 relative">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span class="absolute -top-2 -right-2 bg-primary-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : '0'; ?>
                        </span>
                    </a>
                    
                    <!-- User Menu -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
                                <span class="text-primary-700 font-medium text-sm">
                                    <?php echo substr($_SESSION['first_name'] ?? $_SESSION['username'], 0, 1); ?>
                                </span>
                            </div>
                            <span class="hidden sm:inline-block font-medium text-gray-700">
                                <?php echo $_SESSION['first_name'] ?? $_SESSION['username']; ?>
                            </span>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50" style="display: none;">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Dashboard</a>
                            <div class="border-b border-gray-200"></div>
                            <?php endif; ?>
                            
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                            <a href="orders.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Orders</a>
                            <a href="borrowings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Borrowings</a>
                            <div class="border-b border-gray-200"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Sign Out</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="space-x-2 flex items-center">
                        <a href="login.php" class="hidden sm:block text-primary-600 hover:text-primary-700 font-medium transition-colors">Sign In</a>
                        <a href="signup.php" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">Sign Up</a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Mobile Menu Toggle -->
                    <button id="mobile-menu-toggle" class="md:hidden text-gray-600 hover:text-primary-600 transition-colors p-1">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Search Bar (Hidden by default) -->
            <div id="search-bar" class="pb-4 hidden">
                <form action="books.php" method="GET" class="relative">
                    <input type="text" name="q" placeholder="Search for books, authors, or categories..." 
                           class="w-full py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </form>
            </div>
        </div>
    </header>
    
    <!-- Mobile Menu (Off-canvas) -->
    <div id="mobile-menu" class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 md:hidden transform -translate-x-full transition-transform duration-300">
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
                
                <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="border-t border-gray-200 my-4 pt-4">
                    <a href="login.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors">Sign In</a>
                    <a href="signup.php" class="block font-medium text-gray-600 hover:text-primary-600 transition-colors mt-2">Sign Up</a>
                </div>
                <?php endif; ?>
            </nav>
        </div>
    </div>
    
    <!-- Notification Messages -->
    <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
    <div class="fixed top-20 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md z-50 cart-animation" id="update-notification">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm">Cart successfully updated!</p>
            </div>
            <button class="ml-auto" onclick="document.getElementById('update-notification').remove();">
                <i class="fas fa-times text-green-500"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
    <div class="fixed top-20 right-4 bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded shadow-md z-50 cart-animation" id="remove-notification">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm">Item removed from cart.</p>
            </div>
            <button class="ml-auto" onclick="document.getElementById('remove-notification').remove();">
                <i class="fas fa-times text-blue-500"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="fixed top-20 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md z-50 cart-animation" id="error-notification">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm">
                    <?php 
                    $error_type = $_GET['error'];
                    $error_message = isset($_GET['message']) ? $_GET['message'] : '';
                    
                    if ($error_type == 'order') {
                        echo "Error creating order. Please try again.";
                    } elseif ($error_type == 'order_items') {
                        echo "Error adding items to order. Please try again.";
                    } elseif ($error_type == 'borrowing') {
                        echo "Error processing borrowing: " . htmlspecialchars($error_message);
                    } else {
                        echo "An error occurred. Please try again.";
                    }
                    ?>
                </p>
            </div>
            <button class="ml-auto" onclick="document.getElementById('error-notification').remove();">
                <i class="fas fa-times text-red-500"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <section class="bg-gradient-to-r from-primary-900 to-primary-700 text-white py-10">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl md:text-4xl font-bold font-display mb-4">Your Cart</h1>
            <div class="flex items-center">
                <a href="index.php" class="text-primary-100 hover:text-white transition-colors">Home</a>
                <i class="fas fa-chevron-right text-xs mx-2 text-primary-200"></i>
                <span class="text-white">Cart</span>
            </div>
        </div>
    </section>
    
    <!-- Main Content -->
    <section class="py-10">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Purchase Cart -->
            <div class="mb-10 cart-animation">
                <div class="flex items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Shopping Cart</h2>
                    <span class="ml-2 bg-primary-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo count($purchase_items); ?></span>
                </div>
                
                <?php if (count($purchase_items) > 0): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- Cart Items -->
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($purchase_items as $item): ?>
                        <div class="cart-item p-4 sm:p-6 flex flex-col sm:flex-row items-start">
                            <div class="flex-shrink-0 w-full sm:w-24 h-24 mb-4 sm:mb-0">
                                <img class="w-full h-full object-cover rounded-md" 
                                     src="<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'assets/images/book-placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>">
                            </div>
                            
                            <div class="flex-grow sm:ml-6">
                                <div class="flex flex-col sm:flex-row sm:justify-between">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-800">
                                            <a href="book-details.php?id=<?php echo $item['book_id']; ?>" class="hover:text-primary-600 transition-colors">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </h3>
                                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($item['author']); ?></p>
                                        
                                        <?php if ($item['quantity'] > $item['stock_quantity']): ?>
                                        <p class="text-red-500 text-sm mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i> 
                                            Only <?php echo $item['stock_quantity']; ?> available
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-4 sm:mt-0">
                                        <p class="text-primary-600 font-bold">$<?php echo number_format($item['price'], 2); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mt-4">
                                    <form method="post" action="cart.php" class="flex items-center">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                        <button type="button" class="quantity-btn minus bg-gray-200 hover:bg-gray-300 text-gray-600 hover:text-gray-700 rounded-l-md w-8 h-8 flex items-center justify-center focus:outline-none">
                                            <i class="fas fa-minus text-xs"></i>
                                        </button>
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" 
                                               class="quantity-input bg-gray-100 border-0 text-center w-12 h-8 focus:outline-none">
                                        <button type="button" class="quantity-btn plus bg-gray-200 hover:bg-gray-300 text-gray-600 hover:text-gray-700 rounded-r-md w-8 h-8 flex items-center justify-center focus:outline-none">
                                            <i class="fas fa-plus text-xs"></i>
                                        </button>
                                        <button type="submit" name="update_quantity" class="ml-2 text-sm text-primary-600 hover:text-primary-800 focus:outline-none">
                                            Update
                                        </button>
                                    </form>
                                    
                                    <div class="mt-3 sm:mt-0 flex">
                                        <a href="cart.php?remove=<?php echo $item['cart_item_id']; ?>" 
                                           class="text-red-500 hover:text-red-700 transition-colors text-sm flex items-center">
                                            <i class="fas fa-trash-alt mr-1"></i> Remove
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="bg-gray-50 p-4 sm:p-6">
                        <div class="flex justify-between mb-4">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">$<?php echo number_format($purchase_total, 2); ?></span>
                        </div>
                        
                        <div class="flex justify-between mb-4 text-lg font-bold">
                            <span>Total:</span>
                            <span class="text-primary-600">$<?php echo number_format($purchase_total, 2); ?></span>
                        </div>
                        
                        <form method="post" action="cart.php">
                            <button type="submit" name="checkout" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-3 rounded-lg transition-colors flex items-center justify-center">
                                <i class="fas fa-credit-card mr-2"></i> Proceed to Checkout
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-shopping-cart text-5xl"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">Your shopping cart is empty</h3>
                    <p class="text-gray-500 mb-6">Looks like you haven't added any books to purchase yet.</p>
                    <a href="books.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                        Browse Books
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Borrowing Cart -->
            <div class="cart-animation">
                <div class="flex items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Borrowing Cart</h2>
                    <span class="ml-2 bg-accent-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo count($borrow_items); ?></span>
                </div>
                
                <?php if (count($borrow_items) > 0): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <!-- Borrowing Days Selector -->
                    <div class="p-4 sm:p-6 bg-accent-50">
                        <form method="post" action="cart.php" class="flex flex-col sm:flex-row items-start sm:items-center">
                            <div class="mr-4">
                                <label for="borrowing_days" class="block text-sm font-medium text-gray-700 mb-1">Borrowing Days:</label>
                                <div class="flex items-center">
                                    <button type="button" class="days-btn minus bg-gray-200 hover:bg-gray-300 text-gray-600 hover:text-gray-700 rounded-l-md w-8 h-8 flex items-center justify-center focus:outline-none">
                                        <i class="fas fa-minus text-xs"></i>
                                    </button>
                                    <input type="number" name="borrowing_days" id="borrowing_days" value="<?php echo $borrowing_days; ?>" min="1" 
                                           class="bg-gray-100 border-0 text-center w-16 h-8 focus:outline-none">
                                    <button type="button" class="days-btn plus bg-gray-200 hover:bg-gray-300 text-gray-600 hover:text-gray-700 rounded-r-md w-8 h-8 flex items-center justify-center focus:outline-none">
                                        <i class="fas fa-plus text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" name="update_borrowing_days" class="mt-3 sm:mt-0 py-2 px-4 bg-accent-600 hover:bg-accent-700 text-white rounded-lg transition-colors">
                                Update Borrowing Days
                            </button>
                        </form>
                    </div>
                    
                    <!-- Borrowing Items -->
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($borrow_items as $item): ?>
                        <div class="cart-item p-4 sm:p-6 flex flex-col sm:flex-row items-start">
                            <div class="flex-shrink-0 w-full sm:w-24 h-24 mb-4 sm:mb-0">
                                <img class="w-full h-full object-cover rounded-md" 
                                     src="<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'assets/images/book-placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>">
                            </div>
                            
                            <div class="flex-grow sm:ml-6">
                                <div class="flex flex-col sm:flex-row sm:justify-between">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-800">
                                            <a href="book-details.php?id=<?php echo $item['book_id']; ?>" class="hover:text-accent-600 transition-colors">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </h3>
                                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($item['author']); ?></p>
                                        <p class="text-accent-600 text-sm mt-1">
                                            <i class="fas fa-clock mr-1"></i> Borrowing for <?php echo $item['borrowing_days']; ?> days
                                        </p>
                                        
                                        <?php if ($item['quantity'] > $item['available_for_borrowing']): ?>
                                        <p class="text-red-500 text-sm mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i> 
                                            Only <?php echo $item['available_for_borrowing']; ?> available for borrowing
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-4 sm:mt-0">
                                        <p class="text-accent-600 font-bold">$<?php echo number_format($item['borrowing_price_per_day'], 2); ?> / day</p>
                                        <p class="text-sm text-gray-600">Total: $<?php echo number_format($item['total_borrowing_fee'], 2); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end mt-4">
                                    <a href="cart.php?remove=<?php echo $item['cart_item_id']; ?>" 
                                       class="text-red-500 hover:text-red-700 transition-colors text-sm flex items-center">
                                        <i class="fas fa-trash-alt mr-1"></i> Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Borrowing Summary -->
                    <div class="bg-gray-50 p-4 sm:p-6">
                        <div class="flex justify-between mb-4">
                            <span class="text-gray-600">Borrowing Fee:</span>
                            <span class="font-medium">$<?php echo number_format($borrowing_total, 2); ?></span>
                        </div>
                        
                        <div class="flex justify-between mb-4 text-lg font-bold">
                            <span>Total:</span>
                            <span class="text-accent-600">$<?php echo number_format($borrowing_total, 2); ?></span>
                        </div>
                        
                        <form method="post" action="cart.php">
                            <input type="hidden" name="borrowing_days" value="<?php echo $borrowing_days; ?>">
                            <button type="submit" name="borrow_checkout" class="w-full bg-accent-600 hover:bg-accent-700 text-white font-medium py-3 rounded-lg transition-colors flex items-center justify-center">
                                <i class="fas fa-book mr-2"></i> Complete Borrowing
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-book text-5xl"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">Your borrowing cart is empty</h3>
                    <p class="text-gray-500 mb-6">Looks like you haven't added any books to borrow yet.</p>
                    <a href="books.php" class="inline-block bg-accent-600 hover:bg-accent-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                        Browse Books
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Continue Shopping -->
            <div class="mt-8 text-center">
                <a href="books.php" class="inline-flex items-center text-primary-600 hover:text-primary-800 font-medium">
                    <i class="fas fa-arrow-left mr-2"></i> Continue Shopping
                </a>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div>
                    <h3 class="text-xl font-bold mb-4">BookStore</h3>
                    <p class="text-gray-300 mb-4">Your destination for books of all genres, authors, and interests.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-pinterest"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors">Home</a></li>
                        <li><a href="books.php" class="text-gray-300 hover:text-white transition-colors">Books</a></li>
                        <li><a href="categories.php" class="text-gray-300 hover:text-white transition-colors">Categories</a></li>
                        <li><a href="about.php" class="text-gray-300 hover:text-white transition-colors">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-300 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                
                <!-- Customer Service -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="faq.php" class="text-gray-300 hover:text-white transition-colors">FAQs</a></li>
                        <li><a href="shipping.php" class="text-gray-300 hover:text-white transition-colors">Shipping Policy</a></li>
                        <li><a href="returns.php" class="text-gray-300 hover:text-white transition-colors">Returns & Refunds</a></li>
                        <li><a href="privacy.php" class="text-gray-300 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="terms.php" class="text-gray-300 hover:text-white transition-colors">Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <!-- Newsletter -->
                <div>
                    <h3 class="text-lg font-bold mb-4">Newsletter</h3>
                    <p class="text-gray-300 mb-4">Subscribe for updates on new books, authors, and special offers.</p>
                    <form action="#" method="POST" class="space-y-2">
                        <div>
                            <input type="email" placeholder="Your Email" required
                                   class="w-full px-4 py-2 rounded-lg bg-gray-700 border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400">&copy; 2025 BookStore. All rights reserved.</p>
                <div class="mt-4 md:mt-0">
                    <img src="assets/images/payment-methods.png" alt="Payment Methods" class="h-8">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
    
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.remove('-translate-x-full');
        });
        
        document.getElementById('close-mobile-menu').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.add('-translate-x-full');
        });
        
        // Search toggle
        document.getElementById('search-toggle').addEventListener('click', function() {
            document.getElementById('search-bar').classList.toggle('hidden');
        });
        
        // Quantity buttons for cart items
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentNode.querySelector('.quantity-input');
                const currentValue = parseInt(input.value);
                
                if (this.classList.contains('plus')) {
                    input.value = currentValue + 1;
                } else if (this.classList.contains('minus') && currentValue > 1) {
                    input.value = currentValue - 1;
                }
            });
        });
        
        // Borrowing days buttons
        document.querySelectorAll('.days-btn').forEach(button => {
            button.addEventListener('click', function() {
                const input = document.getElementById('borrowing_days');
                const currentValue = parseInt(input.value);
                
                if (this.classList.contains('plus')) {
                    input.value = currentValue + 1;
                } else if (this.classList.contains('minus') && currentValue > 1) {
                    input.value = currentValue - 1;
                }
            });
        });
        
        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            const notifications = document.querySelectorAll('#update-notification, #remove-notification, #error-notification');
            notifications.forEach(notification => {
                if (notification) {
                    notification.remove();
                }
            });
        }, 5000);
    </script>
</body>
</html>