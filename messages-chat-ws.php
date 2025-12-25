<?php
session_start();
require_once 'config/database.php';
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

// Get user's premium status
$query = "SELECT is_premium FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_data = $stmt->fetch();
$is_premium = $user_data['is_premium'] ?? false;

// Get conversations list
$query = "SELECT DISTINCT
          CASE 
            WHEN m.sender_id = :user_id THEN m.receiver_id 
            ELSE m.sender_id 
          END as contact_id,
          u.username, u.is_online, u.last_seen,
          (SELECT message FROM messages 
           WHERE ((sender_id = contact_id AND receiver_id = :user_id2) 
           OR (sender_id = :user_id3 AND receiver_id = contact_id))
           ORDER BY created_at DESC LIMIT 1) as last_message,
          (SELECT created_at FROM messages 
           WHERE ((sender_id = contact_id AND receiver_id = :user_id4) 
           OR (sender_id = :user_id5 AND receiver_id = contact_id))
           ORDER BY created_at DESC LIMIT 1) as last_message_time,
          (SELECT COUNT(*) FROM messages 
           WHERE sender_id = contact_id 
           AND receiver_id = :user_id6
           AND is_read = FALSE) as unread_count
          FROM messages m
          LEFT JOIN users u ON u.id = CASE 
            WHEN m.sender_id = :user_id7 THEN m.receiver_id 
            ELSE m.sender_id 
          END
          WHERE (m.sender_id = :user_id8 OR m.receiver_id = :user_id9)
          AND u.id IS NOT NULL
          ORDER BY last_message_time DESC
          LIMIT 50";

$stmt = $db->prepare($query);
for($i = 1; $i <= 9; $i++) {
    $param = ':user_id' . ($i > 1 ? $i : '');
    $stmt->bindParam($param, $_SESSION['user_id']);
}
$stmt->execute();
$conversations = $stmt->fetchAll();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
/* Keep all the existing CSS from the previous version */
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
}

.messages-container {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    height: calc(100vh - 60px);
    background: #0a1628;
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

.connection-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    background: rgba(255, 255, 255, 0.15);
    margin-top: 0.5rem;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: pulse 2s infinite;
}

.status-dot.disconnected {
    background: #ef4444;
    animation: none;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
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

.conversations-list {
    flex: 1;
    overflow-y: auto;
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
}

.conversation-item:hover {
    background: rgba(66, 103, 245, 0.1);
}

.conversation-item.active {
    background: rgba(66, 103, 245, 0.2);
    border-left: 3px solid var(--primary-blue);
}

.chat-area {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #0a1628;
}

.chat-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(66, 103, 245, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #0d1b2a;
    flex-shrink: 0;
}

.messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: #0a1628;
}

.message-bubble {
    max-width: 75%;
    padding: 0.75rem 1rem;
    border-radius: 16px;
    word-wrap: break-word;
    animation: messageSlideIn 0.3s ease;
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

.typing-indicator {
    align-self: flex-start;
    background: #1e293b;
    padding: 0.75rem 1rem;
    border-radius: 16px;
    border-bottom-left-radius: 4px;
    display: none;
}

.typing-indicator.show {
    display: block;
}

.typing-dots {
    display: flex;
    gap: 0.25rem;
}

.typing-dot {
    width: 8px;
    height: 8px;
    background: var(--text-gray);
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-10px); }
}

.message-input-area {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(66, 103, 245, 0.2);
    background: #0d1b2a;
    flex-shrink: 0;
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
    font-family: inherit;
    font-size: 0.95rem;
}

.message-input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(66, 103, 245, 0.15);
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
}

.send-btn:hover:not(:disabled) {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(66, 103, 245, 0.5);
}

.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .messages-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 140px);
    }
    
    .conversations-sidebar {
        display: none;
    }
}
</style>

