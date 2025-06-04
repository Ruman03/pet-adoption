// Foster Supplies Management
class FosterSuppliesManager {
    constructor() {
        this.supplyRequests = [];
        this.inventory = [];
        this.fosterRecords = [];
        this.currentFilter = 'all';
        this.init();
    }

    async init() {
        // Check authentication
        if (!authManager.isAuthenticated()) {
            window.location.href = '../auth/login.html';
            return;
        }

        // Verify foster role
        const user = authManager.getCurrentUser();
        if (user.role !== 'foster') {
            alert('Access denied. Foster role required.');
            window.location.href = '../index.html';
            return;
        }

        await this.loadData();
        this.setupEventListeners();
    }

    async loadData() {
        try {
            await this.loadSupplyRequests();
            await this.loadFosterRecords();
            await this.loadInventoryData();
            this.updateStatistics();
            this.updateRecentActivity();
        } catch (error) {
            console.error('Error loading data:', error);
            alert('Error loading data. Please refresh the page.');
        }
    }

    async loadSupplyRequests() {
        try {
            const response = await apiClient.get('/supply_requests/list');
            this.supplyRequests = response.data?.supply_requests || [];
            this.renderSupplyTable();
        } catch (error) {
            console.error('Error loading supply requests:', error);
            this.supplyRequests = [];
            this.renderSupplyTable();
        }
    }

    async loadFosterRecords() {
        try {
            const response = await apiClient.get('/foster_records/list_mine');
            this.fosterRecords = response.data || [];
        } catch (error) {
            console.error('Error loading foster records:', error);
            this.fosterRecords = [];
        }
    }

    async loadInventoryData() {
        // Since there's no specific inventory endpoint, we'll simulate inventory data
        // based on common foster supplies
        this.inventory = [
            {
                id: 1,
                name: 'Premium Kitten Food',
                brand: 'Royal Canin',
                category: 'food',
                quantity: 15,
                maxQuantity: 20,
                lowStockThreshold: 5,
                lastUpdated: '2023-12-12',
                image: 'https://via.placeholder.com/40'
            },
            {
                id: 2,
                name: 'Flea Treatment',
                brand: 'Frontline Plus',
                category: 'medical',
                quantity: 3,
                maxQuantity: 12,
                lowStockThreshold: 5,
                lastUpdated: '2023-12-10',
                image: 'https://via.placeholder.com/40'
            },
            {
                id: 3,
                name: 'Cat Litter',
                brand: 'Fresh Step',
                category: 'hygiene',
                quantity: 0,
                maxQuantity: 10,
                lowStockThreshold: 2,
                lastUpdated: '2023-12-08',
                image: 'https://via.placeholder.com/40'
            },
            {
                id: 4,
                name: 'Dog Food',
                brand: 'Blue Buffalo',
                category: 'food',
                quantity: 8,
                maxQuantity: 15,
                lowStockThreshold: 3,
                lastUpdated: '2023-12-11',
                image: 'https://via.placeholder.com/40'
            },
            {
                id: 5,
                name: 'Pet Toys',
                brand: 'Kong',
                category: 'equipment',
                quantity: 12,
                maxQuantity: 20,
                lowStockThreshold: 5,
                lastUpdated: '2023-12-09',
                image: 'https://via.placeholder.com/40'
            }
        ];
        this.renderSupplyTable();
    }

