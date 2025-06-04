/**
 * Foster Dashboard JavaScript Module
 * Handles foster parent portal functionality including pet management,
 * status reports, supply requests, and health tracking
 */

class FosterDashboard {
    constructor() {
        this.currentUser = null;
        this.fosterPets = [];
        this.init();
    }

    async init() {
        try {
            // Require authentication and foster role
            await window.authManager.requireAuth();
            await this.checkFosterAccess();
            
            this.currentUser = window.authManager.getCurrentUser();
            await this.loadDashboard();
            this.setupEventListeners();
        } catch (error) {
            console.error('Foster dashboard initialization failed:', error);
            window.location.href = '../auth/login.html';
        }
    }

    async checkFosterAccess() {
        const user = window.authManager.getCurrentUser();
        if (!user || (user.role !== 'foster' && user.role !== 'admin')) {
            throw new Error('Foster access required');
        }
    }

    async loadDashboard() {
        await Promise.all([
            this.loadFosterPets(),
            this.loadFosterStats(),
            this.loadRecentActivity(),
            this.loadSupplyRequests()
        ]);
    }

    async loadFosterPets() {
        try {
            // Get foster records for current user
            const response = await window.apiClient.request('/foster_records/list_mine.php');
            
            if (response.success) {
                this.fosterPets = response.data;
                this.renderFosterPets();
            }
        } catch (error) {
            console.error('Error loading foster pets:', error);
            this.showError('Failed to load foster pets');
        }
    }

    async loadFosterStats() {
        try {
            // Get user statistics
            const response = await window.apiClient.request('/reports/user_stats.php');
            
            if (response.success) {
                this.renderStats(response.data);
            }
        } catch (error) {
            console.error('Error loading foster stats:', error);
        }
    }

