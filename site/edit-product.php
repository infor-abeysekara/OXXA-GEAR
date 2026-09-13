<?php
$page_title = 'Edit Product - OXXA GEAR';
include('../include/header.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['userid'];

// Get product ID from URL
if (!isset($_GET['id'])) {
    header('Location: seller-dashboard.php');
    exit();
}

$product_id = sanitizeInput($_GET['id']);

// Verify product belongs to this seller
$verifyQuery = "SELECT * FROM production WHERE pid = ? AND user_id = ?";
$verifyStmt = $conn->prepare($verifyQuery);
$verifyStmt->bind_param("ss", $product_id, $user_id);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    $_SESSION['error'] = 'Product not found or you do not have permission to edit it.';
    header('Location: seller-dashboard.php');
    exit();
}

$product = $verifyResult->fetch_assoc();

// Get product sizes
$sizesQuery = "SELECT * FROM productsize WHERE pid = ? ORDER BY price ASC";
$sizesStmt = $conn->prepare($sizesQuery);
$sizesStmt->bind_param("s", $product_id);
$sizesStmt->execute();
$sizesResult = $sizesStmt->get_result();
$sizes = $sizesResult->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $pname = sanitizeInput($_POST['pname']);
    $brand = sanitizeInput($_POST['brand']);
    $categories = sanitizeInput($_POST['categories']);
    $price = floatval($_POST['price']);
    $qty = intval($_POST['qty']);
    $description = sanitizeInput($_POST['description']);
    
    // Handle image upload
    $image_path = $product['image']; // Keep existing image by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../image/';
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if it exists
                if (!empty($product['image']) && file_exists($upload_dir . $product['image'])) {
                    unlink($upload_dir . $product['image']);
                }
                $image_path = $new_filename;
            }
        }
    }
    
    // Update product
    $updateQuery = "UPDATE production SET pname = ?, brand = ?, categories = ?, price = ?, qty = ?, discription = ?, image = ? WHERE pid = ? AND user_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("sssdiisss", $pname, $brand, $categories, $price, $qty, $description, $image_path, $product_id, $user_id);
    
    if ($updateStmt->execute()) {
        $_SESSION['success'] = 'Product updated successfully!';
        header('Location: seller-dashboard.php');
        exit();
    } else {
        $error = 'Failed to update product. Please try again.';
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card bg-dark text-white border-secondary">
                <div class="card-header bg-success">
                    <h4 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Product
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="pname" class="form-label">Product Name *</label>
                                    <input type="text" class="form-control" id="pname" name="pname" 
                                           value="<?php echo htmlspecialchars($product['pname']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand *</label>
                                    <input type="text" class="form-control" id="brand" name="brand" 
                                           value="<?php echo htmlspecialchars($product['brand']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="categories" class="form-label">Category *</label>
                                    <select class="form-select" id="categories" name="categories" required>
                                        <option value="">Select Category</option>
                                        <option value="Protein" <?php echo ($product['categories'] === 'Protein') ? 'selected' : ''; ?>>Protein</option>
                                        <option value="Pre-Workout" <?php echo ($product['categories'] === 'Pre-Workout') ? 'selected' : ''; ?>>Pre-Workout</option>
                                        <option value="Creatine" <?php echo ($product['categories'] === 'Creatine') ? 'selected' : ''; ?>>Creatine</option>
                                        <option value="Mass Gainers" <?php echo ($product['categories'] === 'Mass Gainers') ? 'selected' : ''; ?>>Mass Gainers</option>
                                        <option value="Fat Burners" <?php echo ($product['categories'] === 'Fat Burners') ? 'selected' : ''; ?>>Fat Burners</option>
                                        <option value="Recovery" <?php echo ($product['categories'] === 'Recovery') ? 'selected' : ''; ?>>Recovery</option>
                                        <option value="Vitamin" <?php echo ($product['categories'] === 'Vitamin') ? 'selected' : ''; ?>>Vitamins</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Base Price (Rs.) *</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" 
                                           value="<?php echo $product['price']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="qty" class="form-label">Base Quantity *</label>
                                    <input type="number" class="form-control" id="qty" name="qty" 
                                           value="<?php echo $product['qty']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="image" class="form-label">Product Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <small class="text-muted">Leave empty to keep current image</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['discription']); ?></textarea>
                        </div>
                        
                        <!-- Current Image Preview -->
                        <?php if (!empty($product['image'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Current Image:</label>
                                <div>
                                    <img src="../image/<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="Current product image" class="img-thumbnail" style="max-width: 200px;">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Product Sizes Section -->
                        <?php if (!empty($sizes)): ?>
                            <div class="mb-4">
                                <h5 class="text-success">Current Product Sizes</h5>
                                <div class="table-responsive">
                                    <table class="table table-dark table-sm">
                                        <thead>
                                            <tr>
                                                <th>Size</th>
                                                <th>Price</th>
                                                <th>Quantity</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sizes as $size): ?>
                                                <tr id="size-row-<?php echo $size['id']; ?>">
                                                    <td><?php echo htmlspecialchars($size['size']); ?></td>
                                                    <td>Rs. <?php echo number_format($size['price'], 2); ?></td>
                                                    <td><?php echo $size['qty']; ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editSize('<?php echo $size['id']; ?>', '<?php echo $size['size']; ?>', '<?php echo $size['price']; ?>', '<?php echo $size['qty']; ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteSize('<?php echo $size['id']; ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <a href="seller-dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" name="update_product" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Update Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Size Edit Modal -->
<div class="modal fade" id="sizeEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Size</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sizeEditForm">
                    <input type="hidden" id="edit_size_id" name="size_id">
                    <div class="mb-3">
                        <label class="form-label">Size</label>
                        <input type="text" class="form-control" id="edit_size_name" name="size" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (Rs.)</label>
                        <input type="number" class="form-control" id="edit_size_price" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="edit_size_qty" name="qty" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateSize()">Update Size</button>
            </div>
        </div>
    </div>
</div>

<script>
function editSize(id, size, price, qty) {
    document.getElementById('edit_size_id').value = id;
    document.getElementById('edit_size_name').value = size;
    document.getElementById('edit_size_price').value = price;
    document.getElementById('edit_size_qty').value = qty;
    
    new bootstrap.Modal(document.getElementById('sizeEditModal')).show();
}

function updateSize() {
    const formData = new FormData(document.getElementById('sizeEditForm'));
    
    fetch('../Backend/update-product-size.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function deleteSize(id) {
    if (confirm('Are you sure you want to delete this size?')) {
        fetch('../Backend/delete-product-size.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'size_id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('size-row-' + id).remove();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}
</script>

<?php include("../include/footer.php"); ?>