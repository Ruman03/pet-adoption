// Volunteer Applications Management
class VolunteerApplicationsManager {
    constructor() {
        this.applications = [];
        this.availablePositions = [];
        this.init();
    }

    async init() {
        // Check authentication
        if (!authManager.isAuthenticated()) {
            window.location.href = '../auth/login.html';
            return;
        }

        // Verify volunteer role
        const user = authManager.getCurrentUser();
        if (user.role !== 'volunteer') {
            alert('Access denied. Volunteer role required.');
            window.location.href = '../index.html';
            return;
        }

        await this.loadData();
        this.setupEventListeners();
    }

    async loadData() {
        try {
            await this.loadApplications();
            await this.loadAvailablePositions();
            this.updateStatistics();
        } catch (error) {
            console.error('Error loading data:', error);
            alert('Error loading data. Please refresh the page.');
        }
    }

    async loadApplications() {
        try {
            const response = await apiClient.get('/volunteer/applications');
            this.applications = response.data || [];
            this.renderApplications();
        } catch (error) {
            console.error('Error loading applications:', error);
            this.applications = [];
            this.renderApplications();
        }
    }

    async loadAvailablePositions() {
        try {
            const response = await apiClient.get('/volunteer/positions');
            this.availablePositions = response.data || [];
            this.populatePositionDropdown();
        } catch (error) {
            console.error('Error loading positions:', error);
            this.availablePositions = [];
        }
    }

