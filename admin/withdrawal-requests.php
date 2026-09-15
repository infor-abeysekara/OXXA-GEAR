<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// -------------------------------------------------------------
// Filters & Where Clauses
// -------------------------------------------------------------
$status_filter = $_GET['status'] ?? 'Pending';
$where_clauses = ["1=1"];
$params = [];

if ($status_filter != 'all') {
    $where_clauses[] = "wr.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch requests
$stmt = $pdo->prepare("
    SELECT wr.*, b.business_name, u.email, u.name as owner_name 
    FROM withdrawal_requests wr 
    JOIN users u ON wr.seller_id = u.id 
    JOIN seller_profiles b ON u.id = b.user_id 
    WHERE $where_sql 
    ORDER BY wr.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counters
$countStmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending_c,
        SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) as approved_c,
        SUM(CASE WHEN status='Paid' THEN 1 ELSE 0 END) as paid_c,
        SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected_c
    FROM withdrawal_requests
");
$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Requests - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .section-card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .section-header {
            background: #fff;
            border-bottom: 1px solid #E5E7EB;
            border-left: 4px solid #0066FF;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .bg-pending { background: #FEF3C7; color: #92400E; }
        .bg-approved { background: #DBEAFE; color: #1E40AF; }
        .bg-paid { background: #D1FAE5; color: #065F46; }
        .bg-rejected { background: #FEE2E2; color: #991B1B; }

        .drawer-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 1040;
            opacity: 0; visibility: hidden; transition: 0.3s;
        }
        .drawer-overlay.active { opacity: 1; visibility: visible; }
        .drawer {
            position: fixed; top: 0; right: -500px; width: 500px; height: 100vh;
            background: #fff; z-index: 1050; box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            transition: 0.3s; display: flex; flex-direction: column;
        }
        .drawer.active { right: 0; }
        @media (max-width: 576px) { .drawer { width: 100%; right: -100%; } }
        
        .drawer-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #0066FF; color: white;}
        .drawer-body { padding: 20px; overflow-y: auto; flex-grow: 1; background: #f8f9fa; }
        .drawer-footer { padding: 20px; border-top: 1px solid #eee; background: #fff; }
        
        .info-box { background: white; border-radius: 10px; padding: 15px; border: 1px solid #eee; margin-bottom: 15px; }
        .info-label { font-size: 11px; color: #888; text-transform: uppercase; font-weight: bold; margin-bottom: 3px; }
        .info-value { font-size: 14px; color: #333; font-weight: 600; }
        
    </style>
</head>
<body>

    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        <?php include("components/topbar.php"); ?>

        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-0">Withdrawal Requests</h2>
                    <p class="text-muted mb-0">Manage and process seller payouts.</p>
                </div>
            </div>

            <!-- Tab Navigation -->
            <ul class="nav nav-pills mb-4 border-bottom pb-3">
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter=='Pending'?'active':'' ?>" href="?status=Pending">
                        Pending <span class="badge bg-warning text-dark ms-1"><?= $counts['pending_c'] ?? 0 ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter=='Approved'?'active':'' ?>" href="?status=Approved">
                        Approved (To Pay) <span class="badge bg-primary ms-1"><?= $counts['approved_c'] ?? 0 ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter=='Paid'?'active':'' ?>" href="?status=Paid">
                        Paid <span class="badge bg-success ms-1"><?= $counts['paid_c'] ?? 0 ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter=='Rejected'?'active':'' ?>" href="?status=Rejected">
                        Rejected
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter=='all'?'active':'' ?>" href="?status=all">
                        All Requests
                    </a>
                </li>
            </ul>

            <div class="section-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-uppercase text-secondary text-xs fw-bold px-4 py-3">Date</th>
                                <th class="text-uppercase text-secondary text-xs fw-bold px-4 py-3">Seller</th>
                                <th class="text-uppercase text-secondary text-xs fw-bold px-4 py-3 text-end">Amount</th>
                                <th class="text-uppercase text-secondary text-xs fw-bold px-4 py-3">Bank Details</th>
                                <th class="text-uppercase text-secondary text-xs fw-bold px-4 py-3 text-center">Status</th>
                                <th class="text-uppercase text-secondary text-xs fw-bold px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($requests) > 0): ?>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold text-dark"><?= date('M d, Y', strtotime($req['created_at'])) ?></div>
                                            <div class="text-muted small"><?= date('h:i A', strtotime($req['created_at'])) ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($req['business_name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($req['owner_name']) ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="fw-bold text-dark fs-5">Rs. <?= number_format($req['amount'], 2) ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($req['bank_name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($req['account_number']) ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php
                                            $badge = 'bg-secondary';
                                            if($req['status'] == 'Pending') $badge = 'bg-pending';
                                            if($req['status'] == 'Approved') $badge = 'bg-approved';
                                            if($req['status'] == 'Paid') $badge = 'bg-paid';
                                            if($req['status'] == 'Rejected') $badge = 'bg-rejected';
                                            ?>
                                            <span class="badge-status <?= $badge ?>"><?= $req['status'] ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button class="btn btn-sm btn-light border shadow-sm" onclick='openDrawer(<?= json_encode($req) ?>)'>
                                                <i class="fas fa-eye text-primary"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                        <h5>No requests found.</h5>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Drawer component -->
    <?php include("components/withdrawal-drawer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openDrawer(data) {
            document.getElementById('drawer_req_id').value = data.id;
            document.getElementById('drawer_seller_name').innerText = data.business_name;
            document.getElementById('drawer_amount').innerText = "Rs. " + parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('drawer_date').innerText = new Date(data.created_at).toLocaleString();
            
            document.getElementById('drawer_bank').innerText = data.bank_name;
            document.getElementById('drawer_branch').innerText = data.branch_name;
            document.getElementById('drawer_acc_no').innerText = data.account_number;
            document.getElementById('drawer_acc_name').innerText = data.account_holder_name;
            
            document.getElementById('drawer_note').innerText = data.note ? data.note : '-';
            
            // Status UI updates
            const statusBadge = document.getElementById('drawer_status');
            statusBadge.innerText = data.status;
            statusBadge.className = 'badge-status';
            
            const pendingActions = document.getElementById('actions_pending');
            const approvedActions = document.getElementById('actions_approved');
            const paidInfo = document.getElementById('info_paid');
            const rejectedInfo = document.getElementById('info_rejected');
            
            pendingActions.classList.add('d-none');
            approvedActions.classList.add('d-none');
            paidInfo.classList.add('d-none');
            rejectedInfo.classList.add('d-none');
            
            if(data.status === 'Pending') {
                statusBadge.classList.add('bg-pending');
                pendingActions.classList.remove('d-none');
            } else if(data.status === 'Approved') {
                statusBadge.classList.add('bg-approved');
                approvedActions.classList.remove('d-none');
            } else if(data.status === 'Paid') {
                statusBadge.classList.add('bg-paid');
                paidInfo.classList.remove('d-none');
                document.getElementById('drawer_ref_no').innerText = data.reference_no;
                document.getElementById('drawer_paid_date').innerText = new Date(data.paid_at).toLocaleString();
                if(data.proof_image) {
                    document.getElementById('drawer_proof').innerHTML = `<a href="../assets/uploads/${data.proof_image}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-image"></i> View Slip</a>`;
                } else {
                    document.getElementById('drawer_proof').innerHTML = '-';
                }
            } else if(data.status === 'Rejected') {
                statusBadge.classList.add('bg-rejected');
                rejectedInfo.classList.remove('d-none');
                document.getElementById('drawer_reject_reason').innerText = data.reject_reason;
            }

            document.getElementById('drawerOverlay').classList.add('active');
            document.getElementById('withdrawalDrawer').classList.add('active');
        }

        function closeDrawer() {
            document.getElementById('drawerOverlay').classList.remove('active');
            document.getElementById('withdrawalDrawer').classList.remove('active');
            document.getElementById('rejectReasonContainer').classList.add('d-none');
            document.getElementById('reject_reason').value = '';
        }
        
        function showRejectInput() {
            document.getElementById('rejectReasonContainer').classList.remove('d-none');
            document.getElementById('reject_reason').required = true;
        }

        function copyAcc() {
            const acc = document.getElementById('drawer_acc_no').innerText;
            navigator.clipboard.writeText(acc);
            alert("Account number copied!");
        }
    </script>
    <?php include("../include/footer.php"); ?>
</body>
</html>
