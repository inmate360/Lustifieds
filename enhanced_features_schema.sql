-- Enhanced User Profiles
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_views INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_earnings DECIMAL(10,2) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscriber_count INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_badge VARCHAR(50);
ALTER TABLE users ADD COLUMN IF NOT EXISTS about_me TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS social_links JSON;

-- Coins System
CREATE TABLE IF NOT EXISTS user_coins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    lifetime_purchased DECIMAL(10,2) DEFAULT 0.00,
    lifetime_spent DECIMAL(10,2) DEFAULT 0.00,
    lifetime_earned DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_coins (user_id)
);

-- Coin Transactions
CREATE TABLE IF NOT EXISTS coin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('purchase', 'earn', 'spend', 'refund', 'tip', 'subscription') NOT NULL,
    description TEXT,
    reference_type VARCHAR(50),
    reference_id INT,
    bitcoin_tx_hash VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Media Content (Paywall Content)
CREATE TABLE IF NOT EXISTS media_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_type ENUM('photo', 'video', 'photo_set', 'video_set') NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_free BOOLEAN DEFAULT FALSE,
    is_exclusive BOOLEAN DEFAULT FALSE,
    thumbnail_path VARCHAR(500),
    preview_path VARCHAR(500),
    blur_preview BOOLEAN DEFAULT TRUE,
    view_count INT DEFAULT 0,
    purchase_count INT DEFAULT 0,
    like_count INT DEFAULT 0,
    status ENUM('draft', 'published', 'archived', 'removed') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_creator (creator_id),
    INDEX idx_status (status),
    INDEX idx_price (price),
    INDEX idx_published (published_at)
);

-- Media Files (Individual files in content)
CREATE TABLE IF NOT EXISTS media_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    duration INT DEFAULT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES media_content(id) ON DELETE CASCADE,
    INDEX idx_content (content_id)
);

-- Media Purchases
CREATE TABLE IF NOT EXISTS media_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    buyer_id INT NOT NULL,
    creator_id INT NOT NULL,
    price_paid DECIMAL(10,2) NOT NULL,
    transaction_id INT,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES media_content(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES coin_transactions(id),
    UNIQUE KEY unique_purchase (content_id, buyer_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_creator (creator_id),
    INDEX idx_content (content_id)
);

-- Subscriptions (Monthly subscriptions to creators)
CREATE TABLE IF NOT EXISTS creator_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    subscriber_id INT NOT NULL,
    monthly_price DECIMAL(10,2) NOT NULL,
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NULL,
    auto_renew BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'cancelled', 'expired', 'paused') DEFAULT 'active',
    total_paid DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subscription (creator_id, subscriber_id, status),
    INDEX idx_creator (creator_id),
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_status (status)
);

-- Creator Settings
CREATE TABLE IF NOT EXISTS creator_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    is_creator BOOLEAN DEFAULT FALSE,
    creator_name VARCHAR(100),
    subscription_price DECIMAL(10,2) DEFAULT 9.99,
    allow_tips BOOLEAN DEFAULT TRUE,
    allow_custom_requests BOOLEAN DEFAULT TRUE,
    custom_request_price DECIMAL(10,2) DEFAULT 20.00,
    welcome_message TEXT,
    revenue_share DECIMAL(5,2) DEFAULT 80.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_creator (user_id)
);

-- Content Likes
CREATE TABLE IF NOT EXISTS media_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES media_content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (content_id, user_id),
    INDEX idx_content (content_id),
    INDEX idx_user (user_id)
);

-- Tips
CREATE TABLE IF NOT EXISTS tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    message TEXT,
    content_id INT NULL,
    transaction_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES media_content(id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES coin_transactions(id),
    INDEX idx_from (from_user_id),
    INDEX idx_to (to_user_id),
    INDEX idx_content (content_id)
);

-- City Selection Persistence
CREATE TABLE IF NOT EXISTS user_city_selection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    city_id INT NOT NULL,
    city_slug VARCHAR(100) NOT NULL,
    state_id INT NOT NULL,
    selected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_city (user_id),
    INDEX idx_city (city_id)
);