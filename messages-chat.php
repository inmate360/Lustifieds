<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UnifiedMessaging.php';
require_once 'includes/maintenance_check.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$messaging = new UnifiedMessaging($db);

// Get other user if specified
$other_user_id = (int)($_GET['user'] ?? 0);
$other_user = null;

if($other_user_id) {
    $query = "SELECT id, username, is_online, last_seen FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $other_user_id);
    $stmt->execute();
    $other_user = $stmt->fetch();
}

// Get user's message limit info
$limitInfo = $messaging->canSendMessage($_SESSION['user_id']);

// Get conversations list
$conversations = $messaging->getConversationsList($_SESSION['user_id']);

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
body {
    background: #0a1628;
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

.page-content {
    padding: 0;
    margin: 0;
}

.messages-wrapper {
    width: 100%;
    height: 100vh;
    max-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #0a1628;
    position: relative;
}

.messages-container {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    height: calc(100vh - 60px);
    background: #0a1628;
    border-radius: 0;
    overflow: hidden;
}

.conversations-sidebar {
    border-right: 1px solid rgba(66, 103, 245, 0.2);
    display: flex;
    flex-direction: column;
    background: #0d1b2a;
    overflow: hidden;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.2);
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    flex-shrink: 0;
}

.sidebar-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
}

.sidebar-subtitle {
    opacity: 0.9;
    font-size: 0.9rem;
    margin: 0;
}

.message-limit-banner {
    background: rgba(245, 158, 11, 0.15);
    padding: 1rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.2);
    text-align: center;
    flex-shrink: 0;
}

.message-limit-banner.premium {
    background: rgba(251, 191, 36, 0.15);
}

.limit-text {
    font-size: 0.85rem;
    color: var(--text-white);
    line-height: 1.6;
}

.upgrade-link {
    color: var(--featured-gold);
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    margin-top: 0.25rem;
}

.upgrade-link:hover {
    text-decoration: underline;
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    background: #0d1b2a;
}

.conversation-item {
    padding: 1rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.1);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 1rem;
    background: transparent;
}

.conversation-item:hover {
    background: rgba(66, 103, 245, 0.1);
}

.conversation-item.active {
    background: rgba(66, 103, 245, 0.2);
    border-left: 3px solid var(--primary-blue);
}

.conversation-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    position: relative;
}

.online-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 14px;
    height: 14px;
    background: var(--success-green);
    border: 3px solid #0d1b2a;
    border-radius: 50%;
}

.conversation-info {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.conversation-name {
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 0.25rem;
    font-size: 1rem;
}

.conversation-preview {
    font-size: 0.85rem;
    color: var(--text-gray);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-badge {
    background: var(--primary-blue);
    color: white;
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.chat-area {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #0a1628;
    position: relative;
}

.chat-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #0d1b2a;
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.chat-user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.chat-username {
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 0.25rem;
}

.chat-status {
    font-size: 0.8rem;
    color: var(--text-gray);
}

.messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: #0a1628;
    min-height: 0;
}

.message-bubble {
    max-width: 75%;
    padding: 0.75rem 1rem;
    border-radius: 16px;
    word-wrap: break-word;
    animation: messageSlideIn 0.3s ease;
    position: relative;
}

@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-sent {
    align-self: flex-end;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border-bottom-right-radius: 4px;
}

.message-received {
    align-self: flex-start;
    background: #1e293b;
    color: var(--text-white);
    border-bottom-left-radius: 4px;
    border: 1px solid rgba(66, 103, 245, 0.2);
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 0.35rem;
    display: block;
}

.message-image {
    max-width: 100%;
    max-height: 300px;
    border-radius: 12px;
    margin-top: 0.5rem;
    cursor: pointer;
    transition: transform 0.2s;
}

.message-image:hover {
    transform: scale(1.02);
}

.message-input-area {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(66, 103, 245, 0.2);
    background: #0d1b2a;
    flex-shrink: 0;
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.warning-message {
    background: rgba(245, 158, 11, 0.15);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    font-size: 0.85rem;
    color: var(--text-white);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.message-input-form {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
}

.message-input {
    flex: 1;
    padding: 0.875rem 1.25rem;
    background: #0a1628;
    border: 2px solid rgba(66, 103, 245, 0.3);
    border-radius: 24px;
    color: var(--text-white);
    resize: none;
    max-height: 120px;
    min-height: 48px;
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.2s;
    line-height: 1.4;
}

.message-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.message-input:focus {
    outline: none;
    border-color: var(--primary-blue);
    background: #0d1b2a;
    box-shadow: 0 0 0 3px rgba(66, 103, 245, 0.15);
}

.message-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.send-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border: none;
    color: white;
    font-size: 1.3rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.send-btn:hover:not(:disabled) {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(66, 103, 245, 0.5);
}

.send-btn:active:not(:disabled) {
    transform: scale(1.05);
}

.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-gray);
    padding: 2rem;
    background: #0a1628;
}

.empty-icon {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Premium Modal */
.premium-upsell-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 10000;
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

.premium-upsell-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.premium-upsell-content {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    border-radius: 24px;
    max-width: 500px;
    width: 100%;
    padding: 2.5rem 2rem;
    text-align: center;
    color: white;
    animation: slideUp 0.4s ease;
    position: relative;
}

.premium-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255, 255, 255, 0.15);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 1.5rem;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
}

.premium-close:hover {
    background: rgba(255, 255, 255, 0.25);
}

.premium-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.premium-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
}

