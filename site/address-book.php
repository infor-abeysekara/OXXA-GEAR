<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

if (!isset($_SESSION['userid'])) {
    header("Location: index.php?open=login");
    exit();
}
session_write_close(); // Free session lock for parallel AJAX requests

$userid = $_SESSION['userid'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userid]);
$user = $stmt->fetch();

// Fetch addresses
$addrStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default_shipping DESC, id DESC");
$addrStmt->execute([$userid]);
$addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

$provinces = getSriLankanProvinces(); // Assuming this is defined in functions.php

$page_title = 'Address Book - OXXA GEAR';
include("../include/header.php");
?>

<div class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-6 flex items-center text-sm font-medium text-slate">
            <a href="index.php" class="hover:text-primary transition-colors">Home</a>
            <i class="fas fa-chevron-right mx-2 text-[10px]"></i>
            <span class="text-navy">Address Book</span>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <div class="w-full lg:w-[260px] flex-shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <div class="p-6 text-center border-b border-gray-50 flex flex-col items-center">
                        <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../assets/uploads/profiles/' . $user['profile_image'])): ?>
                            <img src="../assets/uploads/profiles/<?php echo $user['profile_image']; ?>" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-xl mb-4">
                        <?php else: ?>
                            <div class="w-24 h-24 rounded-full bg-black text-white flex items-center justify-center text-3xl font-black ring-4 ring-white shadow-xl mb-4">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="font-black text-navy text-lg uppercase tracking-wide"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    
                    <div class="p-3 flex flex-col gap-1">
                        <a href="profile.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="far fa-user-circle w-6 text-lg"></i> My Profile
                        </a>
                        <a href="address-book.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm bg-blue-50 text-[#0066FF] transition-colors">
                            <i class="far fa-address-book w-6 text-lg"></i> Address Book
                        </a>
                        <a href="my-orders.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="fas fa-shopping-bag w-6 text-lg"></i> My Orders
                        </a>
                        <a href="wishlist.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="far fa-heart w-6 text-lg"></i> Wishlist
                        </a>
                        <a href="reviews.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="far fa-star w-6 text-lg"></i> My Reviews
                        </a>
                        <a href="returns.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="fas fa-undo-alt w-6 text-lg"></i> My Returns
                        </a>
                        <a href="coupons.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="fas fa-ticket-alt w-6 text-lg"></i> My Coupons
                        </a>
                        <a href="recently-viewed.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors border-b border-gray-100 pb-4 mb-1">
                            <i class="far fa-eye w-6 text-lg"></i> Recently Viewed
                        </a>
                        <?php if($user['user_type'] == 'seller'): ?>
                        <a href="seller-dashboard.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors mt-2 border-t border-gray-100 pt-3">
                            <i class="fas fa-store w-6 text-lg"></i> Seller Dashboard
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Main Content -->
            <div class="flex-1">
                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden p-8 md:p-10 min-h-[400px]">
                    
                    <?php if (empty($addresses)): ?>
                        <!-- Empty State -->
                        <div class="text-center flex flex-col items-center justify-center h-full">
                            <div class="w-24 h-24 bg-blue-50 rounded-full flex items-center justify-center mb-6">
                                <i class="far fa-address-book text-[#0066FF] text-4xl"></i>
                            </div>
                            <h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-2">Address Book</h2>
                            <p class="text-gray-500 mb-8 max-w-md mx-auto">Manage your delivery and billing addresses for a faster checkout experience.</p>
                            <button onclick="openAddressModal()" class="px-8 py-3 bg-[#0066FF] text-white font-bold rounded-full uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">
                                <i class="fas fa-plus me-2"></i> Add New Address
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Address List -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-6">
                            <h2 class="text-2xl font-black text-navy uppercase tracking-wide">Address Book</h2>
                            <button onclick="openAddressModal()" class="px-6 py-2 bg-[#0066FF] text-white font-bold rounded-full uppercase text-sm tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30 flex items-center gap-2">
                                <i class="fas fa-plus"></i> Add New
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="addressListContainer">
                            <?php foreach ($addresses as $address): ?>
                                <div class="border <?php echo $address['is_default_shipping'] ? 'border-[#0066FF] bg-blue-50/30 shadow-sm' : 'border-gray-200'; ?> rounded-2xl p-6 relative flex flex-col">
                                    <?php if ($address['is_default_shipping']): ?>
                                        <div class="absolute top-0 right-0 bg-[#0066FF] text-white text-xs font-bold px-3 py-1 rounded-bl-xl rounded-tr-2xl uppercase tracking-wider">
                                            Default
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                                            <?php if ($address['label'] == 'Home'): ?>
                                                <i class="fas fa-home"></i>
                                            <?php elseif ($address['label'] == 'Office'): ?>
                                                <i class="fas fa-building"></i>
                                            <?php else: ?>
                                                <i class="fas fa-map-marker-alt"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-navy text-lg leading-tight"><?php echo htmlspecialchars($address['full_name']); ?></h4>
                                            <span class="text-xs font-bold text-gray-400 uppercase"><?php echo htmlspecialchars($address['label']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="text-slate text-sm space-y-1 mb-6 flex-grow">
                                        <p><i class="fas fa-phone-alt w-5 text-gray-400"></i> <?php echo htmlspecialchars($address['phone1']); ?></p>
                                        <p class="flex items-start">
                                            <i class="fas fa-map-marker-alt w-5 mt-1 text-gray-400 shrink-0"></i>
                                            <span>
                                                <?php echo htmlspecialchars($address['address_line1']); ?>
                                                <?php if(!empty($address['address_line2'])) echo '<br>' . htmlspecialchars($address['address_line2']); ?>
                                                <br><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['postal_code']); ?>
                                                <br><?php echo htmlspecialchars($address['province']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 pt-4 border-t border-gray-100 mt-auto">
                                        <button onclick='editAddress(<?php echo json_encode($address); ?>)' class="flex-1 py-2 text-center text-sm font-bold text-gray-600 hover:bg-gray-100 rounded-lg transition-colors border border-gray-200">
                                            Edit
                                        </button>
                                        <button onclick="deleteAddress(<?php echo $address['id']; ?>)" class="w-10 h-10 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-lg transition-colors border border-gray-200">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <?php if (!$address['is_default_shipping']): ?>
                                            <button onclick="setDefaultAddress(<?php echo $address['id']; ?>)" class="flex-1 py-2 text-center text-sm font-bold text-[#0066FF] hover:bg-blue-50 rounded-lg transition-colors border border-[#0066FF]/30">
                                                Set Default
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Address Modal -->
<div id="addressModal" class="fixed inset-0 z-[100] hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-navy/60 backdrop-blur-sm transition-opacity opacity-0" id="addressModalBackdrop" onclick="closeAddressModal()"></div>
    
    <!-- Modal Content -->
    <div class="flex items-center justify-center min-h-screen px-4 py-8 pointer-events-none">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl transform scale-95 opacity-0 transition-all pointer-events-auto relative max-h-[90vh] flex flex-col" id="addressModalContent">
            
            <div class="p-6 border-b border-gray-100 flex justify-between items-center shrink-0">
                <h3 class="text-xl font-black text-navy uppercase tracking-wide" id="addressModalTitle">Add New Address</h3>
                <button onclick="closeAddressModal()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto custom-scrollbar">
                <form id="addressForm" class="space-y-5">
                    <input type="hidden" name="action" id="addressAction" value="add">
                    <input type="hidden" name="id" id="addressId" value="">
                    
                    <!-- Label Selection -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Address Label</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="label" value="Home" class="peer hidden" checked>
                                <div class="text-center py-3 border-2 border-gray-200 rounded-xl peer-checked:border-[#0066FF] peer-checked:bg-blue-50 peer-checked:text-[#0066FF] text-gray-500 font-bold transition-colors">
                                    <i class="fas fa-home block text-xl mb-1"></i>
                                    Home
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="label" value="Office" class="peer hidden">
                                <div class="text-center py-3 border-2 border-gray-200 rounded-xl peer-checked:border-[#0066FF] peer-checked:bg-blue-50 peer-checked:text-[#0066FF] text-gray-500 font-bold transition-colors">
                                    <i class="fas fa-building block text-xl mb-1"></i>
                                    Office
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="label" value="Other" class="peer hidden">
                                <div class="text-center py-3 border-2 border-gray-200 rounded-xl peer-checked:border-[#0066FF] peer-checked:bg-blue-50 peer-checked:text-[#0066FF] text-gray-500 font-bold transition-colors">
                                    <i class="fas fa-map-marker-alt block text-xl mb-1"></i>
                                    Other
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Full Name <span class="text-[#0066FF]">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-user"></i></span>
                                <input type="text" name="full_name" id="full_name" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Phone Number <span class="text-[#0066FF]">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-phone-alt"></i></span>
                                <input type="text" name="phone" id="phone" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" required>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Address Line 1 <span class="text-[#0066FF]">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-map-pin"></i></span>
                            <input type="text" name="address_line1" id="address_line1" placeholder="Street address, P.O. box, etc." class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Address Line 2 (Optional)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-map-pin text-gray-300"></i></span>
                            <input type="text" name="address_line2" id="address_line2" placeholder="Apartment, suite, unit, building, floor, etc." class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">City <span class="text-[#0066FF]">*</span></label>
                            <input type="text" name="city" id="city" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Postal Code</label>
                            <input type="text" name="postal_code" id="postal_code" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Province <span class="text-[#0066FF]">*</span></label>
                            <div class="relative">
                                <select name="province" id="province" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors appearance-none" required>
                                    <option value="" disabled selected>Select Province</option>
                                    <?php foreach($provinces as $prov): ?>
                                        <option value="<?php echo htmlspecialchars($prov); ?>"><?php echo htmlspecialchars($prov); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-2">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" name="is_default" id="is_default" value="1" class="peer appearance-none w-6 h-6 border-2 border-gray-300 rounded-md checked:bg-[#0066FF] checked:border-[#0066FF] transition-colors cursor-pointer">
                                <i class="fas fa-check absolute text-white text-sm opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-700 group-hover:text-navy transition-colors">Set as default shipping address</span>
                        </label>
                    </div>
                </form>
            </div>
            
            <div class="p-6 border-t border-gray-100 bg-gray-50 rounded-b-3xl flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeAddressModal()" class="px-6 py-3 bg-white border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 hover:text-navy transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="submitAddressForm()" class="px-8 py-3 bg-[#0066FF] text-white font-bold rounded-xl uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-md shadow-blue-500/20 flex items-center">
                    <span id="submitBtnText">Save Address</span>
                    <i class="fas fa-circle-notch fa-spin hidden ml-2" id="submitLoader"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const modal = document.getElementById('addressModal');
const modalBackdrop = document.getElementById('addressModalBackdrop');
const modalContent = document.getElementById('addressModalContent');
const addressForm = document.getElementById('addressForm');

function openAddressModal(mode = 'add', data = null) {
    modal.classList.remove('hidden');
    
    // reset form
    addressForm.reset();
    document.getElementById('addressId').value = '';
    document.getElementById('addressAction').value = 'add';
    document.getElementById('addressModalTitle').textContent = 'Add New Address';
    document.getElementById('submitBtnText').textContent = 'Save Address';
    if (mode === 'add') {
        document.getElementById('full_name').value = '<?php echo addslashes($user['first_name'] . ' ' . $user['last_name']); ?>';
        document.getElementById('phone').value = '<?php echo addslashes($user['phone'] ?? ''); ?>';
    }
    
    if (mode === 'edit' && data) {
        document.getElementById('addressAction').value = 'update';
        document.getElementById('addressId').value = data.id;
        document.getElementById('addressModalTitle').textContent = 'Edit Address';
        document.getElementById('submitBtnText').textContent = 'Update Address';
        
        document.getElementById('full_name').value = data.full_name;
        document.getElementById('phone').value = data.phone1;
        document.getElementById('address_line1').value = data.address_line1;
        document.getElementById('address_line2').value = data.address_line2 || '';
        document.getElementById('city').value = data.city;
        document.getElementById('postal_code').value = data.postal_code || '';
        document.getElementById('province').value = data.province;
        
        // Select label
        const labels = document.getElementsByName('label');
        for (let i = 0; i < labels.length; i++) {
            if (labels[i].value === data.label) {
                labels[i].checked = true;
            }
        }
        
        if (data.is_default_shipping == 1) {
            document.getElementById('is_default').checked = true;
        }
    }
    
    // Animate in
    setTimeout(() => {
        modalBackdrop.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeAddressModal() {
    // Animate out
    modalBackdrop.classList.add('opacity-0');
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function editAddress(data) {
    openAddressModal('edit', data);
}

function submitAddressForm() {
    if (!addressForm.checkValidity()) {
        addressForm.reportValidity();
        return;
    }
    
    const submitBtn = addressForm.querySelector('button[onclick="submitAddressForm()"]');
    if(submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('opacity-50', 'cursor-not-allowed'); }
    
    const submitLoader = document.getElementById('submitLoader');
    submitLoader.classList.remove('hidden');
    document.getElementById('submitBtnText').textContent = 'Processing...';
    
    const formData = new FormData(addressForm);
    
    fetch('../Backend/address-backend.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitLoader.classList.add('hidden');
        if(submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-50', 'cursor-not-allowed'); }
        
        if (data.success) {
            closeAddressModal();
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            // Update the address list without full reload
            fetch(window.location.href)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.getElementById('addressListContainer');
                    if (newContainer) {
                        document.getElementById('addressListContainer').innerHTML = newContainer.innerHTML;
                    } else {
                        window.location.reload(); // Fallback if no addresses
                    }
                });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#0066FF'
            });
        }
    })
    .catch(error => {
        submitLoader.classList.add('hidden');
        if(submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-50', 'cursor-not-allowed'); }
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An unexpected error occurred',
            confirmButtonColor: '#0066FF'
        });
    });
}

function deleteAddress(id) {
    Swal.fire({
        title: 'Delete Address?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            fetch('../Backend/address-backend.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    // Update list dynamically
                    fetch(window.location.href)
                        .then(res => res.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newContainer = doc.getElementById('addressListContainer');
                            if (newContainer && document.getElementById('addressListContainer')) {
                                document.getElementById('addressListContainer').innerHTML = newContainer.innerHTML;
                            } else {
                                window.location.reload();
                            }
                        });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}

function setDefaultAddress(id) {
    const formData = new FormData();
    formData.append('action', 'set_default');
    formData.append('id', id);
    
    fetch('../Backend/address-backend.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            // Update list dynamically
            fetch(window.location.href)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.getElementById('addressListContainer');
                    if (newContainer && document.getElementById('addressListContainer')) {
                        document.getElementById('addressListContainer').innerHTML = newContainer.innerHTML;
                    } else {
                        window.location.reload();
                    }
                });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    });
}
</script>

<?php include("../include/footer.php"); ?>
