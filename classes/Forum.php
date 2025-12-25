<?php
class Forum {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get all categories with stats
     */
    public function getCategories($include_hidden = false) {
        $where = $include_hidden ? '' : 'WHERE is_hidden = FALSE';
        
        $query = "SELECT c.*,
                  u.username as last_post_username,
                  t.title as last_thread_title,
                  t.slug as last_thread_slug
                  FROM forum_categories c
                  LEFT JOIN forum_posts p ON c.last_post_id = p.id
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN forum_threads t ON p.thread_id = t.id
                  $where
                  ORDER BY display_order ASC, name ASC";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }
    
    /**
     * Get category by slug
     */
    public function getCategoryBySlug($slug) {
        $query = "SELECT * FROM forum_categories WHERE slug = :slug LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch();
    }
    
    /**
     * Get threads in category
     */
    public function getThreads($category_id, $page = 1, $per_page = 20, $sort = 'latest') {
        $offset = ($page - 1) * $per_page;
        
        $order = match($sort) {
            'popular' => 't.views DESC',
            'replies' => 't.replies_count DESC',
            'oldest' => 't.created_at ASC',
            default => 't.is_pinned DESC, t.last_reply_at DESC, t.created_at DESC'
        };
        
        $query = "SELECT t.*,
                  u.username,
                  u.is_premium,
                  last_user.username as last_reply_username,
                  COUNT(DISTINCT fr.id) as reactions_count
                  FROM forum_threads t
                  LEFT JOIN users u ON t.user_id = u.id
                  LEFT JOIN forum_posts lp ON t.last_reply_id = lp.id
                  LEFT JOIN users last_user ON lp.user_id = last_user.id
                  LEFT JOIN forum_reactions fr ON t.id = fr.thread_id
                  WHERE t.category_id = :category_id 
                  AND t.is_deleted = FALSE
                  GROUP BY t.id
                  ORDER BY $order
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get thread count in category
     */
    public function getThreadCount($category_id) {
        $query = "SELECT COUNT(*) FROM forum_threads 
                  WHERE category_id = :category_id AND is_deleted = FALSE";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['category_id' => $category_id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get thread by slug
     */
    public function getThreadBySlug($slug) {
        $query = "SELECT t.*,
                  u.username,
                  u.is_premium,
                  u.created_at as user_joined,
                  fs.posts_count as user_posts,
                  fs.reputation_score as user_reputation,
                  COUNT(DISTINCT fr.id) as reactions_count
                  FROM forum_threads t
                  LEFT JOIN users u ON t.user_id = u.id
                  LEFT JOIN forum_user_stats fs ON u.id = fs.user_id
                  LEFT JOIN forum_reactions fr ON t.id = fr.thread_id
                  WHERE t.slug = :slug AND t.is_deleted = FALSE
                  GROUP BY t.id
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch();
    }
    
    /**
     * Get posts in thread
     */
    public function getPosts($thread_id, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT p.*,
                  u.username,
                  u.is_premium,
                  u.created_at as user_joined,
                  fs.posts_count as user_posts,
                  fs.reputation_score as user_reputation,
                  COUNT(DISTINCT fr.id) as reactions_count
                  FROM forum_posts p
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN forum_user_stats fs ON u.id = fs.user_id
                  LEFT JOIN forum_reactions fr ON p.id = fr.post_id
                  WHERE p.thread_id = :thread_id AND p.is_deleted = FALSE
                  GROUP BY p.id
                  ORDER BY p.created_at ASC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create thread
     */
    public function createThread($user_id, $category_id, $title, $content, $tags = []) {
        try {
            $this->db->beginTransaction();
            
            // Generate slug
            $slug = $this->generateSlug($title);
            
            // Insert thread
            $query = "INSERT INTO forum_threads (category_id, user_id, title, slug, content, last_reply_at) 
                      VALUES (:category_id, :user_id, :title, :slug, :content, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'category_id' => $category_id,
                'user_id' => $user_id,
                'title' => $title,
                'slug' => $slug,
                'content' => $content
            ]);
            
            $thread_id = $this->db->lastInsertId();
            
            // Update category stats
            $this->updateCategoryStats($category_id);
            
            // Update user stats
            $this->updateUserStats($user_id);
            
            // Add tags
            if(!empty($tags)) {
                $this->addThreadTags($thread_id, $tags);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'thread_id' => $thread_id, 'slug' => $slug];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("Forum create thread error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create thread'];
        }
    }
    
    /**
     * Create post (reply)
     */
    public function createPost($user_id, $thread_id, $content) {
        try {
            $this->db->beginTransaction();
            
            // Insert post
            $query = "INSERT INTO forum_posts (thread_id, user_id, content) 
                      VALUES (:thread_id, :user_id, :content)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'thread_id' => $thread_id,
                'user_id' => $user_id,
                'content' => $content
            ]);
            
            $post_id = $this->db->lastInsertId();
            
            // Update thread stats
            $query = "UPDATE forum_threads 
                      SET replies_count = replies_count + 1,
                          last_reply_id = :post_id,
                          last_reply_at = NOW()
                      WHERE id = :thread_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'post_id' => $post_id,
                'thread_id' => $thread_id
            ]);
            
            // Get thread category
            $query = "SELECT category_id FROM forum_threads WHERE id = :thread_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['thread_id' => $thread_id]);
            $category_id = $stmt->fetchColumn();
            
            // Update category stats
            $this->updateCategoryStats($category_id);
            
            // Update user stats
            $this->updateUserStats($user_id);
            
            $this->db->commit();
            
            return ['success' => true, 'post_id' => $post_id];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("Forum create post error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create post'];
        }
    }
    
    /**
     * Update thread
     */
    public function updateThread($thread_id, $user_id, $title, $content) {
        $query = "UPDATE forum_threads 
                  SET title = :title, content = :content, updated_at = NOW()
                  WHERE id = :thread_id AND user_id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            'title' => $title,
            'content' => $content,
            'thread_id' => $thread_id,
            'user_id' => $user_id
        ]);
        
