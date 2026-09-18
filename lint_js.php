<?php
$c = file_get_contents('admin/manage-users.php');
// find the last script block which contains our JS
if (preg_match_all('/<script>(.*?)<\/script>/s', $c, $m)) {
    $js = end($m[1]); // The last script block
    // Strip the PHP tags because they break JS linting
    $js = preg_replace('/<\?php.*?\?>/s', '[]', $js);
    file_put_contents('admin/test.js', $js);
}
?>
