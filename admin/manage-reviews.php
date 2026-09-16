<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $review_id = $_POST['review_id'];
    $action = $_POST['action'];
    
    if ($action == 'approve') {
        $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?");
        $stmt->execute([$review_id]);
    } elseif ($action == 'reject') {
        $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$review_id]);
    } elseif ($action == 'reply') {
        $reply = trim($_POST['admin_reply']);
        $stmt = $pdo->prepare("UPDATE reviews SET admin_reply = ? WHERE id = ?");
        $stmt->execute([$reply, $review_id]);
    }
    
    header("Location: manage-reviews.php");
    exit();
}

$current_page = 'manage-reviews.php';

// Fetch Reviews
$filter = $_GET['filter'] ?? 'pending';
$query = "SELECT r.*, p.name as product_name, u.first_name, u.last_name 
          FROM reviews r 
          JOIN products p ON r.product_id = p.id 
          JOIN users u ON r.user_id = u.id";

if ($filter != 'all') {
    $query .= " WHERE r.status = :filter";
}
$query .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($query);
if ($filter != 'all') {
    $stmt->bindParam(':filter', $filter);
}
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - OXXA GEAR Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F8F9FA; }
        .main-content { margin-left: 250px; padding: 30px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-header { background: white; border-bottom: 1px solid #f0f0f0; border-radius: 15px 15px 0 0 !important; padding: 20px 25px; }
        
        .review-card {
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            transition: all 0.2s;
        }
        .review-card:hover {
            border-color: #0066FF;
            box-shadow: 0 5px 15px rgba(0,102,255,0.1);
        }
        
        .review-images img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
            border: 1px solid #eee;
        }
    </style>
</head>
<body>

    <?php include('components/sidebar.php'); ?>

    <div class="main-content">
        <?php include('components/topbar.php'); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-black text-dark text-uppercase tracking-wide mb-1">Manage Reviews</h4>
                <p class="text-muted mb-0">Approve, reject, and reply to customer reviews</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="btn-group">
                    <a href="?filter=pending" class="btn btn-sm <?= $filter == 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">Pending</a>
                    <a href="?filter=approved" class="btn btn-sm <?= $filter == 'approved' ? 'btn-success' : 'btn-outline-secondary' ?>">Approved</a>
                    <a href="?filter=rejected" class="btn btn-sm <?= $filter == 'rejected' ? 'btn-danger' : 'btn-outline-secondary' ?>">Rejected</a>
                    <a href="?filter=all" class="btn btn-sm <?= $filter == 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
                </div>
            </div>
            <div class="card-body bg-light">
                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $rev): 
                        // Fetch images
                        $imgStmt = $pdo->prepare("SELECT * FROM review_images WHERE review_id = ?");
                        $imgStmt->execute([$rev['id']]);
                        $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                        <div class="review-card">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= $rev['rating'] ? 'text-warning' : 'text-light' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="badge bg-<?= $rev['status'] == 'pending' ? 'warning' : ($rev['status'] == 'approved' ? 'success' : 'danger') ?> ms-2 text-uppercase">
                                            <?= $rev['status'] ?>
                                        </span>
                                    </div>
                                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($rev['title']) ?></h5>
                                    <p class="text-muted mb-2"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                                    
                                    <?php if(count($images) > 0): ?>
                                    <div class="review-images mb-3">
                                        <?php foreach($images as $img): ?>
                                            <img src="../assets/uploads/reviews/<?= htmlspecialchars($img['image_path']) ?>">
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-sm text-secondary">
                                        <strong>User:</strong> <?= $rev['is_anonymous'] ? 'Anonymous' : htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?> <br>
                                        <strong>Product:</strong> <?= htmlspecialchars($rev['product_name']) ?> <br>
                                        <strong>Fit Feedback:</strong> <span class="badge bg-info text-dark"><?= htmlspecialchars($rev['fit_feedback']) ?></span> <br>
                                        <strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($rev['created_at'])) ?>
                                    </div>
                                    
                                    <?php if(!empty($rev['admin_reply'])): ?>
                                        <div class="mt-3 p-3 bg-light rounded border border-primary border-opacity-25">
                                            <strong class="text-primary"><i class="fas fa-reply me-1"></i> OXXA GEAR Reply:</strong>
                                            <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($rev['admin_reply'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <?php if($rev['status'] == 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success fw-bold me-2"><i class="fas fa-check me-1"></i> Approve</button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger fw-bold"><i class="fas fa-times me-1"></i> Reject</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <button class="btn btn-outline-primary btn-sm fw-bold w-100" data-bs-toggle="collapse" data-bs-target="#reply-<?= $rev['id'] ?>">
                                            <i class="fas fa-reply me-1"></i> <?= empty($rev['admin_reply']) ? 'Add Reply' : 'Edit Reply' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="collapse mt-3" id="reply-<?= $rev['id'] ?>">
                                <div class="card card-body bg-light border-0">
                                    <form method="POST">
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <input type="hidden" name="action" value="reply">
                                        <div class="mb-2">
                                            <label class="form-label fw-bold">Admin Reply</label>
                                            <textarea name="admin_reply" class="form-control" rows="3" required><?= htmlspecialchars($rev['admin_reply']) ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold">Save Reply</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center p-5">
                        <i class="fas fa-star text-muted mb-3" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold">No Reviews Found</h5>
                        <p class="text-muted">There are no reviews matching this filter.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
