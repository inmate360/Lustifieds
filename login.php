<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CSRF.php';
require_once 'classes/RateLimiter.php';
require_once 'classes/SpamProtection.php';
require_once 'classes/InputSanitizer.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// If already logged in, redirect
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Initialize security classes
$rateLimiter = new RateLimiter($db);
$spamProtection = new SpamProtection($db);
$error = '';

// Check if IP is blocked
$ipCheck = $spamProtection->isBlocked();
if($ipCheck) {
    $error = 'Access denied. ' . ($ipCheck['reason'] ?? 'Please contact support.');
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && !$ipCheck) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Check rate limit
        $identifier = RateLimiter::getIdentifier();
        $rateCheck = $rateLimiter->checkLimit($identifier, 'login', 5, 900); // 5 attempts per 15 minutes
        
        if(!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            $error = "Too many login attempts. Please try again in {$minutes} minutes.";
            
            // Block IP after too many attempts
            if($rateCheck['retry_after'] > 3600) {
                $spamProtection->blockIP(
                    RateLimiter::getClientIP(),
                    'Excessive login attempts',
                    $rateCheck['retry_after']
                );
            }
        } else {
            $username = InputSanitizer::cleanString($_POST['username'], 50);
            $password = $_POST['password'];
            
            if(empty($username) || empty($password)) {
                $error = 'Please enter both username/email and password';
            } else {
                // Check for SQL injection attempts
                if(InputSanitizer::detectSQLInjection($username)) {
                    $error = 'Invalid login attempt detected';
                    $spamProtection->blockIP(RateLimiter::getClientIP(), 'SQL injection attempt', 86400);
                } else {
                    $query = "SELECT id, username, email, password, is_suspended, is_banned, email_verified 
                              FROM users 
                              WHERE username = :username OR email = :email 
                              LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $username);
                    $stmt->execute();
                    
                    $user = $stmt->fetch();
                    
                    if($user && password_verify($password, $user['password'])) {
                        // Check if account is suspended or banned
                        if($user['is_banned']) {
                            $error = 'Your account has been permanently banned. Contact support for more information.';
                        } elseif($user['is_suspended']) {
                            $error = 'Your account is currently suspended. Contact support for more information.';
                        } else {
                            // Successful login
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['email_verified'] = (bool)$user['email_verified'];
                            
                            // Update last login
                            $ip = RateLimiter::getClientIP();
                            $query = "UPDATE users 
                                      SET last_login = NOW(), last_ip = :ip, is_online = TRUE 
                                      WHERE id = :user_id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':ip', $ip);
                            $stmt->bindParam(':user_id', $user['id']);
                            $stmt->execute();
                            
                            // Reset rate limit for this user
                            $rateLimiter->checkLimit($user['id'], 'login_reset', 999, 1);
                            
                            // Destroy CSRF token
                            CSRF::destroyToken();
                            
                            // Redirect
                            $redirect = $_GET['redirect'] ?? 'choose-location.php';
                            $redirect = filter_var($redirect, FILTER_SANITIZE_URL);
                            header('Location: ' . $redirect);
                            exit();
                        }
                    } else {
                        $error = 'Invalid username/email or password';
                    }
                }
            }
        }
    }
}

// Generate new CSRF token
$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<div class="flex min-h-[70vh] items-center justify-center px-4 py-12">
  <div class="w-full max-w-md">
    
    <!-- Logo -->
    <div class="mb-8 text-center">
      <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl border-2 border-gh-border bg-gradient-to-br from-gh-panel to-gh-panel2 text-xl font-black shadow-lg">
        <span class="bg-gradient-to-br from-gh-accent to-gh-success bg-clip-text text-transparent">TP</span>
      </div>
      <h1 class="text-2xl font-extrabold tracking-tight">Welcome back</h1>
      <p class="mt-2 text-sm text-gh-muted">Login to your Turnpage account</p>
    </div>

    <!-- Security Badge -->
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel px-4 py-3 text-center text-xs text-gh-muted">
      <i class="bi bi-shield-lock-fill mr-2 text-gh-success"></i>
      Your connection is secure and encrypted
    </div>

    <!-- Login Card -->
    <div class="rounded-xl border border-gh-border bg-gh-panel shadow-lg">
      <div class="p-6">
        
        <?php if(!empty($error)): ?>
          <div class="mb-5 rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="flex items-start gap-3">
              <i class="bi bi-exclamation-triangle-fill text-lg text-gh-danger"></i>
              <div class="flex-1 text-sm">
                <span class="font-semibold text-gh-danger">Error:</span>
                <span class="text-gh-fg"> <?php echo htmlspecialchars($error); ?></span>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          
          <div>
            <label class="mb-2 block text-sm font-semibold text-gh-fg">Username or Email</label>
            <input type="text" 
                   name="username" 
                   required 
                   autofocus
                   placeholder="Enter your username or email"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
          </div>

          <div>
            <div class="mb-2 flex items-center justify-between">
              <label class="text-sm font-semibold text-gh-fg">Password</label>
              <a href="forgot-password.php" class="text-xs font-semibold text-gh-accent hover:underline">Forgot password?</a>
            </div>
            <input type="password" 
                   name="password" 
                   required 
                   placeholder="Enter your password"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
          </div>

          <button type="submit" 
                  class="w-full rounded-lg bg-gh-accent px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
            Sign in
          </button>
        </form>
      </div>

      <div class="border-t border-gh-border bg-gh-panel2 px-6 py-4 text-center text-sm">
        <span class="text-gh-muted">Don't have an account?</span>
        <a href="register.php" class="ml-1 font-semibold text-gh-accent hover:underline">Sign up free</a>
      </div>
    </div>

    <!-- Footer Links -->
    <div class="mt-6 text-center text-xs text-gh-muted">
      <a href="terms.php" class="hover:text-gh-fg hover:underline">Terms</a>
      <span class="mx-2">•</span>
      <a href="privacy.php" class="hover:text-gh-fg hover:underline">Privacy</a>
      <span class="mx-2">•</span>
      <a href="support.php" class="hover:text-gh-fg hover:underline">Support</a>
    </div>
  </div>
</div>

<?php include 'views/footer.php'; ?>