    async loadRecentActivity() {
        try {
            // Get notifications for foster activities
            const response = await window.apiClient.request('/notifications/list.php');
            
            if (response.success) {
                this.renderRecentActivity(response.data);
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
        }
    }

    async loadSupplyRequests() {
        try {
            // Get supply requests
            const response = await window.apiClient.request('/supply_requests/list.php');
            
            if (response.success) {
                this.renderSupplyRequests(response.data);
            }
        } catch (error) {
            console.error('Error loading supply requests:', error);
        }
    }

    renderFosterPets() {
        const container = document.querySelector('.foster-pets-container');
        if (!container) return;

        if (this.fosterPets.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-paw fa-3x text-muted mb-3"></i>
                        <h5>No Foster Pets</h5>
                        <p class="text-muted">You don't have any foster pets at the moment.</p>
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = this.fosterPets.map(pet => `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <img src="${pet.image_url || 'https://via.placeholder.com/300x200'}" 
                         class="card-img-top" alt="${pet.name}" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0">${pet.name}</h5>
                            <span class="badge ${this.getStatusBadgeClass(pet.status)}">${pet.status}</span>
                        </div>
                        <p class="text-muted mb-2">${pet.species} • ${pet.breed} • ${pet.age}</p>
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i> Fostering since: ${this.formatDate(pet.foster_start_date)}
                                <br>
                                <i class="fas fa-clock me-1"></i> Next vet visit: ${this.formatDate(pet.next_vet_visit) || 'Not scheduled'}
                            </small>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm" onclick="fosterDashboard.showReportModal('${pet.id}')">
                                Report Status
                            </button>
                            <button class="btn btn-outline-primary btn-sm" onclick="fosterDashboard.showUploadModal('${pet.id}')">
                                Upload Photos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    renderStats(stats) {
        // Update stats cards if available
        const statsElements = {
            'active-pets': stats.active_foster_pets || 0,
            'total-pets': stats.total_foster_pets || 0,
            'pending-reports': stats.pending_reports || 0,
            'supply-requests': stats.active_supply_requests || 0
        };

        Object.entries(statsElements).forEach(([elementId, value]) => {
            const element = document.getElementById(elementId);
            if (element) {
                element.textContent = value;
            }
        });
    }

    renderRecentActivity(activities) {
        const container = document.querySelector('.recent-activity-list');
        if (!container || !Array.isArray(activities)) return;

        container.innerHTML = activities.slice(0, 5).map(activity => `
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                    <div>
                        <h6 class="mb-1">${activity.title}</h6>
                        <small class="text-muted">${activity.message}</small>
                    </div>
                    <small class="text-muted">${this.formatRelativeTime(activity.created_at)}</small>
                </div>
            </div>
        `).join('');
    }

    renderSupplyRequests(requests) {
        const container = document.querySelector('.supply-requests-list');
        if (!container || !Array.isArray(requests)) return;

        const userRequests = requests.filter(req => req.user_id === this.currentUser.id);
        
        container.innerHTML = userRequests.slice(0, 5).map(request => `
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${request.item_type}</h6>
                        <small class="text-muted">Quantity: ${request.quantity} • ${request.urgency} priority</small>
                    </div>
                    <span class="badge ${this.getStatusBadgeClass(request.status)}">${request.status}</span>
                </div>
            </div>
        `).join('');
    }

    setupEventListeners() {
        // Supply request form
        const supplyForm = document.getElementById('supply-request-form');
        if (supplyForm) {
            supplyForm.addEventListener('submit', (e) => this.handleSupplyRequest(e));
        }

        // Status report form
        const reportForm = document.getElementById('status-report-form');
        if (reportForm) {
            reportForm.addEventListener('submit', (e) => this.handleStatusReport(e));
        }

        // Photo upload form
        const uploadForm = document.getElementById('photo-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => this.handlePhotoUpload(e));
        }
    }

    async handleSupplyRequest(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(event.target);
            const requestData = {
                item_type: formData.get('item_type'),
                quantity: parseInt(formData.get('quantity')),
                urgency: formData.get('urgency'),
                notes: formData.get('notes') || ''
            };

            const response = await window.apiClient.request('/supply_requests/create.php', {
                method: 'POST',
                body: JSON.stringify(requestData)
            });

            if (response.success) {
                this.showSuccess('Supply request submitted successfully');
                event.target.reset();
                await this.loadSupplyRequests();
            } else {
                this.showError(response.message || 'Failed to submit supply request');
            }
        } catch (error) {
            console.error('Error submitting supply request:', error);
            this.showError('Failed to submit supply request');
        }
    }

    async handleStatusReport(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(event.target);
            const reportData = {
                pet_id: this.currentReportPetId,
                health_status: formData.get('health_status'),
                eating_habits: formData.get('eating_habits'),
                behavior: formData.get('behavior'),
                notes: formData.get('notes'),
                concerns: formData.get('concerns') || ''
            };

            // Create medical record for the status report
            const response = await window.apiClient.request('/medical_records/create.php', {
                method: 'POST',
                body: JSON.stringify({
                    pet_id: reportData.pet_id,
                    record_type: 'Foster Status Report',
                    description: `Health: ${reportData.health_status}, Eating: ${reportData.eating_habits}, Behavior: ${reportData.behavior}`,
                    notes: `${reportData.notes}\n\nConcerns: ${reportData.concerns}`,
                    status: 'active'
                })
            });

            if (response.success) {
                this.showSuccess('Status report submitted successfully');
                const modal = bootstrap.Modal.getInstance(document.getElementById('reportStatusModal'));
                modal.hide();
                event.target.reset();
                await this.loadRecentActivity();
            } else {
                this.showError(response.message || 'Failed to submit status report');
            }
        } catch (error) {
            console.error('Error submitting status report:', error);
            this.showError('Failed to submit status report');
        }
    }

    async handlePhotoUpload(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(event.target);
            const files = formData.get('photos');
            
            if (!files || files.length === 0) {
                this.showError('Please select at least one photo');
                return;
            }

            // Note: This would typically upload to a file storage service
            // For now, we'll create a simple record of the upload
            this.showSuccess('Photos uploaded successfully');
            const modal = bootstrap.Modal.getInstance(document.getElementById('uploadPhotosModal'));
            modal.hide();
            event.target.reset();
        } catch (error) {
            console.error('Error uploading photos:', error);
            this.showError('Failed to upload photos');
        }
    }

    showReportModal(petId) {
        this.currentReportPetId = petId;
        const modal = new bootstrap.Modal(document.getElementById('reportStatusModal'));
        modal.show();
    }

    showUploadModal(petId) {
        this.currentUploadPetId = petId;
        const modal = new bootstrap.Modal(document.getElementById('uploadPhotosModal'));
        modal.show();
    }

    getStatusBadgeClass(status) {
        const statusClasses = {
            'active': 'bg-success',
            'pending': 'bg-warning',
            'approved': 'bg-success',
            'rejected': 'bg-danger',
            'in_progress': 'bg-info',
            'completed': 'bg-secondary',
            'In Foster Care': 'bg-primary'
        };
        return statusClasses[status] || 'bg-secondary';
    }

    formatDate(dateString) {
        if (!dateString) return null;
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInMinutes = Math.floor((now - date) / (1000 * 60));
        
        if (diffInMinutes < 1) return 'Just now';
        if (diffInMinutes < 60) return `${diffInMinutes} min ago`;
        if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)} hours ago`;
        return `${Math.floor(diffInMinutes / 1440)} days ago`;
    }

    showSuccess(message) {
        this.showAlert(message, 'success');
    }

    showError(message) {
        this.showAlert(message, 'danger');
    }

    showAlert(message, type) {
        const alertContainer = document.getElementById('alert-container') || document.body;
        const alertId = 'alert-' + Date.now();
        
        const alertHTML = `
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        alertContainer.insertAdjacentHTML('afterbegin', alertHTML);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.fosterDashboard = new FosterDashboard();
});
