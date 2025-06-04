/**
 * Admin Dashboard JavaScript
 * Handles admin-specific functionality including user management, reports, and system settings
 */

class AdminDashboard {
    constructor() {
        this.users = [];
        this.pets = [];
        this.stats = {};
        this.init();
    }

    async init() {
        // Check admin permissions
        if (!window.authManager.requireRole('admin')) {
            return;
        }

        await this.loadDashboardData();
        this.setupEventListeners();
        this.updateUI();
    }

    async loadDashboardData() {
        try {
            // Load dashboard statistics
            await this.loadStats();
            
            // Load recent users
            await this.loadRecentUsers();
            
            // Load recent pets
            await this.loadRecentPets();

        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showError('Failed to load dashboard data');
        }
    }

    async loadStats() {
        try {
            const response = await window.apiClient.getAdminReports();
            if (response.success) {
                this.stats = response.stats;
                this.updateStatsDisplay();
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async loadRecentUsers() {
        try {
            const response = await window.apiClient.getAllUsers(1, 10); // First 10 users
            if (response.success) {
                this.users = response.users;
                this.updateUsersTable();
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    async loadRecentPets() {
        try {
            const response = await window.apiClient.getPets();
            if (response.success) {
                this.pets = response.pets.slice(0, 10); // First 10 pets
                this.updatePetsTable();
            }
        } catch (error) {
            console.error('Error loading pets:', error);
        }
    }

    updateStatsDisplay() {
        const statsMap = {
            'total-users': this.stats.total_users || 0,
            'total-pets': this.stats.total_pets || 0,
            'total-applications': this.stats.total_applications || 0,
            'pending-applications': this.stats.pending_applications || 0,
            'total-volunteers': this.stats.total_volunteers || 0,
            'active-fosters': this.stats.active_fosters || 0
        };

        Object.entries(statsMap).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    updateUsersTable() {
        const tbody = document.querySelector('#users-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        this.users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${user.first_name} ${user.last_name}</td>
                <td>${user.email}</td>
                <td><span class="badge bg-primary">${this.formatRole(user.role)}</span></td>
                <td><span class="badge ${user.status === 'active' ? 'bg-success' : 'bg-warning'}">${user.status}</span></td>
                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="adminDashboard.editUser(${user.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="adminDashboard.toggleUserStatus(${user.id})">
                        <i class="fas fa-ban"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    updatePetsTable() {
        const tbody = document.querySelector('#pets-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        this.pets.forEach(pet => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${pet.id}</td>
                <td>${pet.name}</td>
                <td><span class="badge bg-info">${pet.species}</span></td>
                <td><span class="badge bg-secondary">${pet.breed}</span></td>
                <td><span class="badge ${this.getStatusBadgeClass(pet.status)}">${pet.status}</span></td>
                <td>${new Date(pet.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="adminDashboard.viewPet(${pet.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="adminDashboard.editPet(${pet.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    setupEventListeners() {
        // Refresh data button
        const refreshBtn = document.getElementById('refresh-data');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadDashboardData());
        }

        // Export reports button
        const exportBtn = document.getElementById('export-reports');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportReports());
        }

        // Quick actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-add-user')) {
                this.showAddUserModal();
            } else if (e.target.classList.contains('quick-add-pet')) {
                this.showAddPetModal();
            }
        });
    }

    async editUser(userId) {
        try {
            const response = await window.apiClient.getUserById(userId);
            if (response.success) {
                this.showEditUserModal(response.user);
            }
        } catch (error) {
            console.error('Error loading user:', error);
            this.showError('Failed to load user details');
        }
    }

    async toggleUserStatus(userId) {
        if (!confirm('Are you sure you want to change this user\'s status?')) {
            return;
        }

        try {
            const response = await window.apiClient.updateUserStatus(userId, 'toggle');
            if (response.success) {
                this.showSuccess('User status updated successfully');
                await this.loadRecentUsers();
            }
        } catch (error) {
            console.error('Error updating user status:', error);
            this.showError('Failed to update user status');
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

    async exportReports() {
        try {
            const response = await window.apiClient.getAdminReports();
            if (response.success) {
                this.downloadReport(response.data);
                this.showSuccess('Report exported successfully');
            }
        } catch (error) {
            console.error('Error exporting reports:', error);
            this.showError('Failed to export reports');
        }
    }

    downloadReport(data) {
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `admin-report-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    formatRole(role) {
        const roleMap = {
            'admin': 'Administrator',
            'adopter': 'Pet Adopter',
            'shelter_staff': 'Shelter Staff',
            'volunteer': 'Volunteer',
            'veterinarian': 'Veterinarian',
            'foster_parent': 'Foster Parent'
        };
        return roleMap[role] || role;
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

    showAddUserModal() {
        // Implementation for add user modal
        console.log('Show add user modal');
    }

    showAddPetModal() {
        // Implementation for add pet modal
        console.log('Show add pet modal');
    }

    showEditUserModal(user) {
        // Implementation for edit user modal
        console.log('Show edit user modal', user);
    }

    showEditPetModal(pet) {
        // Implementation for edit pet modal
        console.log('Show edit pet modal', pet);
    }

    showPetDetailsModal(pet) {
        // Implementation for pet details modal
        console.log('Show pet details modal', pet);
    }

    updateUI() {
        // Update page title with user name
        const pageTitle = document.querySelector('h1, .page-title');
        if (pageTitle && window.authManager.user) {
            pageTitle.textContent = `Welcome, ${window.authManager.user.first_name}!`;
        }

        // Update last updated time
        const lastUpdated = document.getElementById('last-updated');
        if (lastUpdated) {
            lastUpdated.textContent = new Date().toLocaleString();
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

        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Initialize admin dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.adminDashboard = new AdminDashboard();
});
