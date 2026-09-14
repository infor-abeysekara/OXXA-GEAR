<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

$success_message = '';
$error_message = '';

$active_tab = $_GET['tab'] ?? 'brands';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // TAB 1: BRANDS MANAGEMENT
    if ($action === 'add_brand') {
        $active_tab = 'brands';
        $name = trim($_POST['name']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $categories = $_POST['categories'] ?? []; // Array of category IDs
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $logo_image = null;
        if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo_image'];
            $uploadDir = '../assets/uploads/brands/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (in_array($fileType, $allowedTypes)) {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fileName = 'brand_' . uniqid() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                list($width, $height) = getimagesize($file['tmp_name']);
                $newSize = 100;
                $image_p = imagecreatetruecolor($newSize, $newSize);
                
                if ($fileType == 'image/png') {
                    imagealphablending($image_p, false);
                    imagesavealpha($image_p, true);
                    $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
                    imagefilledrectangle($image_p, 0, 0, $newSize, $newSize, $transparent);
                    $image = imagecreatefrompng($file['tmp_name']);
                } elseif ($fileType == 'image/webp') {
                    $image = imagecreatefromwebp($file['tmp_name']);
                } else {
                    $image = imagecreatefromjpeg($file['tmp_name']);
                }
                
                $min_dim = min($width, $height);
                $src_x = ($width - $min_dim) / 2;
                $src_y = ($height - $min_dim) / 2;
                
                imagecopyresampled($image_p, $image, 0, 0, $src_x, $src_y, $newSize, $newSize, $min_dim, $min_dim);
                
                if ($fileType == 'image/png') imagepng($image_p, $targetPath, 9);
                elseif ($fileType == 'image/webp') imagewebp($image_p, $targetPath, 80);
                else imagejpeg($image_p, $targetPath, 80);
                
                imagedestroy($image_p);
                imagedestroy($image);
                $logo_image = $fileName;
            } else {
                $error_message = "Invalid brand image type. Use JPG, PNG or WEBP.";
            }
        }
        
        if (empty($error_message)) {
            $stmt = $conn->prepare("INSERT INTO brands (name, slug, logo_image, is_active) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $slug, $logo_image, $is_active);
            if ($stmt->execute()) {
                $brand_id = $conn->insert_id;
                if (!empty($categories)) {
                    $catStmt = $conn->prepare("INSERT INTO brand_category (brand_id, category_id) VALUES (?, ?)");
                    foreach ($categories as $cat_id) {
                        $catStmt->bind_param("ii", $brand_id, $cat_id);
                        $catStmt->execute();
                    }
                }
                $success_message = "Brand added successfully!";
            } else {
                $error_message = "Failed to add brand. Maybe slug already exists.";
            }
        }
    } elseif ($action === 'delete_brand') {
        $active_tab = 'brands';
        $brand_id = (int)$_POST['brand_id'];
        $stmt = $conn->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->bind_param("i", $brand_id);
        if ($stmt->execute()) $success_message = "Brand deleted successfully.";
        else $error_message = "Failed to delete brand.";
    }
    
    // TAB 2: CATEGORY IMAGES
    elseif ($action === 'upload_category_image') {
        $active_tab = 'categories';
        $category_id = (int)$_POST['category_id'];
        
        if (isset($_FILES['cat_image']) && $_FILES['cat_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['cat_image'];
            $uploadDir = '../assets/uploads/categories/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (in_array($fileType, $allowedTypes)) {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fileName = 'cat_' . $category_id . '_' . uniqid() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                list($width, $height) = getimagesize($file['tmp_name']);
                
                // Target dimensions: 400x500 (Portrait)
                $target_w = 400;
                $target_h = 500;
                
                $image_p = imagecreatetruecolor($target_w, $target_h);
                
                if ($fileType == 'image/png') {
                    imagealphablending($image_p, false);
                    imagesavealpha($image_p, true);
                    $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
                    imagefilledrectangle($image_p, 0, 0, $target_w, $target_h, $transparent);
                    $image = imagecreatefrompng($file['tmp_name']);
                } elseif ($fileType == 'image/webp') {
                    $image = imagecreatefromwebp($file['tmp_name']);
                } else {
                    $image = imagecreatefromjpeg($file['tmp_name']);
                }
                
                // Calculate crop to fill 400x500
                $src_ratio = $width / $height;
                $target_ratio = $target_w / $target_h;
                
                if ($src_ratio > $target_ratio) {
                    $crop_h = $height;
                    $crop_w = $height * $target_ratio;
                    $src_x = ($width - $crop_w) / 2;
                    $src_y = 0;
                } else {
                    $crop_w = $width;
                    $crop_h = $width / $target_ratio;
                    $src_x = 0;
                    $src_y = ($height - $crop_h) / 2;
                }
                
                imagecopyresampled($image_p, $image, 0, 0, $src_x, $src_y, $target_w, $target_h, $crop_w, $crop_h);
                
                // Optimize to stay under ~300KB
                if ($fileType == 'image/png') imagepng($image_p, $targetPath, 8); // 0-9 compression
                elseif ($fileType == 'image/webp') imagewebp($image_p, $targetPath, 70); // 0-100 quality
                else imagejpeg($image_p, $targetPath, 75); // 0-100 quality
                
                imagedestroy($image_p);
                imagedestroy($image);
                
                // Update DB
                $stmt = $conn->prepare("UPDATE categories SET image = ? WHERE id = ?");
                $stmt->bind_param("si", $fileName, $category_id);
                if ($stmt->execute()) {
                    $success_message = "Category image updated successfully!";
                } else {
                    $error_message = "Failed to update category image in database.";
                }
            } else {
                $error_message = "Invalid image type. Use JPG, PNG or WEBP.";
            }
        } else {
            $error_message = "Please select a valid image.";
        }
    }
}

