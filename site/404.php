<?php
http_response_code(404);
$page_title = 'Page Not Found - OXXA GEAR';
$is_404 = true;
include('../include/header.php');
?>

<div class="bg-gray-50 min-h-[80vh] flex items-center justify-center py-20 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    
    <!-- Background abstract elements -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
        <div class="absolute -top-[10%] -right-[5%] w-[500px] h-[500px] rounded-full bg-blue-500/5 blur-3xl"></div>
        <div class="absolute -bottom-[10%] -left-[5%] w-[400px] h-[400px] rounded-full bg-navy/5 blur-3xl"></div>
    </div>

    <div class="max-w-3xl w-full text-center relative z-10">
        <!-- 404 Graphic -->
        <div class="mb-8 relative inline-block">
            <h1 class="text-[120px] md:text-[180px] font-black text-navy leading-none tracking-tighter drop-shadow-sm select-none">
                4<span class="text-[#0066FF]">0</span>4
            </h1>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full flex justify-center">
                <div class="bg-white/90 backdrop-blur-sm px-6 py-2 rounded-full shadow-lg border border-gray-100 transform -rotate-12">
                    <span class="text-sm font-bold text-red-500 uppercase tracking-widest"><i class="fas fa-exclamation-triangle me-2"></i>Out of Bounds</span>
                </div>
            </div>
        </div>

        <h2 class="text-3xl md:text-4xl font-black text-navy uppercase tracking-wide mb-4">
            Looks like you're off track!
        </h2>
        
        <p class="text-slate text-lg max-w-xl mx-auto mb-10">
            The gear you're looking for has either been moved, discontinued, or never existed in our inventory. Let's get you back in the game.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="index.php" class="w-full sm:w-auto bg-[#0066FF] hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex items-center justify-center hover:-translate-y-1">
                <i class="fas fa-home me-2"></i> Back to Home
            </a>
            <a href="products.php" class="w-full sm:w-auto bg-white hover:bg-gray-50 text-navy border-2 border-gray-200 px-8 py-4 rounded-xl font-bold uppercase tracking-wide transition-all flex items-center justify-center hover:-translate-y-1">
                <i class="fas fa-shopping-bag me-2"></i> Shop Gear
            </a>
        </div>
        
        <!-- Search help -->
        <div class="mt-16 pt-8 border-t border-gray-200 max-w-md mx-auto">
            <p class="text-sm text-gray-500 font-medium mb-4 uppercase tracking-widest">Or try searching</p>
            <form action="products.php" method="GET" class="relative">
                <input type="text" name="search" placeholder="Search for products, brands..." class="w-full bg-white border border-gray-200 text-navy rounded-xl py-3 pl-5 pr-12 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all shadow-sm">
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-gray-400 hover:text-[#0066FF] transition-colors">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<?php
include("../include/footer.php");
?>