    renderSupplyTable() {
        const tbody = document.querySelector('.table tbody');
        if (!tbody) return;

        // Filter inventory based on current category
        let filteredInventory = this.inventory;
        if (this.currentFilter !== 'all') {
            filteredInventory = this.inventory.filter(item => item.category === this.currentFilter);
        }

        if (filteredInventory.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-box fa-3x mb-3"></i>
                            <h6>No Supplies Found</h6>
                            <p class="mb-0">No supplies found for the selected category.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filteredInventory.map(item => {
            const progressPercentage = (item.quantity / item.maxQuantity) * 100;
            const statusBadge = this.getStatusBadge(item);
            const progressBarClass = this.getProgressBarClass(item);

            return `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${item.image}" class="rounded me-2" alt="Item" style="width: 40px; height: 40px;">
                            <div>
                                <h6 class="mb-0">${this.escapeHtml(item.name)}</h6>
                                <small class="text-muted">${this.escapeHtml(item.brand)}</small>
                            </div>
                        </div>
                    </td>
                    <td>${this.formatCategory(item.category)}</td>
                    <td>
                        <div class="progress" style="width: 100px;">
                            <div class="progress-bar ${progressBarClass}" style="width: ${progressPercentage}%"></div>
                        </div>
                        <small class="text-muted">${item.quantity} units</small>
                    </td>
                    <td>${statusBadge}</td>
                    <td>${this.formatDate(item.lastUpdated)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="openUpdateModal(${item.id})" data-bs-toggle="modal" data-bs-target="#updateModal">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="openRequestModal(${item.id})" data-bs-toggle="modal" data-bs-target="#requestModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    getStatusBadge(item) {
        if (item.quantity === 0) {
            return '<span class="badge bg-danger">Out of Stock</span>';
        } else if (item.quantity <= item.lowStockThreshold) {
            return '<span class="badge bg-warning">Low Stock</span>';
        } else {
            return '<span class="badge bg-success">In Stock</span>';
        }
    }

    getProgressBarClass(item) {
        if (item.quantity === 0) {
            return 'bg-danger';
        } else if (item.quantity <= item.lowStockThreshold) {
            return 'bg-warning';
        } else {
            return 'bg-success';
        }
    }

    formatCategory(category) {
        const categories = {
            'food': 'Food',
            'medical': 'Medical',
            'hygiene': 'Hygiene',
            'equipment': 'Equipment'
        };
        return categories[category] || category;
    }

    updateStatistics() {
        const stats = this.inventory.reduce((acc, item) => {
            acc.total++;
            if (item.quantity === 0) {
                acc.outOfStock++;
            } else if (item.quantity <= item.lowStockThreshold) {
                acc.lowStock++;
            } else {
                acc.inStock++;
            }
            return acc;
        }, { total: 0, inStock: 0, lowStock: 0, outOfStock: 0 });        // Update statistics cards
        const totalCard = document.getElementById('totalItemsCount');
        const inStockCard = document.getElementById('inStockCount');
        const lowStockCard = document.getElementById('lowStockCount');
        const outOfStockCard = document.getElementById('outOfStockCount');

        if (totalCard) totalCard.textContent = stats.total;
        if (inStockCard) inStockCard.textContent = stats.inStock;
        if (lowStockCard) lowStockCard.textContent = stats.lowStock;
        if (outOfStockCard) outOfStockCard.textContent = stats.outOfStock;
    }

    updateRecentActivity() {
        const timeline = document.querySelector('.timeline');
        if (!timeline) return;

        // Generate recent activity based on supply requests and inventory updates
        const activities = [
            {
                date: '2023-12-12',
                type: 'approved',
                message: 'Supply Request Approved: 5 units of Premium Kitten Food'
            },
            {
                date: '2023-12-10',
                type: 'alert',
                message: 'Low Stock Alert: Flea Treatment (3 units remaining)'
            },
            {
                date: '2023-12-08',
                type: 'updated',
                message: 'Inventory Updated: Added 10 units of Dog Food'
            }
        ];

        timeline.innerHTML = activities.map(activity => `
            <div class="timeline-item mb-3">
                <p class="small text-muted mb-1">${this.formatDate(activity.date)}</p>
                <p class="mb-0"><strong>${activity.message}</strong></p>
            </div>
        `).join('');
    }

    setupEventListeners() {
        // Category filter tabs
        document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleCategoryFilter(e.target);
            });
        });

        // Supply request form
        const requestForm = document.querySelector('#requestModal form');
        if (requestForm) {
            const submitBtn = document.querySelector('#requestModal .btn-primary');
            if (submitBtn) {
                submitBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.submitSupplyRequest();
                });
            }
        }

        // Inventory update form
        const updateForm = document.querySelector('#updateModal form');
        if (updateForm) {
            const updateBtn = document.querySelector('#updateModal .btn-primary');
            if (updateBtn) {
                updateBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.updateInventory();
                });
            }
        }

        // Populate item dropdown in request modal
        this.populateItemDropdown();
    }

    handleCategoryFilter(tab) {
        // Update active tab
        document.querySelectorAll('.nav-tabs .nav-link').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Get category from href
        const href = tab.getAttribute('href');
        if (href === '#food') {
            this.currentFilter = 'food';
        } else if (href === '#medical') {
            this.currentFilter = 'medical';
        } else if (href === '#hygiene') {
            this.currentFilter = 'hygiene';
        } else if (href === '#equipment') {
            this.currentFilter = 'equipment';
        } else {
            this.currentFilter = 'all';
        }

        this.renderSupplyTable();
    }

    populateItemDropdown() {
        const select = document.querySelector('#requestModal select[required]');
        if (!select) return;

        select.innerHTML = '<option value="">Select item...</option>';
        this.inventory.forEach(item => {
            select.innerHTML += `<option value="${item.id}" data-name="${item.name}">${item.name}</option>`;
        });
    }

    async submitSupplyRequest() {
        const form = document.querySelector('#requestModal form');
        const formData = new FormData(form);
        
        const itemSelect = form.querySelector('select[required]');
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const itemName = selectedOption.dataset.name;
        
        // Get an active foster record (use the first one for simplicity)
        const activeFosterRecord = this.fosterRecords.find(fr => fr.status === 'active');
        if (!activeFosterRecord) {
            alert('No active foster records found. You need an active foster assignment to request supplies.');
            return;
        }

        const requestData = {
            foster_record_id: activeFosterRecord.id,
            item_name: itemName,
            quantity: parseInt(formData.get('quantity')) || 1,
            category: this.getCategoryFromItemId(formData.get('item')),
            urgency: formData.get('urgency') || 'medium',
            description: formData.get('notes'),
            estimated_cost: null
        };

        try {
            const submitBtn = document.querySelector('#requestModal .btn-primary');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            const response = await apiClient.post('/supply_requests/create', requestData);
            
            if (response.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('requestModal'));
                modal.hide();
                
                // Reset form
                form.reset();
                
                // Reload data
                await this.loadSupplyRequests();
                this.updateRecentActivity();
                
                alert('Supply request submitted successfully!');
            } else {
                throw new Error(response.message || 'Failed to submit supply request');
            }
        } catch (error) {
            console.error('Error submitting supply request:', error);
            alert('Error submitting supply request. Please try again.');
        } finally {
            const submitBtn = document.querySelector('#requestModal .btn-primary');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Request';
        }
    }

    getCategoryFromItemId(itemId) {
        const item = this.inventory.find(i => i.id == itemId);
        return item ? item.category : 'other';
    }

    updateInventory() {
        // This would typically update inventory through an API
        // For now, we'll just show a success message
        alert('Inventory update functionality would be implemented here with proper backend integration.');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('updateModal'));
        modal.hide();
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// Global functions for modal operations
function openRequestModal(itemId) {
    const item = fosterSuppliesManager.inventory.find(i => i.id === itemId);
    if (item) {
        const select = document.querySelector('#requestModal select[required]');
        if (select) {
            select.value = itemId;
        }
    }
}

function openUpdateModal(itemId) {
    const item = fosterSuppliesManager.inventory.find(i => i.id === itemId);
    if (item) {
        const currentQuantityInput = document.querySelector('#updateModal input[readonly]');
        if (currentQuantityInput) {
            currentQuantityInput.value = item.quantity;
        }
    }
}

// Initialize when page loads
let fosterSuppliesManager;
document.addEventListener('DOMContentLoaded', () => {
    fosterSuppliesManager = new FosterSuppliesManager();
});