// Fetch all categories
$catRes = $conn->query("SELECT * FROM categories ORDER BY id ASC");
$all_categories = $catRes->fetch_all(MYSQLI_ASSOC);

// Fetch all brands
$brandsQuery = "
    SELECT b.*, 
           (SELECT COUNT(*) FROM brand_category bc WHERE bc.brand_id = b.id) as cat_count,
           (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) as product_count,
           GROUP_CONCAT(c.name SEPARATOR ', ') as linked_categories
    FROM brands b
    LEFT JOIN brand_category bc ON b.id = bc.brand_id
    LEFT JOIN categories c ON bc.category_id = c.id
    GROUP BY b.id
    ORDER BY b.id DESC
";
$brandsRes = $conn->query($brandsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-header { background: white; border-bottom: 1px solid #f0f0f0; border-radius: 15px 15px 0 0 !important; padding: 20px 25px; }
        
        .nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 600; padding: 12px 20px; border-bottom: 2px solid transparent; }
        .nav-tabs .nav-link:hover { border-color: transparent; color: #0066FF; }
        .nav-tabs .nav-link.active { border-color: transparent; border-bottom: 2px solid #0066FF; color: #0066FF; background: transparent; }
        
        .brand-logo-preview { width: 50px; height: 50px; object-fit: contain; background: #fff; border: 1px solid #eee; border-radius: 8px; }
        
        .cat-card-preview {
            width: 100%;
            aspect-ratio: 4/5;
            border-radius: 12px;
            object-fit: cover;
            background: #eee;
            position: relative;
        }
        .cat-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 20px;
            color: white;
            border-radius: 0 0 12px 12px;
        }
    </style>
</head>
<body>

    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        <?php include("components/topbar.php"); ?>

        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold text-dark">System Settings</h2>
                    <p class="text-muted">Manage brands, categories, and general configurations.</p>
                </div>
            </div>

            <?php if(!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs mb-4 border-bottom-0" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'brands' ? 'active' : ''; ?>" id="brands-tab" data-bs-toggle="tab" data-bs-target="#brands" type="button" role="tab">Categories & Brands</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'categories' ? 'active' : ''; ?>" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">Shop By Category Images</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General Configuration</button>
                </li>
            </ul>

            <!-- Tabs Content -->
            <div class="tab-content" id="settingsTabsContent">
                
                <!-- TAB 1: BRANDS -->
                <div class="tab-pane fade <?php echo $active_tab === 'brands' ? 'show active' : ''; ?>" id="brands" role="tabpanel">
                    <div class="row">
                        <!-- Add Brand Form -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header"><h5 class="mb-0 fw-bold">Add New Brand</h5></div>
                                <div class="card-body">
                                    <form action="" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="add_brand">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Brand Name</label>
                                            <input type="text" name="name" class="form-control" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Brand Logo (100x100)</label>
                                            <input type="file" name="logo_image" class="form-control" accept="image/*">
                                            <small class="text-muted">Will be resized to square.</small>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label fw-bold">Link to Categories</label>
                                            <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                                <?php foreach($all_categories as $cat): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" id="cat_<?php echo $cat['id']; ?>">
                                                        <label class="form-check-label" for="cat_<?php echo $cat['id']; ?>">
                                                            <?php echo htmlspecialchars($cat['name']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                            <label class="form-check-label fw-bold" for="is_active">Active Brand</label>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-100 fw-bold">Save Brand</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Brands List -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold">Brands List</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-4">Brand</th>
                                                    <th>Categories</th>
                                                    <th>Products</th>
                                                    <th>Status</th>
                                                    <th class="text-end pe-4">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if($brandsRes->num_rows > 0): ?>
                                                    <?php while($b = $brandsRes->fetch_assoc()): ?>
                                                        <tr>
                                                            <td class="ps-4">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <?php if($b['logo_image']): ?>
                                                                        <img src="../assets/uploads/brands/<?php echo htmlspecialchars($b['logo_image']); ?>" class="brand-logo-preview">
                                                                    <?php else: ?>
                                                                        <div class="brand-logo-preview d-flex align-items-center justify-content-center bg-light text-muted">
                                                                            <i class="fas fa-image"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div>
                                                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($b['name']); ?></h6>
                                                                        <small class="text-muted">/<?php echo htmlspecialchars($b['slug']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-secondary"><?php echo $b['cat_count']; ?></span>
                                                                <small class="d-block text-muted text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($b['linked_categories'] ?? ''); ?>">
                                                                    <?php echo htmlspecialchars($b['linked_categories'] ?? 'None'); ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary rounded-pill px-3"><?php echo $b['product_count']; ?></span>
                                                            </td>
                                                            <td>
                                                                <?php if($b['is_active']): ?>
                                                                    <span class="badge bg-success">Active</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">Inactive</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end pe-4">
                                                                <form action="" method="POST" onsubmit="return confirm('Delete this brand?');" class="d-inline">
                                                                    <input type="hidden" name="action" value="delete_brand">
                                                                    <input type="hidden" name="brand_id" value="<?php echo $b['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="5" class="text-center py-4">No brands found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- TAB 2: CATEGORY IMAGES -->
                <div class="tab-pane fade <?php echo $active_tab === 'categories' ? 'show active' : ''; ?>" id="categories" role="tabpanel">
                    <div class="row g-4">
                        <?php foreach($all_categories as $cat): ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="card h-100 overflow-hidden border-0 shadow-sm group">
                                    <div class="position-relative">
                                        <?php if(!empty($cat['image'])): ?>
                                            <img src="../assets/uploads/categories/<?php echo htmlspecialchars($cat['image']); ?>" class="cat-card-preview" alt="Category Image">
                                        <?php else: ?>
                                            <div class="cat-card-preview d-flex align-items-center justify-content-center bg-dark text-white">
                                                <i class="fas fa-image fa-3x opacity-25"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="cat-overlay">
                                            <h5 class="fw-bold mb-0 text-white text-uppercase" style="letter-spacing: 1px;"><?php echo htmlspecialchars($cat['name']); ?></h5>
                                        </div>
                                    </div>
                                    <div class="card-body p-3 text-center">
                                        <button type="button" class="btn btn-outline-primary w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal<?php echo $cat['id']; ?>">
                                            <i class="fas fa-camera me-2"></i> Change Image
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Modal for this Category -->
                            <div class="modal fade" id="uploadModal<?php echo $cat['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4 shadow-lg">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold">Update <?php echo htmlspecialchars($cat['name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="" method="POST" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="upload_category_image">
                                                <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Upload Portrait Image</label>
                                                    <input class="form-control" type="file" name="cat_image" accept="image/*" required>
                                                    <div class="form-text mt-2">
                                                        <ul class="mb-0 text-muted small">
                                                            <li>Will be cropped to <strong>400x500 pixels</strong> (Portrait).</li>
                                                            <li>Optimized to under 300KB automatically.</li>
                                                            <li>Live preview updates instantly after saving.</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Upload & Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB 3: GENERAL CONFIGURATION -->
                <div class="tab-pane fade <?php echo $active_tab === 'general' ? 'show active' : ''; ?>" id="general" role="tabpanel">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-cogs fa-4x text-muted mb-3 opacity-25"></i>
                            <h4 class="fw-bold text-muted">General Configuration</h4>
                            <p class="text-muted">Global settings will be placed here in future updates.</p>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
