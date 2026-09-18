
        const usersData = [];
        let filteredUsers = [...usersData];
        let currentTab = 'all';
        let selectedIds = new Set();
        
        const tableBody = document.getElementById('tableBody');
        const noResults = document.getElementById('noResults');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const typeFilter = document.getElementById('typeFilter');
        const selectAll = document.getElementById('selectAll');
        const bulkActionBar = document.getElementById('bulkActionBar');
        const selectedCount = document.getElementById('selectedCount');
        
        function getInitials(firstName, lastName) {
            return ((firstName || '').charAt(0) + (lastName || '').charAt(0)).toUpperCase() || 'U';
        }
        
        function formatDate(dateString) {
            if(!dateString || dateString === '0000-00-00 00:00:00') return 'Unknown';
            const d = new Date(dateString);
            if (isNaN(d.getTime())) return 'Unknown';
            return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }
        
        function renderTable() {
            tableBody.innerHTML = '';
            
            if(filteredUsers.length === 0) {
                noResults.classList.remove('hidden');
                return;
            } else {
                noResults.classList.add('hidden');
            }
            
            filteredUsers.forEach(user => {
                try {
                    const tr = document.createElement('tr');
                    tr.className = 'table-row text-sm';
                    
                    const isSelected = selectedIds.has(parseInt(user.id));
                
                // Avatar html
                let avatarHtml = '';
                if(user.profile_image) {
                    avatarHtml = `<img src="../assets/uploads/profiles/${user.profile_image}" class="w-10 h-10 rounded-full object-cover shadow-sm">`;
                } else {
                    avatarHtml = `<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-black shadow-sm">${getInitials(user.first_name, user.last_name)}</div>`;
                }
                
                // Role pill
                let roleHtml = '';
                if(user.user_type === 'seller') {
                    const statusText = user.business_approved == 1 ? 'Approved' : (user.business_approved == 0 ? 'Pending' : 'Rejected');
                    const statusColor = user.business_approved == 1 ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                    const bType = user.business_type ? `<span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full font-semibold ml-2">${user.business_type}</span>` : '';
                    
                    roleHtml = `
                        <div class="font-bold text-navy">${user.business_name || 'Not Setup'}</div>
                        <div class="mt-1 flex items-center">
                            <span class="${statusColor} text-[10px] uppercase font-bold px-2 py-0.5 rounded-full">${statusText}</span>
                            ${bType}
                        </div>
                    `;
                } else {
                    roleHtml = `<span class="bg-blue-50 text-blue-600 font-bold px-3 py-1 rounded-full text-xs uppercase tracking-wider">Customer</span>`;
                }
                
                // Stats HTML
                let statsHtml = '';
                if(user.user_type === 'seller') {
                    statsHtml = `<div class="text-xs font-semibold text-gray-600">Products: <span class="text-navy">${user.total_products}</span> | Orders: <span class="text-navy">${user.seller_orders}</span> <br> Earned: <span class="text-green-600 font-bold">Rs.${Number(user.total_earned).toLocaleString()}</span></div>`;
                } else {
                    statsHtml = `<div class="text-xs font-semibold text-gray-600">Orders: <span class="text-navy">${user.buyer_orders}</span> <br> Spent: <span class="text-green-600 font-bold">Rs.${Number(user.total_spent).toLocaleString()}</span></div>`;
                }
                
                // Status HTML
                let statusHtml = '';
                if(user.is_approved == 1) {
                    statusHtml = `<div class="flex items-center gap-2"><span class="pulse-dot pulse-active"></span><span class="font-bold text-green-600 text-xs uppercase tracking-wider">Active</span></div>`;
                } else {
                    statusHtml = `<div class="flex items-center gap-2"><span class="pulse-dot pulse-suspended"></span><span class="font-bold text-yellow-600 text-xs uppercase tracking-wider">Suspended</span></div>`;
                }
                
                tr.innerHTML = `
                    <td class="p-4 pl-6"><input type="checkbox" class="custom-checkbox row-cb" value="${user.id}" ${isSelected ? 'checked' : ''}></td>
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            ${avatarHtml}
                            <div>
                                <div class="font-bold text-navy">${user.first_name || ''} ${user.last_name || ''}</div>
                                <div class="text-xs text-gray-400">@${user.username} <span class="mx-1">•</span> Joined ${formatDate(user.created_at)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <div class="font-semibold text-gray-700 flex items-center gap-1"><a href="mailto:${user.email}" class="hover:text-primary transition-colors">${user.email}</a> <i class="fas fa-check-circle text-green-500 text-xs ml-1" title="Verified"></i></div>
                        <div class="text-xs text-gray-500 mt-1 cursor-pointer flex items-center gap-1" onclick="copyToClipboard('${user.phone || ''}')"><i class="fas fa-phone-alt"></i> ${user.phone || 'N/A'} <i class="far fa-copy opacity-0 group-hover:opacity-100 ml-1"></i></div>
                    </td>
                    <td class="p-4">${roleHtml}</td>
                    <td class="p-4">${statsHtml}</td>
                    <td class="p-4">${statusHtml}</td>
                    <td class="p-4 pr-6 text-right relative">
                        <button onclick="viewUser(${user.id})" class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors mr-2">View</button>
                        
                        <div class="inline-block relative">
                            <button onclick="toggleDropdown(this)" class="w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-500 flex items-center justify-center transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu absolute right-0 top-full mt-1 bg-white border border-gray-100 rounded-xl shadow-xl w-40 z-50 hidden text-left overflow-hidden">
                                <a href="#" class="block px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-user-circle w-5 text-gray-400"></i> Profile</a>
                                ${user.user_type === 'seller' ? `<a href="#" class="block px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-box w-5 text-gray-400"></i> Products</a>` : ''}
                                <a href="#" onclick="actionSingle('suspend', ${user.id}); return false;" class="block px-4 py-2 text-sm font-semibold text-yellow-600 hover:bg-yellow-50"><i class="fas fa-pause-circle w-5 text-yellow-400"></i> Suspend</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="#" onclick="actionSingle('delete', ${user.id}); return false;" class="block px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"><i class="fas fa-trash w-5 text-red-400"></i> Delete</a>
                            </div>
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
                } catch(e) {
                    console.error("Error rendering row for user:", user, e);
                }
            });
            
            // Attach checkbox events
            document.querySelectorAll('.row-cb').forEach(cb => {
                cb.addEventListener('change', function() {
                    if(this.checked) selectedIds.add(parseInt(this.value));
                    else selectedIds.delete(parseInt(this.value));
                    updateBulkBar();
                    
                    // Update select all state
                    const allVisibleCb = Array.from(document.querySelectorAll('.row-cb'));
                    selectAll.checked = allVisibleCb.length > 0 && allVisibleCb.every(c => c.checked);
                });
            });
        }
        
        function applyFilters() {
            const search = searchInput.value.toLowerCase();
            const status = statusFilter.value;
            const bType = typeFilter.value;
            
            filteredUsers = usersData.filter(user => {
                // Tab filter
                if(currentTab === 'seller' && user.user_type !== 'seller') return false;
                if(currentTab === 'customer' && user.user_type !== 'customer') return false;
                if(currentTab === 'suspended' && user.is_approved == 1) return false;
                if(currentTab === 'pending' && (user.user_type !== 'seller' || user.business_approved == 1)) return false;
                
                // Status Filter
                if(status === 'active' && user.is_approved == 0) return false;
                if(status === 'suspended' && user.is_approved == 1) return false;
                if(status === 'pending' && user.business_approved == 1) return false;
                
                // Type filter
                if(bType !== 'all' && user.business_type !== bType) return false;
                
                // Search
                if(search) {
                    const text = `${user.first_name} ${user.last_name} ${user.username} ${user.email} ${user.business_name || ''}`.toLowerCase();
                    if(!text.includes(search)) return false;
                }
                
                return true;
            });
            
            renderTable();
        }
        
        // Event Listeners for Filters
        if (searchInput) searchInput.addEventListener('keyup', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
        if (typeFilter) typeFilter.addEventListener('change', applyFilters);
        
        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                try {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentTab = this.getAttribute('data-tab');
                    applyFilters();
                } catch (err) {
                    console.error("Tab click error:", err);
                }
            });
        });
        
        // Select All
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const isChecked = this.checked;
                document.querySelectorAll('.row-cb').forEach(cb => {
                    cb.checked = isChecked;
                    if(isChecked) selectedIds.add(parseInt(cb.value));
                    else selectedIds.delete(parseInt(cb.value));
                });
                updateBulkBar();
            });
        }
        
        function updateBulkBar() {
            selectedCount.textContent = selectedIds.size;
            if(selectedIds.size > 0) {
                bulkActionBar.classList.remove('translate-y-24', 'opacity-0');
                bulkActionBar.classList.add('translate-y-0', 'opacity-100');
            } else {
                bulkActionBar.classList.add('translate-y-24', 'opacity-0');
                bulkActionBar.classList.remove('translate-y-0', 'opacity-100');
            }
        }
        
        // Dropdown toggle
        function toggleDropdown(btn) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
            const menu = btn.nextElementSibling;
            menu.classList.toggle('hidden');
            event.stopPropagation();
        }
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        });
        
        // Modal Logic
        function viewUser(id) {
            const user = usersData.find(u => u.id == id);
            if(!user) return;
            
            const modal = document.getElementById('viewUserModal');
            const content = document.getElementById('modalContent');
            
            let avatarHtml = user.profile_image 
                ? `<img src="../assets/uploads/profiles/${user.profile_image}" class="w-24 h-24 rounded-2xl object-cover shadow-lg border-4 border-white mb-4">`
                : `<div class="w-24 h-24 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-600 font-black text-4xl shadow-lg border-4 border-white mb-4">${getInitials(user.first_name, user.last_name)}</div>`;
                
            let leftHtml = `
                <div>
                    ${avatarHtml}
                    <h2 class="text-2xl font-black">${user.first_name} ${user.last_name}</h2>
                    <p class="text-gray-500 font-bold mb-4">@${user.username}</p>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center gap-3"><i class="fas fa-envelope text-gray-400 w-5"></i> <a href="mailto:${user.email}" class="font-semibold text-gray-700 hover:text-primary">${user.email}</a></div>
                        <div class="flex items-center gap-3"><i class="fas fa-phone text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">${user.phone || 'N/A'}</span></div>
                        <div class="flex items-center gap-3"><i class="fas fa-calendar-alt text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">Joined ${formatDate(user.created_at)}</span></div>
                    </div>
                    
                    ${user.user_type === 'seller' ? `
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                        <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Business Details</h4>
                        <div class="font-bold text-navy">${user.business_name || 'N/A'}</div>
                        <div class="text-sm font-semibold text-gray-600">${user.business_type || ''}</div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            let rightHtml = `
                <div>
                    <h4 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Performance Stats</h4>
                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <div class="bg-blue-50 rounded-xl p-4">
                            <div class="text-xs font-bold text-blue-400 uppercase tracking-wider">${user.user_type === 'seller' ? 'Products' : 'Orders'}</div>
                            <div class="text-2xl font-black text-blue-700">${user.user_type === 'seller' ? user.total_products : user.buyer_orders}</div>
                        </div>
                        <div class="bg-green-50 rounded-xl p-4">
                            <div class="text-xs font-bold text-green-500 uppercase tracking-wider">${user.user_type === 'seller' ? 'Revenue' : 'Spent'}</div>
                            <div class="text-xl font-black text-green-700">Rs.${Number(user.user_type === 'seller' ? user.total_earned : user.total_spent).toLocaleString()}</div>
                        </div>
                    </div>
                    
                    <h4 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Recent Activity Log</h4>
                    <div class="border-l-2 border-gray-100 ml-2 pl-4 py-2 space-y-4 mb-8">
                        <div class="relative">
                            <div class="absolute -left-[21px] top-1 w-3 h-3 rounded-full bg-primary ring-4 ring-white"></div>
                            <p class="text-sm font-semibold text-navy">Account Created</p>
                            <p class="text-xs font-bold text-gray-400">${formatDate(user.created_at)}</p>
                        </div>
                        <!-- Placeholder for more logs -->
                    </div>
                    
                    <div class="flex gap-2">
                        <button onclick="actionSingle('suspend', ${user.id})" class="flex-1 bg-yellow-50 hover:bg-yellow-100 text-yellow-600 font-bold py-3 rounded-xl transition-colors">Suspend User</button>
                        <button onclick="actionSingle('delete', ${user.id})" class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 font-bold py-3 rounded-xl transition-colors">Delete User</button>
                    </div>
                </div>
            `;
            
            content.innerHTML = leftHtml + rightHtml;
            document.getElementById('viewUserModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1500 });
        }
        
        // --- Actions ---
        
        function processAction(action, userIds) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to ${action} ${userIds.length} user(s).`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'delete' ? '#EF4444' : '#F59E0B',
                cancelButtonColor: '#64748B',
                confirmButtonText: `Yes, ${action}!`
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/bulk-users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: action, ids: userIds })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire('Success!', data.message, 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }
        
        function actionSingle(action, id) {
            closeModal();
            processAction(action, [id]);
        }
        
        function bulkSuspend() {
            processAction('suspend', Array.from(selectedIds));
        }
        
        function bulkDelete() {
            processAction('delete', Array.from(selectedIds));
        }
        
        function exportCSV() {
            // Simple CSV export of visible rows
            let csv = "ID,Name,Username,Email,Phone,Role,Status\n";
            filteredUsers.forEach(u => {
                let role = u.user_type === 'seller' ? `Seller (${u.business_name || 'No Biz'})` : 'Customer';
                let status = u.is_approved == 1 ? 'Active' : 'Suspended';
                csv += `${u.id},"${u.first_name} ${u.last_name}",${u.username},${u.email},${u.phone},"${role}",${status}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'oxxa_users_export.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        // Initial render
        renderTable();

    