<?php
if (extension_loaded('gd') && function_exists('gd_info')) {
    echo "GD is enabled.";
} else {
    echo "GD is NOT enabled.";
}
?>
