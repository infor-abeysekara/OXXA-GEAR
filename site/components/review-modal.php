<!-- Review Modal -->
<div id="reviewModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeReviewModal()"></div>
    <div class="bg-white rounded-[2rem] w-full max-w-lg relative z-10 overflow-hidden shadow-2xl">
        <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-xl font-black text-navy uppercase tracking-widest">Write a Review</h3>
            <button onclick="closeReviewModal()" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 hover:border-red-500 transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form action="components/submit-review.php" method="POST" enctype="multipart/form-data" class="p-6 overflow-y-auto max-h-[70vh] custom-scrollbar">
            <input type="hidden" name="order_id" id="review_order_id">
            <input type="hidden" name="product_id" id="review_product_id">
            <input type="hidden" name="rating" id="review_rating" value="5">

            <!-- Star Rating -->
            <div class="mb-6 flex flex-col items-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Overall Rating</p>
                <div class="flex items-center justify-center gap-2" id="star_rating_container">
                    <i class="fas fa-star text-3xl text-yellow-400 cursor-pointer transition-transform hover:scale-110" data-rating="1"></i>
                    <i class="fas fa-star text-3xl text-yellow-400 cursor-pointer transition-transform hover:scale-110" data-rating="2"></i>
                    <i class="fas fa-star text-3xl text-yellow-400 cursor-pointer transition-transform hover:scale-110" data-rating="3"></i>
                    <i class="fas fa-star text-3xl text-yellow-400 cursor-pointer transition-transform hover:scale-110" data-rating="4"></i>
                    <i class="fas fa-star text-3xl text-yellow-400 cursor-pointer transition-transform hover:scale-110" data-rating="5"></i>
                </div>
            </div>

            <div class="mb-5">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Review Title</label>
                <input type="text" name="title" required placeholder="Example: Very comfortable and true to size!" class="w-full bg-gray-50 border border-gray-200 text-navy font-bold rounded-xl px-4 py-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF]">
            </div>

            <div class="mb-5">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Review Details</label>
                <textarea name="comment" required rows="4" placeholder="How is the fit? Is the color accurate? Would you recommend this?" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl px-4 py-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF]"></textarea>
            </div>

            <!-- Fit Feedback Pills -->
            <div class="mb-5">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">How does it fit?</label>
                <input type="hidden" name="fit_feedback" id="review_fit" value="True to Size">
                <div class="flex gap-2">
                    <button type="button" onclick="selectFit(this, 'Runs Small')" class="fit-pill flex-1 py-2 rounded-xl text-xs font-bold uppercase tracking-wide border-2 border-gray-100 text-gray-500 hover:border-[#0066FF] hover:text-[#0066FF] transition-colors">Runs Small</button>
                    <button type="button" onclick="selectFit(this, 'True to Size')" class="fit-pill active flex-1 py-2 rounded-xl text-xs font-bold uppercase tracking-wide border-2 border-[#0066FF] bg-blue-50 text-[#0066FF] transition-colors">True to Size</button>
                    <button type="button" onclick="selectFit(this, 'Runs Large')" class="fit-pill flex-1 py-2 rounded-xl text-xs font-bold uppercase tracking-wide border-2 border-gray-100 text-gray-500 hover:border-[#0066FF] hover:text-[#0066FF] transition-colors">Runs Large</button>
                </div>
            </div>

            <!-- Add Photos -->
            <div class="mb-5">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Add Photos (Max 4)</label>
                <input type="file" name="review_images[]" id="review_images" multiple accept="image/*" class="hidden" onchange="previewImages()">
                
                <div class="flex gap-3 overflow-x-auto pb-2" id="image_preview_container">
                    <button type="button" onclick="document.getElementById('review_images').click()" class="w-20 h-20 flex-shrink-0 border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center text-gray-400 hover:border-[#0066FF] hover:text-[#0066FF] transition-colors bg-gray-50">
                        <i class="fas fa-camera text-xl mb-1"></i>
                        <span class="text-[10px] font-bold uppercase">Upload</span>
                    </button>
                </div>
            </div>

            <!-- Anonymous Option -->
            <div class="mb-6 flex items-center">
                <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1" class="w-4 h-4 text-[#0066FF] border-gray-300 rounded focus:ring-[#0066FF]">
                <label for="is_anonymous" class="ml-2 text-sm font-medium text-gray-600">Post anonymously</label>
            </div>

            <button type="submit" class="w-full bg-[#0066FF] text-white font-black rounded-xl py-4 hover:bg-blue-700 transition-colors uppercase tracking-widest shadow-lg shadow-blue-500/30">
                Submit Review
            </button>
        </form>
    </div>
</div>

<script>
// Star Rating Logic
const stars = document.querySelectorAll('#star_rating_container i');
const ratingInput = document.getElementById('review_rating');

stars.forEach(star => {
    star.addEventListener('click', function() {
        const rating = this.getAttribute('data-rating');
        ratingInput.value = rating;
        
        // Update visual
        stars.forEach((s, index) => {
            if(index < rating) {
                s.classList.remove('far', 'text-gray-300');
                s.classList.add('fas', 'text-yellow-400');
            } else {
                s.classList.remove('fas', 'text-yellow-400');
                s.classList.add('far', 'text-gray-300');
            }
        });
    });
});

// Fit Selection Logic
function selectFit(btn, fitValue) {
    document.getElementById('review_fit').value = fitValue;
    
    // Reset all pills
    document.querySelectorAll('.fit-pill').forEach(p => {
        p.classList.remove('border-[#0066FF]', 'bg-blue-50', 'text-[#0066FF]', 'active');
        p.classList.add('border-gray-100', 'text-gray-500');
    });
    
    // Activate clicked pill
    btn.classList.remove('border-gray-100', 'text-gray-500');
    btn.classList.add('border-[#0066FF]', 'bg-blue-50', 'text-[#0066FF]', 'active');
}

// Image Preview Logic
function previewImages() {
    const input = document.getElementById('review_images');
    const container = document.getElementById('image_preview_container');
    
    // Keep the upload button, remove previous previews
    container.innerHTML = `
        <button type="button" onclick="document.getElementById('review_images').click()" class="w-20 h-20 flex-shrink-0 border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center text-gray-400 hover:border-[#0066FF] hover:text-[#0066FF] transition-colors bg-gray-50">
            <i class="fas fa-camera text-xl mb-1"></i>
            <span class="text-[10px] font-bold uppercase">Upload</span>
        </button>
    `;

    if (input.files) {
        // Enforce max 4
        if (input.files.length > 4) {
            alert('You can only upload a maximum of 4 images.');
            input.value = '';
            return;
        }

        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgWrap = document.createElement('div');
                imgWrap.className = 'w-20 h-20 flex-shrink-0 rounded-xl overflow-hidden border border-gray-200 relative';
                imgWrap.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                container.appendChild(imgWrap);
            }
            reader.readAsDataURL(file);
        });
    }
}
</script>
