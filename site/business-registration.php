<?php
session_start();
$page_title = 'Business Profile - OXXA GEAR';
include('../include/header.php');
include('../include/connection.php');
include('../include/functions.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}
session_write_close(); // Free session lock for parallel AJAX requests

$provinces = getSriLankanProvinces(); // Assume this exists in functions.php

// Check if business profile exists
$stmt = $pdo->prepare("SELECT * FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['userid']]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<div class="bg-gray-50 min-h-[90vh] py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-black text-navy uppercase tracking-wide">Business Verification</h1>
            <p class="text-slate mt-1">Complete your business verification to start selling.</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-8 flex items-start">
                <i class="fas fa-check-circle mt-1 me-3 text-xl"></i>
                <div>
                    <h4 class="font-bold">Application Submitted Successfully!</h4>
                    <p class="text-sm mt-1">Your business registration has been received and is currently under review by our team. You will be notified once approved.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 flex items-center">
                <i class="fas fa-exclamation-circle me-3 text-xl"></i>
                <span class="font-bold flex-1">
                    Error: <?php echo htmlspecialchars($_GET['error'] ?? 'Please check your form and try again.'); ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($business): ?>
            <!-- Business Registration Status -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-8">
                    <?php if ($business['is_approved'] == 1): ?>
                        <!-- Top Success Header -->
                        <div class="mb-10 text-center relative z-10 py-6">
                            <div class="inline-flex flex-col items-center">
                                <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-4 relative before:absolute before:inset-0 before:bg-green-100 before:rounded-full before:animate-ping before:-z-10 shadow-sm border border-green-200">
                                    <i class="fas fa-check text-4xl text-green-600"></i>
                                </div>
                                <h2 class="text-3xl font-black text-navy uppercase tracking-wide">Business Verified</h2>
                                <p class="text-slate mt-2 text-sm font-medium">Your business account is fully active. You can now start selling.</p>
                                <div class="mt-4 inline-flex items-center gap-3">
                                    <span class="bg-blue-50 text-[#0066FF] px-3 py-1 rounded-full text-xs font-bold shadow-sm border border-blue-100">
                                        <i class="fas fa-shield-alt me-1"></i> Verified by OXXA Team
                                    </span>
                                    <span class="text-gray-400 text-xs font-bold">
                                        Verified Date: <?= date('d M Y', strtotime($business['approved_at'] ?? $business['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Stepper -->
                            <div class="mt-12 flex justify-center items-center max-w-lg mx-auto mb-4">
                                <div class="flex items-center w-full">
                                    <div class="flex flex-col items-center relative z-10">
                                        <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center font-bold text-sm shadow-md border-2 border-white"><i class="fas fa-check"></i></div>
                                        <div class="text-[10px] font-bold text-slate mt-2 uppercase tracking-wide absolute top-10 whitespace-nowrap">1. Submit BR</div>
                                    </div>
                                    <div class="flex-1 h-1 bg-green-500 rounded mx-2 relative z-0"></div>
                                    <div class="flex flex-col items-center relative z-10">
                                        <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center font-bold text-sm shadow-md border-2 border-white"><i class="fas fa-check"></i></div>
                                        <div class="text-[10px] font-bold text-slate mt-2 uppercase tracking-wide absolute top-10 whitespace-nowrap">2. Review</div>
                                    </div>
                                    <div class="flex-1 h-1 bg-green-500 rounded mx-2 relative z-0"></div>
                                    <div class="flex flex-col items-center relative z-10">
                                        <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center font-bold text-sm shadow-md border-2 border-white"><i class="fas fa-check"></i></div>
                                        <div class="text-[10px] font-bold text-green-600 mt-2 uppercase tracking-wide absolute top-10 whitespace-nowrap">3. Verified</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2-Column Layout -->
                        <div class="flex flex-col lg:flex-row gap-6 mt-8 text-left">
                            
                            <!-- Left Column: Business Profile -->
                            <div id="viewProfileContainer" class="lg:w-[60%] bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-gray-100 relative transition-all duration-300">
                                <div class="flex justify-between items-center mb-6 border-b border-gray-50 pb-4">
                                    <h3 class="font-black text-navy text-xl uppercase tracking-wide">Business Profile</h3>
                                    <button onclick="toggleEditMode(true)" class="text-[#0066FF] hover:bg-blue-50 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors flex items-center gap-2">
                                        <i class="fas fa-pencil-alt"></i> Edit Profile
                                    </button>
                                </div>
                                
                                <!-- Logo and Name -->
                                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-8 bg-gray-50 p-5 rounded-xl border border-gray-100">
                                    <form action="../Backend/update-business-logo.php" method="POST" enctype="multipart/form-data" class="relative group cursor-pointer shrink-0" onclick="document.getElementById('biz_logo_verified').click()">
                                        <?php 
                                        $logo_exists = !empty($business['logo_path']) && file_exists('../assets/uploads/' . $business['logo_path']);
                                        if ($logo_exists): 
                                        ?>
                                            <img src="../assets/uploads/<?= htmlspecialchars($business['logo_path']) ?>" class="w-20 h-20 rounded-full object-cover ring-4 ring-white shadow-sm group-hover:opacity-80 transition-opacity">
                                        <?php else: ?>
                                            <div class="w-20 h-20 rounded-full bg-[#0066FF] text-white flex items-center justify-center text-3xl font-black shadow-sm ring-4 ring-white group-hover:opacity-80 transition-opacity">
                                                <?= strtoupper(substr($business['business_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute inset-0 bg-black/40 rounded-full flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="text-white text-[9px] font-bold uppercase tracking-widest text-center leading-tight mt-1">Change<br>Logo</span>
                                        </div>
                                        <div class="absolute bottom-0 right-0 w-7 h-7 bg-navy text-white rounded-full flex items-center justify-center shadow-md border-2 border-white z-10 transition-transform group-hover:scale-110">
                                            <i class="fas fa-camera text-[10px]"></i>
                                        </div>
                                        <input type="file" id="biz_logo_verified" name="logo" accept="image/*" class="hidden" onchange="this.form.submit()">
                                    </form>
                                    <div class="text-center sm:text-left mt-2 sm:mt-0">
                                        <h4 class="text-xl md:text-2xl font-black text-navy leading-tight"><?= htmlspecialchars($business['business_name']) ?></h4>
                                        <p class="text-sm text-gray-500 font-bold mt-1"><?= htmlspecialchars($business['business_type']) ?> &bull; <?= htmlspecialchars($business['nature_of_business']) ?></p>
                                    </div>
                                </div>
                                
                                <!-- Details Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6">
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">BR Number</p>
                                        <p class="text-navy font-semibold flex items-center justify-between gap-2">
                                            <span><?= htmlspecialchars($business['business_reg_id']) ?></span>
                                            <a href="../assets/uploads/<?= htmlspecialchars($business['certificate_path']) ?>" target="_blank" class="text-[#0066FF] hover:underline text-[10px] uppercase font-bold bg-blue-50 px-2 py-0.5 rounded">View Cert</a>
                                        </p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Date of Incorporation</p>
                                        <p class="text-navy font-semibold"><?= htmlspecialchars($business['date_of_incorporation']) ?></p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Owner Name</p>
                                        <p class="text-navy font-semibold"><?= htmlspecialchars($business['owner_name']) ?></p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Owner NIC</p>
                                        <p class="text-navy font-semibold"><?= htmlspecialchars($business['owner_nic']) ?></p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Business Phone</p>
                                        <p class="text-navy font-semibold"><?= htmlspecialchars($business['business_number'] ?: $business['personal_phone']) ?></p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Business Email</p>
                                        <p class="text-navy font-semibold break-all"><?= htmlspecialchars($business['business_email'] ?: $business['personal_email']) ?></p>
                                    </div>
                                    <div class="sm:col-span-2 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Registered Address</p>
                                        <p class="text-navy font-semibold">
                                            <?= htmlspecialchars($business['address_line1']) ?><?= !empty($business['address_line2']) ? ', ' . htmlspecialchars($business['address_line2']) : '' ?>,
                                            <?= htmlspecialchars($business['city']) ?>, <?= htmlspecialchars($business['province']) ?>
                                        </p>
                                    </div>
                                    <div class="sm:col-span-2 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Selling Categories</p>
                                        <p class="text-navy font-semibold"><?= htmlspecialchars($business['selling_categories'] ?? '') ?></p>
                                    </div>
                                    <?php if(!empty($business['bank_name'])): ?>
                                    <div class="sm:col-span-2 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Bank Details</p>
                                        <p class="text-navy font-semibold text-sm">
                                            <?= htmlspecialchars($business['bank_name']) ?> - <?= htmlspecialchars($business['branch_name']) ?><br>
                                            <span class="text-gray-600">A/C: <?= htmlspecialchars($business['account_number']) ?> (<?= htmlspecialchars($business['account_holder_name']) ?>)</span>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Documents Status -->
                                <div class="mt-8 pt-5 border-t border-gray-100 flex items-center gap-4 flex-wrap">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Verified Docs:</span>
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-green-700 bg-green-50 px-2.5 py-1 rounded-md border border-green-200">
                                        <i class="fas fa-check-circle"></i> BR Certificate
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-green-700 bg-green-50 px-2.5 py-1 rounded-md border border-green-200">
                                        <i class="fas fa-check-circle"></i> Owner NIC
                                    </div>
                                    <?php if(!empty($business['bank_book_path'])): ?>
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-green-700 bg-green-50 px-2.5 py-1 rounded-md border border-green-200">
                                        <i class="fas fa-check-circle"></i> Bank Book
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Left Column: Business Profile (EDIT MODE) -->
                            <div id="editProfileContainer" class="lg:w-[60%] bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-gray-100 relative hidden transition-all duration-300">
                                <div class="flex justify-between items-center mb-6 border-b border-gray-50 pb-4">
                                    <h3 class="font-black text-navy text-xl uppercase tracking-wide">Edit Business Profile</h3>
                                    <button type="button" onclick="toggleEditMode(false)" class="text-gray-500 hover:bg-gray-50 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors flex items-center gap-2">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                                
                                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-start gap-3">
                                    <i class="fas fa-info-circle mt-0.5 text-blue-600"></i>
                                    <div>Editing your profile will place your account under review again. You won't be able to sell products until admin approves the changes.</div>
                                </div>

                                <form action="../Backend/edit-business-profile.php" method="POST" id="editBusinessForm" class="space-y-6">
                                    
                                    <!-- Business Info -->
                                    <div>
                                        <h4 class="font-bold text-navy mb-4 border-b border-gray-100 pb-2">Business Information</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Business Name <span class="text-red-500">*</span></label>
                                                <input type="text" name="business_name" value="<?= htmlspecialchars($business['business_name']) ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">BR Number <i class="fas fa-lock text-gray-400 ms-1" title="Cannot be changed"></i></label>
                                                <input type="text" name="business_reg_id" value="<?= htmlspecialchars($business['business_reg_id']) ?>" readonly class="w-full bg-gray-100 border-gray-200 text-gray-500 rounded-xl py-2 px-3 text-sm cursor-not-allowed">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Business Type <span class="text-red-500">*</span></label>
                                                <select name="business_type" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                                    <option value="Sole Proprietorship" <?= $business['business_type'] == 'Sole Proprietorship' ? 'selected' : '' ?>>Sole Proprietorship</option>
                                                    <option value="Partnership" <?= $business['business_type'] == 'Partnership' ? 'selected' : '' ?>>Partnership</option>
                                                    <option value="Private Limited Company" <?= $business['business_type'] == 'Private Limited Company' ? 'selected' : '' ?>>Private Limited Company</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Nature of Business <span class="text-red-500">*</span></label>
                                                <select name="nature_of_business" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                                    <option value="Retail" <?= $business['nature_of_business'] == 'Retail' ? 'selected' : '' ?>>Retail</option>
                                                    <option value="Wholesale" <?= $business['nature_of_business'] == 'Wholesale' ? 'selected' : '' ?>>Wholesale</option>
                                                    <option value="Manufacturer" <?= $business['nature_of_business'] == 'Manufacturer' ? 'selected' : '' ?>>Manufacturer</option>
                                                    <option value="Distributor" <?= $business['nature_of_business'] == 'Distributor' ? 'selected' : '' ?>>Distributor</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contact Info -->
                                    <div>
                                        <h4 class="font-bold text-navy mb-4 border-b border-gray-100 pb-2">Contact Details</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Owner Name <span class="text-red-500">*</span></label>
                                                <input type="text" name="owner_name" value="<?= htmlspecialchars($business['owner_name']) ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Owner NIC <i class="fas fa-lock text-gray-400 ms-1" title="Cannot be changed"></i></label>
                                                <input type="text" name="owner_nic" value="<?= htmlspecialchars($business['owner_nic']) ?>" readonly class="w-full bg-gray-100 border-gray-200 text-gray-500 rounded-xl py-2 px-3 text-sm cursor-not-allowed">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Personal Phone <span class="text-red-500">*</span></label>
                                                <input type="text" name="personal_phone" value="<?= htmlspecialchars($business['personal_phone']) ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Business Phone</label>
                                                <input type="text" name="business_number" value="<?= htmlspecialchars($business['business_number']) ?>" class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Personal Email <span class="text-red-500">*</span></label>
                                                <input type="email" name="personal_email" value="<?= htmlspecialchars($business['personal_email']) ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Business Email</label>
                                                <input type="email" name="business_email" value="<?= htmlspecialchars($business['business_email']) ?>" class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div>
                                        <h4 class="font-bold text-navy mb-4 border-b border-gray-100 pb-2">Business Address</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div class="sm:col-span-2">
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Address Line 1 <span class="text-red-500">*</span></label>
                                                <input type="text" name="address_line1" value="<?= htmlspecialchars($business['address_line1']) ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Address Line 2</label>
                                                <input type="text" name="address_line2" value="<?= htmlspecialchars($business['address_line2']) ?>" class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">City <span class="text-red-500">*</span></label>
                                                <input type="text" name="city" value="<?= htmlspecialchars($business['city']) ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Postal Code <span class="text-red-500">*</span></label>
                                                <input type="text" name="postal_code" value="<?= htmlspecialchars($business['postal_code']) ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Province <span class="text-red-500">*</span></label>
                                                <select name="province" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                                    <?php foreach($provinces as $prov): ?>
                                                        <option value="<?= htmlspecialchars($prov) ?>" <?= $business['province'] == $prov ? 'selected' : '' ?>><?= htmlspecialchars($prov) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bank Details -->
                                    <div>
                                        <h4 class="font-bold text-navy mb-4 border-b border-gray-100 pb-2">Bank Details</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Bank Name <span class="text-red-500">*</span></label>
                                                <select name="bank_name" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                                    <option value="Commercial Bank" <?= ($business['bank_name'] ?? '') == 'Commercial Bank' ? 'selected' : '' ?>>Commercial Bank</option>
                                                    <option value="Bank of Ceylon" <?= ($business['bank_name'] ?? '') == 'Bank of Ceylon' ? 'selected' : '' ?>>Bank of Ceylon</option>
                                                    <option value="People\'s Bank" <?= ($business['bank_name'] ?? '') == 'People\'s Bank' ? 'selected' : '' ?>>People's Bank</option>
                                                    <option value="Hatton National Bank" <?= ($business['bank_name'] ?? '') == 'Hatton National Bank' ? 'selected' : '' ?>>Hatton National Bank</option>
                                                    <option value="Sampath Bank" <?= ($business['bank_name'] ?? '') == 'Sampath Bank' ? 'selected' : '' ?>>Sampath Bank</option>
                                                    <option value="Seylan Bank" <?= ($business['bank_name'] ?? '') == 'Seylan Bank' ? 'selected' : '' ?>>Seylan Bank</option>
                                                    <option value="National Savings Bank" <?= ($business['bank_name'] ?? '') == 'National Savings Bank' ? 'selected' : '' ?>>National Savings Bank</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Branch Name <span class="text-red-500">*</span></label>
                                                <input type="text" name="branch_name" value="<?= htmlspecialchars($business['branch_name'] ?? '') ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Account Number <span class="text-red-500">*</span></label>
                                                <input type="text" name="account_number" value="<?= htmlspecialchars($business['account_number'] ?? '') ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-1">Account Holder Name <span class="text-red-500">*</span></label>
                                                <input type="text" name="account_holder_name" value="<?= htmlspecialchars($business['account_holder_name'] ?? '') ?>" required class="w-full border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-[#0066FF] focus:border-[#0066FF]">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Selling Categories -->
                                    <div>
                                        <h4 class="font-bold text-navy mb-4 border-b border-gray-100 pb-2">Selling Information</h4>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">What do you sell? <span class="text-red-500">*</span></label>
                                            <div class="grid grid-cols-2 gap-3">
                                                <?php 
                                                $cats = ['SPORTS WEAR', 'FOOTWEAR', 'FITNESS & GYM', 'NUTRITION', 'ACCESSORIES', 'EQUIPMENT'];
                                                $selected_cats = explode(', ', $business['selling_categories'] ?? '');
                                                foreach($cats as $cat): 
                                                ?>
                                                <label class="flex items-center gap-2 p-2 border border-gray-200 rounded-lg bg-white cursor-pointer hover:border-[#0066FF] transition-colors">
                                                    <input type="checkbox" name="categories[]" value="<?= $cat ?>" <?= in_array($cat, $selected_cats) ? 'checked' : '' ?> class="w-4 h-4 text-[#0066FF] rounded focus:ring-[#0066FF]">
                                                    <span class="text-xs font-semibold text-gray-700"><?= $cat ?></span>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                                        <button type="button" onclick="toggleEditMode(false)" class="px-5 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">Cancel</button>
                                        <button type="submit" class="px-6 py-2.5 rounded-xl font-bold text-white bg-[#0066FF] hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            <div class="lg:w-[40%] flex flex-col">
                                <div class="bg-gradient-to-br from-blue-50 to-[#eef5ff] rounded-2xl p-6 md:p-8 shadow-sm border border-blue-100 relative overflow-hidden h-full flex flex-col">
                                    <div class="absolute -right-4 -top-4 text-blue-200/40 text-9xl transform rotate-12">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                    
                                    <h3 class="font-black text-navy text-xl uppercase tracking-wide mb-8 relative z-10 flex items-center gap-2">
                                        <span class="w-8 h-8 rounded-lg bg-[#0066FF] text-white flex items-center justify-center text-sm shadow-md"><i class="fas fa-star"></i></span>
                                        What's Next?
                                    </h3>
                                    
                                    <ul class="space-y-6 relative z-10 flex-grow">
                                        <li class="flex items-start gap-4">
                                            <div class="w-8 h-8 rounded-full bg-white text-[#0066FF] flex items-center justify-center font-black shadow-sm shrink-0 border border-blue-100 mt-0.5">1</div>
                                            <div>
                                                <h4 class="font-bold text-navy text-[15px]">Add Your First Product</h4>
                                                <p class="text-[13px] font-medium text-slate mt-1 leading-snug">Include buying and selling price for profit calculation.</p>
                                            </div>
                                        </li>
                                        <li class="flex items-start gap-4">
                                            <div class="w-8 h-8 rounded-full bg-white text-[#0066FF] flex items-center justify-center font-black shadow-sm shrink-0 border border-blue-100 mt-0.5">2</div>
                                            <div>
                                                <h4 class="font-bold text-navy text-[15px]">Wait for Approval</h4>
                                                <p class="text-[13px] font-medium text-slate mt-1 leading-snug">Our team approves products within 2-4 hours.</p>
                                            </div>
                                        </li>
                                        <li class="flex items-start gap-4">
                                            <div class="w-8 h-8 rounded-full bg-white text-[#0066FF] flex items-center justify-center font-black shadow-sm shrink-0 border border-blue-100 mt-0.5">3</div>
                                            <div>
                                                <h4 class="font-bold text-navy text-[15px]">Start Selling & Earn</h4>
                                                <p class="text-[13px] font-medium text-slate mt-1 leading-snug">Keep your buying price + 90% of the profit margin.</p>
                                            </div>
                                        </li>
                                    </ul>
                                    
                                    <div class="mt-8 space-y-3 relative z-10 pt-6 border-t border-blue-200/50">
                                        <a href="seller-add-product.php" class="block w-full bg-[#0066FF] hover:bg-blue-700 text-white text-center py-3.5 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30">
                                            Add First Product
                                        </a>
                                        <a href="seller-dashboard.php" class="block w-full bg-white hover:bg-gray-50 border border-blue-200 text-[#0066FF] hover:text-navy text-center py-3.5 rounded-xl font-bold uppercase tracking-wide transition-all shadow-sm">
                                            View My Store
                                        </a>
                                    </div>
                                    
                                    <div class="mt-5 text-center relative z-10">
                                        <a href="#" class="text-xs font-bold text-slate hover:text-[#0066FF] transition-colors"><i class="fas fa-headset me-1"></i> Need help? Contact Support</a>
                                    </div>
                                </div>
                            </div>
                            
                        </div>

                        <!-- Footer Trust Box -->
                        <div class="mt-6 bg-navy text-white rounded-2xl p-6 text-center shadow-lg border border-gray-800 relative overflow-hidden bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]">
                            <div class="absolute inset-0 bg-gradient-to-r from-[#0066FF]/20 to-transparent"></div>
                            <p class="relative z-10 text-sm font-medium text-gray-300 max-w-4xl mx-auto leading-relaxed">
                                <span class="font-bold text-white text-base"><i class="fas fa-globe-asia me-2 text-[#0066FF]"></i> Your business is now live on OXXA GEAR.</span><br>
                                10,000+ customers can discover your products. Remember our <span class="text-green-400 font-bold px-1 py-0.5 bg-green-400/10 rounded mx-1">10% profit share model</span> — you keep your entire Buying Price + 90% of the calculated Profit!
                            </p>
                        </div>
                    <?php elseif ($business['is_approved'] == -1): ?>
                        <div class="flex flex-col items-center text-center py-10">
                            <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fas fa-times text-4xl text-red-600"></i>
                            </div>
                            <h2 class="text-2xl font-black text-red-600 uppercase tracking-wide">Verification Rejected</h2>
                            <p class="text-slate mt-2 max-w-md mx-auto">Unfortunately, your business registration was rejected. Please contact support for more details.</p>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center text-center py-10">
                            <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fas fa-hourglass-half text-4xl text-yellow-600"></i>
                            </div>
                            <h2 class="text-2xl font-black text-navy uppercase tracking-wide">Verification Pending</h2>
                            <p class="text-slate mt-2 max-w-md mx-auto">Your business documents are currently under review by our admin team. This usually takes 1-2 business days.</p>
                            
                            <div class="mt-8 w-full max-w-lg bg-gray-50 rounded-xl p-6 text-left border border-gray-100">
                                <h3 class="font-bold text-navy mb-4 border-b border-gray-200 pb-2">Application Overview</h3>
                                <div class="grid grid-cols-2 gap-y-4 text-sm">
                                    <div class="text-gray-500">Business Name:</div>
                                    <div class="font-semibold text-navy"><?= htmlspecialchars($business['business_name']) ?></div>
                                    
                                    <div class="text-gray-500">BR Number:</div>
                                    <div class="font-semibold text-navy"><?= htmlspecialchars($business['business_reg_id']) ?></div>
                                    
                                    <div class="text-gray-500">Owner Name:</div>
                                    <div class="font-semibold text-navy"><?= htmlspecialchars($business['owner_name']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Form Error Banner -->
            <div id="formErrorBanner" class="hidden bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 items-center">
                <i class="fas fa-exclamation-circle me-3 text-xl"></i>
                <span class="font-bold flex-1" id="formErrorText">
                    Please correct the highlighted errors.
                </span>
            </div>

            <!-- New Business Registration Form -->
            <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="bg-navy p-8 text-center border-b-4 border-[#0066FF]">
                    <h2 class="text-2xl font-black text-white uppercase tracking-wide">
                        Verify Your Business
                    </h2>
                    <p class="text-gray-300 text-sm mt-2 max-w-2xl mx-auto">Please complete the form below with accurate information. All documents will be securely reviewed by our team to approve your seller account.</p>
                </div>
                
                <form action="#" method="POST" enctype="multipart/form-data" class="p-8 md:p-10 space-y-12" id="sellerVerifyForm">
                    
                    <!-- 1. Business Information -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">1</span>
                            Business Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Name <span class="text-red-500">*</span></label>
                                <input type="text" id="business_name" name="business_name" required placeholder="e.g. Ravindu Sports" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_business_name"></span>
                                <p class="text-xs text-gray-400 mt-1">This name will be displayed on your OXXA shop.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Type <span class="text-red-500">*</span></label>
                                <select id="business_type" name="business_type" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <option value="" disabled selected>Select Type</option>
                                    <option value="Sole Proprietorship">Sole Proprietorship</option>
                                    <option value="Partnership">Partnership</option>
                                    <option value="Private Limited Company">Private Limited Company (Pvt Ltd)</option>
                                </select>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_business_type"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Registration Number (BR) <span class="text-red-500">*</span></label>
                                <input type="text" id="br_number" name="business_reg_id" required placeholder="e.g. WP-C-32194" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF] uppercase">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_business_reg_id"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Date of Incorporation <span class="text-red-500">*</span></label>
                                <input type="date" id="date_of_incorporation" name="date_of_incorporation" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_date_of_incorporation"></span>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Nature of Business <span class="text-red-500">*</span></label>
                                <select id="nature_of_business" name="nature_of_business" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <option value="" disabled selected>Select Category</option>
                                    <option value="Retail">Retail</option>
                                    <option value="Wholesale">Wholesale</option>
                                    <option value="Manufacturer">Manufacturer</option>
                                    <option value="Distributor">Distributor</option>
                                </select>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_nature_of_business"></span>
                            </div>
                        </div>
                    </section>

                    <!-- 2. Owner / Contact Person Details -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">2</span>
                            Owner / Contact Person
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Owner Full Name <span class="text-red-500">*</span></label>
                                <input type="text" id="owner_name" name="owner_name" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_owner_name"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">NIC Number <span class="text-red-500">*</span></label>
                                <input type="text" id="owner_nic" name="owner_nic" required placeholder="e.g. 199012345678 or 901234567V" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_owner_nic"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Personal Phone <span class="text-red-500">*</span></label>
                                <input type="text" id="personal_phone" name="personal_phone" required placeholder="07XXXXXXXX" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_personal_phone"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Personal Email <span class="text-red-500">*</span></label>
                                <input type="email" id="personal_email" name="personal_email" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_personal_email"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Phone <span class="text-red-500">*</span></label>
                                <input type="text" id="business_phone" name="business_phone" required placeholder="011XXXXXXX" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_business_phone"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Email <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                <input type="email" id="business_email" name="business_email" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_business_email"></span>
                            </div>
                        </div>
                    </section>

                    <!-- 3. Business Address -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">3</span>
                            Business Address
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Address Line 1 <span class="text-red-500">*</span></label>
                                <input type="text" id="address_line1" name="address_line1" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_address_line1"></span>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Address Line 2 <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                <input type="text" id="address_line2" name="address_line2" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_address_line2"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">City <span class="text-red-500">*</span></label>
                                <input type="text" id="city" name="city" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_city"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Postal Code <span class="text-red-500">*</span></label>
                                <input type="text" id="postal_code" name="postal_code" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_postal_code"></span>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Province <span class="text-red-500">*</span></label>
                                <select id="province" name="province" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <option value="" disabled selected>Select Province</option>
                                    <?php foreach($provinces as $prov): ?>
                                        <option value="<?php echo htmlspecialchars($prov); ?>"><?php echo htmlspecialchars($prov); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_province"></span>
                            </div>
                        </div>
                    </section>

                    <!-- 4. Documents Upload -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">4</span>
                            Documents Upload
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">Supported formats: JPG, PNG, PDF. Maximum size: 5MB per file (Logo: 2MB).</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <!-- BR Certificate -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">BR Certificate <span class="text-red-500">*</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-[#0066FF]/50 rounded-xl cursor-pointer bg-blue-50/30 hover:bg-blue-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-cloud-upload-alt text-2xl text-[#0066FF] mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Click to upload BR Certificate</p>
                                    </div>
                                    <input type="file" id="certificate_file" name="certificate_file" required accept=".jpg,.jpeg,.png,.pdf" class="hidden file-input" data-max-size="5" />
                                </label>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_certificate_file"></span>
                            </div>

                            <!-- NIC Front & Back -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Owner NIC (Front & Back) <span class="text-red-500">*</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-[#0066FF]/50 rounded-xl cursor-pointer bg-blue-50/30 hover:bg-blue-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-id-card text-2xl text-[#0066FF] mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Click to upload NIC Copy</p>
                                    </div>
                                    <input type="file" id="nic_file" name="nic_file" required accept=".jpg,.jpeg,.png,.pdf" class="hidden file-input" data-max-size="5" />
                                </label>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_nic_file"></span>
                            </div>

                            <!-- Business Logo -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Logo <span class="text-red-500">*</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-[#0066FF]/50 rounded-xl cursor-pointer bg-blue-50/30 hover:bg-blue-50 transition-colors relative overflow-hidden group">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6" id="logoPreviewWrapper">
                                        <i class="fas fa-image text-2xl text-[#0066FF] mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Click to upload Shop Logo</p>
                                    </div>
                                    <img id="logoPreviewImage" class="absolute inset-0 w-full h-full object-cover hidden" alt="Logo Preview" />
                                    <input type="file" id="logo_file" name="logo_file" required accept=".jpg,.jpeg,.png" class="hidden file-input" data-max-size="2" data-is-logo="true" />
                                </label>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_logo_file"></span>
                                <p class="text-[10px] text-gray-400 mt-1">Min 200x200 pixels, ratio 1:1, Max 2MB</p>
                            </div>

                            <!-- Shop Photo -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Shop Photo <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-white hover:bg-gray-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-store text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Upload Shop Photo (Optional)</p>
                                    </div>
                                    <input type="file" id="shop_photo_file" name="shop_photo_file" accept=".jpg,.jpeg,.png" class="hidden file-input" data-max-size="3" />
                                </label>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_shop_photo_file"></span>
                            </div>
                        </div>
                    </section>

                    <!-- 5. Bank Details -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">5</span>
                            Bank Details
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">This account will be used for your seller payouts.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Bank Name <span class="text-red-500">*</span></label>
                                <select id="bank_name" name="bank_name" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <option value="" disabled selected>Select Bank</option>
                                    <option value="Commercial Bank">Commercial Bank</option>
                                    <option value="Bank of Ceylon">Bank of Ceylon</option>
                                    <option value="People's Bank">People's Bank</option>
                                    <option value="Hatton National Bank">Hatton National Bank</option>
                                    <option value="Sampath Bank">Sampath Bank</option>
                                    <option value="Seylan Bank">Seylan Bank</option>
                                    <option value="National Savings Bank">National Savings Bank</option>
                                </select>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_bank_name"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Branch Name <span class="text-red-500">*</span></label>
                                <input type="text" id="branch_name" name="branch_name" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_branch_name"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Account Number <span class="text-red-500">*</span></label>
                                <input type="text" id="account_number" name="account_number" required placeholder="Digits only (10-16 digits)" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_account_number"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Account Holder Name <span class="text-red-500">*</span></label>
                                <input type="text" id="account_holder_name" name="account_holder_name" required placeholder="Must match BR Name" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_account_holder_name"></span>
                            </div>
                            
                            <div class="md:col-span-2 file-upload-wrapper mt-4">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Bank Book / Slip Photo <span class="text-gray-400 text-xs normal-case">(Optional but recommended)</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-white hover:bg-gray-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-university text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Upload Bank Proof (Optional)</p>
                                    </div>
                                    <input type="file" id="bank_book_file" name="bank_book_file" accept=".jpg,.jpeg,.png,.pdf" class="hidden file-input" data-max-size="5" />
                                </label>
                                <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_bank_book_file"></span>
                            </div>
                        </div>
                    </section>

                    <!-- 6. Selling Information -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">6</span>
                            Selling Information
                        </h3>
                        <div class="grid grid-cols-1 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">What do you sell? <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="categories_container">
                                    <?php 
                                    $cats = ['SPORTS WEAR', 'FOOTWEAR', 'FITNESS & GYM', 'NUTRITION', 'ACCESSORIES', 'EQUIPMENT'];
                                    foreach($cats as $cat): 
                                    ?>
                                    <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg bg-white cursor-pointer hover:border-[#0066FF] transition-colors">
                                        <input type="checkbox" name="categories[]" value="<?php echo $cat; ?>" class="category-checkbox w-5 h-5 text-[#0066FF] rounded focus:ring-[#0066FF]">
                                        <span class="text-sm font-semibold text-gray-700"><?php echo $cat; ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <span class="error-msg text-red-500 text-[12px] mt-2 hidden font-bold block" id="err_categories"></span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-2">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Estimated Monthly Products</label>
                                    <select id="estimated_products" name="estimated_products" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                        <option value="1-50">1 - 50 items</option>
                                        <option value="51-200">51 - 200 items</option>
                                        <option value="200+">200+ items</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Website / Facebook Page <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                    <input type="url" id="social_website" name="social_website" placeholder="https://" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <span class="error-msg text-red-500 text-[12px] mt-1 hidden font-bold block" id="err_social_website"></span>
                                </div>
                            </div>
                            
                        </div>
                    </section>

                    <!-- 7. Declaration -->
                    <section>
                        <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 flex items-start gap-4">
                            <input type="checkbox" name="declaration" id="declaration" value="1" required class="w-6 h-6 mt-1 text-[#0066FF] rounded focus:ring-[#0066FF]">
                            <div>
                                <label for="declaration" class="text-navy font-bold text-lg cursor-pointer">I declare that all information provided is true and accurate.</label>
                                <p class="text-sm text-gray-600 mt-1">I agree to OXXA GEAR's Terms and Conditions for Sellers. I understand that submitting false documents will result in permanent ban and legal actions.</p>
                            </div>
                        </div>
                        <span class="error-msg text-red-500 text-[12px] mt-2 hidden font-bold block" id="err_declaration"></span>
                    </section>
                    
                    <div class="pt-6 border-t border-gray-100 flex justify-end">
                        <button type="submit" id="submitVerificationBtn" class="bg-[#0066FF] text-white px-12 py-4 rounded-xl font-black text-lg uppercase tracking-wider hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/30 flex items-center justify-center min-w-[300px] disabled:opacity-50 disabled:cursor-not-allowed">
                            <span>Submit Registration</span>
                            <i class="fas fa-spinner fa-spin ms-3 hidden" id="submitLoader"></i>
                        </button>
                    </div>
                </form>
            </div>
            
        <?php endif; ?>

    </div>
</div>

<script src="<?php echo $base_path; ?>assets/js/seller-verification.js?v=<?php echo time(); ?>"></script>
<script>
function toggleEditMode(isEdit) {
    const viewContainer = document.getElementById('viewProfileContainer');
    const editContainer = document.getElementById('editProfileContainer');
    
    if (isEdit) {
        viewContainer.classList.add('hidden');
        editContainer.classList.remove('hidden');
    } else {
        viewContainer.classList.remove('hidden');
        editContainer.classList.add('hidden');
    }
}
</script>

<?php include('../include/footer.php'); ?>