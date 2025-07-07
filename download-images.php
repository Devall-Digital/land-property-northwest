<?php
// Land Property Northwest - Image Download Script
// ==============================================
// Run this once to download all required images

set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Create images directory
$image_dir = 'images';
if (!file_exists($image_dir)) {
    mkdir($image_dir, 0755, true);
}

// Image sources and configurations
$images = [
    // Hero background - Modern house with new windows
    'hero-bg.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80',
        'description' => 'Hero background - Modern house exterior'
    ],
    
    // About section - Professional installation team
    'about-team.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1558618644-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'description' => 'Professional window installation team'
    ],
    
    // Service images
    'windows-service.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1545259741-2ea3ebf61fa0?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        'description' => 'UPVC windows service'
    ],
    
    'doors-service.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1449844908441-8829872d2607?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        'description' => 'Composite doors service'
    ],
    
    'improvements-service.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1562259949-e8e7689d7828?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        'description' => 'Home improvements service'
    ],
    
    // Gallery images
    'gallery-1.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
        'description' => 'Window installation project 1'
    ],
    
    'gallery-2.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
        'description' => 'Window installation project 2'
    ],
    
    'gallery-3.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
        'description' => 'Window installation project 3'
    ],
    
    'gallery-4.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1416331108676-a22ccb276e35?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
        'description' => 'Door installation project'
    ],
    
    // Before/After examples
    'before-after-1.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        'description' => 'Before/After transformation 1'
    ],
    
    'before-after-2.jpg' => [
        'url' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        'description' => 'Before/After transformation 2'
    ]
];

$downloaded = [];
$failed = [];

echo "<h2>Land Property Northwest - Image Download Progress</h2>\n";
echo "<p>Downloading professional images for your website...</p>\n\n";

foreach ($images as $filename => $config) {
    echo "Downloading: {$filename} - {$config['description']}...\n";
    
    $image_data = @file_get_contents($config['url']);
    
    if ($image_data !== false) {
        $file_path = $image_dir . '/' . $filename;
        
        if (file_put_contents($file_path, $image_data)) {
            $file_size = filesize($file_path);
            $downloaded[] = $filename;
            echo "✅ Success! Downloaded {$filename} (" . round($file_size/1024, 1) . " KB)\n";
        } else {
            $failed[] = $filename;
            echo "❌ Failed to save {$filename}\n";
        }
    } else {
        $failed[] = $filename;
        echo "❌ Failed to download {$filename}\n";
    }
    
    // Small delay to be nice to the server
    usleep(500000); // 0.5 seconds
}

echo "\n<h3>Download Summary:</h3>\n";
echo "✅ Successfully downloaded: " . count($downloaded) . " images\n";
echo "❌ Failed: " . count($failed) . " images\n\n";

if (!empty($downloaded)) {
    echo "<h3>Downloaded Files:</h3>\n";
    foreach ($downloaded as $file) {
        echo "- {$file}\n";
    }
}

if (!empty($failed)) {
    echo "\n<h3>Failed Downloads:</h3>\n";
    foreach ($failed as $file) {
        echo "- {$file}\n";
    }
    echo "\nYou can manually download these images or run the script again.\n";
}

// Create a simple image optimization function
function optimizeImage($source, $destination, $quality = 80) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
        imagedestroy($image);
        return true;
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, round(9 * $quality / 100));
        imagedestroy($image);
        return true;
    }
    
    return false;
}

echo "\n<h3>Next Steps:</h3>\n";
echo "1. Update your CSS to use local images: images/hero-bg.jpg\n";
echo "2. Add gallery section to your website\n";
echo "3. Replace with your own customer project photos when available\n";
echo "4. Create favicon files using the logo\n";

echo "\n<p>🎉 Image setup complete! Your website now has professional images.</p>\n";
?>