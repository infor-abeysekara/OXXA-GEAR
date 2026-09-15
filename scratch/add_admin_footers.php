<?php
$admin_dir = "C:/wamp64/www/OXXA GEAR/admin/";
$files = glob($admin_dir . "*.php");

foreach ($files as $file) {
    if (basename($file) === 'index.php') continue; // Don't add to login page

    $content = file_get_contents($file);
    if (strpos($content, 'include("../include/footer.php")') === false) {
        $content = str_replace(
            '</body>', 
            "    <?php include(\"../include/footer.php\"); ?>\n</body>", 
            $content
        );
        file_put_contents($file, $content);
        echo "Updated: " . basename($file) . "\n";
    } else {
        echo "Skipped: " . basename($file) . " (Already has footer)\n";
    }
}
echo "Done.\n";
