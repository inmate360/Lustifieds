<?php
/**
 * Run this script once to create necessary directories
 * Access via: http://yourdomain.com/setup-directories.php
 */

$directories = [
    'uploads/listings',
    'uploads/thumbnails',
    'logs'
];

echo "<h2>Setting up directories...</h2>";

foreach($directories as $dir) {
    if(!file_exists($dir)) {
        if(mkdir($dir, 0755, true)) {
            echo "✓ Created directory: {$dir}<br>";
        } else {
            echo "✗ Failed to create directory: {$dir}<br>";
        }
    } else {
        echo "→ Directory already exists: {$dir}<br>";
    }
}

// Create .htaccess for uploads directory security
$htaccess_content = "Options -Indexes\n";
$htaccess_content .= "<Files *.php>\n";
$htaccess_content .= "Deny from all\n";
$htaccess_content .= "</Files>";

file_put_contents('uploads/.htaccess', $htaccess_content);
echo "✓ Created security .htaccess<br>";

echo "<h3>Setup complete!</h3>";
echo "<p><strong>Important:</strong> Delete this file (setup-directories.php) after running it.</p