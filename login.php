<?php
require_once 'config.php'; // This will handle session configuration

// Add this line before requiring the layout
$hide_sidebar = true;
$hide_header = true;  // Optional: if you want to hide the header too

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
// Enable reCAPTCHA for security - always visible
$show_recaptcha = true; // Always show reCAPTCHA for better security
// Preserve posted username for UX
$posted_username = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $password = $_POST['password'] ?? ''; // Don't sanitize passwords as it may change them
    // reCAPTCHA response is intentionally ignored since reCAPTCHA is disabled,
    // but define the variable to avoid undefined variable notices on some PHP configs.
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $posted_username = $username ?? '';
    
    // Check if IP is locked out
    if (!checkLoginAttempts($ip_address)) {
        $remaining_time = LOCKOUT_TIME;
        $error = "Too many failed login attempts. Please try again in " . ceil($remaining_time / 60) . " minutes.";
        logSecurityEvent('login_blocked', "Login blocked for IP: $ip_address due to too many failed attempts");
    } else {
        // Always verify reCAPTCHA for enhanced security
        $recaptcha_valid = verifyRecaptcha($recaptcha_response);
        if (!$recaptcha_valid) {
            $error = "Please complete the reCAPTCHA verification.";
            recordFailedLogin($ip_address, $username);
        }
        
        if ($recaptcha_valid && !empty($username) && !empty($password)) {
            // Use the new authentication function
            if (authenticateUser($username, $password)) {
                error_log("[LOGIN DEBUG] Authentication successful");
                // Successful login
                clearLoginAttempts($ip_address);
                
                // Get user info for logging
                $user = getCurrentUser();
                $_SESSION['ip_address'] = $ip_address;
                $_SESSION['login_time'] = time();
                
                // Additional diagnostic info: log cookies and session cookie params
                error_log("[LOGIN DEBUG] \\$_COOKIE: " . print_r($_COOKIE, true));
                error_log("[LOGIN DEBUG] session_id(): " . session_id());
                error_log("[LOGIN DEBUG] session_get_cookie_params(): " . print_r(session_get_cookie_params(), true));

                // Log server environment info for debugging
                error_log("[LOGIN DEBUG] Server IP: " . ($_SERVER['SERVER_ADDR'] ?? 'unknown'));
                error_log("[LOGIN DEBUG] Remote IP: " . $ip_address);
                error_log("[LOGIN DEBUG] Session ID: " . session_id());
                error_log("[LOGIN DEBUG] Session variables set: " . print_r($_SESSION, true));
                
                // Log successful login
                logSecurityEvent('successful_login', "User $username logged in successfully", $user['id']);
                logAdminAction('login', "Successful login from IP: $ip_address");

                // Diagnostic logging to help troubleshoot session/cookie issues
                error_log("[LOGIN DEBUG] Session after login: " . print_r($_SESSION, true));
                // Log headers that will be sent (for debugging only)
                if (function_exists('headers_sent')) {
                    ob_start();
                    foreach (headers_list() as $h) {
                        error_log("[LOGIN DEBUG] Pending header: " . $h);
                    }
                    ob_end_clean();
                }

                error_log("[LOGIN DEBUG] Redirecting to index.php");
                header("Location: index.php");
                exit();
            } else {
                error_log("[LOGIN DEBUG] Authentication failed");
                $error = "Invalid username or password";
                recordFailedLogin($ip_address, $username);
                error_log("[LOGIN DEBUG] Login failed, recording failed attempt");
            }
        } elseif (empty($username) || empty($password)) {
            error_log("[LOGIN DEBUG] Username or password is empty");
            $error = "Please enter both username and password";
        }
    }
}

ob_start();
?>

<style>
/* reCAPTCHA Theme Integration */
.g-recaptcha {
    transform: scale(1);
    transform-origin: center;
    margin: 0 auto;
}

/* Smooth transitions for theme changes */
.g-recaptcha > div {
    transition: all 0.3s ease;
}

/* Submit button disabled state */
button[type="submit"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}
</style>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-900 dark:to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 shadow-2xl rounded-2xl p-8 sm:p-10">
            <div class="flex flex-col items-center mb-6">
                <img class="w-80 mb-6" src="imgs/yfs.png" alt="YFS Logo">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Sign in to your account</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">YFSuite Product Management System</p>
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

           
            <!-- reCAPTCHA - Always visible for security -->
            <div class="flex justify-center mb-4">
                <div class="g-recaptcha" 
                     data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>" 
                     data-theme="light" 
                     data-size="normal"
                     data-callback="recaptchaCallback"
                     data-expired-callback="recaptchaExpired"></div>
            </div>

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
            <div class="flex flex-row gap-3">
                <a href="price_sheet.php" 
                    class="group flex-1 flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Price Sheet
                </a>
                <a href="price_sheet.php" 
                    class="group flex-1 flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    Purchase Order
                </a>
            </div>
        </form>
    </div>
</div>

<!-- reCAPTCHA Script with improved loading -->
<script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaAPILoad&render=explicit" async defer></script>

<script>
var recaptchaWidget;
var recaptchaTheme = 'light'; // Default theme
var recaptchaLoaded = false;
var recaptchaAttempts = 0;
var maxRecaptchaAttempts = 10;