.premium-features {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    text-align: left;
}

.premium-feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0;
    font-size: 0.95rem;
}

/* Mobile Optimization */
@media (max-width: 768px) {
    .messages-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 140px);
    }
    
    .conversations-sidebar {
        display: none;
    }
    
    .conversations-sidebar.mobile-show {
        display: flex;
    }
    
    .message-bubble {
        max-width: 85%;
        font-size: 0.95rem;
    }
    
    .chat-header {
        padding: 0.875rem 1rem;
    }
    
    .messages-area {
        padding: 1rem;
    }
    
    .message-input-area {
        padding: 0.875rem 1rem;
    }
    
    .premium-upsell-content {
        padding: 2rem 1.5rem;
    }
}

/* Scrollbar Styling */
.conversations-list::-webkit-scrollbar,
.messages-area::-webkit-scrollbar {
    width: 8px;
}

.conversations-list::-webkit-scrollbar-track,
.messages-area::-webkit-scrollbar-track {
    background: transparent;
}

.conversations-list::-webkit-scrollbar-thumb,
.messages-area::-webkit-scrollbar-thumb {
    background: rgba(66, 103, 245, 0.3);
    border-radius: 4px;
}

.conversations-list::-webkit-scrollbar-thumb:hover,
.messages-area::-webkit-scrollbar-thumb:hover {
    background: rgba(66, 103, 245, 0.5);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Light Theme Overrides */
:root[data-theme="light"] body {
    background: #f8fafc;
}

:root[data-theme="light"] .messages-wrapper {
    background: #f8fafc;
}

:root[data-theme="light"] .messages-container {
    background: #f8fafc;
}

:root[data-theme="light"] .conversations-sidebar {
    background: #f8fafc;
}

:root[data-theme="light"] .conversations-list {
    background: #f8fafc;
}

:root[data-theme="light"] .conversation-item {
    border-bottom-color: #e2e8f0;
}

:root[data-theme="light"] .online-indicator {
    border-color: #f8fafc;
}

:root[data-theme="light"] .chat-area {
    background: #ffffff;
}

:root[data-theme="light"] .chat-header {
    background: #f8fafc;
    border-bottom-color: #e2e8f0;
}

:root[data-theme="light"] .messages-area {
    background: #ffffff;
}

:root[data-theme="light"] .message-received {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #0f172a;
}

:root[data-theme="light"] .message-input-area {
    background: #f8fafc;
    border-top-color: #e2e8f0;
}

:root[data-theme="light"] .message-input {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #0f172a;
}

:root[data-theme="light"] .message-input::placeholder {
    color: #94a3b8;
}

:root[data-theme="light"] .message-input:focus {
    background: #ffffff;
}

:root[data-theme="light"] .empty-state {
    background: #ffffff;
}
</style>

<div class="messages-wrapper">
    <div class="messages-container">
        
        <!-- Conversations Sidebar -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2>💬 Messages</h2>
                <p class="sidebar-subtitle">
                    <?php echo count($conversations); ?> conversation<?php echo count($conversations) != 1 ? 's' : ''; ?>
                </p>
            </div>
            
            <!-- Message Limit Banner -->
            <?php if($limitInfo['is_premium']): ?>
            <div class="message-limit-banner premium">
                <div class="limit-text">
                    ⭐ <strong>Premium</strong> - Unlimited Messages
                </div>
            </div>
            <?php else: ?>
            <div class="message-limit-banner">
                <div class="limit-text">
                    <strong><?php echo $limitInfo['remaining']; ?> / 25</strong> messages left today<br>
                    <a href="/membership.php" class="upgrade-link">🔥 Upgrade for unlimited</a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Conversations List -->
            <div class="conversations-list" id="conversationsList">
                <?php if(count($conversations) > 0): ?>
                    <?php foreach($conversations as $conv): ?>
                    <div class="conversation-item <?php echo $other_user_id == $conv['contact_id'] ? 'active' : ''; ?>" 
                         onclick="openConversation(<?php echo $conv['contact_id']; ?>)">
                        <div class="conversation-avatar">
                            👤
                            <?php if($conv['is_online']): ?>
                            <span class="online-indicator"></span>
                            <?php endif; ?>
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name"><?php echo htmlspecialchars($conv['username']); ?></div>
                            <div class="conversation-preview">
                                <?php 
                                $preview = $conv['last_message'] ?? 'No messages yet';
                                echo htmlspecialchars(substr($preview, 0, 35));
                                echo strlen($preview) > 35 ? '...' : '';
                                ?>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.25rem;">
                            <?php if($conv['unread_count'] > 0): ?>
                            <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div style="padding: 3rem 2rem; text-align: center; color: var(--text-gray);">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.5;">💬</div>
                    <p style="margin: 0; font-size: 1rem;">No conversations yet</p>
                    <p style="margin: 0.5rem 0 0; font-size: 0.85rem; opacity: 0.8;">Start browsing to connect!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area" id="chatArea">
            <?php if($other_user): ?>
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="chat-user-info">
                    <div class="conversation-avatar" style="width: 40px; height: 40px; font-size: 1.25rem;">
                        👤
                        <?php if($other_user['is_online']): ?>
                        <span class="online-indicator" style="width: 12px; height: 12px;"></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="chat-username"><?php echo htmlspecialchars($other_user['username']); ?></div>
                        <div class="chat-status" id="userStatus">
                            <?php echo $other_user['is_online'] ? '🟢 Online now' : '⚪ Offline'; ?>
                        </div>
                    </div>
                </div>
                <a href="/profile.php?id=<?php echo $other_user['id']; ?>" class="btn-secondary btn-small">
                    View Profile
                </a>
            </div>
            
            <!-- Messages Area -->
            <div class="messages-area" id="messagesArea">
                <div style="text-align: center; padding: 3rem 2rem; color: var(--text-gray);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.5;">⏳</div>
                    <p>Loading messages...</p>
                </div>
            </div>
            
            <!-- Message Input -->
            <div class="message-input-area" id="messageInputArea">
                <?php if(!$limitInfo['is_premium']): ?>
                <div class="warning-message">
                    ⚠️ <strong>Free users:</strong> Phone numbers blocked. 
                    <a href="/membership.php" style="color: var(--featured-gold); text-decoration: underline;">Upgrade</a> to share contact info.
                </div>
                <?php endif; ?>
                
                <form class="message-input-form" id="messageForm" onsubmit="sendMessage(event)">
                    <textarea 
                        id="messageInput" 
                        class="message-input" 
                        placeholder="<?php echo $limitInfo['can_send'] ? 'Type a message...' : 'Daily limit reached'; ?>"
                        rows="1"
                        <?php echo !$limitInfo['can_send'] ? 'disabled' : ''; ?>></textarea>
                    <button type="submit" class="send-btn" id="sendBtn" <?php echo !$limitInfo['can_send'] ? 'disabled' : ''; ?>>
                        ➤
                    </button>
                </form>
                <?php if(!$limitInfo['can_send']): ?>
                <div style="text-align: center; margin-top: 0.75rem; font-size: 0.85rem;">
                    <span style="color: var(--danger-red); font-weight: 600;">Daily limit reached.</span> 
                    <a href="/membership.php" style="color: var(--featured-gold); font-weight: 600;">Upgrade to Premium</a>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Empty State with Recent Conversations -->
            <div class="empty-state">
                <div class="empty-icon">💬</div>
                <h3 style="margin: 0 0 0.5rem; color: var(--text-white);">Select a conversation</h3>
                <p style="margin: 0 0 2rem; opacity: 0.8;">Choose someone to start messaging</p>
                
                <?php if(count($conversations) > 0): ?>
                <div style="width: 100%; max-width: 500px;">
                    <h4 style="color: var(--primary-blue); margin-bottom: 1rem;">Recent Conversations</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach(array_slice($conversations, 0, 5) as $conv): ?>
                        <div onclick="openConversation(<?php echo $conv['contact_id']; ?>)" 
                             style="background: rgba(66, 103, 245, 0.1); padding: 1rem; border-radius: 12px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 1rem; border: 1px solid rgba(66, 103, 245, 0.2);"
                             onmouseover="this.style.background='rgba(66, 103, 245, 0.2)'"
                             onmouseout="this.style.background='rgba(66, 103, 245, 0.1)'">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; position: relative;">
                                👤
                                <?php if($conv['is_online']): ?>
                                <span style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: var(--success-green); border: 2px solid #0a1628; border-radius: 50%;"></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1; text-align: left;">
                                <div style="font-weight: 600; color: var(--text-white); margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($conv['username']); ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-gray);">
                                    <?php 
                                    $preview = $conv['last_message'] ?? 'No messages yet';
                                    echo htmlspecialchars(substr($preview, 0, 40));
                                    echo strlen($preview) > 40 ? '...' : '';
                                    ?>
                                </div>
                            </div>
                            <?php if($conv['unread_count'] > 0): ?>
                            <span style="background: var(--primary-blue); color: white; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">
                                <?php echo $conv['unread_count']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Premium Modal -->
<div class="premium-upsell-modal" id="premiumModal">
    <div class="premium-upsell-content">
        <button class="premium-close" onclick="closePremiumModal()">×</button>
        
        <div class="premium-icon">💎</div>
        <h2 class="premium-title" id="modalTitle">Upgrade to Premium</h2>
        <p id="modalMessage" style="font-size: 1rem; margin-bottom: 1.5rem; opacity: 0.9;">
            Unlock unlimited messaging and contact sharing!
        </p>
        
        <div class="premium-features">
            <div class="premium-feature-item">
                <span style="font-size: 1.3rem;">✅</span>
                <span><strong>Unlimited Messages</strong></span>
            </div>
            <div class="premium-feature-item">
                <span style="font-size: 1.3rem;">📞</span>
                <span><strong>Share Phone Numbers</strong></span>
            </div>
            <div class="premium-feature-item">
                <span style="font-size: 1.3rem;">📸</span>
                <span><strong>Send Images</strong></span>
            </div>
            <div class="premium-feature-item">
                <span style="font-size: 1.3rem;">⭐</span>
                <span><strong>Featured Listings</strong></span>
            </div>
        </div>
        
        <div style="display: flex; gap: 0.75rem;">
            <a href="/membership.php" class="btn-primary" style="flex: 1; padding: 1rem; font-size: 1rem; background: white; color: #1e3a8a; text-align: center; text-decoration: none;">
                Upgrade - $19.99/mo
            </a>
            <button onclick="closePremiumModal()" class="btn-secondary" style="padding: 1rem 1.5rem; background: rgba(255,255,255,0.15); color: white; border: none;">
                Later
            </button>
        </div>
    </div>
</div>

<script>
const currentUserId = <?php echo $_SESSION['user_id']; ?>;
const otherUserId = <?php echo $other_user_id ?: 'null'; ?>;
let lastMessageId = 0;
let refreshInterval;
let isPremium = <?php echo $limitInfo['is_premium'] ? 'true' : 'false'; ?>;

function loadMessages() {
    if(!otherUserId) return;
    
    fetch(`/api/messages.php?action=get_conversation&user_id=${otherUserId}`)
        .then(response => response.json())
        .then(data => {
            if(data.success && data.messages) {
                displayMessages(data.messages);
            }
        })
        .catch(error => console.error('Error:', error));
}

function displayMessages(messages) {
    const area = document.getElementById('messagesArea');
    if(!area) return;
    
    const wasAtBottom = area.scrollHeight - area.scrollTop === area.clientHeight;
    
    area.innerHTML = '';
    
    if(messages.length === 0) {
        area.innerHTML = `
            <div style="text-align: center; padding: 3rem 2rem; color: var(--text-gray);">
                <div style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.5;">👋</div>
                <p style="font-size: 1.1rem; margin: 0;">No messages yet</p>
                <p style="font-size: 0.9rem; margin: 0.5rem 0 0; opacity: 0.7;">Say hello to start the conversation!</p>
            </div>
        `;
        return;
    }
    
    messages.forEach(msg => {
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${msg.sender_id == currentUserId ? 'message-sent' : 'message-received'}`;
        
        let content = escapeHtml(msg.message);
        
        if(content.includes('[PHONE NUMBER BLOCKED]') || content.includes('[CONTACT INFO BLOCKED]')) {
            content = content.replace(/\[PHONE NUMBER BLOCKED\]/g, '<span style="background: rgba(239, 68, 68, 0.3); padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem;">🚫 Blocked</span>');
            content = content.replace(/\[CONTACT INFO BLOCKED\]/g, '<span style="background: rgba(239, 68, 68, 0.3); padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem;">🚫 Blocked</span>');
        }
        
        if(msg.has_image && msg.image_url) {
            content += `<br><img src="${msg.image_url}" class="message-image" alt="Image">`;
        }
        
        const time = new Date(msg.created_at).toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit' 
        });
        
        bubble.innerHTML = `${content}<span class="message-time">${time}</span>`;
        
        area.appendChild(bubble);
        lastMessageId = Math.max(lastMessageId, msg.id);
    });
    
    if(wasAtBottom || messages.length > 0) {
        setTimeout(() => {
            area.scrollTop = area.scrollHeight;
        }, 50);
    }
}

function sendMessage(event) {
    event.preventDefault();
    
    const input = document.getElementById('messageInput');
    const btn = document.getElementById('sendBtn');
    
    if(!input || !btn) {
        console.error('Input or button not found!');
        return;
    }
    
    const message = input.value.trim();
    
    if(!message || !otherUserId) return;
    
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', otherUserId);
    formData.append('message', message);
    
    fetch('/api/messages.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Clear and reset input
            input.value = '';
            input.style.height = 'auto';
            
            // Load new messages
            loadMessages();
            
            // Update limit info
            if(data.limit_info) {
                updateLimitBanner(data.limit_info);
            }
            
            // Show warning if needed
            if(data.warning) {
                showWarningToast(data.warning);
            }
            
            // Re-focus input after a short delay
            setTimeout(() => {
                if(input) input.focus();
            }, 100);
            
        } else if(data.censored || data.upgrade_required) {
            showPremiumModal(
                data.censored ? '🚫 Phone Numbers Blocked' : '💬 Limit Reached',
                data.error
            );
        } else {
            alert(data.error || 'Failed to send');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send message');
    })
    .finally(() => {
        btn.disabled = false;
    });
    
    return false;
}

function showPremiumModal(title, message) {
    const modal = document.getElementById('premiumModal');
    if(modal) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalMessage').textContent = message;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closePremiumModal() {
    const modal = document.getElementById('premiumModal');
    if(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function showWarningToast(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        z-index: 10001;
        max-width: 300px;
    `;
    toast.innerHTML = `<strong>⚠️ Warning</strong><br>${message}`;
    
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function updateLimitBanner(limitInfo) {
    if(limitInfo.is_premium) return;
    
    const banner = document.querySelector('.message-limit-banner .limit-text');
    if(banner) {
        const remaining = limitInfo.remaining;
        const color = remaining <= 5 ? '#ef4444' : (remaining <= 10 ? '#f59e0b' : 'inherit');
        
        banner.innerHTML = `
            <strong style="color: ${color}">${remaining} / 25</strong> messages left today<br>
            <a href="/membership.php" class="upgrade-link">🔥 Upgrade for unlimited</a>
        `;
    }
    
    // Update input state if limit reached
    const input = document.getElementById('messageInput');
    const btn = document.getElementById('sendBtn');
    
    if(!limitInfo.can_send && input && btn) {
        input.disabled = true;
        btn.disabled = true;
        input.placeholder = 'Daily limit reached';
    }
}

function openConversation(userId) {
    window.location.href = '/messages-chat.php?user=' + userId;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize input handlers
function initializeInput() {
    const messageInput = document.getElementById('messageInput');
    if(!messageInput) {
        console.error('Message input not found!');
        return;
    }
    
    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        const newHeight = Math.min(this.scrollHeight, 120);
        this.style.height = newHeight + 'px';
    });
    
    // Enter to send (Shift+Enter for new line)
    messageInput.addEventListener('keydown', function(e) {
        if(e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const form = document.getElementById('messageForm');
            if(form) {
                sendMessage(e);
            }
        }
    });
    
    console.log('Input handlers initialized');
}

// ESC to close modals
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        closePremiumModal();
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing...');
    
    // Initialize input handlers
    initializeInput();
    
    // Load messages if chat is open
    if(otherUserId) {
        console.log('Loading messages for user:', otherUserId);
        loadMessages();
        
        // Refresh every 3 seconds
        refreshInterval = setInterval(loadMessages, 3000);
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if(refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<?php include 'views/footer.php'; ?>