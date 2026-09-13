<?php
$page_title = 'Notifications - OXXA GEAR';
include('../include/header.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['userid'];

// Mark notifications as read if requested
if (isset($_POST['mark_read'])) {
    $notification_id = $_POST['notification_id'];
    $updateQuery = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("is", $notification_id, $user_id);
    $stmt->execute();
}

// Mark all as read if requested
if (isset($_POST['mark_all_read'])) {
    $updateQuery = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
}

// Get notifications
$notificationsQuery = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($notificationsQuery);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card border-0 shadow-lg">
                <div class="card-header text-white d-flex justify-content-between align-items-center"
                    style="background-color: #188754;">
                    <h4 class="mb-0">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </h4>
                    <?php if ($notifications->num_rows > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-check-double me-1"></i>Mark All Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($notifications->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($notification = $notifications->fetch_assoc()): ?>
                                <div class="list-group-item <?php echo $notification['is_read'] ? 'bg-light' : 'bg-white border-start border-4' ?>"
                                    style="<?php echo !$notification['is_read'] ? 'border-color: #188754 !important;' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas <?php
                                                                switch ($notification['type']) {
                                                                    case 'success':
                                                                        echo 'fa-check-circle text-success';
                                                                        break;
                                                                    case 'warning':
                                                                        echo 'fa-exclamation-triangle text-warning';
                                                                        break;
                                                                    case 'error':
                                                                        echo 'fa-times-circle text-danger';
                                                                        break;
                                                                    case 'order':
                                                                        echo 'fa-shopping-cart';
                                                                        break;
                                                                    default:
                                                                        echo 'fa-info-circle text-info';
                                                                }
                                                                ?> me-2"></i>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y - g:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge ms-2" style="background-color: #188754;">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0 <?php echo !$notification['is_read'] ? 'fw-semibold' : ''; ?>">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                        </div>
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" class="ms-3">
                                                <input type="hidden" name="notification_id"
                                                    value="<?php echo $notification['id']; ?>">
                                                <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No notifications yet</h5>
                            <p class="text-muted">You'll receive notifications about orders, business registration updates,
                                and other important activities.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../include/footer.php"); ?>