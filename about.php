<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">

<style>
.about-hero {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    padding: 4rem 2rem;
    border-radius: 15px;
    margin-bottom: 3rem;
    text-align: center;
    color: white;
}

.about-hero h1 {
    font-size: 3rem;
    margin-bottom: 1rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.about-section {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.about-section h2 {
    color: var(--primary-blue);
    margin-bottom: 1.5rem;
    font-size: 2rem;
}

.about-section p {
    color: var(--text-gray);
    line-height: 1.8;
    margin-bottom: 1rem;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.feature-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
}

.feature-card:hover {
    border-color: var(--primary-blue);
    transform: translateY(-5px);
}

.feature-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.feature-card h3 {
    color: var(--text-white);
    margin-bottom: 1rem;
}

.feature-card p {
    color: var(--text-gray);
}

.stats-section {
    background: linear-gradient(135deg, rgba(66, 103, 245, 0.1), rgba(29, 155, 240, 0.1));
    border: 2px solid var(--primary-blue);
    border-radius: 15px;
    padding: 3rem 2rem;
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 3rem;
    font-weight: bold;
    color: var(--primary-blue);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-gray);
    font-size: 1.1rem;
}

.team-section {
    text-align: center;
    padding: 3rem 0;
}

.cta-section {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    padding: 3rem 2rem;
    border-radius: 15px;
    text-align: center;
    color: white;
}

@media (max-width: 768px) {
    .about-hero h1 {
        font-size: 2rem;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-content">
    <div class="container" style="max-width: 1200px; margin: 2rem auto; padding: 0 20px;">
        
        <!-- Hero Section -->
        <div class="about-hero">
            <h1>About Turnpage</h1>
            <p style="font-size: 1.2rem; max-width: 800px; margin: 0 auto;">
                Your trusted platform for local connections and personal classifieds
            </p>
        </div>

        <!-- Mission Section -->
        <div class="about-section">
            <h2>🎯 Our Mission</h2>
            <p>
                Turnpage was created to provide a safe, reliable, and user-friendly platform for people to connect locally. 
                Whether you're looking for companionship, friendships, or casual encounters, we're committed to creating 
                a respectful community where adults can meet and interact freely.
            </p>
            <p>
                We believe in empowering our users with the tools they need to make genuine connections while maintaining 
                their privacy and security. Our platform combines modern technology with a human touch to ensure the best 
                possible experience.
            </p>
        </div>

        <!-- Features Section -->
        <div class="about-section">
            <h2>✨ What Makes Us Different</h2>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Privacy First</h3>
                    <p>Your privacy is our top priority. We use advanced encryption and never share your personal information.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🤖</div>
                    <h3>AI Moderation</h3>
                    <p>Our advanced AI system automatically detects and removes spam, scams, and inappropriate content.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🌍</div>
                    <h3>Local Focus</h3>
                    <p>Connect with people in your area using our location-based matching and neighborhood filtering.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>Real-Time Chat</h3>
                    <p>Message instantly with typing indicators, read receipts, and image sharing capabilities.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">✓</div>
                    <h3>Verified Users</h3>
                    <p>Our verification system helps ensure authentic profiles and reduces fake accounts.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Fast & Easy</h3>
                    <p>Post your ad in minutes with our streamlined listing creation process and intuitive interface.</p>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <h2 style="color: var(--primary-blue); margin-bottom: 2rem;">📊 By The Numbers</h2>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">50K+</div>
                    <div class="stat-label">Active Users</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number">100K+</div>
                    <div class="stat-label">Connections Made</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number">200+</div>
                    <div class="stat-label">Cities Covered</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support Available</div>
                </div>
            </div>
        </div>

        <!-- Our Values -->
        <div class="about-section">
            <h2>💎 Our Values</h2>
            
            <div style="display: grid; gap: 2rem; margin-top: 2rem;">
                <div>
                    <h3 style="color: var(--primary-blue); margin-bottom: 0.5rem;">🛡️ Safety & Security</h3>
                    <p style="color: var(--text-gray);">
                        We invest heavily in security measures, including SSL encryption, secure payment processing, 
                        and regular security audits to protect your data.
                    </p>
                </div>
                
                <div>
                    <h3 style="color: var(--primary-blue); margin-bottom: 0.5rem;">🤝 Respect & Consent</h3>
                    <p style="color: var(--text-gray);">
                        We promote a culture of mutual respect and consent. All interactions should be consensual, 
                        and we have zero tolerance for harassment or abuse.
                    </p>
                </div>
                
                <div>
                    <h3 style="color: var(--primary-blue); margin-bottom: 0.5rem;">🌈 Inclusivity</h3>
                    <p style="color: var(--text-gray);">
                        Everyone is welcome here regardless of gender, sexual orientation, race, or background. 
                        We celebrate diversity and create a welcoming space for all.
                    </p>
                </div>
                
                <div>
                    <h3 style="color: var(--primary-blue); margin-bottom: 0.5rem;">🎯 Authenticity</h3>
                    <p style="color: var(--text-gray);">
                        We encourage genuine profiles and authentic connections. Our verification system and 
                        AI moderation help keep fake profiles off the platform.
                    </p>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="cta-section">
            <h2 style="margin-bottom: 1rem;">Ready to Get Started?</h2>
            <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                Join thousands of people already making connections on Turnpage
            </p>
            
            <?php if(!isset($_SESSION['user_id'])): ?>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="/register.php" class="btn-primary" style="background: white; color: var(--primary-blue); font-size: 1.1rem; padding: 1rem 2rem;">
                    Sign Up Free
                </a>
                <a href="/how-it-works.php" class="btn-secondary" style="border-color: white; color: white; font-size: 1.1rem; padding: 1rem 2rem;">
                    Learn More
                </a>
            </div>
            <?php else: ?>
            <a href="/create-listing.php" class="btn-primary" style="background: white; color: var(--primary-blue); font-size: 1.1rem; padding: 1rem 2rem;">
                Create Your First Listing
            </a>
            <?php endif; ?>
        </div>

        <!-- Contact Info -->
        <div class="about-section" style="text-align: center;">
            <h2>📧 Get In Touch</h2>
            <p style="color: var(--text-gray); margin-bottom: 2rem;">
                Have questions or feedback? We'd love to hear from you!
            </p>
            
            <div style="display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap;">
                <div>
                    <strong style="color: var(--primary-blue);">Support Email</strong><br>
                    <a href="mailto:support@turnpage.io" style="color: var(--text-white);">support@turnpage.io</a>
                </div>
                
                <div>
                    <strong style="color: var(--primary-blue);">Business Inquiries</strong><br>
                    <a href="mailto:business@turnpage.io" style="color: var(--text-white);">business@turnpage.io</a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'views/footer.php'; ?>