<div class="messages-wrapper">
    <div class="messages-container">
        
        <!-- Conversations Sidebar -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2 style="margin: 0 0 0.5rem">💬 Messages</h2>
                <div class="connection-status">
                    <span class="status-dot" id="statusDot"></span>
                    <span id="connectionStatus">Connecting...</span>
                </div>
            </div>
            
            <div class="message-limit-banner <?php echo $is_premium ? 'premium' : ''; ?>">
                <div class="limit-text" id="limitBanner">
                    <?php if($is_premium): ?>
                    ⭐ <strong>Premium</strong> - Unlimited Messages
                    <?php else: ?>
                    <strong><span id="remainingCount">25</span> / 25</strong> messages left today<br>
                    <a href="/membership.php" style="color: var(--featured-gold); text-decoration: none; font-weight: 600;">🔥 Upgrade for unlimited</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="conversations-list">
                <?php foreach($conversations as $conv): ?>
                <div class="conversation-item <?php echo $other_user_id == $conv['contact_id'] ? 'active' : ''; ?>" 
                     onclick="openConversation(<?php echo $conv['contact_id']; ?>)">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; position: relative;">
                        👤
                        <?php if($conv['is_online']): ?>
                        <span style="position: absolute; bottom: 0; right: 0; width: 14px; height: 14px; background: var(--success-green); border: 3px solid #0d1b2a; border-radius: 50%;"></span>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: var(--text-white); margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($conv['username']); ?>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages', 0, 35)); ?>
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
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if($other_user): ?>
            <div class="chat-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        👤
                    </div>
                    <div>
                        <div style="font-size: 1.15rem; font-weight: 600; color: var(--text-white);">
                            <?php echo htmlspecialchars($other_user['username']); ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-gray);" id="userStatus">
                            <?php echo $other_user['is_online'] ? '🟢 Online' : '⚪ Offline'; ?>
                        </div>
                    </div>
                </div>
                <a href="/profile.php?id=<?php echo $other_user['id']; ?>" class="btn-secondary btn-small">
                    View Profile
                </a>
            </div>
            
            <div class="messages-area" id="messagesArea">
                <div style="text-align: center; padding: 3rem 2rem; color: var(--text-gray);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.5;">⏳</div>
                    <p>Connecting to chat...</p>
                </div>
            </div>
            
            <div class="typing-indicator" id="typingIndicator">
                <div class="typing-dots">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
            </div>
            
            <div class="message-input-area">
                <?php if(!$is_premium): ?>
                <div style="background: rgba(245, 158, 11, 0.15); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.75rem; font-size: 0.85rem; color: var(--text-white); border: 1px solid rgba(245, 158, 11, 0.3);">
                    ⚠️ <strong>Free users:</strong> Phone numbers blocked. 
                    <a href="/membership.php" style="color: var(--featured-gold); text-decoration: underline;">Upgrade</a> to share contact info.
                </div>
                <?php endif; ?>
                
                <form style="display: flex; gap: 0.75rem; align-items: flex-end;" onsubmit="sendMessage(event)">
                    <textarea 
                        id="messageInput" 
                        class="message-input" 
                        placeholder="Type a message..."
                        rows="1"></textarea>
                    <button type="submit" class="send-btn" id="sendBtn">➤</button>
                </form>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-gray); padding: 2rem;">
                <div style="font-size: 5rem; margin-bottom: 1rem; opacity: 0.5;">💬</div>
                <h3 style="margin: 0 0 0.5rem; color: var(--text-white);">Select a conversation</h3>
                <p style="margin: 0; opacity: 0.8;">Choose someone to start messaging</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const userId = <?php echo $_SESSION['user_id']; ?>;
const otherUserId = <?php echo $other_user_id ?: 'null'; ?>;
const isPremium = <?php echo $is_premium ? 'true' : 'false'; ?>;

let ws = null;
let typingTimeout = null;
let reconnectAttempts = 0;
const maxReconnectAttempts = 5;

// Connect to WebSocket
function connectWebSocket() {
    ws = new WebSocket('ws://localhost:8080');
    
    ws.onopen = function() {
        console.log('Connected to WebSocket');
        updateConnectionStatus(true);
        reconnectAttempts = 0;
        
        // Authenticate
        ws.send(JSON.stringify({
            type: 'auth',
            user_id: userId,
            token: 'session_token'
        }));
        
        // Load conversation if open
        if(otherUserId) {
            ws.send(JSON.stringify({
                type: 'load_conversation',
                other_user_id: otherUserId,
                limit: 50
            }));
        }
    };
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        console.log('Received:', data);
        
        switch(data.type) {
            case 'auth_success':
                console.log('Authenticated successfully');
                break;
                
            case 'conversation_loaded':
                displayMessages(data.messages);
                break;
                
            case 'new_message':
                handleNewMessage(data);
                break;
                
            case 'typing':
                handleTyping(data);
                break;
                
            case 'error':
                handleError(data);
                break;
                
            case 'messages_read':
                // Update read receipts if needed
                break;
        }
    };
    
    ws.onerror = function(error) {
        console.error('WebSocket error:', error);
        updateConnectionStatus(false);
    };
    
    ws.onclose = function() {
        console.log('WebSocket disconnected');
        updateConnectionStatus(false);
        
        // Try to reconnect
        if(reconnectAttempts < maxReconnectAttempts) {
            reconnectAttempts++;
            setTimeout(() => {
                console.log(`Reconnection attempt ${reconnectAttempts}/${maxReconnectAttempts}`);
                connectWebSocket();
            }, 3000 * reconnectAttempts);
        }
    };
}

