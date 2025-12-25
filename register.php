<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CSRF.php';
require_once 'classes/RateLimiter.php';
require_once 'classes/SpamProtection.php';
require_once 'classes/InputSanitizer.php';
require_once 'classes/EmailVerification.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// Check if user already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if TOS was accepted
if(!isset($_SESSION['tos_accepted']) || !$_SESSION['tos_accepted']) {
    header('Location: register-tos.php');
    exit();
}

// Initialize security classes
$rateLimiter = new RateLimiter($db);
$emailVerification = new EmailVerification($db);
$spamProtection = new SpamProtection($db);

$error = '';
$success = '';

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
        $rateCheck = $rateLimiter->checkLimit($identifier, 'register', 3, 3600); // 3 attempts per hour
        
        if(!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            $error = "Too many registration attempts. Please try again in {$minutes} minutes.";
        } else {
            // Sanitize inputs
            $username = InputSanitizer::cleanUsername($_POST['username']);
            $email = InputSanitizer::cleanEmail($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $age_verification = isset($_POST['age_verification']);
            
            // Validation
            if(empty($username) || empty($email) || empty($password)) {
                $error = 'All fields are required';
            } elseif($username === false) {
                $error = 'Invalid username. Only letters, numbers, underscore and hyphen allowed.';
            } elseif($email === false) {
                $error = 'Invalid email address';
            } elseif(!$age_verification) {
                $error = 'You must confirm you are 18 years or older';
            } elseif(strlen($username) < 3 || strlen($username) > 30) {
                $error = 'Username must be between 3 and 30 characters';
            } elseif(strlen($password) < 8) {
                $error = 'Password must be at least 8 characters';
            } elseif(!preg_match('/[A-Z]/', $password)) {
                $error = 'Password must contain at least one uppercase letter';
            } elseif(!preg_match('/[a-z]/', $password)) {
                $error = 'Password must contain at least one lowercase letter';
            } elseif(!preg_match('/[0-9]/', $password)) {
                $error = 'Password must contain at least one number';
            } elseif($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } elseif(InputSanitizer::detectXSS($username . $email)) {
                $error = 'Invalid characters detected';
                $spamProtection->blockIP(RateLimiter::getClientIP(), 'XSS attempt', 86400);
            } else {
                // Check if username already exists
                $query = "SELECT id FROM users WHERE username = :username LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                if($stmt->rowCount() > 0) {
                    $error = 'Username already taken';
                } else {
                    // Check if email already exists
                    $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    
                    if($stmt->rowCount() > 0) {
                        $error = 'Email already registered';
                    } else {
                        // Create account
                        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
                        $tos_accepted_at = $_SESSION['tos_accepted_at'] ?? date('Y-m-d H:i:s');
                        $ip = RateLimiter::getClientIP();
                        
                        $query = "INSERT INTO users (username, email, password, tos_accepted_at, registration_ip, created_at) 
                                  VALUES (:username, :email, :password, :tos_accepted_at, :ip, NOW())";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':tos_accepted_at', $tos_accepted_at);
                        $stmt->bindParam(':ip', $ip);
                        
                        if($stmt->execute()) {
                            $user_id = $db->lastInsertId();
                            
                            // Send email verification
                            $emailVerification->sendVerification($user_id, $email);
                            
                            // Log in the user
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['username'] = $username;
                            $_SESSION['email'] = $email;
                            $_SESSION['email_verified'] = false;
                            
                            // Clear TOS session data
                            unset($_SESSION['tos_accepted']);
                            unset($_SESSION['tos_accepted_at']);
                            
                            // Destroy CSRF token
                            CSRF::destroyToken();
                            
                            // Redirect to email verification notice
                            header('Location: verify-email-notice.php');
                            exit();
                        } else {
                            $error = 'Registration failed. Please try again.';
                        }
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
      <h1 class="text-2xl font-extrabold tracking-tight">Create your account</h1>
      <p class="mt-2 text-sm text-gh-muted">Join thousands of people connecting locally</p>
    </div>

    <!-- TOS Accepted Badge -->
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel px-4 py-3">
      <div class="flex items-start gap-3 text-sm">
        <i class="bi bi-check-circle-fill text-lg text-gh-success"></i>
        <div>
          <div class="font-semibold text-gh-fg">Terms Accepted</div>
          <div class="text-gh-muted">Thank you for reviewing and accepting our Terms of Service.</div>
        </div>
      </div>
    </div>

    <!-- Register Card -->
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

        <form method="POST" action="register.php" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          
          <div>
            <label class="mb-2 block text-sm font-semibold text-gh-fg">Username</label>
            <input type="text" 
                   name="username" 
                   required 
                   autofocus
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   placeholder="Choose a username"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            <p class="mt-1.5 text-xs text-gh-muted">3-30 characters, letters, numbers, underscore and hyphen</p>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-gh-fg">Email address</label>
            <input type="email" 
                   name="email" 
                   required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   placeholder="you@example.com"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-gh-fg">Password</label>
            <input type="password" 
                   name="password" 
                   required 
                   placeholder="Create a strong password"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            <div class="mt-1.5 space-y-1 text-xs text-gh-muted">
              <div>• At least 8 characters</div>
              <div>• One uppercase, one lowercase letter</div>
              <div>• At least one number</div>
            </div>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-gh-fg">Confirm password</label>
            <input type="password" 
                   name="confirm_password" 
                   required 
                   placeholder="Re-enter your password"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
          </div>

          <!-- Age Verification -->
          <div class="rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <label class="flex cursor-pointer items-start gap-3">
              <input type="checkbox" 
                     name="age_verification" 
                     required
                     class="mt-0.5 h-4 w-4 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
              <span class="flex-1 text-sm">
                <span class="font-semibold text-gh-fg">I confirm that I am 18 years or older</span>
                <span class="block text-gh-muted">This site is for adults only</span>
              </span>
            </label>
          </div>

          <button type="submit" 
                  class="w-full rounded-lg bg-gh-accent px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
            Create account
          </button>
        </form>
      </div>

      <div class="border-t border-gh-border bg-gh-panel2 px-6 py-4 text-center text-sm">
        <span class="text-gh-muted">Already have an account?</span>
        <a href="login.php" class="ml-1 font-semibold text-gh-accent hover:underline">Sign in</a>
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
