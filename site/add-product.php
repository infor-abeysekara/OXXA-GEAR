<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}

// Check if business is approved
$business = getBusinessRegistration($conn, $_SESSION['userid']);
$canSell = $business && $business['approve'] == 1;

if (!$canSell) {
    header('Location: business-registration.php');
    exit();
}

$page_title = 'Add Product - OXXA GEAR';
include('../include/header.php');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card border-0 shadow-lg">
                <div class="card-header text-white text-center" style="background-color: #188754;">
                    <h4 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Add New Product
                    </h4>
                </div>
                <div class="card-body p-5">
                    <?php
                    // Display error messages
                    if(isset($_GET['error'])) {
                        $error = $_GET['error'];
                        $error_message = '';
                        switch($error) {
                            case 'pname': $error_message = 'Product name is required'; break;
                            case 'brand': $error_message = 'Brand is required'; break;
                            case 'category': $error_message = 'Category is required'; break;
                            case 'description': $error_message = 'Description is required'; break;
                            case 'image': $error_message = 'Product image is required'; break;
                            case 'sizes': $error_message = 'At least one size with price and quantity is required'; break;
                            case 'format': $error_message = 'Invalid image format. Use JPG, JPEG, PNG, GIF, or WEBP'; break;
                            case 'large': $error_message = 'Image size too large. Maximum 1MB allowed'; break;
                            case 'upload': $error_message = 'Failed to upload image'; break;
                            case 'database': $error_message = 'Database error occurred'; break;
                            default: $error_message = 'An error occurred';
                        }
                        echo '<div class="alert alert-danger">' . $error_message . '</div>';
                    }

                    if(isset($_GET['success'])) {
                        echo '<div class="alert alert-success">Product added successfully!</div>';
                    }
                    ?>

                    <form action="../Backend/add-product-backend.php" method="POST" enctype="multipart/form-data" id="productForm">
                        <!-- Product Image -->
                        <div class="mb-4 text-center">
                            <label class="form-label fw-semibold">Product Image *</label>
                            <div class="d-flex justify-content-center mb-3">
                                <div class="position-relative">
                                    <img id="imagePreview" src="https://via.placeholder.com/200x200/6c757d/ffffff?text=Product+Image" 
                                         class="rounded border border-gray-300" 
                                         style="width: 200px; height: 200px; object-fit: cover;">
                                    <label for="image" class="position-absolute bottom-0 end-0 text-white rounded-circle p-2" 
                                           style="cursor: pointer; background-color: #188754;">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                </div>
                            </div>
                            <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" required style="display: none;">
                            <small class="text-muted">JPG, JPEG, PNG, GIF, WEBP (Max 1MB)</small>
                            <div id="imageError" class="text-danger mt-2" style="display: none;"></div>
                        </div>

                        <div class="row">
                            <!-- Product Name -->
                            <div class="col-md-6 mb-3">
                                <label for="pname" class="form-label fw-semibold">Product Name *</label>
                                <input type="text" class="form-control" id="pname" name="pname" required>
                                <div id="pnameError" class="text-danger mt-1" style="display: none;"></div>
                            </div>

                            <!-- Brand -->
                            <div class="col-md-6 mb-3">
                                <label for="brand" class="form-label fw-semibold">Brand *</label>
                                <input type="text" class="form-control" id="brand" name="brand" required>
                                <div id="brandError" class="text-danger mt-1" style="display: none;"></div>
                            </div>

                            <!-- Category -->
                            <div class="col-md-12 mb-3">
                                <label for="categories" class="form-label fw-semibold">Category *</label>
                                <select class="form-select" id="categories" name="categories" required>
                                    <option value="">Select Category</option>
                                    <option value="Protein Powder">Protein Powder</option>
                                    <option value="Vitamins">Vitamins</option>
                                    <option value="Pre-Workout">Pre-Workout</option>
                                    <option value="Post-Workout">Post-Workout</option>
                                    <option value="Weight Loss">Weight Loss</option>
                                    <option value="Mass Gainer">Mass Gainer</option>
                                    <option value="Creatine">Creatine</option>
                                    <option value="BCAA">BCAA</option>
                                    <option value="Amino Acids">Amino Acids</option>
                                    <option value="Fat Burners">Fat Burners</option>
                                    <option value="Recovery">Recovery</option>
                                    <option value="Supplements">Supplements</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div id="categoriesError" class="text-danger mt-1" style="display: none;"></div>
                            </div>

                            <!-- Description -->
                            <div class="col-md-12 mb-4">
                                <label for="discription" class="form-label fw-semibold">Description *</label>
                                <textarea class="form-control" id="discription" name="discription" rows="4" required placeholder="Describe your product features, benefits, and usage instructions..."></textarea>
                                <div id="discriptionError" class="text-danger mt-1" style="display: none;"></div>
                            </div>
                        </div>

                        <!-- Main Product Price and Quantity (for default size) -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="price" class="form-label fw-semibold">Base Price (LKR) *</label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                                <small class="text-muted">This will be the default price for standard size</small>
                                <div id="priceError" class="text-danger mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="qty" class="form-label fw-semibold">Base Quantity *</label>
                                <input type="number" class="form-control" id="qty" name="qty" min="0" required>
                                <small class="text-muted">Stock quantity for standard size</small>
                                <div id="qtyError" class="text-danger mt-1" style="display: none;"></div>
                            </div>
                        </div>

                        <!-- Additional Product Sizes and Pricing -->
                        <div class="mb-4">
                            <h6 class="fw-semibold mb-3">Additional Product Sizes (Optional)</h6>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Add different sizes for your product with their respective prices and quantities. The base price above will be used for the standard size.
                            </div>
                            
                            <div id="sizesContainer">
                                <!-- Additional sizes will be added here -->
                            </div>

                            <button type="button" id="addSize" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>Add Additional Size
                            </button>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="seller-dashboard.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" name="add_product" class="btn btn-lg" style="background-color: #188754; color: white;">
                                <i class="fas fa-plus-circle me-2"></i>Add Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('productForm');
    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('imagePreview');
    
    // Image validation and preview
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const imageError = document.getElementById('imageError');
        
        if (file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                imageError.textContent = 'Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.';
                imageError.style.display = 'block';
                this.value = '';
                return;
            }
            
            // Validate file size (1MB = 1048576 bytes)
            if (file.size > 1048576) {
                imageError.textContent = 'File size too large. Maximum 1MB allowed.';
                imageError.style.display = 'block';
                this.value = '';
                return;
            }
            
            // Clear error and show preview
            imageError.style.display = 'none';
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('.text-danger').forEach(el => el.style.display = 'none');
        
        // Validate product name
        const pname = document.getElementById('pname').value.trim();
        if (!pname) {
            document.getElementById('pnameError').textContent = 'Product name is required.';
            document.getElementById('pnameError').style.display = 'block';
            isValid = false;
        }
        
        // Validate brand
        const brand = document.getElementById('brand').value.trim();
        if (!brand) {
            document.getElementById('brandError').textContent = 'Brand is required.';
            document.getElementById('brandError').style.display = 'block';
            isValid = false;
        }
        
        // Validate category
        const category = document.getElementById('categories').value;
        if (!category) {
            document.getElementById('categoriesError').textContent = 'Please select a category.';
            document.getElementById('categoriesError').style.display = 'block';
            isValid = false;
        }
        
        // Validate description
        const description = document.getElementById('discription').value.trim();
        if (!description) {
            document.getElementById('discriptionError').textContent = 'Description is required.';
            document.getElementById('discriptionError').style.display = 'block';
            isValid = false;
        }
        
        // Validate price
        const price = document.getElementById('price').value;
        if (!price || parseFloat(price) <= 0) {
            document.getElementById('priceError').textContent = 'Valid price is required.';
            document.getElementById('priceError').style.display = 'block';
            isValid = false;
        }
        
        // Validate quantity
        const qty = document.getElementById('qty').value;
        if (!qty || parseInt(qty) < 0) {
            document.getElementById('qtyError').textContent = 'Valid quantity is required.';
            document.getElementById('qtyError').style.display = 'block';
            isValid = false;
        }
        
        // Validate image
        const image = document.getElementById('image').files[0];
        if (!image) {
            document.getElementById('imageError').textContent = 'Product image is required.';
            document.getElementById('imageError').style.display = 'block';
            isValid = false;
        }
        
        // Validate additional sizes
        const additionalSizes = document.querySelectorAll('.size-select');
        const additionalPrices = document.querySelectorAll('.price-input');
        const additionalQuantities = document.querySelectorAll('.quantity-input');
        
        // Check for duplicate sizes in additional sizes
        const selectedSizes = ['Standard']; // Standard is always included
        for (let i = 0; i < additionalSizes.length; i++) {
            if (additionalSizes[i].value) {
                if (selectedSizes.includes(additionalSizes[i].value)) {
                    alert('Duplicate sizes are not allowed. Please select different sizes.');
                    isValid = false;
                    break;
                }
                selectedSizes.push(additionalSizes[i].value);
            }
        }
        
        // Validate that if a size is selected, price and quantity are also provided
        for (let i = 0; i < additionalSizes.length; i++) {
            const hasSize = additionalSizes[i].value;
            const hasPrice = additionalPrices[i].value;
            const hasQuantity = additionalQuantities[i].value;
            
            if (hasSize && (!hasPrice || !hasQuantity)) {
                alert('Please provide both price and quantity for all selected sizes.');
                isValid = false;
                break;
            }
            
            if (!hasSize && (hasPrice || hasQuantity)) {
                alert('Please select a size for all entries with price and quantity.');
                isValid = false;
                break;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});

