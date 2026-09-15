<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="withdrawalDrawer">
    <div class="drawer-header">
        <h5 class="mb-0 fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Request Details</h5>
        <button class="btn-close btn-close-white" onclick="closeDrawer()"></button>
    </div>
    
    <div class="drawer-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-0" id="drawer_seller_name">Business Name</h4>
                <div class="mt-2" id="drawer_status">Status</div>
            </div>
            <div class="text-end">
                <div class="text-muted small text-uppercase fw-bold">Amount Requested</div>
                <h3 class="fw-black text-primary mb-0" id="drawer_amount">Rs. 0.00</h3>
                <div class="text-muted small" id="drawer_date">Date</div>
            </div>
        </div>

        <div class="info-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-university text-secondary me-2"></i>Bank Transfer Details</h6>
                <button class="btn btn-sm btn-light border py-0 px-2 text-primary" onclick="copyAcc()"><i class="fas fa-copy"></i> Copy Acc</button>
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <div class="info-label">Bank Name</div>
                    <div class="info-value" id="drawer_bank">Bank</div>
                </div>
                <div class="col-6">
                    <div class="info-label">Branch</div>
                    <div class="info-value" id="drawer_branch">Branch</div>
                </div>
                <div class="col-12">
                    <div class="info-label">Account Number</div>
                    <div class="info-value fs-5 font-monospace text-primary tracking-widest" id="drawer_acc_no">123456789</div>
                </div>
                <div class="col-12">
                    <div class="info-label">Account Holder Name</div>
                    <div class="info-value" id="drawer_acc_name">Name</div>
                </div>
            </div>
        </div>

        <div class="info-box">
            <div class="info-label">Seller's Note</div>
            <div class="info-value text-muted" id="drawer_note">Note</div>
        </div>

        <!-- Paid Info -->
        <div id="info_paid" class="info-box border-success bg-success bg-opacity-10 d-none">
            <h6 class="fw-bold text-success mb-3"><i class="fas fa-check-circle me-2"></i> Payment Information</h6>
            <div class="row g-3">
                <div class="col-6">
                    <div class="info-label text-success">Reference No</div>
                    <div class="info-value" id="drawer_ref_no">-</div>
                </div>
                <div class="col-6">
                    <div class="info-label text-success">Paid On</div>
                    <div class="info-value" id="drawer_paid_date">-</div>
                </div>
                <div class="col-12">
                    <div class="info-label text-success">Proof Document</div>
                    <div class="info-value" id="drawer_proof">-</div>
                </div>
            </div>
        </div>

        <!-- Rejected Info -->
        <div id="info_rejected" class="info-box border-danger bg-danger bg-opacity-10 d-none">
            <h6 class="fw-bold text-danger mb-3"><i class="fas fa-times-circle me-2"></i> Rejection Reason</h6>
            <div class="info-value text-danger" id="drawer_reject_reason">-</div>
        </div>
    </div>
    
    <div class="drawer-footer">
        
        <!-- Actions for PENDING -->
        <div id="actions_pending" class="d-none">
            <form action="../Backend/process-withdrawal.php" method="POST">
                <input type="hidden" name="request_id" id="drawer_req_id">
                
                <div id="rejectReasonContainer" class="mb-3 d-none">
                    <label class="form-label fw-bold text-danger">Reason for Rejection *</label>
                    <textarea name="reject_reason" id="reject_reason" class="form-control border-danger" rows="2" placeholder="e.g., Bank details incorrect..."></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger flex-fill fw-bold" onclick="showRejectInput()">Reject</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger d-none" id="btn_real_reject">Confirm Reject</button>
                    <button type="submit" name="action" value="approve" class="btn btn-primary flex-fill fw-bold">Approve Request</button>
                </div>
            </form>
            <script>
                // Switch reject button behavior
                document.querySelector('button[onclick="showRejectInput()"]').addEventListener('click', function() {
                    this.classList.add('d-none');
                    document.getElementById('btn_real_reject').classList.remove('d-none');
                });
            </script>
        </div>

        <!-- Actions for APPROVED -->
        <div id="actions_approved" class="d-none">
            <form action="../Backend/process-withdrawal.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="request_id" id="drawer_req_id_pay">
                <input type="hidden" name="action" value="pay">
                
                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-money-check me-2"></i> Record Payment</h6>
                
                <div class="mb-3">
                    <label class="info-label">Bank Reference Number *</label>
                    <input type="text" name="reference_no" class="form-control" required placeholder="e.g., TR-987654321">
                </div>
                
                <div class="mb-3">
                    <label class="info-label">Upload Transfer Slip *</label>
                    <input type="file" name="proof_image" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                
                <button type="submit" class="btn btn-success w-full fw-bold w-100 py-2"><i class="fas fa-check-circle me-2"></i> Mark as Paid</button>
            </form>
            <script>
                // Sync the request ID
                document.getElementById('drawer_req_id_pay').value = document.getElementById('drawer_req_id').value;
            </script>
        </div>

    </div>
</div>
