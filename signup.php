<?php
// Include database connection
require_once 'connection.php';

// Initialize variables
$username = $email = $firstName = $lastName = "";
$password = $confirmPassword = "";
$errors = [];
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize input data
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $firstName = trim(filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_SPECIAL_CHARS));
    $lastName = trim(filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_SPECIAL_CHARS));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Username must be between 3 and 50 characters";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Username already exists";
        }
        $stmt->close();
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already in use";
        }
        $stmt->close();
    }
    
    // Validate first name
    if (empty($firstName)) {
        $errors[] = "First name is required";
    } elseif (strlen($firstName) > 50) {
        $errors[] = "First name is too long";
    }
    
    // Validate last name
    if (empty($lastName)) {
        $errors[] = "Last name is required";
    } elseif (strlen($lastName) > 50) {
        $errors[] = "Last name is too long";
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must include at least one uppercase letter";
    } elseif (!preg_match("/[a-z]/", $password)) {
        $errors[] = "Password must include at least one lowercase letter";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must include at least one number";
    }
    
    // Confirm passwords match
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, insert user data
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'customer'; // Default role
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $hashedPassword, $firstName, $lastName, $role);
        
        if ($stmt->execute()) {
            $success = true;
            
            // Redirect to login page after 2 seconds
            header("refresh:2;url=login.php");
        } else {
            $errors[] = "Registration failed: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - BookStore</title>
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
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                    }
                },
            },
        }
    </script>
    <style>
        /* Overall styling */
        body {
            background-color: #f8fafc;
            background-image: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            background-attachment: fixed;
        }
        
        .backdrop-blur {
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        /* 3D Book Effect */
        .book-3d {
            position: relative;
            transform-style: preserve-3d;
            transform: rotateY(-30deg) rotateX(5deg);
            transition: transform 0.6s ease;
        }
        
        .book-3d:hover {
            transform: rotateY(-20deg) rotateX(5deg) translateY(-10px);
        }
        
        .book-cover {
            position: relative;
            width: 200px;
            height: 280px;
            background: linear-gradient(145deg, #0ea5e9, #0369a1);
            border-radius: 2px;
            box-shadow: 
                5px 5px 20px rgba(0, 0, 0, 0.3),
                1px 1px 0 rgba(255, 255, 255, 0.2) inset;
        }
        
        .book-spine {
            position: absolute;
            width: 40px;
            height: 280px;
            transform: rotateY(90deg) translateZ(-20px) translateX(-20px);
            background: linear-gradient(90deg, #0c4a6e, #075985);
            border-radius: 2px 0 0 2px;
        }
        
        .book-pages {
            position: absolute;
            width: 190px;
            height: 270px;
            top: 5px;
            left: 5px;
            background: #fff;
            border-radius: 1px;
            transform: translateZ(-1px);
        }
        
        .book-title {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-align: center;
            width: 80%;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.5rem;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        /* Form styling */
        .form-floating {
            position: relative;
        }
        
        .form-floating input:focus ~ label,
        .form-floating input:not(:placeholder-shown) ~ label,
        .form-floating textarea:focus ~ label,
        .form-floating textarea:not(:placeholder-shown) ~ label {
            transform: translateY(-1.5rem) scale(0.85);
            color: #0ea5e9;
            background-color: white;
            padding: 0 0.25rem;
            z-index: 1;
        }
        
        .form-floating label {
            transition: all 0.2s ease-in-out;
            transform-origin: 0 0;
            pointer-events: none;
        }
        
        /* Input focus effects */
        .custom-input:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.2);
        }
        
        /* Password strength bar */
        .strength-meter {
            height: 0.3rem;
            background-color: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .strength-meter-fill {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 9999px;
        }
        
        /* Button styling */
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #0369a1);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(7, 89, 133, 0.25);
        }
        
        /* Floating animations */
        @keyframes float {
            0% {
                transform: translateY(0px) rotateY(-30deg) rotateX(5deg);
            }
            50% {
                transform: translateY(-15px) rotateY(-30deg) rotateX(5deg);
            }
            100% {
                transform: translateY(0px) rotateY(-30deg) rotateX(5deg);
            }
        }
        
        .float {
            animation: float 6s ease-in-out infinite;
        }
        
        /* Checkmark animation */
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }
        
        @keyframes checkmark {
            0% {
                stroke-dashoffset: 100;
            }
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        .checkmark-circle {
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: checkmark 1s ease-in-out forwards;
        }
        
        /* Card and container styles */
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            border-radius: 16px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .book-3d {
                transform: scale(0.8) rotateY(-30deg) rotateX(5deg);
            }
        }
    </style>
</head>
<body class="min-h-screen font-sans flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-gradient-to-br from-primary-50 via-primary-100 to-accent-50 opacity-80 z-[-1]"></div>
    <div class="fixed inset-0 z-[-1]">
        <div class="absolute top-0 left-0 w-64 h-64 bg-primary-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
        <div class="absolute top-0 right-0 w-80 h-80 bg-accent-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-0 left-20 w-72 h-72 bg-secondary-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
    </div>
    
    <div class="w-full max-w-6xl glass-card overflow-hidden flex flex-col md:flex-row shadow-soft">
        <!-- Left Side - 3D Book and Info -->
        <div class="bg-gradient-to-br from-primary-600 to-primary-800 md:w-5/12 py-12 px-8 hidden md:flex md:flex-col justify-between relative overflow-hidden">
            <div class="relative z-10">
                <h2 class="text-white text-4xl font-bold mb-6 font-display">Join Our Literary Community</h2>
                <p class="text-primary-100 text-lg mb-10">Create your account to unlock a world of books, personalized recommendations, and exclusive member benefits.</p>
                
                <div class="space-y-5">
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="bg-primary-500 rounded-full p-2 mr-4 flex-shrink-0">
                            <i class="fas fa-book-open text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-medium">Extensive Collection</h3>
                            <p class="text-primary-100 text-sm">Access thousands of titles across all genres</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="bg-primary-500 rounded-full p-2 mr-4 flex-shrink-0">
                            <i class="fas fa-hand-holding-heart text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-medium">Flexible Options</h3>
                            <p class="text-primary-100 text-sm">Buy or borrow books based on your preference</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="bg-primary-500 rounded-full p-2 mr-4 flex-shrink-0">
                            <i class="fas fa-comments text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-medium">Literary Community</h3>
                            <p class="text-primary-100 text-sm">Share reviews and connect with fellow readers</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="absolute right-0 bottom-10 -mr-10">
                <div class="book-3d float">
                    <div class="book-spine"></div>
                    <div class="book-cover">
                        <div class="book-pages"></div>
                        <div class="book-title">BookStore Membership</div>
                    </div>
                </div>
            </div>
            
            <!-- Decorative elements -->
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0">
                <svg class="absolute top-0 left-0 opacity-10" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <pattern id="dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                        <circle fill="white" cx="3" cy="3" r="1.5"></circle>
                    </pattern>
                    <rect x="0" y="0" width="100%" height="100%" fill="url(#dots)"></rect>
                </svg>
            </div>
        </div>
        
        <!-- Right Side - Form -->
        <div class="md:w-7/12 p-6 sm:p-10 bg-white relative">
            <?php if ($success): ?>
            <div class="flex flex-col items-center justify-center h-full py-12">
                <div class="success-checkmark mb-8">
                    <svg class="w-24 h-24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none" stroke="#10b981" stroke-width="2"/>
                        <path class="checkmark-check" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" d="M14.1 27.2l7.1 7.2 16.7-16.8" stroke-dasharray="100" stroke-dashoffset="100" style="animation: checkmark 1s ease-in-out forwards 0.5s;"/>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-4 text-center font-display">Welcome Aboard!</h2>
                <p class="text-gray-600 mb-8 text-center max-w-md">Your account has been successfully created. You'll be redirected to the login page shortly.</p>
                <div class="bg-primary-50 p-4 rounded-lg border border-primary-100 mb-8 max-w-md">
                    <p class="text-primary-700 text-sm">We've sent a confirmation email to your inbox. Please verify your email to unlock all features.</p>
                </div>
                <a href="login.php" class="btn-primary text-white font-medium py-3 px-8 rounded-full inline-flex items-center transition-all">
                    <span>Continue to Login</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
            <?php else: ?>
            <div class="mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2 font-display">Create Your Account</h1>
                <p class="text-gray-600">Join our literary community in just a few steps</p>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700 font-medium">Please address the following:</p>
                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6" id="signupForm">
                <div class="flex flex-col md:flex-row gap-5">
                    <!-- Username Field -->
                    <div class="form-floating relative w-full">
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="Username" required>
                        <label for="username" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">Username</label>
                        <div class="absolute right-3 top-3.5">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <p id="username-availability" class="mt-1 text-xs"></p>
                    </div>
                    
                    <!-- Email Field -->
                    <div class="form-floating relative w-full">
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="Email" required>
                        <label for="email" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">Email Address</label>
                        <div class="absolute right-3 top-3.5">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col md:flex-row gap-5">
                    <!-- First Name Field -->
                    <div class="form-floating relative w-full">
                        <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="First Name" required>
                        <label for="firstName" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">First Name</label>
                        <div class="absolute right-3 top-3.5">
                            <i class="fas fa-user-circle text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Last Name Field -->
                    <div class="form-floating relative w-full">
                        <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="Last Name" required>
                        <label for="lastName" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">Last Name</label>
                        <div class="absolute right-3 top-3.5">
                            <i class="fas fa-user-circle text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col md:flex-row gap-5">
                    <!-- Password Field -->
                    <div class="w-full">
                        <div class="form-floating relative">
                            <input type="password" id="password" name="password" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="Password" required>
                            <label for="password" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">Password</label>
                            <div class="absolute right-3 top-3.5">
                                <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Password Strength Meter -->
                        <div class="mt-2">
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strength-meter-fill"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Weak</span>
                                <span>Strong</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirm Password Field -->
                    <div class="w-full">
                        <div class="form-floating relative">
                            <input type="password" id="confirmPassword" name="confirmPassword" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="Confirm Password" required>
                            <label for="confirmPassword" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">Confirm Password</label>
                            <div id="password-match" class="absolute right-3 top-3.5 hidden">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div id="password-mismatch" class="absolute right-3 top-3.5 hidden">
                                <i class="fas fa-times-circle text-red-500"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Password Requirements -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-sm font-medium text-gray-700 mb-2">Password requirements:</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div class="flex items-center">
                            <i id="req-length" class="fas fa-circle text-xs text-gray-300 mr-2"></i>
                            <span class="text-xs text-gray-600">At least 8 characters</span>
                        </div>
                        <div class="flex items-center">
                            <i id="req-uppercase" class="fas fa-circle text-xs text-gray-300 mr-2"></i>
                            <span class="text-xs text-gray-600">One uppercase letter</span>
                        </div>
                        <div class="flex items-center">
                            <i id="req-lowercase" class="fas fa-circle text-xs text-gray-300 mr-2"></i>
                            <span class="text-xs text-gray-600">One lowercase letter</span>
                        </div>
                        <div class="flex items-center">
                            <i id="req-number" class="fas fa-circle text-xs text-gray-300 mr-2"></i>
                            <span class="text-xs text-gray-600">One number</span>
                        </div>
                    </div>
                </div>
                
                <!-- Terms Agreement -->
                <div class="flex items-center">
                    <input id="terms" name="terms" type="checkbox" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded" required>
                    <label for="terms" class="ml-2 block text-sm text-gray-700">
                        I agree to the <a href="#" class="text-primary-600 hover:text-primary-500 font-medium">Terms of Service</a> and <a href="#" class="text-primary-600 hover:text-primary-500 font-medium">Privacy Policy</a>
                    </label>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button type="submit" class="btn-primary w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-300">
                    <i class="fas fa-user-plus mr-2"></i>
                        Create Account
                        </button>
                </div>
                
                <!-- Already have an account -->
                <div class="text-center mt-4">
                    <p class="text-sm text-gray-600">
                        Already have an account? 
                        <a href="login.php" class="text-primary-600 hover:text-primary-700 font-medium">Log in here</a>
                    </p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthMeter = document.getElementById('strength-meter-fill');
            const reqLength = document.getElementById('req-length');
            const reqUppercase = document.getElementById('req-uppercase');
            const reqLowercase = document.getElementById('req-lowercase');
            const reqNumber = document.getElementById('req-number');
            
            // Calculate password strength
            let strength = 0;
            
            // Check length
            if (password.length >= 8) {
                strength += 25;
                reqLength.classList.remove('fa-circle', 'text-gray-300');
                reqLength.classList.add('fa-check-circle', 'text-green-500');
            } else {
                reqLength.classList.remove('fa-check-circle', 'text-green-500');
                reqLength.classList.add('fa-circle', 'text-gray-300');
            }
            
            // Check uppercase
            if (/[A-Z]/.test(password)) {
                strength += 25;
                reqUppercase.classList.remove('fa-circle', 'text-gray-300');
                reqUppercase.classList.add('fa-check-circle', 'text-green-500');
            } else {
                reqUppercase.classList.remove('fa-check-circle', 'text-green-500');
                reqUppercase.classList.add('fa-circle', 'text-gray-300');
            }
            
            // Check lowercase
            if (/[a-z]/.test(password)) {
                strength += 25;
                reqLowercase.classList.remove('fa-circle', 'text-gray-300');
                reqLowercase.classList.add('fa-check-circle', 'text-green-500');
            } else {
                reqLowercase.classList.remove('fa-check-circle', 'text-green-500');
                reqLowercase.classList.add('fa-circle', 'text-gray-300');
            }
            
            // Check numbers
            if (/[0-9]/.test(password)) {
                strength += 25;
                reqNumber.classList.remove('fa-circle', 'text-gray-300');
                reqNumber.classList.add('fa-check-circle', 'text-green-500');
            } else {
                reqNumber.classList.remove('fa-check-circle', 'text-green-500');
                reqNumber.classList.add('fa-circle', 'text-gray-300');
            }
            
            // Update strength meter
            strengthMeter.style.width = strength + '%';
            
            // Change color based on strength
            if (strength < 25) {
                strengthMeter.style.backgroundColor = '#ef4444'; // red
            } else if (strength < 50) {
                strengthMeter.style.backgroundColor = '#f97316'; // orange
            } else if (strength < 75) {
                strengthMeter.style.backgroundColor = '#eab308'; // yellow
            } else {
                strengthMeter.style.backgroundColor = '#10b981'; // green
            }
        });
        
        // Check password match
        const confirmPassword = document.getElementById('confirmPassword');
        const password = document.getElementById('password');
        const passwordMatch = document.getElementById('password-match');
        const passwordMismatch = document.getElementById('password-mismatch');
        
        function checkPasswordMatch() {
            if (confirmPassword.value === '') {
                passwordMatch.classList.add('hidden');
                passwordMismatch.classList.add('hidden');
                return;
            }
            
            if (confirmPassword.value === password.value) {
                passwordMatch.classList.remove('hidden');
                passwordMismatch.classList.add('hidden');
            } else {
                passwordMatch.classList.add('hidden');
                passwordMismatch.classList.remove('hidden');
            }
        }
        
        confirmPassword.addEventListener('input', checkPasswordMatch);
        password.addEventListener('input', function() {
            if (confirmPassword.value !== '') {
                checkPasswordMatch();
            }
        });
        
        // Check username availability with AJAX
        let usernameTimer;
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const availabilityText = document.getElementById('username-availability');
            
            clearTimeout(usernameTimer);
            
            if (username.length < 3) {
                availabilityText.textContent = '';
                availabilityText.className = 'mt-1 text-xs';
                return;
            }
            
            availabilityText.textContent = 'Checking availability...';
            availabilityText.className = 'mt-1 text-xs text-gray-500';
            
            usernameTimer = setTimeout(function() {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'check_username.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        const response = JSON.parse(this.responseText);
                        
                        if (response.available) {
                            availabilityText.textContent = 'Username is available!';
                            availabilityText.className = 'mt-1 text-xs text-green-600';
                        } else {
                            availabilityText.textContent = 'Username is already taken';
                            availabilityText.className = 'mt-1 text-xs text-red-600';
                        }
                    }
                };
                
                xhr.send('username=' + username);
            }, 500);
        });
        
        // Form validation before submit
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const terms = document.getElementById('terms').checked;
            let isValid = true;
            
            // Check password requirements
            if (password.length < 8 || 
                !(/[A-Z]/.test(password)) || 
                !(/[a-z]/.test(password)) || 
                !(/[0-9]/.test(password))) {
                isValid = false;
                alert('Please ensure your password meets all requirements.');
            }
            
            // Check password match
            if (password !== confirmPassword) {
                isValid = false;
                alert('Passwords do not match.');
            }
            
            // Check terms
            if (!terms) {
                isValid = false;
                alert('You must agree to the Terms of Service and Privacy Policy.');
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Animation for floating elements (adjust if needed)
        const animationElements = document.querySelectorAll('.animate-blob');
        animationElements.forEach((el, index) => {
            el.style.animationDelay = `${index * 2}s`;
        });
    </script>
</body>
</html>