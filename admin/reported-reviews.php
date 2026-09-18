<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $report_id = intval($_POST['report_id']);
    $review_id = intval($_POST['review_id']);
    
    if ($_POST['action'] === 'hide') {
        $conn->query("UPDATE reviews SET status = 'hidden' WHERE id = $review_id");
        $conn->query("UPDATE review_reports SET status = 'resolved' WHERE id = $report_id");
        $msg = "Review hidden successfully.";
        $msg_type = "success";
    } elseif ($_POST['action'] === 'dismiss') {
        $conn->query("UPDATE reviews SET status = 'active' WHERE id = $review_id");
        $conn->query("UPDATE review_reports SET status = 'resolved' WHERE id = $report_id");
        $msg = "Report dismissed. Review remains active.";
        $msg_type = "info";
    }
}

// Fetch pending reports
$query = "
    SELECT 
        rr.id as report_id, rr.reason, rr.reported_by_type, rr.created_at as report_date,
        r.id as review_id, r.rating, r.comment, r.status as review_status,
        p.name as product_name, p.image as product_image,
        u_cust.first_name as customer_name,
        s.store_name
    FROM review_reports rr
    JOIN reviews r ON rr.review_id = r.id
    JOIN products p ON r.product_id = p.id
    JOIN users u_cust ON r.user_id = u_cust.id
    LEFT JOIN seller_profiles s ON r.seller_id = s.user_id
    WHERE rr.status = 'pending'
    ORDER BY rr.created_at DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reported Reviews - OXXA GEAR Admin</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        .report-card { background: #fff; border-radius: 12px; border: 1px solid #dee2e6; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .report-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 15px 20px; border-radius: 12px 12px 0 0; }
        .report-body { padding: 20px; }
    </style>
</head>
<body>

    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        <div class="d-flex justify-content-between items-center mb-4">
            <h2 class="fw-bold mb-0">Reported Reviews</h2>
        </div>

        <?php if(isset($msg)): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="report-card">
                    <div class="report-header d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-danger me-2"><i class="fas fa-flag"></i> Reported by <?php echo ucfirst($row['reported_by_type']); ?></span>
                            <strong>Reason:</strong> <?php echo htmlspecialchars($row['reason']); ?>
                        </div>
                        <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($row['report_date'])); ?></small>
                    </div>
                    <div class="report-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5><?php echo htmlspecialchars($row['product_name']); ?></h5>
                                <div class="mb-2 text-warning">
                                    <?php for($i=1; $i<=5; $i++) echo ($i <= $row['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                </div>
                                <p class="mb-1"><strong>Customer:</strong> <?php echo htmlspecialchars($row['customer_name']); ?></p>
                                <?php if($row['store_name']): ?>
                                    <p class="mb-2"><strong>Seller:</strong> <?php echo htmlspecialchars($row['store_name']); ?></p>
                                <?php endif; ?>
                                <div class="p-3 bg-light rounded mt-3">
                                    <p class="mb-0 fst-italic">"<?php echo nl2br(htmlspecialchars($row['comment'])); ?>"</p>
                                </div>
                            </div>
                            <div class="col-md-4 text-end d-flex flex-column justify-content-center">
                                <form method="POST" class="d-inline mb-2">
                                    <input type="hidden" name="report_id" value="<?php echo $row['report_id']; ?>">
                                    <input type="hidden" name="review_id" value="<?php echo $row['review_id']; ?>">
                                    <input type="hidden" name="action" value="hide">
                                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Hide this review? It will no longer be visible to customers.');">
                                        <i class="fas fa-eye-slash"></i> Hide Review
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?php echo $row['report_id']; ?>">
                                    <input type="hidden" name="review_id" value="<?php echo $row['review_id']; ?>">
                                    <input type="hidden" name="action" value="dismiss">
                                    <button type="submit" class="btn btn-outline-secondary w-100" onclick="return confirm('Dismiss report? The review will remain active.');">
                                        <i class="fas fa-times"></i> Dismiss Report
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> All good! There are no pending reported reviews.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
