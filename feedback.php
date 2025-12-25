<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$success = '';
$error = '';

// Create feedback table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('bug', 'feature', 'improvement', 'complaint', 'other') DEFAULT 'other',
        status ENUM('new', 'reviewed', 'resolved') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_type (type),
        INDEX idx_created (created_at DESC)
    )";
    $db->exec($create_table);
} catch(PDOException $e) {
    // Table might already exist
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $type = $_POST['type'] ?? 'other';
    
    if(empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        $query = "INSERT INTO feedback (user_id, name, email, subject, message, type) 
                  VALUES (:user_id, :name, :email, :subject, :message, :type)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        
        if($stmt->execute()) {
            $success = 'Thank you for your feedback! We\'ll review it and get back to you soon.';
        } else {
            $error = 'Failed to submit feedback. Please try again.';
        }
    }
}

include 'views/header.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div style="text-align: center; margin-bottom: 3rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">💭</div>
            <h1>Send Us Feedback</h1>
            <p style="color: var(--text-gray); font-size: 1.1rem; max-width: 600px; margin: 1rem auto;">
                We value your input! Help us improve Turnpage by sharing your thoughts, reporting bugs, or suggesting new features.
            </p>
        </div>

        <?php if($success): ?>
        <div class="alert alert-success">
            <strong>✓ Feedback Submitted!</strong><br>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="feedback.php">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" name="name" required 
                               value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required
                               value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Feedback Type</label>
                    <select name="type" required>
                        <option value="feature">💡 Feature Request</option>
                        <option value="bug">🐛 Bug Report</option>
                        <option value="improvement">⚡ Improvement Suggestion</option>
                        <option value="complaint">😟 Complaint</option>
                        <option value="other">📝 Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required placeholder="Brief description of your feedback">
                </div>
                
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="8" required 
                              placeholder="Please provide detailed information about your feedback..."
                              style="resize: vertical;"></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>💡 Tips for great feedback:</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <li>Be specific about the issue or suggestion</li>
                        <li>Include steps to reproduce bugs</li>
                        <li>Mention which device/browser you're using</li>
                        <li>Attach screenshots if possible (email them separately)</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-primary btn-block" style="margin-top: 1rem;">
                    Send Feedback 📤
                </button>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            <div class="card" style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">📧</div>
                <h3 style="color: var(--primary-blue); margin-bottom: 0.5rem;">Email Us</h3>
                <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 1rem;">
                    Prefer email? Contact us directly
                </p>
                <a href="mailto:support@turnpage.io" style="color: var(--primary-blue); word-break: break-all;">
                    support@turnpage.io
                </a>
            </div>
            
            <div class="card" style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">❓</div>
                <h3 style="color: var(--primary-blue); margin-bottom: 0.5rem;">FAQ</h3>
                <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 1rem;">
                    Check our frequently asked questions
                </p>
                <a href="faq.php" class="btn-secondary btn-small">
                    View FAQ
                </a>
            </div>
            
            <div class="card" style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">💬</div>
                <h3 style="color: var(--primary-blue); margin-bottom: 0.5rem;">Community</h3>
                <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 1rem;">
                    Join our community discussions
                </p>
                <a href="blog.php" class="btn-secondary btn-small">
                    Visit Blog
                </a>
            </div>
        </div>

        <div class="card" style="background: linear-gradient(135deg, rgba(66, 103, 245, 0.1), rgba(29, 155, 240, 0.1)); border: 2px solid var(--primary-blue);">
            <h2 style="text-align: center; margin-bottom: 1rem;">We're Here to Help! 💙</h2>
            <p style="color: var(--text-gray); text-align: center; max-width: 600px; margin: 0 auto; line-height: 1.8;">
                Your feedback helps us build a better Turnpage for everyone. We read every submission and use your insights to improve the platform. Thank you for being part of our community!
            </p>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>