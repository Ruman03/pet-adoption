/**
 * Staff Dashboard JavaScript
 * Handles staff-specific functionality including pet management, applications, and daily operations
 */

class StaffDashboard {
    constructor() {
        this.pets = [];
        this.applications = [];
        this.stats = {};
        this.init();
    }

    async init() {
        // Check staff permissions
        if (!window.authManager.requireRole('shelter_staff')) {
            return;
        }

        await this.loadDashboardData();
        this.setupEventListeners();
        this.updateUI();
    }

    async loadDashboardData() {
        try {
            await this.loadStats();
            await this.loadRecentPets();
            await this.loadRecentApplications();
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showError('Failed to load dashboard data');
        }
    }

    async loadStats() {
        try {
            const response = await window.apiClient.getStaffStats();
            if (response.success) {
                this.stats = response.stats;
                this.updateStatsDisplay();
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async loadRecentPets() {
        try {
            const response = await window.apiClient.getPets();
            if (response.success) {
                this.pets = response.pets.slice(0, 10);
                this.updatePetsTable();
            }
        } catch (error) {
            console.error('Error loading pets:', error);
        }
    }

    async loadRecentApplications() {
        try {
            const response = await window.apiClient.getAllApplications();
            if (response.success) {
                this.applications = response.applications.slice(0, 10);
                this.updateApplicationsTable();
            }
        } catch (error) {
            console.error('Error loading applications:', error);
        }
    }

    updateStatsDisplay() {
        const statsMap = {
            'total-pets': this.stats.total_pets || 0,
            'available-pets': this.stats.available_pets || 0,
            'pending-applications': this.stats.pending_applications || 0,
            'adopted-today': this.stats.adopted_today || 0
        };

        Object.entries(statsMap).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    updatePetsTable() {
        const tbody = document.querySelector('#pets-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        this.pets.forEach(pet => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <img src="${pet.photo_url || 'https://via.placeholder.com/50'}" 
                         alt="${pet.name}" class="rounded" width="50" height="50">
                </td>
                <td><strong>${pet.name}</strong></td>
                <td><span class="badge bg-info">${pet.species}</span></td>
                <td>${pet.breed}</td>
                <td><span class="badge ${this.getStatusBadgeClass(pet.status)}">${pet.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="staffDashboard.viewPet(${pet.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="staffDashboard.editPet(${pet.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    updateApplicationsTable() {
        const tbody = document.querySelector('#applications-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        this.applications.forEach(app => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>#${app.id}</td>
                <td>${app.adopter_name || 'N/A'}</td>
                <td>${app.pet_name || 'N/A'}</td>
                <td><span class="badge ${this.getApplicationStatusBadgeClass(app.status)}">${app.status}</span></td>
                <td>${new Date(app.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="staffDashboard.reviewApplication(${app.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${app.status === 'pending' ? `
                        <button class="btn btn-sm btn-success" onclick="staffDashboard.approveApplication(${app.id})">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="staffDashboard.rejectApplication(${app.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    setupEventListeners() {
        // Add Pet button
        const addPetBtn = document.getElementById('add-pet-btn');
        if (addPetBtn) {
            addPetBtn.addEventListener('click', () => this.showAddPetModal());
        }

        // Quick actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-checkin')) {
                this.showCheckInModal();
            } else if (e.target.classList.contains('quick-checkout')) {
                this.showCheckOutModal();
            }
        });

        // Pet form submission
        const petForm = document.getElementById('addPetForm');
        if (petForm) {
            petForm.addEventListener('submit', (e) => this.handleAddPet(e));
        }
    }

    async handleAddPet(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const petData = {};
        formData.forEach((value, key) => {
            petData[key] = value;
        });

        try {
            const response = await window.apiClient.createPet(petData);
            if (response.success) {
                this.showSuccess('Pet added successfully!');
                this.hideAddPetModal();
                await this.loadRecentPets();
            } else {
                this.showError(response.message || 'Failed to add pet');
            }
        } catch (error) {
            console.error('Error adding pet:', error);
            this.showError('Failed to add pet');
        }
    }

    async viewPet(petId) {
        try {
            const response = await window.apiClient.getPetById(petId);
            if (response.success) {
                this.showPetDetailsModal(response.pet);
            }
        } catch (error) {
            console.error('Error loading pet:', error);
            this.showError('Failed to load pet details');
        }
    }

    async editPet(petId) {
        try {
            const response = await window.apiClient.getPetById(petId);
            if (response.success) {
                this.showEditPetModal(response.pet);
            }
        } catch (error) {
            console.error('Error loading pet:', error);
            this.showError('Failed to load pet details');
        }
    }

    async reviewApplication(applicationId) {
        try {
            const response = await window.apiClient.getApplicationById(applicationId);
            if (response.success) {
                this.showApplicationDetailsModal(response.application);
            }
        } catch (error) {
            console.error('Error loading application:', error);
            this.showError('Failed to load application details');
        }
    }

    async approveApplication(applicationId) {
        if (!confirm('Are you sure you want to approve this application?')) {
            return;
        }

        try {
            const response = await window.apiClient.updateApplicationStatus(applicationId, 'approved');
            if (response.success) {
                this.showSuccess('Application approved successfully!');
                await this.loadRecentApplications();
            }
        } catch (error) {
            console.error('Error approving application:', error);
            this.showError('Failed to approve application');
        }
    }

    async rejectApplication(applicationId) {
        if (!confirm('Are you sure you want to reject this application?')) {
            return;
        }

        try {
            const response = await window.apiClient.updateApplicationStatus(applicationId, 'rejected');
            if (response.success) {
                this.showSuccess('Application rejected successfully!');
                await this.loadRecentApplications();
            }
        } catch (error) {
            console.error('Error rejecting application:', error);
            this.showError('Failed to reject application');
        }
    }

    showAddPetModal() {
        const modal = new bootstrap.Modal(document.getElementById('addPetModal'));
        modal.show();
    }

    hideAddPetModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('addPetModal'));
        if (modal) {
            modal.hide();
        }
    }

    showPetDetailsModal(pet) {
        // Implementation for pet details modal
        console.log('Show pet details modal', pet);
    }

    showEditPetModal(pet) {
        // Implementation for edit pet modal
        console.log('Show edit pet modal', pet);
    }

    showApplicationDetailsModal(application) {
        // Implementation for application details modal
        console.log('Show application details modal', application);
    }

    showCheckInModal() {
        console.log('Show check-in modal');
    }

    showCheckOutModal() {
        console.log('Show check-out modal');
    }

    getStatusBadgeClass(status) {
        const statusMap = {
            'available': 'bg-success',
            'pending': 'bg-warning',
            'adopted': 'bg-primary',
            'fostered': 'bg-info',
            'medical_hold': 'bg-danger'
        };
        return statusMap[status] || 'bg-secondary';
    }

    getApplicationStatusBadgeClass(status) {
        const statusMap = {
            'pending': 'bg-warning',
            'approved': 'bg-success',
            'rejected': 'bg-danger',
            'completed': 'bg-primary'
        };
        return statusMap[status] || 'bg-secondary';
    }

    updateUI() {
        // Update welcome message
        const welcomeMsg = document.querySelector('.welcome-user');
        if (welcomeMsg && window.authManager.user) {
            welcomeMsg.textContent = `Welcome back, ${window.authManager.user.first_name}!`;
        }

        // Update current date
        const currentDate = document.getElementById('currentDate');
        if (currentDate) {
            currentDate.textContent = new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    }

    showSuccess(message) {
        this.showAlert(message, 'success');
    }

    showError(message) {
        this.showAlert(message, 'danger');
    }

    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.container-fluid') || document.body;
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Initialize staff dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.staffDashboard = new StaffDashboard();
});