        return $result;
    }
    
    /**
     * Delete thread
     */
    public function deleteThread($thread_id, $user_id) {
        $query = "UPDATE forum_threads 
                  SET is_deleted = TRUE, deleted_at = NOW()
                  WHERE id = :thread_id AND user_id = :user_id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'thread_id' => $thread_id,
            'user_id' => $user_id
        ]);
    }
    
    /**
     * Increment thread views
     */
    public function incrementViews($thread_id) {
        $query = "UPDATE forum_threads SET views = views + 1 WHERE id = :thread_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['thread_id' => $thread_id]);
    }
    
    /**
     * Toggle reaction
     */
    public function toggleReaction($user_id, $thread_id = null, $post_id = null, $type = 'like') {
        try {
            if($thread_id) {
                $query = "SELECT id FROM forum_reactions 
                          WHERE user_id = :user_id AND thread_id = :thread_id AND reaction_type = :type";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['user_id' => $user_id, 'thread_id' => $thread_id, 'type' => $type]);
                
                if($stmt->fetch()) {
                    // Remove reaction
                    $query = "DELETE FROM forum_reactions 
                              WHERE user_id = :user_id AND thread_id = :thread_id AND reaction_type = :type";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute(['user_id' => $user_id, 'thread_id' => $thread_id, 'type' => $type]);
                    return ['success' => true, 'action' => 'removed'];
                } else {
                    // Add reaction
                    $query = "INSERT INTO forum_reactions (user_id, thread_id, reaction_type) 
                              VALUES (:user_id, :thread_id, :type)";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute(['user_id' => $user_id, 'thread_id' => $thread_id, 'type' => $type]);
                    return ['success' => true, 'action' => 'added'];
                }
            }
            
            return ['success' => false, 'error' => 'Invalid parameters'];
            
        } catch(Exception $e) {
            return ['success' => false, 'error' => 'Failed to toggle reaction'];
        }
    }
    
    /**
     * Generate unique slug
     */
    private function generateSlug($title) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        $slug = substr($slug, 0, 200);
        
        // Check if slug exists
        $query = "SELECT id FROM forum_threads WHERE slug = :slug";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['slug' => $slug]);
        
        if($stmt->fetch()) {
            $slug .= '-' . time();
        }
        
        return $slug;
    }
    
    /**
     * Update category stats
     */
    private function updateCategoryStats($category_id) {
        $query = "UPDATE forum_categories c
                  SET posts_count = (
                      SELECT COUNT(*) FROM forum_posts p
                      JOIN forum_threads t ON p.thread_id = t.id
                      WHERE t.category_id = c.id AND p.is_deleted = FALSE
                  ),
                  threads_count = (
                      SELECT COUNT(*) FROM forum_threads t
                      WHERE t.category_id = c.id AND t.is_deleted = FALSE
                  )
                  WHERE c.id = :category_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['category_id' => $category_id]);
    }
    
    /**
     * Update user stats
     */
    private function updateUserStats($user_id) {
        $query = "INSERT INTO forum_user_stats (user_id, threads_count, posts_count, last_post_at)
                  SELECT :user_id,
                      (SELECT COUNT(*) FROM forum_threads WHERE user_id = :user_id2 AND is_deleted = FALSE),
                      (SELECT COUNT(*) FROM forum_posts WHERE user_id = :user_id3 AND is_deleted = FALSE),
                      NOW()
                  ON DUPLICATE KEY UPDATE
                      threads_count = VALUES(threads_count),
                      posts_count = VALUES(posts_count),
                      last_post_at = VALUES(last_post_at)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'user_id' => $user_id,
            'user_id2' => $user_id,
            'user_id3' => $user_id
        ]);
    }
    
    /**
     * Add thread tags
     */
    private function addThreadTags($thread_id, $tags) {
        foreach($tags as $tag_name) {
            $tag_slug = strtolower(trim($tag_name));
            
            // Get or create tag
            $query = "INSERT INTO forum_tags (name, slug) VALUES (:name, :slug)
                      ON DUPLICATE KEY UPDATE use_count = use_count + 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['name' => $tag_name, 'slug' => $tag_slug]);
            
            $query = "SELECT id FROM forum_tags WHERE slug = :slug";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['slug' => $tag_slug]);
            $tag_id = $stmt->fetchColumn();
            
            // Link tag to thread
            $query = "INSERT IGNORE INTO forum_thread_tags (thread_id, tag_id) VALUES (:thread_id, :tag_id)";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['thread_id' => $thread_id, 'tag_id' => $tag_id]);
        }
    }
    
    /**
     * Search threads
     */
    public function searchThreads($search_term, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT t.*,
                  u.username,
                  c.name as category_name,
                  c.slug as category_slug
                  FROM forum_threads t
                  LEFT JOIN users u ON t.user_id = u.id
                  LEFT JOIN forum_categories c ON t.category_id = c.id
                  WHERE MATCH(t.title, t.content) AGAINST(:search IN NATURAL LANGUAGE MODE)
                  AND t.is_deleted = FALSE
                  ORDER BY t.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':search', $search_term);
        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>