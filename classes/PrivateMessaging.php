<?php

class PrivateMessaging {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create a new PM thread
     */
    public function createThread($starter_id, $recipient_id, $subject, $message_text, $attachment = null) {
        try {
            $this->db->beginTransaction();
            
            // Check if user can send messages
            $limitCheck = $this->checkMessageLimits($starter_id);
            if (!$limitCheck['can_send']) {
                return [
                    'success' => false,
                    'error' => 'Daily message limit reached',
                    'upgrade_required' => true
                ];
            }
            
            // Create thread
            $query = "INSERT INTO pm_threads (starter_id, recipient_id, subject) 
                      VALUES (:starter_id, :recipient_id, :subject)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':starter_id', $starter_id);
            $stmt->bindParam(':recipient_id', $recipient_id);
            $stmt->bindParam(':subject', $subject);
            $stmt->execute();
            
            $thread_id = $this->db->lastInsertId();
            
            // Add first message
            $query = "INSERT INTO pm_messages (thread_id, sender_id, message_text) 
                      VALUES (:thread_id, :sender_id, :message_text)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':thread_id', $thread_id);
            $stmt->bindParam(':sender_id', $starter_id);
            $stmt->bindParam(':message_text', $message_text);
            $stmt->execute();
            
            $message_id = $this->db->lastInsertId();
            
            // Handle attachment if provided
            if ($attachment && !empty($attachment['tmp_name'])) {
                $this->addAttachment($message_id, $attachment);
            }
            
            // Update message count
            $this->incrementMessageCount($starter_id);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'thread_id' => $thread_id,
                'message_id' => $message_id,
                'limit_info' => $this->checkMessageLimits($starter_id)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Reply to an existing thread
     */
    public function replyToThread($thread_id, $sender_id, $message_text, $quoted_message_id = null, $attachment = null) {
        try {
            $this->db->beginTransaction();
            
            // Verify user is part of this thread
            if (!$this->isUserInThread($thread_id, $sender_id)) {
                return ['success' => false, 'error' => 'Access denied'];
            }
            
            // Check message limits
            $limitCheck = $this->checkMessageLimits($sender_id);
            if (!$limitCheck['can_send']) {
                return [
                    'success' => false,
                    'error' => 'Daily message limit reached',
                    'upgrade_required' => true
                ];
            }
            
            // Add reply
            $query = "INSERT INTO pm_messages (thread_id, sender_id, message_text, quoted_message_id) 
                      VALUES (:thread_id, :sender_id, :message_text, :quoted_message_id)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':thread_id', $thread_id);
            $stmt->bindParam(':sender_id', $sender_id);
            $stmt->bindParam(':message_text', $message_text);
            $stmt->bindParam(':quoted_message_id', $quoted_message_id);
            $stmt->execute();
            
            $message_id = $this->db->lastInsertId();
            
            // Handle attachment
            if ($attachment && !empty($attachment['tmp_name'])) {
                $this->addAttachment($message_id, $attachment);
            }
            
            // Update thread timestamp
            $query = "UPDATE pm_threads SET updated_at = NOW() WHERE id = :thread_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':thread_id', $thread_id);
            $stmt->execute();
            
            // Increment message count
            $this->incrementMessageCount($sender_id);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message_id' => $message_id,
                'limit_info' => $this->checkMessageLimits($sender_id)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get inbox threads for a user
     */
    public function getInbox($user_id, $page = 1, $per_page = 20, $folder_id = null) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT t.*, 
                         u.username as other_user,
                         u.is_online,
                         (SELECT COUNT(*) FROM pm_messages 
                          WHERE thread_id = t.id 
                          AND sender_id != :user_id 
                          AND is_read = FALSE 
                          AND is_deleted_by_recipient = FALSE) as unread_count,
                         (SELECT message_text FROM pm_messages 
                          WHERE thread_id = t.id 
                          ORDER BY created_at DESC LIMIT 1) as last_message,
                         (SELECT created_at FROM pm_messages 
                          WHERE thread_id = t.id 
                          ORDER BY created_at DESC LIMIT 1) as last_message_time
                  FROM pm_threads t
                  JOIN users u ON (
                      CASE 
                          WHEN t.starter_id = :user_id THEN u.id = t.recipient_id
                          ELSE u.id = t.starter_id
                      END
                  )
                  WHERE (t.starter_id = :user_id OR t.recipient_id = :user_id)
                  AND (
                      CASE 
                          WHEN t.starter_id = :user_id THEN t.is_deleted_by_starter = FALSE
                          ELSE t.is_deleted_by_recipient = FALSE
                      END
                  )";
        
        if ($folder_id) {
            $query .= " AND EXISTS (
                SELECT 1 FROM pm_thread_folders 
                WHERE thread_id = t.id 
                AND user_id = :user_id 
                AND folder_id = :folder_id
            )";
        }
        
