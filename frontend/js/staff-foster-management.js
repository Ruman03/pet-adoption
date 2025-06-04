// Staff Foster Management JavaScript

class StaffFosterManager {
    constructor() {
        this.fosters = [];
        this.pets = [];
        this.applications = [];
        this.currentFilter = 'all';
        this.init();
    }

    async init() {
        try {
            await this.checkAuth();
            await this.loadData();
            this.setupEventListeners();
            this.renderDashboard();
        } catch (error) {
            console.error('Failed to initialize staff foster management:', error);
            this.showAlert('Failed to load foster data', 'danger');
        }
    }

    async checkAuth() {
        if (!authManager.isAuthenticated()) {
            window.location.href = '../auth/login.html';
            return;
        }

        const user = authManager.getCurrentUser();
        if (user.role !== 'staff' && user.role !== 'admin') {
            window.location.href = '../index.html';
            return;
        }
    }

    async loadData() {
        try {
            this.showLoading();
            
            // Load foster families
            const fostersResponse = await apiClient.get('/staff/fosters');
            if (fostersResponse.success) {
                this.fosters = fostersResponse.data || [];
            }

            // Load pets in foster
            const petsResponse = await apiClient.get('/staff/foster-pets');
            if (petsResponse.success) {
                this.pets = petsResponse.data || [];
            }

            // Load foster applications
            const applicationsResponse = await apiClient.get('/staff/foster-applications');
            if (applicationsResponse.success) {
                this.applications = applicationsResponse.data || [];
            }
            
        } catch (error) {
            console.error('Error loading data:', error);
            this.showAlert('Failed to load data', 'danger');
        } finally {
            this.hideLoading();
        }
    }

