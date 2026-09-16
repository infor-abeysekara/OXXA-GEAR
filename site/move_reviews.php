<?php
$file = 'C:/wamp64/www/OXXA GEAR/site/product-details.php';
$content = file_get_contents($file);

// Find the reviews section
$startStr = "            <!-- Reviews Section -->";
$endStr = "            </div>\n\n            <!-- Desktop Add to Cart (Hidden on Mobile) -->";

$startPos = strpos($content, $startStr);
$endPos = strpos($content, "<!-- Desktop Add to Cart (Hidden on Mobile) -->");
if ($startPos !== false && $endPos !== false) {
    // Extract the section
    $reviewsSection = substr($content, $startPos, $endPos - $startPos);
    
    // Remove it from the original place
    $content = str_replace($reviewsSection, "", $content);
    
    // Insert it after the desktop thumbnails in the left column
    $targetInsert = "            <?php endif; ?>\n        </div>\n\n        <!-- Product Details -->";
    
    // Ensure we don't insert it multiple times by checking
    if (strpos($content, $targetInsert) !== false) {
        $replacement = "            <?php endif; ?>\n\n" . $reviewsSection . "\n        </div>\n\n        <!-- Product Details -->";
        $content = str_replace($targetInsert, $replacement, $content);
        
        file_put_contents($file, $content);
        echo "Successfully moved reviews section.";
    } else {
        echo "Target insert location not found.";
    }
} else {
    echo "Reviews section not found.";
}
?>