    renderApplications() {
        const tbody = document.getElementById('applicationsTableBody');
        
        if (!this.applications.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-file-alt fa-3x mb-3"></i>
                            <h6>No Applications</h6>
                            <p class="mb-0">You haven't submitted any volunteer applications yet.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.applications.map(app => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="${this.getPositionIcon(app.position)} text-primary me-3"></i>
                        <div>
                            <h6 class="mb-0">${app.position}</h6>
                            <small class="text-muted">${app.location || 'Main Shelter'}</small>
                        </div>
                    </div>
                </td>
                <td>${this.formatDate(app.submitted_date)}</td>
                <td>${this.getStatusBadge(app.status)}</td>
                <td>${this.getNextSteps(app)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-2" onclick="viewApplicationDetails(${app.id})">
                        View Details
                    </button>
                    ${this.getActionButton(app)}
                </td>
            </tr>
        `).join('');
    }

    getPositionIcon(position) {
        const icons = {
            'Animal Care Volunteer': 'fas fa-paw',
            'Event Coordinator': 'fas fa-calendar-alt',
            'Pet Photographer': 'fas fa-camera',
            'Dog Walker': 'fas fa-dog',
            'Cat Socializer': 'fas fa-cat',
            'Foster Coordinator': 'fas fa-home',
            'Transport Volunteer': 'fas fa-car',
            'Administrative': 'fas fa-file-alt'
        };
        return icons[position] || 'fas fa-user';
    }

    getStatusBadge(status) {
        const badges = {
            'pending': '<span class="badge bg-warning">Pending</span>',
            'in_review': '<span class="badge bg-info">In Review</span>',
            'approved': '<span class="badge bg-success">Approved</span>',
            'rejected': '<span class="badge bg-danger">Rejected</span>',
            'interview_scheduled': '<span class="badge bg-primary">Interview Scheduled</span>',
            'training_required': '<span class="badge bg-secondary">Training Required</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    }

    getNextSteps(application) {
        const steps = {
            'pending': 'Awaiting initial review',
            'in_review': 'Application under review',
            'approved': 'Complete orientation',
            'rejected': 'Application closed',
            'interview_scheduled': 'Prepare for interview',
            'training_required': 'Complete required training'
        };
        return steps[application.status] || 'Contact coordinator';
    }

    getActionButton(application) {
        switch (application.status) {
            case 'approved':
                return '<button class="btn btn-sm btn-outline-success" onclick="startTraining(' + application.id + ')">Start Training</button>';
            case 'interview_scheduled':
                return '<button class="btn btn-sm btn-outline-info" onclick="viewInterview(' + application.id + ')">View Interview</button>';
            case 'training_required':
                return '<button class="btn btn-sm btn-outline-warning" onclick="viewTraining(' + application.id + ')">Continue Training</button>';
            default:
                return '';
        }
    }

    updateStatistics() {
        const stats = this.applications.reduce((acc, app) => {
            acc.total++;
            switch (app.status) {
                case 'approved':
                    acc.approved++;
                    break;
                case 'pending':
                    acc.pending++;
                    break;
                case 'in_review':
                case 'interview_scheduled':
                    acc.inReview++;
                    break;
            }
            return acc;
        }, { total: 0, approved: 0, pending: 0, inReview: 0 });

        document.getElementById('totalApplicationsCount').textContent = stats.total;
        document.getElementById('approvedCount').textContent = stats.approved;
        document.getElementById('pendingCount').textContent = stats.pending;
        document.getElementById('inReviewCount').textContent = stats.inReview;
    }

    populatePositionDropdown() {
        const select = document.getElementById('applicationPosition');
        select.innerHTML = '<option value="">Select a position</option>';
        
        this.availablePositions.forEach(position => {
            select.innerHTML += `<option value="${position.id}">${position.title}</option>`;
        });
    }

    setupEventListeners() {
        // New application form
        document.getElementById('newApplicationForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitApplication();
        });

        // Filter and sort buttons
        document.getElementById('filterBtn').addEventListener('click', () => this.showFilterModal());
        document.getElementById('sortBtn').addEventListener('click', () => this.toggleSort());
    }

    async submitApplication() {
        const form = document.getElementById('newApplicationForm');
        const formData = new FormData(form);
        
        // Get availability checkboxes
        const availability = [];
        if (document.getElementById('weekdays').checked) availability.push('weekdays');
        if (document.getElementById('weekends').checked) availability.push('weekends');
        if (document.getElementById('evenings').checked) availability.push('evenings');

        const applicationData = {
            position_id: formData.get('position'),
            location: formData.get('location'),
            availability: availability,
            experience: formData.get('experience'),
            motivation: formData.get('motivation'),
            references: [
                formData.get('reference1'),
                formData.get('reference2')
            ].filter(ref => ref.trim())
        };

        try {
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            await apiClient.post('/volunteer/applications', applicationData);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('newApplicationModal'));
            modal.hide();
            
            // Reset form
            form.reset();
            
            // Reload applications
            await this.loadApplications();
            
            alert('Application submitted successfully!');
        } catch (error) {
            console.error('Error submitting application:', error);
            alert('Error submitting application. Please try again.');
        } finally {
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').innerHTML = 'Submit Application';
        }
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    showFilterModal() {
        // Implementation for filter modal
        console.log('Show filter modal');
    }

    toggleSort() {
        // Implementation for sorting
        console.log('Toggle sort');
    }
}

// Global functions for button clicks
async function viewApplicationDetails(applicationId) {
    try {
        const response = await apiClient.get(`/volunteer/applications/${applicationId}`);
        const application = response.data;
        
        // Show application details in modal
        showApplicationDetailsModal(application);
    } catch (error) {
        console.error('Error loading application details:', error);
        alert('Error loading application details.');
    }
}

function showApplicationDetailsModal(application) {
    const modal = document.getElementById('applicationDetailsModal');
    const content = document.getElementById('applicationDetailsContent');
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Position Details</h6>
                <p><strong>Position:</strong> ${application.position}</p>
                <p><strong>Location:</strong> ${application.location || 'Main Shelter'}</p>
                <p><strong>Status:</strong> ${application.status}</p>
                <p><strong>Submitted:</strong> ${new Date(application.submitted_date).toLocaleDateString()}</p>
            </div>
            <div class="col-md-6">
                <h6>Availability</h6>
                <p>${application.availability ? application.availability.join(', ') : 'Not specified'}</p>
            </div>
        </div>
        <div class="mt-3">
            <h6>Experience</h6>
            <p>${application.experience || 'No experience provided'}</p>
        </div>
        <div class="mt-3">
            <h6>Motivation</h6>
            <p>${application.motivation || 'No motivation provided'}</p>
        </div>
        ${application.references && application.references.length > 0 ? `
            <div class="mt-3">
                <h6>References</h6>
                <ul>
                    ${application.references.map(ref => `<li>${ref}</li>`).join('')}
                </ul>
            </div>
        ` : ''}
        ${application.notes ? `
            <div class="mt-3">
                <h6>Coordinator Notes</h6>
                <p class="text-muted">${application.notes}</p>
            </div>
        ` : ''}
    `;
    
    new bootstrap.Modal(modal).show();
}

function startTraining(applicationId) {
    window.location.href = `training.html?application=${applicationId}`;
}

function viewInterview(applicationId) {
    // Implementation for viewing interview details
    console.log('View interview for application:', applicationId);
}

function viewTraining(applicationId) {
    window.location.href = `training.html?application=${applicationId}`;
}

// Initialize when page loads
let volunteerApplicationsManager;
document.addEventListener('DOMContentLoaded', () => {
    volunteerApplicationsManager = new VolunteerApplicationsManager();
});
