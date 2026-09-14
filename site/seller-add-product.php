<?php
session_start();
$page_title = 'Add Product - OXXA GEAR Seller';
include('../include/header.php');
include('../include/connection.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}
session_write_close(); // Free session lock for parallel AJAX requests

$stmt = $pdo->prepare("SELECT * FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['userid']]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business || $business['is_approved'] == 0) {
    header('Location: business-registration.php');
    exit();
}

// Fetch categories
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active brands
$brandStmt = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC");
$brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="flex items-center gap-4 mb-8">
            <a href="seller-dashboard.php" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-navy hover:bg-gray-50 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-navy uppercase tracking-wide">Add New Product</h1>
                <p class="text-sm text-slate">List a new item in your store</p>
            </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 flex items-center">
                <i class="fas fa-exclamation-circle me-3"></i>
                <span class="font-bold">Error: <?= htmlspecialchars($_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <form action="../Backend/seller-product-backend.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <!-- Basic Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-box text-[#0066FF] me-2"></i> Product Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Product Name *</label>
                        <input type="text" name="name" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Brand</label>
                        <select name="brand_id" class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                            <option value="">No Brand</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Category *</label>
                        <select name="category_id" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                            <option value="">Select Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Description *</label>
                        <textarea name="description" rows="4" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all"></textarea>
                    </div>
                </div>
            </div>

            <!-- Financials & 10% Rule -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-green-50 rounded-full opacity-50 pointer-events-none"></div>
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-coins text-green-500 me-2"></i> Pricing & Profit</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Inputs -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">
                                Cost Price (Rs.) * 
                                <span class="text-xs font-normal text-slate float-right mt-0.5"><i class="fas fa-eye-slash me-1"></i> Hidden from buyers</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                                <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all font-bold">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">
                                Selling Price (Rs.) *
                                <span class="text-xs font-normal text-slate float-right mt-0.5"><i class="fas fa-eye me-1"></i> Visible to buyers</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                                <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all font-bold">
                            </div>
                        </div>
                    </div>

                    <!-- Calculator UI -->
                    <div class="bg-navy rounded-xl p-6 text-white shadow-inner flex flex-col justify-center">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Financial Breakdown</h3>
                        
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm text-gray-300">Selling Price</span>
                            <span class="font-bold" id="calc_selling">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-700">
                            <span class="text-sm text-gray-300">- Cost Price</span>
                            <span class="font-bold text-red-400" id="calc_cost">Rs. 0.00</span>
                        </div>
                        
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-bold text-[#0066FF]">Gross Profit</span>
                            <span class="font-bold text-[#0066FF]" id="calc_profit">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-700">
                            <span class="text-sm text-gray-300">- Admin Commission (10%)</span>
                            <span class="font-bold text-yellow-400" id="calc_commission">Rs. 0.00</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-base font-black uppercase tracking-wide text-green-400">Your Net Earning</span>
                            <span class="text-xl font-black text-green-400" id="calc_earning">Rs. 0.00</span>
                        </div>
                        <p class="text-[10px] text-gray-500 text-right mt-1">Per item sold</p>
                    </div>
                </div>
            </div>

            <!-- Images & Variants -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-images text-purple-500 me-2"></i> Media & Inventory</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Images -->
                    <div>
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Product Images *</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-[#0066FF] transition-colors relative">
                            <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                            <p class="text-sm text-slate">Select up to 4 images (First is primary)</p>
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG (Max 2MB each)</p>
                        </div>
                    </div>
                    
                    <!-- Variants (Sizes & Qty) -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-bold text-navy uppercase tracking-wide">Sizes & Inventory</label>
                            <button type="button" id="addVariantBtn" class="text-xs font-bold text-[#0066FF] hover:underline uppercase"><i class="fas fa-plus me-1"></i> Add Size</button>
                        </div>
                        
                        <div id="variantsContainer" class="space-y-3">
                            <div class="flex gap-3 variant-row">
                                <input type="text" name="sizes[]" placeholder="Size (e.g. M, L, 42)" required class="w-1/2 bg-gray-50 border border-gray-200 text-navy rounded-lg py-2 px-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all text-sm">
                                <input type="number" name="qtys[]" placeholder="Qty" min="0" required class="w-1/3 bg-gray-50 border border-gray-200 text-navy rounded-lg py-2 px-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all text-sm">
                                <button type="button" class="w-1/6 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-100 transition-colors remove-variant" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 flex items-start">
                <i class="fas fa-info-circle text-yellow-600 mt-0.5 me-3"></i>
                <p class="text-sm text-yellow-800">
                    <strong>Note:</strong> Once submitted, your product will be marked as <span class="font-bold uppercase tracking-widest text-[10px] bg-yellow-200 px-2 py-0.5 rounded ml-1">Pending Approval</span>. It will go live on the OXXA GEAR store immediately after admin verification.
                </p>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" name="add_product" class="bg-[#0066FF] hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex items-center text-lg">
                    Submit for Approval <i class="fas fa-paper-plane ms-2"></i>
                </button>
            </div>

        </form>
    </div>
</div>

<script>
// Financial Calculator Logic
const costInput = document.getElementById('cost_price');
const sellInput = document.getElementById('selling_price');

const vSell = document.getElementById('calc_selling');
const vCost = document.getElementById('calc_cost');
const vProfit = document.getElementById('calc_profit');
const vComm = document.getElementById('calc_commission');
const vEarn = document.getElementById('calc_earning');

function updateFinancials() {
    let cost = parseFloat(costInput.value) || 0;
    let sell = parseFloat(sellInput.value) || 0;
    
    let profit = sell - cost;
    if(profit < 0) profit = 0; // Prevent negative profit calculations in UI
    
    let commission = profit * 0.10;
    let earning = cost + (profit * 0.90);
    
    vSell.textContent = 'Rs. ' + sell.toFixed(2);
    vCost.textContent = 'Rs. ' + cost.toFixed(2);
    vProfit.textContent = 'Rs. ' + profit.toFixed(2);
    vComm.textContent = 'Rs. ' + commission.toFixed(2);
    vEarn.textContent = 'Rs. ' + earning.toFixed(2);
}

costInput.addEventListener('input', updateFinancials);
sellInput.addEventListener('input', updateFinancials);

// Dynamic Variants Logic
const variantsContainer = document.getElementById('variantsContainer');
const addVariantBtn = document.getElementById('addVariantBtn');

addVariantBtn.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'flex gap-3 variant-row';
    row.innerHTML = `
        <input type="text" name="sizes[]" placeholder="Size" required class="w-1/2 bg-gray-50 border border-gray-200 text-navy rounded-lg py-2 px-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all text-sm">
        <input type="number" name="qtys[]" placeholder="Qty" min="0" required class="w-1/3 bg-gray-50 border border-gray-200 text-navy rounded-lg py-2 px-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all text-sm">
        <button type="button" class="w-1/6 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-100 transition-colors remove-variant">
            <i class="fas fa-trash"></i>
        </button>
    `;
    variantsContainer.appendChild(row);
    updateRemoveButtons();
});

variantsContainer.addEventListener('click', (e) => {
    if (e.target.closest('.remove-variant')) {
        const row = e.target.closest('.variant-row');
        if (variantsContainer.children.length > 1) {
            row.remove();
            updateRemoveButtons();
        }
    }
});

function updateRemoveButtons() {
    const btns = document.querySelectorAll('.remove-variant');
    if (btns.length === 1) {
        btns[0].disabled = true;
        btns[0].classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        btns.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        });
    }
}
</script>

<?php include('../include/footer.php'); ?>