// Size management
let sizeCount = 0;

document.getElementById('addSize').addEventListener('click', function() {
    sizeCount++;
    const sizesContainer = document.getElementById('sizesContainer');
    const newSizeRow = document.createElement('div');
    newSizeRow.className = 'size-row border rounded p-3 mb-3';
    newSizeRow.style.backgroundColor = '#f8f9fa';
    
    newSizeRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Size</label>
                <select class="form-select size-select" name="additional_sizes[]" required>
                    <option value="">Select Size</option>
                    <option value="2LBS">2 LBS</option>
                    <option value="4LBS">4 LBS</option>
                    <option value="6LBS">6 LBS</option>
                    <option value="8LBS">8 LBS</option>
                    <option value="0.5KG">0.5 KG</option>
                    <option value="0.8KG">0.8 KG</option>
                    <option value="1KG">1 KG</option>
                    <option value="2KG">2 KG</option>
                    <option value="5KG">5 KG</option>
                    <option value="Small">Small</option>
                    <option value="Medium">Medium</option>
                    <option value="Large">Large</option>
                    <option value="XL">XL</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Price (Rs.)</label>
                <input type="number" class="form-control price-input" name="additional_prices[]" 
                       min="0" step="0.01" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Quantity</label>
                <input type="number" class="form-control quantity-input" name="additional_quantities[]" 
                       min="0" required>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-outline-danger remove-size">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
        </div>
    `;
    
    sizesContainer.appendChild(newSizeRow);
});

// Remove size functionality
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-size') || e.target.closest('.remove-size')) {
        const sizeRow = e.target.closest('.size-row');
        sizeRow.remove();
        sizeCount--;
    }
});
</script>

<?php include("../include/footer.php"); ?>