    setupEventListeners() {
        // Filter tabs
        document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleFilterChange(e.target);
            });
        });

        // Add foster family button
        const addFosterBtn = document.getElementById('addFosterBtn');
        if (addFosterBtn) {
            addFosterBtn.addEventListener('click', () => {
                this.showAddFosterModal();
            });
        }

        // Save new foster
        const saveFosterBtn = document.getElementById('saveFosterBtn');
        if (saveFosterBtn) {
            saveFosterBtn.addEventListener('click', () => {
                this.handleAddFoster();
            });
        }

        // Search functionality
        const searchInput = document.getElementById('fosterSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
        }

        // Foster actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('view-foster-btn')) {
                const fosterId = e.target.dataset.fosterId;
                this.viewFosterDetails(fosterId);
            } else if (e.target.classList.contains('assign-pet-btn')) {
                const fosterId = e.target.dataset.fosterId;
                this.showAssignPetModal(fosterId);
            } else if (e.target.classList.contains('contact-foster-btn')) {
                const fosterId = e.target.dataset.fosterId;
                this.contactFoster(fosterId);
            } else if (e.target.classList.contains('approve-application-btn')) {
                const applicationId = e.target.dataset.applicationId;
                this.approveApplication(applicationId);
            } else if (e.target.classList.contains('reject-application-btn')) {
                const applicationId = e.target.dataset.applicationId;
                this.rejectApplication(applicationId);
            }
        });
    }

    handleFilterChange(tab) {
        // Update active tab
        document.querySelectorAll('.nav-tabs .nav-link').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Set current filter
        const tabText = tab.textContent.toLowerCase();
        if (tabText.includes('active')) {
            this.currentFilter = 'active';
        } else if (tabText.includes('applications')) {
            this.currentFilter = 'applications';
        } else if (tabText.includes('pets')) {
            this.currentFilter = 'pets';
        } else {
            this.currentFilter = 'all';
        }

        this.renderContent();
    }

    handleSearch(searchTerm) {
        // Filter fosters based on search term
        this.searchTerm = searchTerm.toLowerCase();
        this.renderContent();
    }

    async handleAddFoster() {
        const form = document.getElementById('newFosterForm');
        const formData = new FormData(form);

        const fosterData = {
            first_name: formData.get('firstName'),
            last_name: formData.get('lastName'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            address: formData.get('address'),
            experience: formData.get('experience'),
            preferences: formData.get('preferences'),
            capacity: parseInt(formData.get('capacity')) || 1
        };

        // Validate required fields
        if (!fosterData.first_name || !fosterData.last_name || !fosterData.email) {
            this.showAlert('Please fill in all required fields', 'danger');
            return;
        }

        try {
            const response = await apiClient.post('/staff/fosters', fosterData);
            
            if (response.success) {
                // Add to local fosters
                this.fosters.push(response.data);
                
                // Close modal and refresh view
                const modal = bootstrap.Modal.getInstance(document.getElementById('newFosterModal'));
                modal.hide();
                form.reset();
                
                this.renderDashboard();
                this.showAlert('Foster family added successfully', 'success');
            } else {
                throw new Error(response.message || 'Failed to add foster family');
            }
        } catch (error) {
            console.error('Error adding foster family:', error);
            this.showAlert('Failed to add foster family', 'danger');
        }
    }

    async approveApplication(applicationId) {
        try {
            const response = await apiClient.put(`/staff/foster-applications/${applicationId}`, {
                status: 'approved'
            });

            if (response.success) {
                // Update local application
                const app = this.applications.find(a => a.id == applicationId);
                if (app) {
                    app.status = 'approved';
                }
                
                this.renderContent();
                this.showAlert('Application approved successfully', 'success');
            } else {
                throw new Error(response.message || 'Failed to approve application');
            }
        } catch (error) {
            console.error('Error approving application:', error);
            this.showAlert('Failed to approve application', 'danger');
        }
    }

    async rejectApplication(applicationId) {
        try {
            const response = await apiClient.put(`/staff/foster-applications/${applicationId}`, {
                status: 'rejected'
            });

            if (response.success) {
                // Update local application
                const app = this.applications.find(a => a.id == applicationId);
                if (app) {
                    app.status = 'rejected';
                }
                
                this.renderContent();
                this.showAlert('Application rejected', 'success');
            } else {
                throw new Error(response.message || 'Failed to reject application');
            }
        } catch (error) {
            console.error('Error rejecting application:', error);
            this.showAlert('Failed to reject application', 'danger');
        }
    }

    renderDashboard() {
        this.renderStatistics();
        this.renderContent();
    }

    renderStatistics() {
        const activeForsters = this.fosters.filter(f => f.status === 'active').length;
        const petsInFoster = this.pets.length;
        const pendingApplications = this.applications.filter(a => a.status === 'pending').length;
        const successfulPlacements = this.fosters.reduce((sum, f) => sum + (f.successful_placements || 0), 0);

        // Update stat cards
        const activeCard = document.querySelector('.bg-primary h3');
        const petsCard = document.querySelector('.bg-success h3');
        const pendingCard = document.querySelector('.bg-warning h3');
        const successCard = document.querySelector('.bg-info h3');

        if (activeCard) activeCard.textContent = activeForsters;
        if (petsCard) petsCard.textContent = petsInFoster;
        if (pendingCard) pendingCard.textContent = pendingApplications;
        if (successCard) successCard.textContent = successfulPlacements;
    }

    renderContent() {
        const container = document.getElementById('contentContainer');
        if (!container) return;

        if (this.currentFilter === 'applications') {
            this.renderApplications(container);
        } else if (this.currentFilter === 'pets') {
            this.renderFosterPets(container);
        } else {
            this.renderFosters(container);
        }
    }

    renderFosters(container) {
        let fosteredData = this.fosters;

        // Apply filters
        if (this.currentFilter === 'active') {
            fosteredData = fosteredData.filter(f => f.status === 'active');
        }

        // Apply search
        if (this.searchTerm) {
            fosteredData = fosteredData.filter(f => 
                f.first_name.toLowerCase().includes(this.searchTerm) ||
                f.last_name.toLowerCase().includes(this.searchTerm) ||
                f.email.toLowerCase().includes(this.searchTerm)
            );
        }

        if (fosteredData.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-home fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No foster families found</h5>
                    <p class="text-muted">Foster families will appear here when added.</p>
                </div>
            `;
            return;
        }

        let html = '';
        fosteredData.forEach(foster => {
            const statusBadge = this.getStatusBadge(foster.status);
            const currentPets = this.pets.filter(p => p.foster_id == foster.id);
            
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="card-title mb-1">${this.escapeHtml(foster.first_name)} ${this.escapeHtml(foster.last_name)}</h6>
                                    <small class="text-muted">${this.escapeHtml(foster.email)}</small>
                                </div>
                                ${statusBadge}
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Current Pets:</small>
                                    <small class="fw-bold">${currentPets.length}/${foster.capacity || 1}</small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" 
                                         style="width: ${(currentPets.length / (foster.capacity || 1)) * 100}%">
                                    </div>
                                </div>
                            </div>

                            ${currentPets.length > 0 ? `
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Current Pets:</small>
                                    ${currentPets.slice(0, 2).map(pet => `
                                        <span class="badge bg-light text-dark me-1">${this.escapeHtml(pet.name)}</span>
                                    `).join('')}
                                    ${currentPets.length > 2 ? `<span class="badge bg-light text-dark">+${currentPets.length - 2} more</span>` : ''}
                                </div>
                            ` : ''}

                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm view-foster-btn" data-foster-id="${foster.id}">
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
                                <button class="btn btn-outline-success btn-sm assign-pet-btn" data-foster-id="${foster.id}">
                                    <i class="fas fa-plus me-1"></i>Assign Pet
                                </button>
                                <button class="btn btn-outline-info btn-sm contact-foster-btn" data-foster-id="${foster.id}">
                                    <i class="fas fa-envelope me-1"></i>Contact
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    renderApplications(container) {
        const pendingApplications = this.applications.filter(a => a.status === 'pending');
        
        if (pendingApplications.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No pending applications</h5>
                    <p class="text-muted">Foster applications will appear here when submitted.</p>
                </div>
            `;
            return;
        }

        let html = '';
        pendingApplications.forEach(application => {
            html += `
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">${this.escapeHtml(application.first_name)} ${this.escapeHtml(application.last_name)}</h6>
                                    <p class="text-muted mb-1">${this.escapeHtml(application.email)} • ${this.escapeHtml(application.phone)}</p>
                                    <small class="text-muted">Applied: ${this.formatDate(application.created_at)}</small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-success btn-sm me-2 approve-application-btn" data-application-id="${application.id}">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm reject-application-btn" data-application-id="${application.id}">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    renderFosterPets(container) {
        if (this.pets.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-paw fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No pets in foster care</h5>
                    <p class="text-muted">Pets in foster care will appear here.</p>
                </div>
            `;
            return;
        }

        let html = '';
        this.pets.forEach(pet => {
            const foster = this.fosters.find(f => f.id == pet.foster_id);
            
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="${pet.image_url || 'https://via.placeholder.com/60'}" 
                                     class="rounded-circle me-3" 
                                     style="width: 60px; height: 60px; object-fit: cover;"
                                     alt="${this.escapeHtml(pet.name)}">
                                <div>
                                    <h6 class="mb-1">${this.escapeHtml(pet.name)}</h6>
                                    <small class="text-muted">${this.escapeHtml(pet.species)} • ${this.escapeHtml(pet.breed)}</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Foster Family:</small>
                                <strong>${foster ? this.escapeHtml(foster.first_name + ' ' + foster.last_name) : 'Unknown'}</strong>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block">Foster Start:</small>
                                <span>${this.formatDate(pet.foster_start_date)}</span>
                            </div>

                            <button class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-eye me-1"></i>View Details
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    getStatusBadge(status) {
        const badges = {
            'active': '<span class="badge bg-success">Active</span>',
            'inactive': '<span class="badge bg-secondary">Inactive</span>',
            'suspended': '<span class="badge bg-danger">Suspended</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    }

    formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString();
    }

    viewFosterDetails(fosterId) {
        const foster = this.fosters.find(f => f.id == fosterId);
        if (foster) {
            // Implementation for foster details modal
            console.log('View foster details:', foster);
            this.showAlert(`Viewing details for ${foster.first_name} ${foster.last_name}`, 'info');
        }
    }

    showAssignPetModal(fosterId) {
        // Implementation for assign pet modal
        console.log('Assign pet to foster:', fosterId);
        this.showAlert('Assign pet modal would open here', 'info');
    }

    contactFoster(fosterId) {
        const foster = this.fosters.find(f => f.id == fosterId);
        if (foster) {
            // Implementation for contact foster
            console.log('Contact foster:', foster);
            this.showAlert(`Contacting ${foster.first_name} ${foster.last_name}`, 'info');
        }
    }

    showAddFosterModal() {
        const modal = new bootstrap.Modal(document.getElementById('newFosterModal'));
        modal.show();
    }

    showLoading() {
        const container = document.getElementById('contentContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading foster data...</p>
                </div>
            `;
        }
    }

    hideLoading() {
        // Loading will be replaced by render methods
    }

    showAlert(message, type = 'info') {
        let alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alertContainer';
            alertContainer.className = 'position-fixed top-0 end-0 p-3';
            alertContainer.style.zIndex = '1050';
            document.body.appendChild(alertContainer);
        }

        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        alertContainer.insertAdjacentHTML('beforeend', alertHtml);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
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

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new StaffFosterManager();
});
