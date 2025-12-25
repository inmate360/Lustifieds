<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $db;
    protected $users;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
        
        // Database connection
        $host = 'localhost';
        $dbname = 'doublelist_clone';
        $username = 'doublelist_clone';
        $password = 'g4iY?vMI&9on5jyy';
        
        try {
            $this->db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if(!$data) {
            return;
        }

        switch($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
                
            case 'message':
                $this->handleMessage($from, $data);
                break;
                
            case 'typing':
                $this->handleTyping($from, $data);
                break;
                
            case 'mark_read':
                $this->handleMarkRead($from, $data);
                break;
                
            case 'load_conversation':
                $this->handleLoadConversation($from, $data);
                break;
        }
    }

    private function handleAuth($conn, $data) {
        $user_id = $data['user_id'] ?? null;
        $token = $data['token'] ?? null;
        
        if(!$user_id || !$token) {
            return;
        }
        
        // Verify token (you should implement proper token verification)
        $this->users[$conn->resourceId] = [
            'user_id' => $user_id,
            'conn' => $conn
        ];
        
        // Update user online status
        try {
            $stmt = $this->db->prepare("UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch(PDOException $e) {
            error_log("Error updating online status: " . $e->getMessage());
        }
        
        $conn->send(json_encode([
            'type' => 'auth_success',
            'user_id' => $user_id
        ]));
        
        echo "User {$user_id} authenticated\n";
    }

    private function handleMessage($from, $data) {
        $sender_id = $this->getUserId($from);
        $receiver_id = $data['receiver_id'] ?? null;
        $message = $data['message'] ?? '';
        
        if(!$sender_id || !$receiver_id || empty($message)) {
            return;
        }
        
        // Check message limits
        $limitCheck = $this->checkMessageLimit($sender_id);
        if(!$limitCheck['can_send']) {
            $from->send(json_encode([
                'type' => 'error',
                'error' => $limitCheck['error'],
                'upgrade_required' => true
            ]));
            return;
        }
        
        // Censor message for free users
        $censorResult = $this->censorMessage($message, $limitCheck['is_premium']);
        if($censorResult['censored']) {
            $from->send(json_encode([
                'type' => 'error',
                'error' => 'Your message contains phone numbers. Upgrade to Premium to share contact details!',
                'censored' => true,
                'upgrade_required' => true
            ]));
            return;
        }
        
        // Save message to database
        try {
            $stmt = $this->db->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$sender_id, $receiver_id, $censorResult['message']]);
            $message_id = $this->db->lastInsertId();
            
            // Update message count for free users
            if(!$limitCheck['is_premium']) {
                $today = date('Y-m-d');
                $stmt = $this->db->prepare("INSERT INTO message_limits (user_id, messages_sent_today, last_reset_date) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE messages_sent_today = messages_sent_today + 1, last_reset_date = IF(last_reset_date < ?, ?, last_reset_date)");
                $stmt->execute([$sender_id, $today, $today, $today]);
            }
            
            // Get updated limit info
            $newLimit = $this->checkMessageLimit($sender_id);
            
            // Prepare message data
            $messageData = [
                'type' => 'new_message',
                'id' => $message_id,
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message' => $censorResult['message'],
                'created_at' => date('Y-m-d H:i:s'),
                'limit_info' => $newLimit
            ];
            
            // Send to sender
            $from->send(json_encode(array_merge($messageData, ['sent' => true])));
            
            // Send to receiver if online
            foreach($this->users as $client) {
                if($client['user_id'] == $receiver_id) {
                    $client['conn']->send(json_encode($messageData));
                    break;
                }
            }
            
            echo "Message sent from {$sender_id} to {$receiver_id}\n";
            
        } catch(PDOException $e) {
            error_log("Error saving message: " . $e->getMessage());
            $from->send(json_encode([
                'type' => 'error',
                'error' => 'Failed to send message'
            ]));
        }
    }

    private function handleTyping($from, $data) {
        $sender_id = $this->getUserId($from);
        $receiver_id = $data['receiver_id'] ?? null;
        $is_typing = $data['is_typing'] ?? false;
        
        if(!$sender_id || !$receiver_id) {
            return;
        }
        
        // Send typing indicator to receiver
        foreach($this->users as $client) {
            if($client['user_id'] == $receiver_id) {
                $client['conn']->send(json_encode([
                    'type' => 'typing',
                    'user_id' => $sender_id,
                    'is_typing' => $is_typing
                ]));
                break;
            }
        }
    }

    private function handleMarkRead($from, $data) {
        $user_id = $this->getUserId($from);
        $sender_id = $data['sender_id'] ?? null;
        
        if(!$user_id || !$sender_id) {
            return;
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
            $stmt->execute([$sender_id, $user_id]);
            
            // Notify sender that messages were read
            foreach($this->users as $client) {
                if($client['user_id'] == $sender_id) {
                    $client['conn']->send(json_encode([
                        'type' => 'messages_read',
                        'reader_id' => $user_id
                    ]));
                    break;
                }
            }
        } catch(PDOException $e) {
            error_log("Error marking read: " . $e->getMessage());
        }
    }

    private function handleLoadConversation($from, $data) {
        $user_id = $this->getUserId($from);
        $other_user_id = $data['other_user_id'] ?? null;
        $limit = $data['limit'] ?? 50;
        
        if(!$user_id || !$other_user_id) {
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                sender.username as sender_username,
                receiver.username as receiver_username
                FROM messages m
                LEFT JOIN users sender ON m.sender_id = sender.id
                LEFT JOIN users receiver ON m.receiver_id = receiver.id
                WHERE ((m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?))
                AND ((m.sender_id = ? AND m.deleted_by_sender = 0)
                OR (m.receiver_id = ? AND m.deleted_by_receiver = 0))
                ORDER BY m.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id, $user_id, $user_id, $limit]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $from->send(json_encode([
                'type' => 'conversation_loaded',
                'messages' => array_reverse($messages)
            ]));
            
        } catch(PDOException $e) {
            error_log("Error loading conversation: " . $e->getMessage());
        }
    }

    private function checkMessageLimit($user_id) {
        try {
            // Check if premium
            $stmt = $this->db->prepare("SELECT is_premium FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user['is_premium']) {
                return [
                    'can_send' => true,
                    'is_premium' => true,
                    'remaining' => 'Unlimited'
                ];
            }
            
            // Check free user limit
            $today = date('Y-m-d');
            $stmt = $this->db->prepare("SELECT messages_sent_today FROM message_limits WHERE user_id = ? AND last_reset_date = ?");
            $stmt->execute([$user_id, $today]);
            $limit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $sent = $limit['messages_sent_today'] ?? 0;
            $remaining = 25 - $sent;
            
            if($sent >= 25) {
                return [
                    'can_send' => false,
                    'is_premium' => false,
                    'error' => 'Daily message limit reached (25 messages)',
                    'remaining' => 0
                ];
            }
            
            return [
                'can_send' => true,
                'is_premium' => false,
                'remaining' => $remaining,
                'sent_today' => $sent
            ];
            
        } catch(PDOException $e) {
            return ['can_send' => true, 'is_premium' => false, 'remaining' => 25];
        }
    }

    private function censorMessage($message, $is_premium) {
        if($is_premium) {
            return ['censored' => false, 'message' => $message];
        }
        
        $censored = false;
        
        // Phone number patterns
        $phonePatterns = [
            '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',
            '/\b\(\d{3}\)\s?\d{3}[-.\s]?\d{4}\b/',
            '/\b\d{10}\b/',
            '/\b1[-.\s]?\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',
            '/\b\+\d{1,3}[-.\s]?\d{1,14}\b/',
        ];
        
        foreach($phonePatterns as $pattern) {
            if(preg_match($pattern, $message)) {
                $censored = true;
                break;
            }
        }
        
        return ['censored' => $censored, 'message' => $message];
    }

    private function getUserId($conn) {
        foreach($this->users as $id => $user) {
            if($user['conn'] === $conn) {
                return $user['user_id'];
            }
        }
        return null;
    }

    public function onClose(ConnectionInterface $conn) {
        $user_id = $this->getUserId($conn);
        
        if($user_id) {
            // Update offline status
            try {
                $stmt = $this->db->prepare("UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
            } catch(PDOException $e) {
                error_log("Error updating offline status: " . $e->getMessage());
            }
            
            unset($this->users[$conn->resourceId]);
            echo "User {$user_id} disconnected\n";
        }
        
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080
);

echo "WebSocket server running on port 8080\n";
$server->run();