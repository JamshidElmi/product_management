<?php
session_start(); // Start the session
require_once 'config.php';

// Add this line before requiring the layout
$hide_sidebar = true;
$hide_header = true;  // Optional: if you want to hide the header too

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$show_recaptcha = false;
// Preserve posted username for UX
$posted_username = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $posted_username = $username ?? '';
    
    // Check if IP is locked out
    if (!checkLoginAttempts($ip_address)) {
        $remaining_time = LOCKOUT_TIME;
        $error = "Too many failed login attempts. Please try again in " . ceil($remaining_time / 60) . " minutes.";
        logSecurityEvent('login_blocked', "Login blocked for IP: $ip_address due to too many failed attempts");
    } else {
        // Check if reCAPTCHA is required (after 2 failed attempts)
        $stmt = $conn->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ?");
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['attempts'] >= 2) {
                $show_recaptcha = true;
            }
        }
        
        // Verify reCAPTCHA if required
        $recaptcha_valid = true;
        if ($show_recaptcha) {
            $recaptcha_valid = verifyRecaptcha($recaptcha_response);
            if (!$recaptcha_valid) {
                $error = "Please complete the reCAPTCHA verification.";
                recordFailedLogin($ip_address, $username);
            }
        }
        
        if ($recaptcha_valid && !empty($username) && !empty($password)) {
            if (!$conn) {
                die("Database connection error");
            }

            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Successful login
                        clearLoginAttempts($ip_address);
                        
                        // Set session variables
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['ip_address'] = $ip_address;
                        $_SESSION['login_time'] = time();
                        
                        // Log successful login
                        logSecurityEvent('successful_login', "User $username logged in successfully", $user['id']);
                        logAdminAction('login', "Successful login from IP: $ip_address");
                        
                        header("Location: index.php");
                        exit();
                    }
                }
                $stmt->close();
            } else {
                $error = "Failed to prepare the SQL statement";
            }
            
            if (empty($error)) {
                $error = "Invalid username or password";
                recordFailedLogin($ip_address, $username);
            }
        } elseif (empty($username) || empty($password)) {
            $error = "Please enter both username and password";
        }
    }
}

ob_start();
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-900 dark:to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 shadow-2xl rounded-2xl p-8 sm:p-10">
            <div class="flex flex-col items-center mb-6">
                <img class="w-80 mb-6" src="imgs/yfs.png" alt="YFS Logo">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Sign in to your account</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Product Management System</p>
            </div>

            <form class="mt-6 space-y-6" method="POST" novalidate>
            <?php if (!empty($error)): ?>
            <div class="rounded-md bg-red-50 dark:bg-red-900/50 p-4 mb-4" role="alert" aria-live="assertive">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                            <?php echo htmlspecialchars($error); ?>
                        </h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="rounded-md shadow-sm -space-y-px">
                <div class="mb-3">
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Username</label>
                    <input id="username" name="username" type="text" required autofocus autocomplete="username"
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400" 
                           placeholder="Username" value="<?php echo htmlspecialchars($posted_username); ?>">
                </div>

                <div class="mb-1 relative">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Password</label>
                    <div class="mt-1 relative">
                        <input id="password" name="password" type="password" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400" 
                               placeholder="Password">
                        <button type="button" id="togglePassword" aria-label="Show password" title="Show password" class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm text-gray-500">
                            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.94 10.94C4.72 7.79 7.73 6 10 6c2.27 0 5.28 1.79 7.06 4.94a1 1 0 010 .12C15.28 14.21 12.27 16 10 16c-2.27 0-5.28-1.79-7.06-4.94a1 1 0 010-.12z" />
                                <circle cx="10" cy="10" r="2.5" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                            </svg>
                            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3l14 14M9.88 9.88A2.5 2.5 0 0110 7.5c1.38 0 2.5 1.12 2.5 2.5 0 .13-.01.26-.04.38" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mb-4">
                <label class="flex items-center text-sm">
                    <input type="checkbox" name="remember" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <span class="ml-2 text-gray-700 dark:text-gray-300">Remember me</span>
                </label>
                <div class="text-sm">
                    <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Forgot password?</a>
                </div>
            </div>

            <!-- reCAPTCHA -->
            <?php if ($show_recaptcha): ?>
            <div class="flex justify-center mb-4">
                <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
            </div>
            <?php endif; ?>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-primary/50 group-hover:text-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </span>
                    Sign in
                </button>
            </div>
            <div>
                <a href="price_sheet.php" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    View Price Sheet
                </a>
            </div>
        </form>
    </div>
</div>

<!-- reCAPTCHA Script -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script>
// Show/hide reCAPTCHA based on failed attempts
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($show_recaptcha): ?>
    var rc = document.querySelector('.g-recaptcha');
    if (rc) rc.style.display = 'block';
    <?php endif; ?>
});
</script>

<script>
// Password toggle
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('togglePassword');
    var pwd = document.getElementById('password');
    var eyeOpen = document.getElementById('eyeOpen');
    var eyeClosed = document.getElementById('eyeClosed');
    if (toggle && pwd) {
        toggle.addEventListener('click', function() {
            if (pwd.type === 'password') {
                pwd.type = 'text';
                toggle.setAttribute('aria-label', 'Hide password');
                if (eyeOpen) eyeOpen.classList.add('hidden');
                if (eyeClosed) eyeClosed.classList.remove('hidden');
            } else {
                pwd.type = 'password';
                toggle.setAttribute('aria-label', 'Show password');
                if (eyeOpen) eyeOpen.classList.remove('hidden');
                if (eyeClosed) eyeClosed.classList.add('hidden');
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'layout.php';
?>