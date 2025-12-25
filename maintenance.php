<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is admin
$is_admin = false;
if(isset($_SESSION['user_id'])) {
    $query = "SELECT is_admin FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch();
    $is_admin = $user && $user['is_admin'];
}

// Get maintenance settings
$settings = [];
try {
    $query = "SELECT setting_key, setting_value FROM site_settings 
              WHERE setting_key IN ('maintenance_mode', 'maintenance_title', 'maintenance_message', 
                                    'coming_soon_mode', 'coming_soon_message', 'coming_soon_launch_date',
                                    'allow_admin_access')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch(PDOException $e) {
    error_log("Error fetching maintenance settings: " . $e->getMessage());
}

$maintenance_mode = ($settings['maintenance_mode'] ?? '0') == '1';
$coming_soon_mode = ($settings['coming_soon_mode'] ?? '0') == '1';
$allow_admin = ($settings['allow_admin_access'] ?? '1') == '1';

// If admin access is allowed and user is admin, redirect to home
if($allow_admin && $is_admin && ($maintenance_mode || $coming_soon_mode)) {
    // Don't redirect, show admin notice
}

// Determine which mode to show
$show_coming_soon = $coming_soon_mode;
$show_maintenance = $maintenance_mode && !$coming_soon_mode;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $show_coming_soon ? 'Coming Soon' : ($settings['maintenance_title'] ?? 'Site Maintenance'); ?> - Turnpage</title>
    <link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
    <link rel="icon" type="image/png" href="/logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            overflow: hidden;
            height: 100vh;
        }

        /* Video Background */
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .video-background video {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
        }

        /* Overlay */
        .video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                135deg,
                rgba(10, 15, 30, 0.85) 0%,
                rgba(20, 30, 60, 0.90) 50%,
                rgba(10, 15, 30, 0.85) 100%
            );
            z-index: 1;
        }

        /* Animated gradient overlay */
        .gradient-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                45deg,
                rgba(66, 103, 245, 0.1) 0%,
                rgba(29, 155, 240, 0.1) 50%,
                rgba(66, 103, 245, 0.1) 100%
            );
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
            z-index: 2;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Content Container */
        .content-container {
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
            text-align: center;
            color: white;
        }

        /* Logo Animation */
        .logo {
            width: 180px;
            height: 180px;
            margin-bottom: 2rem;
            animation: float 3s ease-in-out infinite;
            filter: drop-shadow(0 10px 40px rgba(66, 103, 245, 0.6));
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Brand Name */
        .brand-name {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #4267F5, #1D9BF0, #4267F5);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 3s linear infinite;
            text-shadow: 0 0 40px rgba(66, 103, 245, 0.5);
        }

        @keyframes shimmer {
            to { background-position: 200% center; }
        }

        /* Tagline */
        .tagline {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 3rem;
            font-weight: 300;
            letter-spacing: 2px;
        }

        /* Message Box */
        .message-box {
            max-width: 700px;
            background: rgba(15, 25, 45, 0.8);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(66, 103, 245, 0.3);
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .message-box h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #4267F5;
        }

        .message-box p {
            font-size: 1.2rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.85);
        }

        /* Countdown Timer */
        .countdown {
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .countdown-item {
            background: rgba(15, 25, 45, 0.8);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(66, 103, 245, 0.3);
            border-radius: 15px;
            padding: 1.5rem 2rem;
            min-width: 120px;
        }

        .countdown-number {
            font-size: 3rem;
            font-weight: 700;
            color: #4267F5;
            display: block;
            margin-bottom: 0.5rem;
        }

        .countdown-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .social-link {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(66, 103, 245, 0.2);
            border: 2px solid rgba(66, 103, 245, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .social-link:hover {
            background: rgba(66, 103, 245, 0.5);
            border-color: #4267F5;
            transform: scale(1.1);
        }

        /* Admin Notice */
        .admin-notice {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(251, 191, 36, 0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            z-index: 100;
            color: #000;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .admin-notice a {
            color: #000;
            text-decoration: underline;
            margin-left: 0.5rem;
        }

        /* Particles Effect */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(66, 103, 245, 0.5);
            border-radius: 50%;
            animation: particle-float linear infinite;
        }

        @keyframes particle-float {
            0% {
                transform: translateY(100vh) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(100px);
                opacity: 0;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .logo {
                width: 120px;
                height: 120px;
            }

            .brand-name {
                font-size: 2.5rem;
            }

            .tagline {
                font-size: 1.2rem;
            }

            .message-box {
                padding: 2rem;
            }

            .message-box h2 {
                font-size: 1.5rem;
            }

            .message-box p {
                font-size: 1rem;
            }

            .countdown {
                gap: 1rem;
            }

            .countdown-item {
                padding: 1rem 1.5rem;
                min-width: 100px;
            }

            .countdown-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php if($show_coming_soon): ?>
    <!-- Coming Soon Mode -->
    <div class="video-background">
        <video autoplay muted loop playsinline id="bgVideo">
            <source src="/steptodown.com_820612.mp4" type="video/mp4">
        </video>
    </div>
    
    <div class="video-overlay"></div>
    <div class="gradient-overlay"></div>
    
    <!-- Animated Particles -->
    <div class="particles" id="particles"></div>
    
    <?php if($is_admin && $allow_admin): ?>
    <div class="admin-notice">
        🛡️ Admin Preview Mode
        <a href="admin/settings.php">Disable</a>
    </div>
    <?php endif; ?>
    
    <div class="content-container">
        <img src="/logo.png" alt="Turnpage" class="logo">
        <h1 class="brand-name">Turnpage</h1>
        <p class="tagline">LOCAL HOOKUP CLASSIFIEDS</p>
        
        <div class="message-box">
            <h2>🚀 Launching Soon</h2>
            <p><?php echo nl2br(htmlspecialchars($settings['coming_soon_message'] ?? 'Something amazing is coming! Stay tuned.')); ?></p>
        </div>
        
        <?php if(!empty($settings['coming_soon_launch_date'])): ?>
        <div class="countdown" id="countdown">
            <div class="countdown-item">
                <span class="countdown-number" id="days">00</span>
                <span class="countdown-label">Days</span>
            </div>
            <div class="countdown-item">
                <span class="countdown-number" id="hours">00</span>
                <span class="countdown-label">Hours</span>
            </div>
            <div class="countdown-item">
                <span class="countdown-number" id="minutes">00</span>
                <span class="countdown-label">Minutes</span>
            </div>
            <div class="countdown-item">
                <span class="countdown-number" id="seconds">00</span>
                <span class="countdown-label">Seconds</span>
            </div>
        </div>
        
        <script>
        // Countdown Timer
        const launchDate = new Date('<?php echo $settings['coming_soon_launch_date']; ?>').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = launchDate - now;
            
            if(distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('days').textContent = String(days).padStart(2, '0');
                document.getElementById('hours').textContent = String(hours).padStart(2, '0');
                document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
                document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
            } else {
                document.getElementById('countdown').innerHTML = '<p style="font-size: 2rem; color: #4267F5;">🎉 We\'re Live!</p>';
                setTimeout(() => location.reload(), 2000);
            }
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        </script>
        <?php endif; ?>
        
        <div class="social-links">
            <a href="https://twitter.com/turnpage" class="social-link" title="Twitter" target="_blank">🐦</a>
            <a href="https://facebook.com/turnpage" class="social-link" title="Facebook" target="_blank">📘</a>
            <a href="https://instagram.com/turnpage" class="social-link" title="Instagram" target="_blank">📸</a>
            <a href="mailto:info@turnpage.io" class="social-link" title="Email">📧</a>
        </div>
    </div>
    
    <script>
    // Create floating particles
    function createParticles() {
        const particlesContainer = document.getElementById('particles');
        const particleCount = 30;
        
        for(let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particle.style.animationDelay = Math.random() * 5 + 's';
            particlesContainer.appendChild(particle);
        }
    }
    
    createParticles();
    
    // Ensure video plays
    document.addEventListener('DOMContentLoaded', function() {
        const video = document.getElementById('bgVideo');
        video.play().catch(e => console.log('Video autoplay failed:', e));
    });
    </script>
    
    <?php else: ?>
    <!-- Maintenance Mode -->
    <div class="video-overlay" style="background: linear-gradient(135deg, rgba(10, 15, 30, 0.95), rgba(20, 30, 60, 0.95));"></div>
    
    <?php if($is_admin && $allow_admin): ?>
    <div class="admin-notice">
        🛡️ Admin Preview Mode
        <a href="admin/settings.php">Disable</a>
    </div>
    <?php endif; ?>
    
    <div class="content-container">
        <img src="/logo.png" alt="Turnpage" class="logo">
        <h1 class="brand-name">Under Maintenance</h1>
        
        <div class="message-box">
            <h2><?php echo htmlspecialchars($settings['maintenance_title'] ?? 'Site Maintenance'); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($settings['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Please check back soon!')); ?></p>
        </div>
        
        <p style="color: rgba(255, 255, 255, 0.6); margin-top: 2rem;">
            We'll be back shortly. Thank you for your patience! 💙
        </p>
    </div>
    <?php endif; ?>
</body>
</html>