        $query .= " ORDER BY t.updated_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        if ($folder_id) {
            $stmt->bindParam(':folder_id', $folder_id);
        }
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get sent threads
     */
    public function getSentMessages($user_id, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT t.*, 
                         u.username as recipient_name,
                         (SELECT message_text FROM pm_messages 
                          WHERE thread_id = t.id 
                          ORDER BY created_at DESC LIMIT 1) as last_message
                  FROM pm_threads t
                  JOIN users u ON u.id = t.recipient_id
                  WHERE t.starter_id = :user_id
                  AND t.is_deleted_by_starter = FALSE
                  ORDER BY t.updated_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get thread with all messages
     */
    public function getThread($thread_id, $user_id) {
        // Verify access
        if (!$this->isUserInThread($thread_id, $user_id)) {
            return null;
        }
        
        // Get thread info
        $query = "SELECT t.*, 
                         s.username as starter_name,
                         r.username as recipient_name
                  FROM pm_threads t
                  JOIN users s ON s.id = t.starter_id
                  JOIN users r ON r.id = t.recipient_id
                  WHERE t.id = :thread_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id);
        $stmt->execute();
        $thread = $stmt->fetch();
        
        if (!$thread) {
            return null;
        }
        
        // Get messages
        $query = "SELECT m.*, 
                         u.username as sender_name,
                         u.is_online,
                         qm.message_text as quoted_text,
                         qu.username as quoted_sender
                  FROM pm_messages m
                  JOIN users u ON u.id = m.sender_id
                  LEFT JOIN pm_messages qm ON qm.id = m.quoted_message_id
                  LEFT JOIN users qu ON qu.id = qm.sender_id
                  WHERE m.thread_id = :thread_id
                  AND (
                      CASE 
                          WHEN m.sender_id = :user_id THEN m.is_deleted_by_sender = FALSE
                          ELSE m.is_deleted_by_recipient = FALSE
                      END
                  )
                  ORDER BY m.created_at ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $messages = $stmt->fetchAll();
        
        // Get attachments for each message
        foreach ($messages as &$message) {
            $message['attachments'] = $this->getMessageAttachments($message['id']);
        }
        
        $thread['messages'] = $messages;
        
        // Mark as read
        $this->markThreadAsRead($thread_id, $user_id);
        
        return $thread;
    }
    
    /**
     * Mark thread as read
     */
    public function markThreadAsRead($thread_id, $user_id) {
        // Get last message ID
        $query = "SELECT id FROM pm_messages 
                  WHERE thread_id = :thread_id 
                  ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id);
        $stmt->execute();
        $lastMessage = $stmt->fetch();
        
        if ($lastMessage) {
            // Update read status
            $query = "INSERT INTO pm_read_status (user_id, thread_id, last_read_message_id, last_read_at)
                      VALUES (:user_id, :thread_id, :message_id, NOW())
                      ON DUPLICATE KEY UPDATE 
                      last_read_message_id = :message_id,
                      last_read_at = NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':thread_id', $thread_id);
            $stmt->bindParam(':message_id', $lastMessage['id']);
            $stmt->execute();
            
            // Mark individual messages as read
            $query = "UPDATE pm_messages 
                      SET is_read = TRUE, read_at = NOW()
                      WHERE thread_id = :thread_id 
                      AND sender_id != :user_id 
                      AND is_read = FALSE";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':thread_id', $thread_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        }
    }
    
    /**
     * Delete thread (soft delete)
     */
    public function deleteThread($thread_id, $user_id) {
        $query = "UPDATE pm_threads SET 
                  is_deleted_by_starter = CASE WHEN starter_id = :user_id THEN TRUE ELSE is_deleted_by_starter END,
                  is_deleted_by_recipient = CASE WHEN recipient_id = :user_id THEN TRUE ELSE is_deleted_by_recipient END
                  WHERE id = :thread_id 
                  AND (starter_id = :user_id OR recipient_id = :user_id)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(DISTINCT m.thread_id) as unread_count
                  FROM pm_messages m
                  JOIN pm_threads t ON t.id = m.thread_id
                  WHERE m.sender_id != :user_id
                  AND m.is_read = FALSE
                  AND m.is_deleted_by_recipient = FALSE
                  AND (t.starter_id = :user_id OR t.recipient_id = :user_id)
                  AND (
                      CASE 
                          WHEN t.starter_id = :user_id THEN t.is_deleted_by_starter = FALSE
                          ELSE t.is_deleted_by_recipient = FALSE
                      END
                  )";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['unread_count'] ?? 0;
    }
    
    /**
     * Search messages
     */
    public function searchMessages($user_id, $search_term, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        $search = "%{$search_term}%";
        
        $query = "SELECT DISTINCT t.*, 
                         u.username as other_user,
                         m.message_text as matching_message
                  FROM pm_threads t
                  JOIN pm_messages m ON m.thread_id = t.id
                  JOIN users u ON (
                      CASE 
                          WHEN t.starter_id = :user_id THEN u.id = t.recipient_id
                          ELSE u.id = t.starter_id
                      END
                  )
                  WHERE (t.starter_id = :user_id OR t.recipient_id = :user_id)
                  AND (t.subject LIKE :search OR m.message_text LIKE :search)
                  ORDER BY t.updated_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':search', $search);
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    // Helper methods
    
    private function isUserInThread($thread_id, $user_id) {
        $query = "SELECT 1 FROM pm_threads 
                  WHERE id = :thread_id 
                  AND (starter_id = :user_id OR recipient_id = :user_id)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch() !== false;
    }
    
    private function addAttachment($message_id, $file) {
        // File upload logic here
        $upload_dir = __DIR__ . '/../uploads/pm_attachments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = basename($file['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $query = "INSERT INTO pm_attachments (message_id, file_name, file_path, file_size, file_type)
                      VALUES (:message_id, :file_name, :file_path, :file_size, :file_type)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':message_id', $message_id);
            $stmt->bindParam(':file_name', $file_name);
            $stmt->bindParam(':file_path', '/uploads/pm_attachments/' . $new_filename);
            $stmt->bindParam(':file_size', $file['size']);
            $stmt->bindParam(':file_type', $file['type']);
            $stmt->execute();
        }
    }
    
    private function getMessageAttachments($message_id) {
        $query = "SELECT * FROM pm_attachments WHERE message_id = :message_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':message_id', $message_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function checkMessageLimits($user_id) {
        // Check if user is premium
        $query = "SELECT is_premium FROM users WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user['is_premium']) {
            return ['can_send' => true, 'is_premium' => true];
        }
        
        // Check daily limit
        $query = "SELECT COUNT(*) as count FROM pm_messages 
                  WHERE sender_id = :user_id 
                  AND DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $daily_limit = 10; // Free users: 10 messages per day
        $remaining = $daily_limit - $result['count'];
        
        return [
            'can_send' => $remaining > 0,
            'is_premium' => false,
            'remaining' => max(0, $remaining),
            'limit' => $daily_limit
        ];
    }
    
    private function incrementMessageCount($user_id) {
        // Track message count for limits
        return true;
    }
}