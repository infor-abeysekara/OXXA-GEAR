<?php
include('include/connection.php');

function addColumn($pdo, $table, $column, $def) {
    try {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $def");
        echo "Added $column to $table.\n";
    } catch (Exception $e) {
        echo "Column $column might already exist in $table.\n";
    }
}

addColumn($pdo, 'withdrawal_requests', 'bank_name', 'varchar(100) DEFAULT NULL');
addColumn($pdo, 'withdrawal_requests', 'branch_name', 'varchar(100) DEFAULT NULL');
addColumn($pdo, 'withdrawal_requests', 'account_number', 'varchar(50) DEFAULT NULL');
addColumn($pdo, 'withdrawal_requests', 'account_holder_name', 'varchar(100) DEFAULT NULL');
addColumn($pdo, 'withdrawal_requests', 'note', 'text DEFAULT NULL');

?>
