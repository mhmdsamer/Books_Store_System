<?php
// Include database connection
require_once 'connection.php';

// Initialize variables
$username = $password = "";
$error = "";
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize input data
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
    $password = $_POST['password'] ?? '';
    
    // Validate form data
    if (empty($username)) {
        $error = "Username is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    } else {
        // Attempt to authenticate user
        $stmt = $conn->prepare("SELECT user_id, username, password, first_name, last_name, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Authentication successful
                $success = true;
                
                // Start session and store user data
                session_start();
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['role'] = $user['role'];
                
                // Update last_login timestamp
                $updateStmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                $updateStmt->bind_param("i", $user['user_id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Redirect based on role after brief success message
                $redirect = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'index.php';
                header("refresh:2;url=$redirect");
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
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
    <title>Login - BookStore</title>
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
    
    <div class="w-full max-w-5xl glass-card overflow-hidden flex flex-col md:flex-row shadow-soft">
        <!-- Left Side - 3D Book and Info -->
        <div class="bg-gradient-to-br from-primary-600 to-primary-800 md:w-5/12 py-12 px-8 hidden md:flex md:flex-col justify-between relative overflow-hidden">
            <div class="relative z-10">
                <h2 class="text-white text-4xl font-bold mb-6 font-display">Welcome Back!</h2>
                <p class="text-primary-100 text-lg mb-10">Sign in to continue your literary journey and access your personalized bookstore experience.</p>
                
                <div class="space-y-5">
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="bg-primary-500 rounded-full p-2 mr-4 flex-shrink-0">
                            <i class="fas fa-bookmark text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-medium">Personal Bookshelf</h3>
                            <p class="text-primary-100 text-sm">Access your saved and purchased titles</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="bg-primary-500 rounded-full p-2 mr-4 flex-shrink-0">
                            <i class="fas fa-heart text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-medium">Custom Recommendations</h3>
                            <p class="text-primary-100 text-sm">Get personalized book suggestions</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-xl p-4">
                        <div class="bg-primary-500 rounded-full p-2 mr-4 flex-shrink-0">
                            <i class="fas fa-bell text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-medium">Special Offers</h3>
                            <p class="text-primary-100 text-sm">Access exclusive member discounts</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="absolute right-0 bottom-10 -mr-10">
                <div class="book-3d float">
                    <div class="book-spine"></div>
                    <div class="book-cover">
                        <div class="book-pages"></div>
                        <div class="book-title">BookStore Member Access</div>
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
                <h2 class="text-3xl font-bold text-gray-800 mb-4 text-center font-display">Welcome Back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                <p class="text-gray-600 mb-8 text-center max-w-md">You've successfully logged in. You'll be redirected to your dashboard shortly.</p>
                <div class="animate-pulse bg-primary-50 p-4 rounded-lg border border-primary-100 mb-8 max-w-md">
                    <p class="text-primary-700 text-sm text-center">Redirecting you in a moment...</p>
                </div>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" class="btn-primary text-white font-medium py-3 px-8 rounded-full inline-flex items-center transition-all">
                    <span>Go to Admin Dashboard</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
                <?php else: ?>
                <a href="index.php" class="btn-primary text-white font-medium py-3 px-8 rounded-full inline-flex items-center transition-all">
                    <span>Continue to BookStore</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2 font-display">Sign In</h1>
                <p class="text-gray-600">Access your BookStore account</p>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6" id="loginForm">
                <!-- Username/Email Field -->
                <div class="form-floating relative">
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="Username or Email" required>
                    <label for="username" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">Username or Email</label>
                    <div class="absolute right-3 top-3.5">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                </div>
                
                <!-- Password Field -->
                <div class="form-floating relative">
                    <input type="password" id="password" name="password" class="custom-input peer w-full h-12 px-4 pt-5 pb-2 border border-gray-300 rounded-lg focus:outline-none placeholder-transparent transition-all" placeholder="Password" required>
                    <label for="password" class="absolute text-gray-500 left-4 top-3.5 text-sm transition-all">Password</label>
                    <div class="absolute right-3 top-3.5">
                        <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Remember Me and Forgot Password -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                            Remember me
                        </label>
                    </div>
                    
                    <div class="text-sm">
                        <a href="forgot-password.php" class="font-medium text-primary-600 hover:text-primary-500">
                            Forgot your password?
                        </a>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button type="submit" class="btn-primary w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </div>
                
                <!-- Alternative Login Methods -->
                <div class="relative py-3">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="px-4 bg-white text-sm text-gray-500">Or continue with</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="py-2.5 px-4 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all flex items-center justify-center">
                        <i class="fab fa-google text-red-500 mr-2"></i>
                        Google
                    </button>
                    <button type="button" class="py-2.5 px-4 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all flex items-center justify-center">
                        <i class="fab fa-facebook text-blue-600 mr-2"></i>
                        Facebook
                    </button>
                </div>
                
                <!-- Sign Up Link -->
                <div class="text-center mt-6">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <a href="signup.php" class="text-primary-600 hover:text-primary-500 font-medium">Sign up</a>
                    </p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
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
    </script>
</body>
</html>