/**
 * OXXA GEAR - Global Cart Manager
 * Handles fly-to-cart animations, dynamic drawer, guest/user syncing, variant selection, and coupon application.
 */

const CartManager = {
    cartData: null,
    activeQuickAdd: null,

    init() {
        this.bindEvents();
        this.fetchCart();
    },

    bindEvents() {
        // Keyboard ESC closes drawer & modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeDrawer();
                this.closeQuickAdd();
            }
        });
    },

    // -------------------------------------------------------------
    // PATH & API RESOLUTION
    // -------------------------------------------------------------
    getApiUrl(queryString = '') {
        const isInsideSite = window.location.pathname.includes('/site/');
        const base = (isInsideSite ? '../' : './') + 'Backend/cart-api.php';
        return queryString ? `${base}?${queryString}` : base;
    },

    resolveImagePath(imgPath) {
        if (!imgPath) return (window.location.pathname.includes('/site/') ? '../' : './') + 'image/placeholder.png';
        if (imgPath.startsWith('http://') || imgPath.startsWith('https://') || imgPath.startsWith('data:')) return imgPath;
        const isInsideSite = window.location.pathname.includes('/site/');
        const clean = imgPath.replace(/^(\.\.\/|\.\/)+/, '');
        return (isInsideSite ? '../' : './') + clean;
    },

    // -------------------------------------------------------------
    // API CALLS & DATA SYNC
    // -------------------------------------------------------------
    async fetchCart() {
        const guestCart = this.getGuestCart();
        const formData = new FormData();
        formData.append('action', 'get_cart');
        if (guestCart.length > 0) {
            formData.append('guest_cart', JSON.stringify(guestCart));
        }

        try {
            const res = await fetch(this.getApiUrl('action=get_cart'), {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                this.cartData = data;
                this.updateBadge(data.count);
                this.renderDrawer(data);

                // Auto-sync guest cart to DB if user is logged in
                if (data.is_logged_in && guestCart.length > 0) {
                    this.syncGuestCartToDB(guestCart);
                }
            }
        } catch (err) {
            console.error('CartManager: Error fetching cart', err);
        }
    },

    async syncGuestCartToDB(guestItems) {
        const formData = new FormData();
        formData.append('action', 'sync_guest_cart');
        formData.append('guest_items', JSON.stringify(guestItems));

        try {
            const res = await fetch(this.getApiUrl(), { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                // Clear localStorage once merged
                localStorage.removeItem('oxxa_cart');
                this.fetchCart();
            }
        } catch (err) {
            console.error('CartManager: Error syncing guest cart', err);
        }
    },

    getGuestCart() {
        try {
            const stored = localStorage.getItem('oxxa_cart');
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    },

    saveGuestCart(items) {
        localStorage.setItem('oxxa_cart', JSON.stringify(items));
    },

    // -------------------------------------------------------------
    // ADD TO CART WITH STOCK & VARIANT RULES
    // -------------------------------------------------------------
    async addToCart(productId, variantId = null, quantity = 1, sourceElement = null) {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        if (variantId) formData.append('variant_id', variantId);
        formData.append('quantity', quantity);

        try {
            const res = await fetch(this.getApiUrl(), {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                this.showToast(data.message || 'Could not add item to cart', 'error');
                return false;
            }

            // If guest user, store in localStorage
            if (data.is_guest && data.item) {
                let guestCart = this.getGuestCart();
                const existingIndex = guestCart.findIndex(i => 
                    i.product_id === data.item.product_id && 
                    ((!i.variant_id && !data.item.variant_id) || i.variant_id === data.item.variant_id)
                );

                if (existingIndex > -1) {
                    const newQty = Math.min(10, guestCart[existingIndex].quantity + quantity);
                    guestCart[existingIndex].quantity = newQty;
                    guestCart[existingIndex].price_at_add = data.item.price_at_add;
                } else {
                    guestCart.push({
                        product_id: data.item.product_id,
                        variant_id: data.item.variant_id,
                        quantity: quantity,
                        price_at_add: data.item.price_at_add
                    });
                }
                this.saveGuestCart(guestCart);
            }

            // Fly animation
            if (sourceElement) {
                this.flyToCart(sourceElement);
            }

            this.showToast(data.message || 'Added to Cart ✓', 'success');

            // Refresh cart and open drawer
            await this.fetchCart();
            setTimeout(() => {
                this.openDrawer();
            }, 400);

            return true;
        } catch (err) {
            console.error('CartManager: Add to cart error', err);
            this.showToast('Network error while adding to cart', 'error');
            return false;
        }
    },

    async updateQty(cartId, productId, variantId, newQty) {
        if (this.cartData && this.cartData.is_logged_in) {
            const formData = new FormData();
            formData.append('action', 'update_qty');
            formData.append('cart_id', cartId);
            formData.append('quantity', newQty);

            const res = await fetch(this.getApiUrl(), { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                this.showToast(data.message, 'warning');
            }
        } else {
            // Guest update in localStorage
            let guestCart = this.getGuestCart();
            if (newQty <= 0) {
                guestCart = guestCart.filter(i => !(i.product_id === productId && ((!i.variant_id && !variantId) || i.variant_id === variantId)));
            } else {
                const item = guestCart.find(i => i.product_id === productId && ((!i.variant_id && !variantId) || i.variant_id === variantId));
                if (item) item.quantity = Math.min(10, newQty);
            }
            this.saveGuestCart(guestCart);
        }
        await this.fetchCart();
    },

    async removeItem(cartId, productId, variantId) {
        if (this.cartData && this.cartData.is_logged_in) {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('cart_id', cartId);
            await fetch(this.getApiUrl(), { method: 'POST', body: formData });
        } else {
            let guestCart = this.getGuestCart();
            guestCart = guestCart.filter(i => !(i.product_id === productId && ((!i.variant_id && !variantId) || i.variant_id === variantId)));
            this.saveGuestCart(guestCart);
        }
        this.showToast('Item removed from cart', 'info');
        await this.fetchCart();
    },

    async moveToWishlist(cartId) {
        if (!this.cartData || !this.cartData.is_logged_in) {
            this.showToast('Please login to save items to your wishlist', 'info');
            if (typeof openAuthModal === 'function') openAuthModal('login');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'move_to_wishlist');
        formData.append('cart_id', cartId);

        const res = await fetch(this.getApiUrl(), { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            this.showToast(data.message, 'success');
            await this.fetchCart();
        } else {
            this.showToast(data.message, 'error');
        }
    },

    // -------------------------------------------------------------
    // QUICK ADD MODAL LOGIC
    // -------------------------------------------------------------
    async openQuickAdd(productId, triggerBtn = null) {
        try {
            const res = await fetch(this.getApiUrl(`action=get_product_variants&product_id=${productId}`));
            const data = await res.json();

            if (!data.success || !data.product) {
                this.showToast('Product details unavailable', 'error');
                return;
            }

            const p = data.product;

            // If no variants, directly add to cart
            if (!p.has_variants || p.colors.length === 0 || (p.colors.length === 1 && p.colors[0].sizes.length === 0)) {
                this.addToCart(p.id, null, 1, triggerBtn);
                return;
            }

            // Populate Modal
            this.activeQuickAdd = {
                product: p,
                triggerElement: triggerBtn,
                selectedColor: p.colors[0],
                selectedSize: null,
                quantity: 1
            };

            document.getElementById('qaProductImage').src = this.resolveImagePath(p.image);
            document.getElementById('qaBrand').innerText = p.brand || 'Official';
            document.getElementById('qaTitle').innerText = p.name;
            document.getElementById('qaPrice').innerText = 'Rs. ' + Number(p.unit_price).toLocaleString();
            
            const origPriceEl = document.getElementById('qaOrigPrice');
            const hdBadgeEl = document.getElementById('qaHotDealBadge');
            if (p.is_hot_deal) {
                origPriceEl.innerText = 'Rs. ' + Number(p.original_price).toLocaleString();
                origPriceEl.classList.remove('hidden');
                hdBadgeEl.innerText = `-${p.discount_percent}% HOT DEAL`;
                hdBadgeEl.classList.remove('hidden');
            } else {
                origPriceEl.classList.add('hidden');
                hdBadgeEl.classList.add('hidden');
            }

            document.getElementById('qaQtyDisplay').innerText = '1';
            document.getElementById('qaTotalDisplay').innerText = 'Rs. ' + Number(p.unit_price).toLocaleString();

            // Render Colors
            const colorSection = document.getElementById('qaColorSection');
            const colorOptions = document.getElementById('qaColorOptions');
            if (p.colors.length > 1 || (p.colors.length === 1 && p.colors[0].color_name !== 'Default')) {
                colorSection.classList.remove('hidden');
                document.getElementById('qaSelectedColorName').innerText = p.colors[0].color_name;
                colorOptions.innerHTML = p.colors.map((c, idx) => `
                    <button type="button" onclick="CartManager.selectColor(${idx})" class="qa-color-btn px-3 py-1.5 rounded-xl border-2 text-xs font-bold transition-all ${idx === 0 ? 'border-primary bg-blue-50/50 text-primary' : 'border-gray-200 text-gray-600 hover:border-gray-300'}">
                        ${c.color_name}
                    </button>
                `).join('');
            } else {
                colorSection.classList.add('hidden');
            }

            // Render Sizes for first color
            this.renderQuickAddSizes(p.colors[0]);

            // Show Modal
            const overlay = document.getElementById('quickAddModalOverlay');
            const modal = document.getElementById('quickAddModal');
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
                modal.classList.remove('scale-95', 'opacity-0');
                modal.classList.add('scale-100', 'opacity-100');
            }, 10);

        } catch (err) {
            console.error('CartManager: Error in openQuickAdd', err);
        }
    },

    selectColor(idx) {
        if (!this.activeQuickAdd) return;
        const color = this.activeQuickAdd.product.colors[idx];
        this.activeQuickAdd.selectedColor = color;
        this.activeQuickAdd.selectedSize = null;
        document.getElementById('qaSelectedColorName').innerText = color.color_name;

        // Highlight selected button
        const buttons = document.querySelectorAll('.qa-color-btn');
        buttons.forEach((b, i) => {
            if (i === idx) {
                b.className = 'qa-color-btn px-3 py-1.5 rounded-xl border-2 text-xs font-bold transition-all border-primary bg-blue-50/50 text-primary';
            } else {
                b.className = 'qa-color-btn px-3 py-1.5 rounded-xl border-2 text-xs font-bold transition-all border-gray-200 text-gray-600 hover:border-gray-300';
            }
        });

        if (color.color_image) {
            document.getElementById('qaProductImage').src = this.resolveImagePath(color.color_image);
        }

        this.renderQuickAddSizes(color);
    },

    renderQuickAddSizes(color) {
        const sizeSection = document.getElementById('qaSizeSection');
        const sizeOptions = document.getElementById('qaSizeOptions');
        const stockWarning = document.getElementById('qaStockWarning');

        if (!color || !color.sizes || color.sizes.length === 0) {
            sizeSection.classList.add('hidden');
            return;
        }

        sizeSection.classList.remove('hidden');
        document.getElementById('qaSelectedSizeName').innerText = 'Select';
        stockWarning.innerText = 'Select a size';
        stockWarning.className = 'text-[11px] font-bold text-gray-400';

        sizeOptions.innerHTML = color.sizes.map((s, idx) => {
            const isOutOfStock = s.stock <= 0;
            const isLowStock = s.stock > 0 && s.stock <= 3;
            return `
                <button type="button" 
                        onclick="${isOutOfStock ? '' : `CartManager.selectSize(${idx})`}" 
                        class="qa-size-btn p-2 rounded-xl border-2 text-center transition-all ${isOutOfStock ? 'opacity-40 cursor-not-allowed border-gray-200 bg-gray-50' : 'hover:border-primary border-gray-200'}" 
                        ${isOutOfStock ? 'disabled' : ''}>
                    <div class="text-xs font-black text-navy uppercase">${s.size}</div>
                    <div class="text-[9px] font-bold ${isOutOfStock ? 'text-red-500' : (isLowStock ? 'text-amber-500' : 'text-emerald-500')}">
                        ${isOutOfStock ? 'Out' : (isLowStock ? `${s.stock} left` : 'In stock')}
                    </div>
                </button>
            `;
        }).join('');

        // Auto-select first in-stock size
        const firstInStockIdx = color.sizes.findIndex(s => s.stock > 0);
        if (firstInStockIdx > -1) {
            this.selectSize(firstInStockIdx);
        }
    },

    selectSize(idx) {
        if (!this.activeQuickAdd || !this.activeQuickAdd.selectedColor) return;
        const sizeObj = this.activeQuickAdd.selectedColor.sizes[idx];
        this.activeQuickAdd.selectedSize = sizeObj;
        document.getElementById('qaSelectedSizeName').innerText = sizeObj.size;

        const stockWarning = document.getElementById('qaStockWarning');
        if (sizeObj.stock <= 3) {
            stockWarning.innerText = `Only ${sizeObj.stock} left in stock!`;
            stockWarning.className = 'text-[11px] font-bold text-amber-600 animate-pulse';
        } else {
            stockWarning.innerText = 'In stock ✓';
            stockWarning.className = 'text-[11px] font-bold text-emerald-600';
        }

        // Highlight selected size button
        const buttons = document.querySelectorAll('.qa-size-btn');
        buttons.forEach((b, i) => {
            if (i === idx) {
                b.classList.add('border-primary', 'bg-blue-50/40', 'ring-2', 'ring-primary/20');
                b.classList.remove('border-gray-200');
            } else {
                b.classList.remove('border-primary', 'bg-blue-50/40', 'ring-2', 'ring-primary/20');
                b.classList.add('border-gray-200');
            }
        });

        // Price update if variant price differs
        let unitPrice = sizeObj.price > 0 ? sizeObj.price : this.activeQuickAdd.product.unit_price;
        if (this.activeQuickAdd.product.is_hot_deal) {
            unitPrice = this.activeQuickAdd.product.unit_price;
        }
        this.activeQuickAdd.unitPrice = unitPrice;
        document.getElementById('qaPrice').innerText = 'Rs. ' + Number(unitPrice).toLocaleString();
        this.updateQuickAddTotal();
    },

    adjustQuickAddQty(delta) {
        if (!this.activeQuickAdd) return;
        let newQty = this.activeQuickAdd.quantity + delta;
        const maxStock = this.activeQuickAdd.selectedSize ? this.activeQuickAdd.selectedSize.stock : 10;
        const maxLimit = Math.min(10, maxStock);

        if (newQty < 1) newQty = 1;
        if (newQty > maxLimit) {
            newQty = maxLimit;
            this.showToast(`Maximum limit is ${maxLimit} units`, 'warning');
        }

        this.activeQuickAdd.quantity = newQty;
        document.getElementById('qaQtyDisplay').innerText = newQty;
        this.updateQuickAddTotal();
    },

    updateQuickAddTotal() {
        if (!this.activeQuickAdd) return;
        const unit = this.activeQuickAdd.unitPrice || this.activeQuickAdd.product.unit_price;
        const total = unit * this.activeQuickAdd.quantity;
        document.getElementById('qaTotalDisplay').innerText = 'Rs. ' + Number(total).toLocaleString();
    },

    confirmQuickAdd() {
        if (!this.activeQuickAdd) return;
        const p = this.activeQuickAdd.product;
        const color = this.activeQuickAdd.selectedColor;
        const size = this.activeQuickAdd.selectedSize;
        const qty = this.activeQuickAdd.quantity;

        // Check if size required
        if (color && color.sizes && color.sizes.length > 0 && !size) {
            this.showToast('Please select a size first', 'warning');
            return;
        }

        const variantId = size ? size.variant_id : null;
        const trigger = this.activeQuickAdd.triggerElement || document.getElementById('qaProductImage');

        const btn = document.getElementById('qaConfirmBtn');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled = true;

        this.addToCart(p.id, variantId, qty, trigger).then((success) => {
            btn.innerHTML = origText;
            btn.disabled = false;
            if (success) {
                this.closeQuickAdd();
            }
        });
    },

    closeQuickAdd() {
        const overlay = document.getElementById('quickAddModalOverlay');
        const modal = document.getElementById('quickAddModal');
        if (!overlay || !modal) return;
        modal.classList.remove('scale-100', 'opacity-100');
        modal.classList.add('scale-95', 'opacity-0');
        overlay.classList.add('opacity-0');
        setTimeout(() => {
            overlay.classList.add('hidden');
            this.activeQuickAdd = null;
        }, 300);
    },

    // -------------------------------------------------------------
    // FLY TO CART ANIMATION
    // -------------------------------------------------------------
    flyToCart(sourceElement) {
        if (!sourceElement) return;

        // Locate image in sourceElement or find nearest img
        let img = sourceElement.querySelector('img') || sourceElement;
        if (img.tagName !== 'IMG') {
            const card = sourceElement.closest('.group') || sourceElement.closest('div');
            if (card) img = card.querySelector('img') || img;
        }

        const target = document.getElementById('cartBadge') || document.querySelector('.fa-shopping-bag');
        if (!img || !target) return;

        const startRect = img.getBoundingClientRect();
        const endRect = target.getBoundingClientRect();

        const flyer = document.createElement('div');
        flyer.style.position = 'fixed';
        flyer.style.zIndex = '99999';
        flyer.style.left = startRect.left + 'px';
        flyer.style.top = startRect.top + 'px';
        flyer.style.width = startRect.width + 'px';
        flyer.style.height = startRect.height + 'px';
        flyer.style.borderRadius = '16px';
        flyer.style.backgroundImage = `url('${img.src}')`;
        flyer.style.backgroundSize = 'cover';
        flyer.style.backgroundPosition = 'center';
        flyer.style.boxShadow = '0 10px 30px rgba(0,102,255,0.4)';
        flyer.style.pointerEvents = 'none';
        flyer.style.transition = 'all 0.65s cubic-bezier(0.25, 1, 0.5, 1)';
        document.body.appendChild(flyer);

        requestAnimationFrame(() => {
            flyer.style.left = (endRect.left + endRect.width / 2 - 16) + 'px';
            flyer.style.top = (endRect.top + endRect.height / 2 - 16) + 'px';
            flyer.style.width = '32px';
            flyer.style.height = '32px';
            flyer.style.opacity = '0.3';
            flyer.style.transform = 'scale(0.3) rotate(15deg)';
        });

        setTimeout(() => {
            flyer.remove();
            // Pulse the cart icon
            target.parentElement.classList.add('scale-125', 'text-primary');
            setTimeout(() => {
                target.parentElement.classList.remove('scale-125', 'text-primary');
            }, 300);
        }, 650);
    },

    // -------------------------------------------------------------
    // DRAWER UI & RENDERING
    // -------------------------------------------------------------
    openDrawer() {
        const overlay = document.getElementById('cartDrawerOverlay');
        const drawer = document.getElementById('cartDrawer');
        if (!overlay || !drawer) return;

        overlay.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        drawer.classList.add('translate-x-0');
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
        }, 10);
        document.body.style.overflow = 'hidden';
    },

    closeDrawer() {
        const overlay = document.getElementById('cartDrawerOverlay');
        const drawer = document.getElementById('cartDrawer');
        if (!overlay || !drawer) return;

        drawer.classList.remove('translate-x-0');
        drawer.classList.add('translate-x-full');
        overlay.classList.add('opacity-0');
        setTimeout(() => {
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    },

    updateBadge(count) {
        const badges = document.querySelectorAll('.cart-badge, #cartBadge, #cartBadgeMobile');
        badges.forEach(b => {
            b.innerText = count > 9 ? '9+' : count;
            if (count > 0) {
                b.classList.remove('hidden');
            } else {
                b.innerText = '0';
            }
        });
    },

    renderDrawer(data) {
        const body = document.getElementById('cartDrawerBody');
        const emptyState = document.getElementById('cartDrawerEmpty');
        const footer = document.getElementById('cartDrawerFooter');
        const countHeader = document.getElementById('cartDrawerCountHeader');

        countHeader.innerText = `(${data.count})`;

        if (data.count === 0) {
            body.classList.add('hidden');
            footer.classList.add('hidden');
            emptyState.classList.remove('hidden');
            document.getElementById('freeShippingBanner').classList.add('hidden');
            return;
        }

        body.classList.remove('hidden');
        footer.classList.remove('hidden');
        emptyState.classList.add('hidden');
        document.getElementById('freeShippingBanner').classList.remove('hidden');

        // Free shipping progress bar
        const fsText = document.getElementById('freeShippingText');
        const fsBar = document.getElementById('freeShippingBar');
        const fsPercent = document.getElementById('freeShippingPercent');

        if (data.is_free_shipping) {
            fsText.innerHTML = '<i class="fas fa-check-circle text-emerald-500"></i> <span class="text-emerald-700 font-bold">🎉 You have unlocked FREE Shipping!</span>';
            fsBar.style.width = '100%';
            fsBar.className = 'h-full bg-emerald-500 rounded-full transition-all duration-500';
            fsPercent.innerText = '100%';
        } else {
            const pct = Math.min(100, Math.round((data.subtotal / 5000) * 100));
            fsText.innerHTML = `<i class="fas fa-truck-fast text-primary"></i> Add <span class="text-primary font-black">Rs. ${Number(data.free_shipping_remaining).toLocaleString()}</span> more for FREE Delivery!`;
            fsBar.style.width = pct + '%';
            fsBar.className = 'h-full bg-primary rounded-full transition-all duration-500';
            fsPercent.innerText = pct + '%';
        }

        // Render Grouped Sellers & Items
        let html = '';
        data.sellers.forEach(seller => {
            html += `
                <div class="seller-block bg-white rounded-2xl border border-gray-100 p-4 shadow-sm space-y-3">
                    <div class="flex items-center justify-between pb-2.5 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-store text-primary text-xs"></i>
                            <span class="text-xs font-black text-navy uppercase tracking-wider">${seller.seller_name}</span>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100">
                            ${seller.items.length} ${seller.items.length === 1 ? 'item' : 'items'}
                        </span>
                    </div>

                    <div class="space-y-4">
            `;

            seller.items.forEach(item => {
                const isHot = item.is_hot_deal;
                const hasPriceChange = item.price_change !== null;
                const itemImg = this.resolveImagePath(item.image);
                const detailUrl = (window.location.pathname.includes('/site/') ? '' : 'site/') + `product-details.php?id=${item.product_id}`;

                html += `
                    <div class="flex gap-3.5 items-start relative group">
                        <!-- Image -->
                        <a href="${detailUrl}" class="w-16 h-16 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden flex-shrink-0 p-1 block">
                            <img src="${itemImg}" alt="${item.name}" class="w-full h-full object-contain mix-blend-multiply">
                        </a>

                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <a href="${detailUrl}" class="text-xs font-black text-navy hover:text-primary transition-colors uppercase truncate pr-4 block">
                                    ${item.name}
                                </a>
                                <button onclick="CartManager.removeItem(${item.cart_id}, ${item.product_id}, ${item.variant_id || 'null'})" class="text-gray-300 hover:text-red-500 transition-colors text-xs p-1" title="Remove">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>

                            <div class="text-[11px] font-bold text-gray-400 mt-0.5">
                                ${item.size} ${item.color ? '&bull; ' + item.color : ''}
                            </div>

                            ${hasPriceChange ? `
                                <div class="mt-1 p-1 px-2 rounded bg-amber-50 border border-amber-200 text-amber-800 text-[10px] font-bold flex items-center gap-1">
                                    <i class="fas fa-exclamation-triangle text-amber-500"></i>
                                    Price updated from Rs. ${Number(item.price_change.from).toLocaleString()} to Rs. ${Number(item.price_change.to).toLocaleString()}
                                </div>
                            ` : ''}

                            <div class="flex items-baseline gap-2 mt-1.5">
                                ${isHot ? `
                                    <span class="text-[10px] text-gray-400 line-through">Rs. ${Number(item.original_price).toLocaleString()}</span>
                                    <span class="text-xs font-black text-emerald-600">Rs. ${Number(item.unit_price).toLocaleString()}</span>
                                    <span class="text-[9px] font-black px-1.5 py-0.2 rounded bg-[#CCFF00] text-black uppercase">-${item.discount_percent}%</span>
                                ` : `
                                    <span class="text-xs font-black text-navy">Rs. ${Number(item.unit_price).toLocaleString()}</span>
                                `}
                            </div>

                            <!-- Stepper & Actions -->
                            <div class="flex items-center justify-between mt-2 pt-1">
                                <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-gray-50 h-7">
                                    <button onclick="CartManager.updateQty(${item.cart_id}, ${item.product_id}, ${item.variant_id || 'null'}, ${item.quantity - 1})" class="w-7 h-full flex items-center justify-center text-gray-500 hover:text-navy hover:bg-gray-100 transition-colors">
                                        <i class="fas fa-minus text-[9px]"></i>
                                    </button>
                                    <span class="w-8 h-full flex items-center justify-center font-black text-navy text-xs bg-white border-x border-gray-200">${item.quantity}</span>
                                    <button onclick="CartManager.updateQty(${item.cart_id}, ${item.product_id}, ${item.variant_id || 'null'}, ${item.quantity + 1})" class="w-7 h-full flex items-center justify-center text-gray-500 hover:text-navy hover:bg-gray-100 transition-colors">
                                        <i class="fas fa-plus text-[9px]"></i>
                                    </button>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button onclick="CartManager.moveToWishlist(${item.cart_id})" class="text-[10px] font-bold text-primary hover:underline" title="Save for Later">
                                        Save for later
                                    </button>
                                    <span class="text-[10px] font-bold ${item.stock_status === 'out_of_stock' ? 'text-red-500' : (item.stock_status === 'low_stock' ? 'text-amber-500' : 'text-emerald-500')}">
                                        ${item.stock_label}
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        body.innerHTML = html;

        // Update Totals
        document.getElementById('drawerSubtotal').innerText = 'Rs. ' + Number(data.subtotal).toLocaleString();
        if (data.is_free_shipping || data.shipping_fee === 0) {
            document.getElementById('drawerShipping').innerHTML = '<span class="text-[#CCFF00] font-black uppercase text-xs px-2 py-0.5 bg-navy rounded shadow-sm">FREE</span> <span class="text-gray-400 line-through text-[10px] ml-1">Rs. 300</span>';
        } else {
            document.getElementById('drawerShipping').innerText = 'Rs. ' + Number(data.shipping_fee).toLocaleString();
        }
        
        const discRow = document.getElementById('drawerDiscountRow');
        if (data.coupon_discount > 0) {
            discRow.classList.remove('hidden');
            document.getElementById('drawerDiscount').innerText = '-Rs. ' + Number(data.coupon_discount).toLocaleString();
        } else {
            discRow.classList.add('hidden');
        }

        document.getElementById('drawerTotal').innerText = 'Rs. ' + Number(data.total).toLocaleString();

        // Update Coupon Badge
        const cBadge = document.getElementById('couponAppliedBadge');
        const cForm = document.getElementById('drawerCouponForm');
        if (data.coupon) {
            cBadge.classList.remove('hidden');
            document.getElementById('appliedCouponCode').innerText = data.coupon.code;
            cForm.classList.add('hidden');
        } else {
            cBadge.classList.add('hidden');
            cForm.classList.remove('hidden');
        }
    },

    // -------------------------------------------------------------
    // COUPON APPLY / REMOVE
    // -------------------------------------------------------------
    async applyCoupon(code) {
        if (!code) return;
        const formData = new FormData();
        formData.append('action', 'apply_coupon');
        formData.append('code', code);

        const res = await fetch(this.getApiUrl(), { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            this.showToast(data.message, 'success');
            await this.fetchCart();
        } else {
            this.showToast(data.message, 'error');
        }
    },

    async removeCoupon() {
        const formData = new FormData();
        formData.append('action', 'remove_coupon');
        const res = await fetch(this.getApiUrl(), { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            this.showToast(data.message, 'info');
            await this.fetchCart();
        }
    },

    showToast(message, type = 'success') {
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            Toast.fire({ icon: type, title: message });
        } else {
            alert(message);
        }
    }
};

// Global hookups
window.openCartDrawer = () => CartManager.openDrawer();
window.closeCartDrawer = () => CartManager.closeDrawer();
window.toggleCartSidebar = () => {
    const drawer = document.getElementById('cartDrawer');
    if (drawer && drawer.classList.contains('translate-x-0')) {
        CartManager.closeDrawer();
    } else {
        CartManager.openDrawer();
    }
};
window.quickAddToCart = (productId, btn = null) => CartManager.openQuickAdd(productId, btn);
window.closeQuickAddModal = () => CartManager.closeQuickAdd();
window.adjustQuickAddQty = (delta) => CartManager.adjustQuickAddQty(delta);
window.confirmQuickAdd = () => CartManager.confirmQuickAdd();
window.applyCartCoupon = (e) => {
    e.preventDefault();
    const input = document.getElementById('drawerCouponInput');
    if (input) CartManager.applyCoupon(input.value.trim());
};
window.removeCartCoupon = () => CartManager.removeCoupon();

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    CartManager.init();
});
