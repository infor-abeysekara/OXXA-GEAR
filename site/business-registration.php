<?php
session_start();
$page_title = 'Business Profile - OXXA GEAR';
include('../include/header.php');
include('../include/connection.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}

// Check if business profile exists
$stmt = $pdo->prepare("SELECT * FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['userid']]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<div class="bg-gray-50 min-h-[90vh] py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-black text-navy uppercase tracking-wide">Seller Dashboard</h1>
            <p class="text-slate mt-1">Manage your business profile and verifications.</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-8 flex items-start">
                <i class="fas fa-check-circle mt-1 me-3"></i>
                <div>
                    <h4 class="font-bold">Application Submitted!</h4>
                    <p class="text-sm mt-1">Your business registration has been received. Please allow 1-2 business days for our admin team to verify your documents.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 flex items-center">
                <i class="fas fa-exclamation-circle me-3"></i>
                <span class="font-bold">Error: Please check your form and try again.</span>
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
                    <?php else: ?>
                        <div class="flex flex-col items-center text-center py-10">
                            <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fas fa-hourglass-half text-4xl text-yellow-600"></i>
                            </div>
                            <h2 class="text-2xl font-black text-navy uppercase tracking-wide">Verification Pending</h2>
                            <p class="text-slate mt-2 max-w-md mx-auto">Your business documents are currently under review by our admin team. This usually takes 1-2 business days.</p>
                            
                            <div class="mt-8 w-full max-w-md bg-gray-50 rounded-xl p-6 text-left border border-gray-100">
                                <h3 class="font-bold text-navy mb-4 border-b border-gray-200 pb-2">Application Details</h3>
                                <div class="grid grid-cols-2 gap-y-4 text-sm">
                                    <div class="text-gray-500">Business Name:</div>
                                    <div class="font-semibold text-navy"><?= htmlspecialchars($business['business_name']) ?></div>
                                    
                                    <div class="text-gray-500">Business Type:</div>
                                    <div class="font-semibold text-navy"><?= htmlspecialchars($business['business_type']) ?></div>
                                    
                                    <div class="text-gray-500">Reg ID:</div>
                                    <div class="font-semibold text-navy"><?= htmlspecialchars($business['business_reg_id']) ?></div>
                                    
                                    <div class="text-gray-500">Contact Number:</div>
                                    <div class="font-semibold text-navy"><?= htmlspecialchars($business['business_number']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- New Business Registration Form -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-navy p-6">
                    <h2 class="text-xl font-black text-white uppercase tracking-wide flex items-center">
                        <i class="fas fa-id-card me-3 text-[#0066FF]"></i> Verify Your Business
                    </h2>
                    <p class="text-gray-400 text-sm mt-1">We require all sellers to be verified businesses to maintain platform quality.</p>
                </div>
                
                <form action="../Backend/business-backend.php" method="POST" enctype="multipart/form-data" class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Business Name -->
                        <div class="col-span-1">
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Business Name *</label>
                            <input type="text" name="bname" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                        </div>

                        <!-- Business Type -->
                        <div class="col-span-1">
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Business Type *</label>
                            <select name="btype" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                                <option value="">Select Type...</option>
                                <option value="Sole Proprietorship">Sole Proprietorship</option>
                                <option value="Partnership">Partnership</option>
                                <option value="Private Limited Company">Private Limited Company</option>
                                <option value="Public Limited Company">Public Limited Company</option>
                            </select>
                        </div>

                        <!-- Business Reg ID -->
                        <div class="col-span-1">
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Business Reg ID (BR) *</label>
                            <input type="text" name="bregid" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                        </div>

                        <!-- Business Contact Number -->
                        <div class="col-span-1">
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Business Contact Number *</label>
                            <input type="text" name="bnumber" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                        </div>

                        <!-- Certificate Upload -->
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Business Certificate (PDF/Image) *</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-[#0066FF] transition-colors relative">
                                <input type="file" name="bcertificate" accept=".pdf,.jpg,.jpeg,.png,.webp" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                <i class="fas fa-file-upload text-4xl text-gray-400 mb-3"></i>
                                <p class="text-sm text-slate">Drag and drop your BR certificate here, or click to browse</p>
                                <p class="text-xs text-gray-400 mt-1">PDF, JPG, PNG (Max 2MB)</p>
                            </div>
                        </div>

                        <!-- Logo Upload -->
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Business Logo (Optional)</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-[#0066FF] transition-colors relative flex items-center justify-center gap-4">
                                <input type="file" name="blogo" accept=".jpg,.jpeg,.png,.webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-camera text-gray-400"></i>
                                </div>
                                <div class="text-left">
                                    <p class="text-sm text-slate">Upload your brand logo</p>
                                    <p class="text-xs text-gray-400">JPG, PNG (Max 1MB)</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-8 border-t border-gray-100 pt-8 flex justify-end">
                        <button type="submit" name="register_business" class="bg-[#0066FF] hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex items-center">
                            Submit Application <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<?php include('../include/footer.php'); ?>