// Foster Reports Management
class FosterReportsManager {
    constructor() {
        this.reports = [];
        this.fosterPets = [];
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
            await this.loadReports();
            await this.loadFosterPets();
            this.updateStatistics();
        } catch (error) {
            console.error('Error loading data:', error);
            alert('Error loading data. Please refresh the page.');
        }
    }

    async loadReports() {
        try {
            const response = await apiClient.get('/foster/reports');
            this.reports = response.data || [];
            this.renderReports();
        } catch (error) {
            console.error('Error loading reports:', error);
            this.reports = [];
            this.renderReports();
        }
    }

    async loadFosterPets() {
        try {
            const response = await apiClient.get('/foster/pets');
            this.fosterPets = response.data || [];
            this.populatePetDropdown();
        } catch (error) {
            console.error('Error loading foster pets:', error);
            this.fosterPets = [];
        }
    }

    renderReports() {
        const tbody = document.getElementById('reportsTableBody');
        
        if (!this.reports.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-file-medical fa-3x mb-3"></i>
                            <h6>No Health Reports</h6>
                            <p class="mb-0">No health reports have been submitted yet.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.reports.map(report => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="${report.pet_photo || 'https://via.placeholder.com/40'}" 
                             class="rounded-circle me-3" 
                             style="width: 40px; height: 40px; object-fit: cover;"
                             alt="${report.pet_name}">
                        <div>
                            <h6 class="mb-0">${report.pet_name}</h6>
                            <small class="text-muted">${report.pet_breed || 'Mixed'}</small>
                        </div>
                    </div>
                </td>
                <td>${this.formatDate(report.report_date)}</td>
                <td>
                    <span class="badge ${this.getReportTypeBadge(report.report_type)}">
                        ${this.formatReportType(report.report_type)}
                    </span>
                </td>
                <td>
                    <span class="badge ${this.getHealthStatusBadge(report.health_status)}">
                        ${this.formatHealthStatus(report.health_status)}
                    </span>
                </td>
                <td>
                    ${report.vet_name ? `
                        <div>
                            <strong>${report.vet_name}</strong><br>
                            <small class="text-muted">${report.vet_clinic}</small>
                        </div>
                    ` : '<span class="text-muted">No vet visit</span>'}
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-2" onclick="viewReportDetails(${report.id})">
                        View Details
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editReport(${report.id})">
                        Edit
                    </button>
                </td>
            </tr>
        `).join('');
    }

    getReportTypeBadge(type) {
        const badges = {
            'routine': 'bg-primary',
            'medical': 'bg-warning',
            'emergency': 'bg-danger',
            'behavior': 'bg-info'
        };
        return badges[type] || 'bg-secondary';
    }

    formatReportType(type) {
        const types = {
            'routine': 'Routine',
            'medical': 'Medical',
            'emergency': 'Emergency',
            'behavior': 'Behavior'
        };
        return types[type] || type;
    }

    getHealthStatusBadge(status) {
        const badges = {
            'excellent': 'bg-success',
            'good': 'bg-primary',
            'fair': 'bg-warning',
            'poor': 'bg-danger',
            'needs_attention': 'bg-warning'
        };
        return badges[status] || 'bg-secondary';
    }

    formatHealthStatus(status) {
        const statuses = {
            'excellent': 'Excellent',
            'good': 'Good',
            'fair': 'Fair',
            'poor': 'Poor',
            'needs_attention': 'Needs Attention'
        };
        return statuses[status] || status;
    }

    updateStatistics() {
        const stats = this.reports.reduce((acc, report) => {
            acc.total++;
            switch (report.health_status) {
                case 'excellent':
                case 'good':
                    acc.healthy++;
                    break;
                case 'needs_attention':
                case 'fair':
                    acc.needsAttention++;
                    break;
                case 'poor':
                    acc.urgent++;
                    break;
            }
            return acc;
        }, { total: 0, healthy: 0, needsAttention: 0, urgent: 0 });

        document.getElementById('totalReportsCount').textContent = stats.total;
        document.getElementById('healthyCount').textContent = stats.healthy;
        document.getElementById('needsAttentionCount').textContent = stats.needsAttention;
        document.getElementById('urgentCount').textContent = stats.urgent;
    }

    populatePetDropdown() {
        const select = document.getElementById('reportPetId');
        select.innerHTML = '<option value="">Select a pet</option>';
        
        this.fosterPets.forEach(pet => {
            select.innerHTML += `<option value="${pet.id}">${pet.name} (${pet.breed || 'Mixed'})</option>`;
        });
    }

    setupEventListeners() {
        // New report form
        document.getElementById('newReportForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitReport();
        });

        // Filter buttons
        document.getElementById('filterAllBtn').addEventListener('click', () => this.filterReports('all'));
        document.getElementById('filterHealthyBtn').addEventListener('click', () => this.filterReports('healthy'));
        document.getElementById('filterNeedsAttentionBtn').addEventListener('click', () => this.filterReports('needs_attention'));
        document.getElementById('filterUrgentBtn').addEventListener('click', () => this.filterReports('urgent'));
    }

    async submitReport() {
        const form = document.getElementById('newReportForm');
        const formData = new FormData(form);
        
        const reportData = {
            pet_id: formData.get('petId'),
            report_type: formData.get('reportType'),
            health_status: formData.get('healthStatus'),
            weight: formData.get('weight') ? parseFloat(formData.get('weight')) : null,
            temperature: formData.get('temperature') ? parseFloat(formData.get('temperature')) : null,
            notes: formData.get('notes'),
            symptoms: formData.get('symptoms'),
            behavior_notes: formData.get('behaviorNotes'),
            vet_visit: formData.get('vetVisit') === 'yes',
            vet_name: formData.get('vetName'),
            vet_clinic: formData.get('vetClinic'),
            medications: formData.get('medications'),
            next_checkup: formData.get('nextCheckup') || null
        };

        try {
            document.getElementById('submitReportBtn').disabled = true;
            document.getElementById('submitReportBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            await apiClient.post('/foster/reports', reportData);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('newReportModal'));
            modal.hide();
            
            // Reset form
            form.reset();
            
            // Reload reports
            await this.loadReports();
            
            alert('Health report submitted successfully!');
        } catch (error) {
            console.error('Error submitting report:', error);
            alert('Error submitting report. Please try again.');
        } finally {
            document.getElementById('submitReportBtn').disabled = false;
            document.getElementById('submitReportBtn').innerHTML = 'Submit Report';
        }
    }

    filterReports(filter) {
        // Remove active class from all filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        
        // Add active class to clicked button
        document.getElementById(`filter${filter.charAt(0).toUpperCase() + filter.slice(1)}Btn`).classList.add('active');
        
        // Filter and re-render reports
        let filteredReports = this.reports;
        
        if (filter !== 'all') {
            filteredReports = this.reports.filter(report => {
                switch (filter) {
                    case 'healthy':
                        return ['excellent', 'good'].includes(report.health_status);
                    case 'needs_attention':
                        return ['needs_attention', 'fair'].includes(report.health_status);
                    case 'urgent':
                        return report.health_status === 'poor';
                    default:
                        return true;
                }
            });
        }
        
        // Re-render with filtered data
        const currentReports = this.reports;
        this.reports = filteredReports;
        this.renderReports();
        this.reports = currentReports; // Restore original data
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
}

// Global functions for button clicks
async function viewReportDetails(reportId) {
    try {
        const response = await apiClient.get(`/foster/reports/${reportId}`);
        const report = response.data;
        
        showReportDetailsModal(report);
    } catch (error) {
        console.error('Error loading report details:', error);
        alert('Error loading report details.');
    }
}

function showReportDetailsModal(report) {
    const modal = document.getElementById('reportDetailsModal');
    const content = document.getElementById('reportDetailsContent');
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Pet Information</h6>
                <p><strong>Name:</strong> ${report.pet_name}</p>
                <p><strong>Breed:</strong> ${report.pet_breed || 'Mixed'}</p>
                <p><strong>Age:</strong> ${report.pet_age || 'Unknown'}</p>
            </div>
            <div class="col-md-6">
                <h6>Report Details</h6>
                <p><strong>Date:</strong> ${new Date(report.report_date).toLocaleDateString()}</p>
                <p><strong>Type:</strong> ${report.report_type}</p>
                <p><strong>Health Status:</strong> ${report.health_status}</p>
            </div>
        </div>
        ${report.weight || report.temperature ? `
            <div class="row mt-3">
                <div class="col-md-6">
                    ${report.weight ? `<p><strong>Weight:</strong> ${report.weight} lbs</p>` : ''}
                </div>
                <div class="col-md-6">
                    ${report.temperature ? `<p><strong>Temperature:</strong> ${report.temperature}°F</p>` : ''}
                </div>
            </div>
        ` : ''}
        ${report.notes ? `
            <div class="mt-3">
                <h6>General Notes</h6>
                <p>${report.notes}</p>
            </div>
        ` : ''}
        ${report.symptoms ? `
            <div class="mt-3">
                <h6>Symptoms</h6>
                <p>${report.symptoms}</p>
            </div>
        ` : ''}
        ${report.behavior_notes ? `
            <div class="mt-3">
                <h6>Behavior Notes</h6>
                <p>${report.behavior_notes}</p>
            </div>
        ` : ''}
        ${report.vet_visit ? `
            <div class="mt-3">
                <h6>Veterinary Visit</h6>
                <p><strong>Veterinarian:</strong> ${report.vet_name}</p>
                <p><strong>Clinic:</strong> ${report.vet_clinic}</p>
                ${report.medications ? `<p><strong>Medications:</strong> ${report.medications}</p>` : ''}
                ${report.next_checkup ? `<p><strong>Next Checkup:</strong> ${new Date(report.next_checkup).toLocaleDateString()}</p>` : ''}
            </div>
        ` : ''}
    `;
    
    new bootstrap.Modal(modal).show();
}

function editReport(reportId) {
    // Implementation for editing reports
    console.log('Edit report:', reportId);
    alert('Edit functionality will be implemented in the next update.');
}

// Handle vet visit toggle
function toggleVetVisit() {
    const vetVisit = document.getElementById('vetVisit').value;
    const vetDetails = document.getElementById('vetDetails');
    
    if (vetVisit === 'yes') {
        vetDetails.style.display = 'block';
        document.getElementById('vetName').required = true;
        document.getElementById('vetClinic').required = true;
    } else {
        vetDetails.style.display = 'none';
        document.getElementById('vetName').required = false;
        document.getElementById('vetClinic').required = false;
    }
}

// Initialize when page loads
let fosterReportsManager;
document.addEventListener('DOMContentLoaded', () => {
    fosterReportsManager = new FosterReportsManager();
});
