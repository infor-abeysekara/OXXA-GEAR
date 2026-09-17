<?php
$file = 'site/seller-add-product.php';
$content = file_get_contents($file);

$end_marker = "<?php include('../include/footer.php'); ?>";
$end_pos = strpos($content, $end_marker);

$script_start = strpos($content, '<script src="../assets/js/seller-add-product-colors.js"></script>');
if ($script_start !== false && $end_pos !== false) {
    $part1 = substr($content, 0, $script_start + strlen('<script src="../assets/js/seller-add-product-colors.js"></script>'));
    $part2 = substr($content, $end_pos);
    
    $new_content = $part1 . "\n\n" . $part2;
    file_put_contents($file, $new_content);
    echo "Successfully stripped old JS.";
} else {
    echo "Markers not found.";
}
?>