function updateConnectionStatus(connected) {
    const statusDot = document.getElementById('statusDot');
    const statusText = document.getElementById('connectionStatus');
    
    if(connected) {
        statusDot.classList.remove('disconnected');
        statusText.textContent = 'Connected';
    } else {
        statusDot.classList.add('disconnected');
        statusText.textContent = 'Disconnected';
    }
}

function displayMessages(messages) {
    const area = document.getElementById('messagesArea');
    area.innerHTML = '';
    
    if(messages.length === 0) {
        area.innerHTML = `
            <div style="text-align: center; padding: 3rem 2rem; color: var(--text-gray);">
                <div style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.5;">👋</div>
                <p style="font-size: 1.1rem; margin: 0;">No messages yet</p>
                <p style="font-size: 0.9rem; margin: 0.5rem 0 0; opacity: 0.7;">Say hello!</p>
            </div>
        `;
        return;
    }
    
    messages.forEach(msg => {
        addMessageToUI(msg);
    });
    
    scrollToBottom();
    
    // Mark as read
    if(otherUserId) {
        ws.send(JSON.stringify({
            type: 'mark_read',
            sender_id: otherUserId
        }));
    }
}

function addMessageToUI(msg) {
    const area = document.getElementById('messagesArea');
    const bubble = document.createElement('div');
    bubble.className = `message-bubble ${msg.sender_id == userId ? 'message-sent' : 'message-received'}`;
    
    const time = new Date(msg.created_at).toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit' 
    });
    
    bubble.innerHTML = `
        ${escapeHtml(msg.message)}
        <span class="message-time">${time}</span>
    `;
    
    area.appendChild(bubble);
}

function handleNewMessage(data) {
    if(data.sent) {
        // Message I sent
        addMessageToUI(data);
        scrollToBottom();
        
        // Update limit
        if(data.limit_info && !isPremium) {
            updateLimitDisplay(data.limit_info);
        }
    } else if(data.sender_id == otherUserId) {
        // Message received
        addMessageToUI(data);
        scrollToBottom();
        
        // Mark as read
        ws.send(JSON.stringify({
            type: 'mark_read',
            sender_id: otherUserId
        }));
    }
}

function handleTyping(data) {
    if(data.user_id == otherUserId) {
        const indicator = document.getElementById('typingIndicator');
        if(data.is_typing) {
            indicator.classList.add('show');
            scrollToBottom();
        } else {
            indicator.classList.remove('show');
        }
    }
}

function handleError(data) {
    if(data.upgrade_required) {
        alert(data.error);
        if(data.censored) {
            // Show premium modal
        }
    } else {
        alert(data.error);
    }
}

function sendMessage(event) {
    event.preventDefault();
    
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if(!message || !otherUserId || !ws || ws.readyState !== WebSocket.OPEN) {
        return;
    }
    
    ws.send(JSON.stringify({
        type: 'message',
        receiver_id: otherUserId,
        message: message
    }));
    
    input.value = '';
    input.style.height = 'auto';
    input.focus();
}

function updateLimitDisplay(limitInfo) {
    const countEl = document.getElementById('remainingCount');
    if(countEl) {
        countEl.textContent = limitInfo.remaining;
        countEl.style.color = limitInfo.remaining <= 5 ? '#ef4444' : 'inherit';
    }
    
    if(!limitInfo.can_send) {
        const input = document.getElementById('messageInput');
        const btn = document.getElementById('sendBtn');
        input.disabled = true;
        btn.disabled = true;
        input.placeholder = 'Daily limit reached';
    }
}

function scrollToBottom() {
    const area = document.getElementById('messagesArea');
    setTimeout(() => {
        area.scrollTop = area.scrollHeight;
    }, 50);
}

function openConversation(userId) {
    window.location.href = '/messages-chat-ws.php?user=' + userId;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Input handlers
const input = document.getElementById('messageInput');
if(input) {
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        
        // Send typing indicator
        if(otherUserId && ws && ws.readyState === WebSocket.OPEN) {
            clearTimeout(typingTimeout);
            
            ws.send(JSON.stringify({
                type: 'typing',
                receiver_id: otherUserId,
                is_typing: true
            }));
            
            typingTimeout = setTimeout(() => {
                ws.send(JSON.stringify({
                    type: 'typing',
                    receiver_id: otherUserId,
                    is_typing: false
                }));
            }, 1000);
        }
    });
    
    input.addEventListener('keydown', function(e) {
        if(e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(e);
        }
    });
}

// Initialize
connectWebSocket();

// Cleanup
window.addEventListener('beforeunload', function() {
    if(ws) {
        ws.close();
    }
});
</script>

<?php include 'views/footer.php'; ?>