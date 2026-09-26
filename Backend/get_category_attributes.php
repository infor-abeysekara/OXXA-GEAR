<?php
include('../include/connection.php');

header('Content-Type: application/json');

if (!isset($_GET['category_id'])) {
    echo json_encode(['success' => false, 'message' => 'Category ID is required']);
    exit;
}

$categoryId = (int)$_GET['category_id'];

try {
    $query = "
        SELECT 
            a.id, 
            a.name, 
            a.input_type, 
            a.default_options, 
            ca.is_variant_axis, 
            ca.is_required
        FROM category_attributes ca
        JOIN attributes a ON ca.attribute_id = a.id
        WHERE ca.category_id = ?
        ORDER BY a.id ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$categoryId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fixed_attributes = [];
    $variant_attributes = [];

    foreach ($results as $row) {
        $attr = [
            'id' => $row['id'],
            'name' => $row['name'],
            'input_type' => $row['input_type'],
            'default_options' => $row['default_options'] ? json_decode($row['default_options'], true) : [],
            'is_required' => (bool)$row['is_required']
        ];

        if ($row['is_variant_axis'] == 1) {
            $variant_attributes[] = $attr;
        } else {
            $fixed_attributes[] = $attr;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'fixed_attributes' => $fixed_attributes,
            'variant_attributes' => $variant_attributes
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
