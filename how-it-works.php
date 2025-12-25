<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">

<style>
.how-hero {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    padding: 4rem 2rem;
    border-radius: 15px;
    margin-bottom: 3rem;
    text-align: center;
    color: white;
}

.how-hero h1 {
    font-size: 3rem;
    margin-bottom: 1rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.steps-container {
    max-width: 1000px;
    margin: 0 auto;
}

.step {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 2rem;
    align-items: center;
    transition: all 0.3s;
}

.step:hover {
    border-color: var(--primary-blue);
    transform: translateX(10px);
}

.step-number {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    color: white;
    flex-shrink: 0;
}

.step-content h3 {
    color: var(--primary-blue);
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.step-content p {
    color: var(--text-gray);
    line-height: 1.8;
}

.features-section {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 3rem 2rem;
    margin: 3rem 0;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.feature-box {
    padding: 1.5rem;
    background: rgba(66, 103, 245, 0.05);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.feature-box h4 {
    color: var(--primary-blue);
    margin-bottom: 0.5rem;
}

.feature-box p {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.tips-section {
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid var(--success-green);
    border-radius: 15px;
    padding: 2rem;
    margin: 3rem 0;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.tip-item {
    display: flex;
    gap: 1rem;
    align-items: start;
}

.tip-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.faq-section {
    max-width: 800px;
    margin: 3rem auto;
}

.faq-item {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}

.faq-item:hover {
    border-color: var(--primary-blue);
}

.faq-question {
    font-weight: 600;
    color: var(--text-white);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.faq-answer {
    color: var(--text-gray);
    margin-top: 1rem;
    display: none;
    line-height: 1.8;
}

.faq-item.active .faq-answer {
    display: block;
}

@media (max-width: 768px) {
    .how-hero h1 {
        font-size: 2rem;
    }
    
    .step {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .step:hover {
        transform: translateY(-5px);
    }
}
</style>

<div class="page-content">
    <div class="container" style="max-width: 1200px; margin: 2rem auto; padding: 0 20px;">
        
        <!-- Hero Section -->
        <div class="how-hero">
            <h1>How Turnpage Works</h1>
            <p style="font-size: 1.2rem; max-width: 800px; margin: 0 auto;">
                Simple, safe, and effective. Start connecting in minutes!
            </p>
        </div>

        <!-- Steps Section -->
        <div class="steps-container">
            <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-blue);">
                Getting Started in 5 Easy Steps
            </h2>

            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Create Your Free Account</h3>
                    <p>
                        Sign up in seconds with just your email and username. No credit card required to join. 
                        Choose a unique username and create a secure password. Verify your email and you're ready to go!
                    </p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Set Your Location</h3>
                    <p>
                        Choose your city or let us auto-detect your location for the best local matches. 
                        Browse by neighborhood, set your search radius, and use travel mode if you're visiting another city. 
                        Your location helps us show you the most relevant listings.
                    </p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Create Your Listing</h3>
                    <p>
                        Post your ad in minutes! Choose a category, write an engaging title and description, 
                        add photos (optional), and specify what you're looking for. Our AI moderation ensures 
                        quality content while keeping the platform safe.
                    </p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h3>Browse & Connect</h3>
                    <p>
                        Search listings in your area using filters for category, distance, and preferences. 
                        View profiles, check who's online, and see nearby users. When you find someone interesting, 
                        send them a message instantly using our real-time chat system.
                    </p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">5</div>
                <div class="step-content">
                    <h3>Meet & Stay Safe</h3>
                    <p>
                        Chat to get to know each other, exchange photos, and when you're comfortable, arrange to meet. 
                        Always meet in public places first, tell a friend where you're going, and trust your instincts. 
                        Use our safety features like blocking and reporting if needed.
                    </p>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="features-section">
            <h2 style="text-align: center; color: var(--primary-blue); margin-bottom: 0.5rem;">
                🚀 Powerful Features
            </h2>
            <p style="text-align: center; color: var(--text-gray); margin-bottom: 2rem;">
                Everything you need for successful connections
            </p>

            <div class="features-grid">
                <div class="feature-box">
                    <h4>📍 Location-Based</h4>
                    <p>Find people nearby with radius search and neighborhood filtering</p>
                </div>

                <div class="feature-box">
                    <h4>💬 Real-Time Chat</h4>
                    <p>Instant messaging with typing indicators and read receipts</p>
                </div>

                <div class="feature-box">
                    <h4>📸 Photo Sharing</h4>
                    <p>Upload multiple photos to your listing and share in messages</p>
                </div>

                <div class="feature-box">
                    <h4>🤖 AI Moderation</h4>
                    <p>Automatic spam detection and content filtering</p>
                </div>

                <div class="feature-box">
                    <h4>✈️ Travel Mode</h4>
                    <p>Post your listing in multiple cities when traveling</p>
                </div>

                <div class="feature-box">
                    <h4>⭐ Favorites</h4>
                    <p>Save listings and profiles for easy access later</p>
                </div>

                <div class="feature-box">
                    <h4>🔔 Notifications</h4>
                    <p>Get instant alerts for messages and activity</p>
                </div>

                <div class="feature-box">
                    <h4>🕶️ Incognito Mode</h4>
                    <p>Browse anonymously without appearing in search results</p>
                </div>
            </div>
        </div>

        <!-- Safety Tips -->
        <div class="tips-section">
            <h2 style="text-align: center; color: var(--success-green); margin-bottom: 0.5rem;">
                🛡️ Safety Tips
            </h2>
            <p style="text-align: center; color: var(--text-gray); margin-bottom: 2rem;">
                Stay safe while using Turnpage
            </p>

            <div class="tips-grid">
                <div class="tip-item">
                    <div class="tip-icon">✓</div>
                    <div>
                        <strong style="color: var(--text-white);">Meet in Public</strong>
                        <p style="color: var(--text-gray); font-size: 0.9rem;">
                            Always meet in a public place for the first time. Coffee shops, restaurants, and busy areas are ideal.
                        </p>
                    </div>
                </div>

                <div class="tip-item">
                    <div class="tip-icon">✓</div>
                    <div>
                        <strong style="color: var(--text-white);">Tell Someone</strong>
                        <p style="color: var(--text-gray); font-size: 0.9rem;">
                            Let a friend or family member know where you're going and who you're meeting.
                        </p>
                    </div>
                </div>

                <div class="tip-item">
                    <div class="tip-icon">✓</div>
                    <div>
                        <strong style="color: var(--text-white);">Trust Your Instincts</strong>
                        <p style="color: var(--text-gray); font-size: 0.9rem;">
                            If something feels off, it probably is. Don't hesitate to leave or cancel plans.
                        </p>
                    </div>
                </div>

                <div class="tip-item">
                    <div class="tip-icon">✓</div>
                    <div>
                        <strong style="color: var(--text-white);">Protect Your Info</strong>
                        <p style="color: var(--text-gray); font-size: 0.9rem;">
                            Don't share personal details like your home address or financial information early on.
                        </p>
                    </div>
                </div>

                <div class="tip-item">
                    <div class="tip-icon">✓</div>
                    <div>
                        <strong style="color: var(--text-white);">Use Platform Chat</strong>
                        <p style="color: var(--text-gray); font-size: 0.9rem;">
                            Keep conversations on Turnpage initially. This provides a record and security.
                        </p>
                    </div>
                </div>

                <div class="tip-item">
                    <div class="tip-icon">✓</div>
                    <div>
                        <strong style="color: var(--text-white);">Report Issues</strong>
                        <p style="color: var(--text-gray); font-size: 0.9rem;">
                            If someone violates our terms or makes you uncomfortable, report them immediately.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-blue);">
                ❓ Frequently Asked Questions
            </h2>

            <div class="faq-item" onclick="this.classList.toggle('active')">
                <div class="faq-question">
                    <span>Is Turnpage really free?</span>
                    <span>▼</span>
                </div>
                <div class="faq-answer">
                    Yes! Creating an account, posting listings, and messaging are completely free. 
                    We offer optional premium features like featured listings and sponsored profiles for those who want extra visibility.
                </div>
            </div>

            <div class="faq-item" onclick="this.classList.toggle('active')">
                <div class="faq-question">
                    <span>How do I verify my account?</span>
                    <span>▼</span>
                </div>
                <div class="faq-answer">
                    After signing up, check your email for a verification link. Click it to verify your account. 
                    For photo verification, go to your settings and upload a selfie matching our guidelines.
                </div>
            </div>

            <div class="faq-item" onclick="this.classList.toggle('active')">
                <div class="faq-question">
                    <span>Can I post in multiple cities?</span>
                    <span>▼</span>
                </div>
                <div class="faq-answer">
                    Yes! Use our Travel Mode feature to post your listing in multiple cities. This is perfect if you're 
                    traveling or relocating. Premium members can post in up to 10 cities simultaneously.
                </div>
            </div>

            <div class="faq-item" onclick="this.classList.toggle('active')">
                <div class="faq-question">
                    <span>What if someone is harassing me?</span>
                    <span>▼</span>
                </div>
                <div class="faq-answer">
                    Use the block button to immediately prevent them from contacting you. Then report their profile using 
                    the report feature. We take harassment seriously and will investigate all reports promptly.
                </div>
            </div>

            <div class="faq-item" onclick="this.classList.toggle('active')">
                <div class="faq-question">
                    <span>How long do listings stay active?</span>
                    <span>▼</span>
                </div>
                <div class="faq-answer">
                    Regular listings stay active for 30 days. You can renew them at any time from your dashboard. 
                    Premium listings remain active for the duration you've purchased.
                </div>
            </div>

            <div class="faq-item" onclick="this.classList.toggle('active')">
                <div class="faq-question">
                    <span>Can I edit my listing after posting?</span>
                    <span>▼</span>
                </div>
                <div class="faq-answer">
                    Absolutely! Go to "My Listings" and click the edit button on any listing. You can update the title, 
                    description, photos, and other details at any time.
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div style="background: linear-gradient(135deg, #4267F5, #1D9BF0); padding: 3rem 2rem; border-radius: 15px; text-align: center; color: white; margin-top: 3rem;">
            <h2 style="margin-bottom: 1rem;">Ready to Start Connecting?</h2>
            <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                Join Turnpage today and meet people in your area
            </p>
            
            <?php if(!isset($_SESSION['user_id'])): ?>
            <a href="/register.php" class="btn-primary" style="background: white; color: var(--primary-blue); font-size: 1.1rem; padding: 1rem 2rem;">
                Get Started Now - It's Free!
            </a>
            <?php else: ?>
            <a href="/create-listing.php" class="btn-primary" style="background: white; color: var(--primary-blue); font-size: 1.1rem; padding: 1rem 2rem;">
                Create Your Listing
            </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'views/footer.php'; ?>