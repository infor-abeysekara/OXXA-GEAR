<?php
session_start();
$page_title = 'Business Registration - OXXA GEAR';
include('../include/header.php');
include('../include/connection.php');
include('../include/functions.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}

// Check if business is already registered
$business = getBusinessRegistration($conn, $_SESSION['userid']);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <?php if ($business): ?>
                <!-- Business Already Registered -->
                <div class="card border-0 shadow-lg">
                    <div class="card-header text-white text-center" style="background-color: #188754;">
                        <h4 class="mb-0">
                            <i class="fas fa-building me-2"></i>Business Registration Status
                        </h4>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($business['approve'] == 1): ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-3x mb-3" style="color: #188754;"></i>
                                <h5 style="color: #188754;">Your business is already registered!</h5>
                                <p class="mb-0">You can now add products to sell on our platform.</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-clock fa-3x mb-3 text-warning"></i>
                                <h5 class="text-warning">Registration Under Review</h5>
                                <p class="mb-0">Your business registration is pending admin approval.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Business Details -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6><strong>Business Name:</strong></h6>
                                <p><?php echo htmlspecialchars($business['bname']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><strong>Registration Date:</strong></h6>
                                <p><?php echo date('F d, Y', strtotime($business['date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><strong>Business Number:</strong></h6>
                                <p><?php echo htmlspecialchars($business['bnumber']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><strong>Registration ID:</strong></h6>
                                <p><?php echo htmlspecialchars($business['bregid']); ?></p>
                            </div>
                            <div class="col-md-12">
                                <h6><strong>Business Type:</strong></h6>
                                <p><?php echo htmlspecialchars($business['btype']); ?></p>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateModal">
                                <i class="fas fa-edit me-2"></i>Update Registration
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- New Business Registration Form -->
                <div class="card border-0 shadow-lg">
                    <div class="card-header text-white text-center" style="background-color: #188754;">
                        <h4 class="mb-0">
                            <i class="fas fa-building me-2"></i>Register Your Business
                        </h4>
                    </div>
                    <div class="card-body p-5">
                        <?php
                        // Display error messages
                        if(isset($_GET['error'])) {
                            $error = $_GET['error'];
                            $error_message = '';
                            switch($error) {
                                case 'bname': $error_message = 'Business name is required'; break;
                                case 'bnumber': $error_message = 'Business number is required'; break;
                                case 'bregid': $error_message = 'Business registration ID is required'; break;
                                case 'btype': $error_message = 'Business type is required'; break;
                                case 'bcertificate': $error_message = 'Business certificate is required'; break;
                                case 'format': $error_message = 'Invalid file format. Use PDF, JPG, JPEG, PNG, GIF, or WEBP'; break;
                                case 'large': $error_message = 'File size too large. Maximum 1MB allowed'; break;
                                case 'upload': $error_message = 'Failed to upload file'; break;
                                case 'database': $error_message = 'Database error occurred'; break;
                                default: $error_message = 'An error occurred';
                            }
                            echo '<div class="alert alert-danger">' . $error_message . '</div>';
                        }

                        if(isset($_GET['success'])) {
                            echo '<div class="alert alert-success">Business registration submitted successfully! Please wait for admin approval.</div>';
                        }
                        ?>

                        <form action="../Backend/business-registration-backend.php" method="POST" enctype="multipart/form-data" id="businessForm">
                            <div class="row">
                                <!-- Business Name -->
                                <div class="col-md-6 mb-3">
                                    <label for="bname" class="form-label fw-semibold">Business Name *</label>
                                    <input type="text" class="form-control" id="bname" name="bname" required>
                                    <div id="bnameError" class="text-danger mt-1" style="display: none;"></div>
                                </div>

                                <!-- Business Number -->
                                <div class="col-md-6 mb-3">
                                    <label for="bnumber" class="form-label fw-semibold">Business Number *</label>
                                    <input type="number" class="form-control" id="bnumber" name="bnumber" required>
                                    <div id="bnumberError" class="text-danger mt-1" style="display: none;"></div>
                                </div>

                                <!-- Business Registration ID -->
                                <div class="col-md-6 mb-3">
                                    <label for="bregid" class="form-label fw-semibold">Business Registration ID *</label>
                                    <input type="text" class="form-control" id="bregid" name="bregid" required>
                                    <div id="bregidError" class="text-danger mt-1" style="display: none;"></div>
                                </div>

                                <!-- Registration Date -->
                                <div class="col-md-6 mb-3">
                                    <label for="date" class="form-label fw-semibold">Registration Date *</label>
                                    <input type="date" class="form-control" id="date" name="date" required>
                                    <div id="dateError" class="text-danger mt-1" style="display: none;"></div>
                                </div>

                                <!-- Business Type -->
                                <div class="col-md-12 mb-3">
                                    <label for="btype" class="form-label fw-semibold">Business Type *</label>
                                    <select class="form-select" id="btype" name="btype" required>
                                        <option value="">Select Business Type</option>
                                        <option value="Sole Proprietorship">Sole Proprietorship</option>
                                        <option value="Partnership">Partnership</option>
                                        <option value="Private Limited Company">Private Limited Company</option>
                                        <option value="Public Limited Company">Public Limited Company</option>
                                        <option value="Non-Profit Organization">Non-Profit Organization</option>
                                    </select>
                                    <div id="btypeError" class="text-danger mt-1" style="display: none;"></div>
                                </div>

                                <!-- Business Certificate -->
                                <div class="col-md-6 mb-3">
                                    <label for="bcertificate" class="form-label fw-semibold">Business Certificate *</label>
                                    <input type="file" class="form-control" id="bcertificate" name="bcertificate" 
                                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" required>
                                    <small class="text-muted">PDF, JPG, JPEG, PNG, GIF, WEBP (Max 1MB)</small>
                                    <div id="bcertificateError" class="text-danger mt-1" style="display: none;"></div>
                                </div>

                                <!-- Business Logo (Optional) -->
                                <div class="col-md-6 mb-3">
                                    <label for="blogo" class="form-label fw-semibold">Business Logo (Optional)</label>
                                    <input type="file" class="form-control" id="blogo" name="blogo" 
                                           accept=".jpg,.jpeg,.png,.gif,.webp">
                                    <small class="text-muted">JPG, JPEG, PNG, GIF, WEBP (Max 1MB)</small>
                                    <div id="blogoError" class="text-danger mt-1" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" name="register_business" class="btn btn-lg" style="background-color: #188754; color: white;">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Registration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Update Modal -->
<?php if ($business): ?>
<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #188754; color: white;">
                <h5 class="modal-title">Update Business Registration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="../Backend/business-registration-backend.php" method="POST" enctype="multipart/form-data" id="updateBusinessForm">
                <input type="hidden" name="update_business" value="1">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Updating your registration will reset your approval status and require admin re-approval.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Business Name *</label>
                            <input type="text" class="form-control" name="bname" value="<?php echo htmlspecialchars($business['bname']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Business Number *</label>
                            <input type="number" class="form-control" name="bnumber" value="<?php echo htmlspecialchars($business['bnumber']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Registration ID *</label>
                            <input type="text" class="form-control" name="bregid" value="<?php echo htmlspecialchars($business['bregid']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Registration Date *</label>
                            <input type="date" class="form-control" name="date" value="<?php echo $business['date']; ?>" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold">Business Type *</label>
                            <select class="form-select" name="btype" required>
                                <option value="">Select Business Type</option>
                                <option value="Sole Proprietorship" <?php echo ($business['btype'] == 'Sole Proprietorship') ? 'selected' : ''; ?>>Sole Proprietorship</option>
                                <option value="Partnership" <?php echo ($business['btype'] == 'Partnership') ? 'selected' : ''; ?>>Partnership</option>
                                <option value="Private Limited Company" <?php echo ($business['btype'] == 'Private Limited Company') ? 'selected' : ''; ?>>Private Limited Company</option>
                                <option value="Public Limited Company" <?php echo ($business['btype'] == 'Public Limited Company') ? 'selected' : ''; ?>>Public Limited Company</option>
                                <option value="Non-Profit Organization" <?php echo ($business['btype'] == 'Non-Profit Organization') ? 'selected' : ''; ?>>Non-Profit Organization</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Update Certificate</label>
                            <input type="file" class="form-control" name="bcertificate" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                            <small class="text-muted">Leave empty to keep current certificate (Max 1MB)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Update Logo</label>
                            <input type="file" class="form-control" name="blogo" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <small class="text-muted">Leave empty to keep current logo (Max 1MB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background-color: #188754; color: white;">Update Registration</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('businessForm');
    const updateForm = document.getElementById('updateBusinessForm');
    
    // File validation function
    function validateFile(fileInput, errorElement, allowPdf = false) {
        const file = fileInput.files[0];
        if (!file) return true;
        
        // Validate file type
        const allowedTypes = allowPdf ? 
            ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'] :
            ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            
        if (!allowedTypes.includes(file.type)) {
            const typeText = allowPdf ? 'PDF, JPG, JPEG, PNG, GIF, and WEBP' : 'JPG, JPEG, PNG, GIF, and WEBP';
            errorElement.textContent = `Invalid file type. Only ${typeText} are allowed.`;
            errorElement.style.display = 'block';
            fileInput.value = '';
            return false;
        }
        
        // Validate file size (1MB = 1048576 bytes)
        if (file.size > 1048576) {
            errorElement.textContent = 'File size too large. Maximum 1MB allowed.';
            errorElement.style.display = 'block';
            fileInput.value = '';
            return false;
        }
        
        errorElement.style.display = 'none';
        return true;
    }
    
    // Add file validation listeners
    if (form) {
        const bcertificateInput = document.getElementById('bcertificate');
        const blogoInput = document.getElementById('blogo');
        
        bcertificateInput.addEventListener('change', function() {
            validateFile(this, document.getElementById('bcertificateError'), true);
        });
        
        blogoInput.addEventListener('change', function() {
            validateFile(this, document.getElementById('blogoError'), false);
        });
        
        // Form validation
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Clear previous errors
            document.querySelectorAll('.text-danger').forEach(el => el.style.display = 'none');
            
            // Validate business name
            const bname = document.getElementById('bname').value.trim();
            if (!bname) {
                document.getElementById('bnameError').textContent = 'Business name is required.';
                document.getElementById('bnameError').style.display = 'block';
                isValid = false;
            }
            
            // Validate business number
            const bnumber = document.getElementById('bnumber').value.trim();
            if (!bnumber) {
                document.getElementById('bnumberError').textContent = 'Business number is required.';
                document.getElementById('bnumberError').style.display = 'block';
                isValid = false;
            }
            
            // Validate registration ID
            const bregid = document.getElementById('bregid').value.trim();
            if (!bregid) {
                document.getElementById('bregidError').textContent = 'Business registration ID is required.';
                document.getElementById('bregidError').style.display = 'block';
                isValid = false;
            }
            
            // Validate date
            const date = document.getElementById('date').value;
            if (!date) {
                document.getElementById('dateError').textContent = 'Registration date is required.';
                document.getElementById('dateError').style.display = 'block';
                isValid = false;
            }
            
            // Validate business type
            const btype = document.getElementById('btype').value;
            if (!btype) {
                document.getElementById('btypeError').textContent = 'Please select a business type.';
                document.getElementById('btypeError').style.display = 'block';
                isValid = false;
            }
            
            // Validate certificate file
            const bcertificate = document.getElementById('bcertificate').files[0];
            if (!bcertificate) {
                document.getElementById('bcertificateError').textContent = 'Business certificate is required.';
                document.getElementById('bcertificateError').style.display = 'block';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Update form file validation
    if (updateForm) {
        const updateCertInput = updateForm.querySelector('input[name="bcertificate"]');
        const updateLogoInput = updateForm.querySelector('input[name="blogo"]');
        
        updateCertInput.addEventListener('change', function() {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'text-danger mt-1';
            this.parentNode.appendChild(errorDiv);
            validateFile(this, errorDiv, true);
        });
        
        updateLogoInput.addEventListener('change', function() {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'text-danger mt-1';
            this.parentNode.appendChild(errorDiv);
            validateFile(this, errorDiv, false);
        });
    }
});
</script>

<?php include("../include/footer.php"); ?>