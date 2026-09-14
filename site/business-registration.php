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
            <h1 class="text-3xl font-black text-navy uppercase tracking-wide">Seller Dashboard</h1>
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
                        <div class="flex flex-col items-center text-center py-10">
                            <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fas fa-check text-4xl text-green-600"></i>
                            </div>
                            <h2 class="text-2xl font-black text-navy uppercase tracking-wide">Business Verified</h2>
                            <p class="text-slate mt-2 max-w-md mx-auto">Your business account is fully active. You can now add products and start selling on OXXA GEAR.</p>
                            
                            <a href="seller-dashboard.php" class="mt-8 bg-[#0066FF] text-white px-8 py-3 rounded-xl font-bold uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">
                                Go to My Products <i class="fas fa-arrow-right ms-2"></i>
                            </a>
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
            <!-- New Business Registration Form -->
            <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="bg-navy p-8 text-center border-b-4 border-[#0066FF]">
                    <h2 class="text-2xl font-black text-white uppercase tracking-wide">
                        Verify Your Business
                    </h2>
                    <p class="text-gray-300 text-sm mt-2 max-w-2xl mx-auto">Please complete the form below with accurate information. All documents will be securely reviewed by our team to approve your seller account.</p>
                </div>
                
                <form action="../Backend/process-business-registration.php" method="POST" enctype="multipart/form-data" class="p-8 md:p-10 space-y-12" id="registrationForm">
                    
                    <!-- 1. Business Information -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">1</span>
                            Business Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Name <span class="text-red-500">*</span></label>
                                <input type="text" name="business_name" required placeholder="e.g. Ravindu Sports" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                <p class="text-xs text-gray-400 mt-1">This name will be displayed on your OXXA shop.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Type <span class="text-red-500">*</span></label>
                                <select name="business_type" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <option value="" disabled selected>Select Type</option>
                                    <option value="Sole Proprietorship">Sole Proprietorship</option>
                                    <option value="Partnership">Partnership</option>
                                    <option value="Private Limited Company">Private Limited Company (Pvt Ltd)</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Registration Number (BR) <span class="text-red-500">*</span></label>
                                <input type="text" name="business_reg_id" required placeholder="e.g. PV12345" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Date of Incorporation</label>
                                <input type="date" name="date_of_incorporation" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Nature of Business <span class="text-red-500">*</span></label>
                                <select name="nature_of_business" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <option value="" disabled selected>Select Category</option>
                                    <option value="Retail">Retail</option>
                                    <option value="Wholesale">Wholesale</option>
                                    <option value="Manufacturer">Manufacturer</option>
                                    <option value="Distributor">Distributor</option>
                                </select>
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
                                <input type="text" name="owner_name" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">NIC Number <span class="text-red-500">*</span></label>
                                <input type="text" name="owner_nic" required placeholder="e.g. 199012345678 or 901234567V" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Personal Phone <span class="text-red-500">*</span></label>
                                <input type="text" name="personal_phone" required pattern="07[0-9]{8}" placeholder="07XXXXXXXX" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]" title="Must be a valid 10-digit phone number starting with 07">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Personal Email <span class="text-red-500">*</span></label>
                                <input type="email" name="personal_email" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Phone <span class="text-red-500">*</span></label>
                                <input type="text" name="business_phone" required placeholder="Shop Phone" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Email <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                <input type="email" name="business_email" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
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
                                <input type="text" name="address_line1" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Address Line 2 <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                <input type="text" name="address_line2" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">City <span class="text-red-500">*</span></label>
                                <input type="text" name="city" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Postal Code <span class="text-red-500">*</span></label>
                                <input type="text" name="postal_code" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Province <span class="text-red-500">*</span></label>
                                <select name="province" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                    <option value="" disabled selected>Select Province</option>
                                    <?php foreach($provinces as $prov): ?>
                                        <option value="<?php echo htmlspecialchars($prov); ?>"><?php echo htmlspecialchars($prov); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- 4. Documents Upload -->
                    <section>
                        <h3 class="text-lg font-black text-navy uppercase tracking-wider mb-5 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-[#0066FF] flex items-center justify-center text-sm">4</span>
                            Documents Upload
                        </h3>
                        <p class="text-sm text-gray-500 mb-6">Supported formats: JPG, PNG, PDF. Maximum size: 5MB per file.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <!-- BR Certificate -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">BR Certificate <span class="text-red-500">*</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-[#0066FF]/50 rounded-xl cursor-pointer bg-blue-50/30 hover:bg-blue-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-cloud-upload-alt text-2xl text-[#0066FF] mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Click to upload BR Certificate</p>
                                    </div>
                                    <input type="file" name="certificate_file" required accept=".jpg,.jpeg,.png,.pdf" class="hidden file-input" />
                                </label>
                            </div>

                            <!-- NIC Front & Back -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Owner NIC (Front & Back) <span class="text-red-500">*</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-[#0066FF]/50 rounded-xl cursor-pointer bg-blue-50/30 hover:bg-blue-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-id-card text-2xl text-[#0066FF] mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Click to upload NIC Copy</p>
                                    </div>
                                    <input type="file" name="nic_file" required accept=".jpg,.jpeg,.png,.pdf" class="hidden file-input" />
                                </label>
                            </div>

                            <!-- Business Logo -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Business Logo <span class="text-red-500">*</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-[#0066FF]/50 rounded-xl cursor-pointer bg-blue-50/30 hover:bg-blue-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-image text-2xl text-[#0066FF] mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Click to upload Shop Logo</p>
                                    </div>
                                    <input type="file" name="logo_file" required accept=".jpg,.jpeg,.png" class="hidden file-input" />
                                </label>
                            </div>

                            <!-- Shop Photo -->
                            <div class="file-upload-wrapper">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Shop Photo <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-white hover:bg-gray-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-store text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Upload Shop Photo (Optional)</p>
                                    </div>
                                    <input type="file" name="shop_photo_file" accept=".jpg,.jpeg,.png" class="hidden file-input" />
                                </label>
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
                                <input type="text" name="bank_name" required placeholder="e.g. Commercial Bank" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Branch Name <span class="text-red-500">*</span></label>
                                <input type="text" name="branch_name" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Account Number <span class="text-red-500">*</span></label>
                                <input type="text" name="account_number" required class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Account Holder Name <span class="text-red-500">*</span></label>
                                <input type="text" name="account_holder_name" required placeholder="Must match BR Name" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                            </div>
                            
                            <div class="md:col-span-2 file-upload-wrapper mt-4">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Bank Book / Slip Photo <span class="text-gray-400 text-xs normal-case">(Optional but recommended)</span></label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-white hover:bg-gray-50 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i class="fas fa-university text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500 font-semibold file-name-display">Upload Bank Proof (Optional)</p>
                                    </div>
                                    <input type="file" name="bank_book_file" accept=".jpg,.jpeg,.png,.pdf" class="hidden file-input" />
                                </label>
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
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <?php 
                                    $cats = ['SPORTS WEAR', 'FOOTWEAR', 'FITNESS & GYM', 'NUTRITION', 'ACCESSORIES', 'EQUIPMENT'];
                                    foreach($cats as $cat): 
                                    ?>
                                    <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg bg-white cursor-pointer hover:border-[#0066FF] transition-colors">
                                        <input type="checkbox" name="categories[]" value="<?php echo $cat; ?>" class="w-5 h-5 text-[#0066FF] rounded focus:ring-[#0066FF]">
                                        <span class="text-sm font-semibold text-gray-700"><?php echo $cat; ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-2">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Estimated Monthly Products</label>
                                    <select name="estimated_products" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
                                        <option value="1-50">1 - 50 items</option>
                                        <option value="51-200">51 - 200 items</option>
                                        <option value="200+">200+ items</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Website / Facebook Page <span class="text-gray-400 text-xs normal-case">(Optional)</span></label>
                                    <input type="url" name="social_website" placeholder="https://" class="w-full border-gray-200 rounded-xl py-3 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
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
                    </section>
                    
                    <div class="pt-6 border-t border-gray-100 flex justify-end">
                        <button type="submit" class="bg-[#0066FF] text-white px-12 py-4 rounded-xl font-black text-lg uppercase tracking-wider hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">
                            Submit Registration
                        </button>
                    </div>
                </form>
            </div>
            
        <?php endif; ?>

    </div>
</div>

<script>
// Logic to show selected file name in upload boxes
document.querySelectorAll('.file-input').forEach(input => {
    input.addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : null;
        const display = this.parentElement.querySelector('.file-name-display');
        const icon = this.parentElement.querySelector('i');
        
        if (fileName) {
            display.textContent = fileName;
            display.classList.add('text-[#0066FF]', 'font-bold');
            display.classList.remove('text-gray-500');
            this.parentElement.classList.add('border-[#0066FF]', 'bg-blue-50');
            icon.classList.remove('fa-cloud-upload-alt', 'text-gray-400');
            icon.classList.add('fa-check-circle', 'text-[#0066FF]');
        }
    });
});
</script>

<?php include('../include/footer.php'); ?>