<?php
class ImageUpload {
    private $db;
    private $maxFileSize = 5242880; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    private $uploadDir = __DIR__ . '/../uploads/';
    
    public function __construct($db) {
        $this->db = $db;
        
        // Create upload directory if doesn't exist
        if(!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        // Create subdirectories
        $subdirs = ['users', 'listings', 'temp'];
        foreach($subdirs as $dir) {
            $path = $this->uploadDir . $dir;
            if(!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    public function getPrimaryImage($listing_id) {
        try {
            $query = "SELECT photo_url FROM listing_photos 
                      WHERE listing_id = :listing_id 
                      ORDER BY display_order ASC 
                      LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':listing_id', $listing_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? $result['photo_url'] : null;
        } catch(PDOException $e) {
            error_log("Error getting primary image: " . $e->getMessage());
            return null;
        }
    }
    
    public function getListingImages($listing_id) {
        try {
            $query = "SELECT * FROM listing_photos 
                      WHERE listing_id = :listing_id 
                      ORDER BY display_order ASC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':listing_id', $listing_id);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting listing images: " . $e->getMessage());
            return [];
        }
    }
    
    public function upload($file, $type = 'listing') {
        // Validate file
        $validation = $this->validateFile($file);
        if(!$validation['valid']) {
            return $validation;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        
        // Determine subdirectory
        $subdir = $type === 'user' ? 'users' : 'listings';
        $filepath = $this->uploadDir . $subdir . '/' . $filename;
        $webPath = '/uploads/' . $subdir . '/' . $filename;
        
        // Move uploaded file
        if(!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
        
        // Process image (resize, optimize)
        $this->processImage($filepath);
        
        // Create thumbnail
        $thumbPath = $this->createThumbnail($filepath, $subdir);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $webPath,
            'thumbnail' => $thumbPath,
            'size' => filesize($filepath)
        ];
    }
    
    private function validateFile($file) {
        // Check for upload errors
        if($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload'
            ];
            
            return [
                'valid' => false,
                'message' => $errors[$file['error']] ?? 'Unknown upload error'
            ];
        }
        
        // Check file size
        if($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File size exceeds maximum allowed (5MB)'
            ];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if(!in_array($mimeType, $this->allowedTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP allowed'
            ];
        }
        
        // Check actual image
        $imageInfo = getimagesize($file['tmp_name']);
        if($imageInfo === false) {
            return [
                'valid' => false,
                'message' => 'File is not a valid image'
            ];
        }
        
        // Check dimensions
        if($imageInfo[0] < 100 || $imageInfo[1] < 100) {
            return [
                'valid' => false,
                'message' => 'Image dimensions too small (minimum 100x100px)'
            ];
        }
        
        return ['valid' => true];
    }
    
    private function processImage($filepath) {
        $imageInfo = getimagesize($filepath);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Only resize if larger than 1920px
        $maxWidth = 1920;
        $maxHeight = 1920;
        
        if($width <= $maxWidth && $height <= $maxHeight) {
            return;
        }
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        // Load image
        $source = $this->loadImage($filepath, $type);
        if(!$source) return;
        
        // Create new image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG/GIF
        if($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }
        
        // Resize
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save
        $this->saveImage($destination, $filepath, $type);
        
        // Clean up
        imagedestroy($source);
        imagedestroy($destination);
    }
    
    private function createThumbnail($filepath, $subdir) {
        $imageInfo = getimagesize($filepath);
        $type = $imageInfo[2];
        
        $thumbWidth = 300;
        $thumbHeight = 300;
        
        // Load source
        $source = $this->loadImage($filepath, $type);
        if(!$source) return null;
        
        // Get dimensions
        $width = imagesx($source);
        $height = imagesy($source);
        
        // Calculate crop dimensions (center crop)
        $size = min($width, $height);
        $x = ($width - $size) / 2;
        $y = ($height - $size) / 2;
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency
        if($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }
        
        // Crop and resize
        imagecopyresampled($thumbnail, $source, 0, 0, $x, $y, $thumbWidth, $thumbHeight, $size, $size);
        
        // Save thumbnail
        $pathInfo = pathinfo($filepath);
        $thumbFilename = $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
        $thumbPath = $this->uploadDir . $subdir . '/' . $thumbFilename;
        $thumbWebPath = '/uploads/' . $subdir . '/' . $thumbFilename;
        
        $this->saveImage($thumbnail, $thumbPath, $type);
        
        // Clean up
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return $thumbWebPath;
    }
    
    private function loadImage($filepath, $type) {
        switch($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filepath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filepath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filepath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filepath);
            default:
                return false;
        }
    }
    
    private function saveImage($image, $filepath, $type) {
        switch($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($image, $filepath, 90);
            case IMAGETYPE_PNG:
                return imagepng($image, $filepath, 8);
            case IMAGETYPE_GIF:
                return imagegif($image, $filepath);
            case IMAGETYPE_WEBP:
                return imagewebp($image, $filepath, 90);
            default:
                return false;
        }
    }
    
    public function delete($path) {
        $fullPath = __DIR__ . '/..' . $path;
        
        if(file_exists($fullPath)) {
            unlink($fullPath);
            
            // Delete thumbnail
            $pathInfo = pathinfo($fullPath);
            $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
            if(file_exists($thumbPath)) {
                unlink($thumbPath);
            }
            
            return true;
        }
        
        return false;
    }
}
?>