<!-- Quick Add / Variant Selection Modal -->
<div id="quickAddModalOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[1100] hidden opacity-0 transition-opacity duration-300 flex items-center justify-center p-4">
    <div id="quickAddModal" class="bg-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl transform scale-95 opacity-0 transition-all duration-300 relative border border-gray-100">
        
        <!-- Modal Header -->
        <div class="p-6 pb-4 border-b border-gray-100 flex items-start justify-between">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gray-50 border border-gray-100 overflow-hidden flex-shrink-0 p-1">
                    <img id="qaProductImage" src="../image/placeholder.png" alt="Product" class="w-full h-full object-contain">
                </div>
                <div>
                    <div id="qaBrand" class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Brand</div>
                    <h3 id="qaTitle" class="text-base font-black text-navy uppercase tracking-wide line-clamp-1">Product Name</h3>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span id="qaOrigPrice" class="text-xs text-gray-400 line-through hidden">Rs. 0</span>
                        <span id="qaPrice" class="text-lg font-black text-navy">Rs. 0</span>
                        <span id="qaHotDealBadge" class="hidden px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-[#CCFF00] text-black">-0% HOT DEAL</span>
                    </div>
                </div>
            </div>
            <button onclick="closeQuickAddModal()" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-400 hover:text-navy flex items-center justify-center transition-colors">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-5 max-h-[60vh] overflow-y-auto">
            
            <!-- Color Selection (if available) -->
            <div id="qaColorSection" class="hidden">
                <label class="block text-xs font-bold text-navy uppercase tracking-wider mb-2">
                    Color: <span id="qaSelectedColorName" class="text-primary font-bold">Select</span>
                </label>
                <div id="qaColorOptions" class="flex flex-wrap gap-2.5">
                    <!-- Dynamic color chips -->
                </div>
            </div>

            <!-- Size Selection (if available) -->
            <div id="qaSizeSection" class="hidden">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-xs font-bold text-navy uppercase tracking-wider">
                        Size: <span id="qaSelectedSizeName" class="text-primary font-bold">Select</span>
                    </label>
                    <span id="qaStockWarning" class="text-[11px] font-bold text-gray-400">Select a size</span>
                </div>
                <div id="qaSizeOptions" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    <!-- Dynamic size pills -->
                </div>
            </div>

            <!-- Quantity Stepper -->
            <div>
                <label class="block text-xs font-bold text-navy uppercase tracking-wider mb-2">Quantity</label>
                <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-50 border border-gray-200">
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="adjustQuickAddQty(-1)" class="w-9 h-9 rounded-xl bg-white border border-gray-200 text-navy font-bold flex items-center justify-center hover:bg-gray-100 transition-colors">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <span id="qaQtyDisplay" class="font-black text-navy text-base w-8 text-center">1</span>
                        <button type="button" onclick="adjustQuickAddQty(1)" class="w-9 h-9 rounded-xl bg-white border border-gray-200 text-navy font-bold flex items-center justify-center hover:bg-gray-100 transition-colors">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Item Total</div>
                        <div id="qaTotalDisplay" class="text-base font-black text-navy">Rs. 0</div>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mt-1.5">* Maximum 10 units per order</p>
            </div>

        </div>

        <!-- Modal Footer -->
        <div class="p-6 pt-3 border-t border-gray-100 flex gap-3">
            <button type="button" onclick="closeQuickAddModal()" class="w-1/3 py-3.5 border border-gray-200 text-gray-600 hover:text-navy hover:bg-gray-50 rounded-xl font-bold uppercase tracking-wider text-xs transition-colors">
                Cancel
            </button>
            <button type="button" id="qaConfirmBtn" onclick="confirmQuickAdd()" class="w-2/3 py-3.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-bold uppercase tracking-wider text-xs shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-shopping-bag"></i>
                <span id="qaBtnText">Add to Cart</span>
            </button>
        </div>

    </div>
</div>