// reCAPTCHA callback function
function recaptchaCallback(response) {
    console.log('reCAPTCHA completed successfully');
    // Enable submit button when reCAPTCHA is completed
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

// reCAPTCHA expired callback
function recaptchaExpired() {
    console.log('reCAPTCHA expired');
    // Disable submit button when reCAPTCHA expires
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

// Global callback function called when reCAPTCHA API loads
function onRecaptchaAPILoad() {
    console.log('reCAPTCHA API loaded');
    recaptchaLoaded = true;
    initializeRecaptcha();
}

// Initialize reCAPTCHA with retry mechanism
function initializeRecaptcha() {
    if (recaptchaAttempts >= maxRecaptchaAttempts) {
        console.error('Max reCAPTCHA initialization attempts reached');
        showRecaptchaError();
        return;
    }

    recaptchaAttempts++;
    console.log('reCAPTCHA initialization attempt:', recaptchaAttempts);

    try {
        // Check if grecaptcha is available
        if (typeof grecaptcha === 'undefined' || !grecaptcha.render) {
            console.log('grecaptcha not ready, retrying...');
            setTimeout(initializeRecaptcha, 500);
            return;
        }

        // Check if dark mode is enabled
        const isDarkMode = document.documentElement.classList.contains('dark') || 
                          localStorage.getItem('theme') === 'dark' || 
                          (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        
        recaptchaTheme = isDarkMode ? 'dark' : 'light';
        
        // Find reCAPTCHA element
        const recaptchaElement = document.querySelector('.g-recaptcha');
        if (!recaptchaElement) {
            console.error('reCAPTCHA element not found');
            setTimeout(initializeRecaptcha, 500);
            return;
        }

        // Clear any existing content
        recaptchaElement.innerHTML = '';
        
        // Render reCAPTCHA
        recaptchaWidget = grecaptcha.render(recaptchaElement, {
            'sitekey': '<?php echo RECAPTCHA_SITE_KEY; ?>',
            'theme': recaptchaTheme,
            'size': 'normal',
            'callback': recaptchaCallback,
            'expired-callback': recaptchaExpired,
            'error-callback': function() {
                console.error('reCAPTCHA error occurred');
                showRecaptchaError();
            }
        });
        
        console.log('reCAPTCHA initialized successfully');
        hideRecaptchaError();
        
    } catch (error) {
        console.error('Error initializing reCAPTCHA:', error);
        setTimeout(initializeRecaptcha, 1000);
    }
}

// Show reCAPTCHA error message
function showRecaptchaError() {
    const recaptchaElement = document.querySelector('.g-recaptcha');
    if (recaptchaElement) {
        recaptchaElement.innerHTML = `
            <div class="text-center p-4 border-2 border-dashed border-red-300 rounded-lg bg-red-50 dark:bg-red-900/20">
                <p class="text-sm text-red-600 dark:text-red-400 mb-2">reCAPTCHA failed to load</p>
                <button type="button" onclick="retryRecaptcha()" class="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700">
                    Retry
                </button>
            </div>
        `;
    }
    
    // Enable submit button as fallback
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

// Hide reCAPTCHA error message
function hideRecaptchaError() {
    // Error message will be replaced by actual reCAPTCHA
}

// Retry reCAPTCHA initialization
function retryRecaptcha() {
    recaptchaAttempts = 0;
    initializeRecaptcha();
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing reCAPTCHA setup');
    
    // Initially disable submit button until reCAPTCHA is completed
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    
    // Start initialization process
    if (recaptchaLoaded) {
        // API already loaded
        initializeRecaptcha();
    } else {
        // Wait for API to load, but also set a timeout as fallback
        setTimeout(function() {
            if (!recaptchaLoaded) {
                console.log('reCAPTCHA API load timeout, attempting to initialize anyway');
                initializeRecaptcha();
            }
        }, 3000);
    }
});

// Handle theme changes
function updateRecaptchaTheme() {
    const isDarkMode = document.documentElement.classList.contains('dark');
    const newTheme = isDarkMode ? 'dark' : 'light';
    
    if (newTheme !== recaptchaTheme && typeof grecaptcha !== 'undefined' && recaptchaWidget !== undefined) {
        console.log('Theme changed to:', newTheme);
        recaptchaTheme = newTheme;
        
        try {
            // Reset and re-render with new theme
            const recaptchaElement = document.querySelector('.g-recaptcha');
            if (recaptchaElement) {
                grecaptcha.reset(recaptchaWidget);
                recaptchaElement.innerHTML = '';
                
                recaptchaWidget = grecaptcha.render(recaptchaElement, {
                    'sitekey': '<?php echo RECAPTCHA_SITE_KEY; ?>',
                    'theme': recaptchaTheme,
                    'size': 'normal',
                    'callback': recaptchaCallback,
                    'expired-callback': recaptchaExpired,
                    'error-callback': function() {
                        console.error('reCAPTCHA error on theme change');
                    }
                });
                
                // Reset submit button state
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
        } catch (error) {
            console.error('Error updating reCAPTCHA theme:', error);
        }
    }
}

// Watch for theme changes
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
            updateRecaptchaTheme();
        }
    });
});

// Start observing theme changes when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });
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