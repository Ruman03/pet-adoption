/**
 * Veterinarian Dashboard JavaScript
 * Handles vet-specific functionality including medical records, appointments, and treatments
 */

class VetDashboard {
    constructor() {
        this.appointments = [];
        this.medicalRecords = [];
        this.stats = {};
        this.init();
    }

    async init() {
        // Check vet permissions
        if (!window.authManager.requireRole('veterinarian')) {
            return;
        }

        await this.loadDashboardData();
        this.setupEventListeners();
        this.updateUI();
    }

    async loadDashboardData() {
        try {
            await this.loadStats();
            await this.loadTodayAppointments();
            await this.loadRecentMedicalRecords();
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showError('Failed to load dashboard data');
        }
    }

    async loadStats() {
        try {
            const response = await window.apiClient.getVetStats();
            if (response.success) {
                this.stats = response.stats;
                this.updateStatsDisplay();
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async loadTodayAppointments() {
        try {
            const response = await window.apiClient.getAppointments();
            if (response.success) {
                // Filter for today's appointments
                const today = new Date().toDateString();
                this.appointments = response.appointments.filter(apt => 
                    new Date(apt.appointment_date).toDateString() === today
                );
                this.updateAppointmentsTable();
            }
        } catch (error) {
            console.error('Error loading appointments:', error);
        }
    }

    async loadRecentMedicalRecords() {
        try {
            const response = await window.apiClient.getAllMedicalRecords();
            if (response.success) {
                this.medicalRecords = response.medical_records.slice(0, 10);
                this.updateMedicalRecordsTable();
            }
        } catch (error) {
            console.error('Error loading medical records:', error);
        }
    }

    updateStatsDisplay() {
        const statsMap = {
            'todays-appointments': this.appointments.length || 0,
            'pending-treatments': this.stats.pending_treatments || 0,
            'medical-records': this.stats.total_medical_records || 0,
            'urgent-cases': this.stats.urgent_cases || 0
        };

        Object.entries(statsMap).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    updateAppointmentsTable() {
        const tbody = document.querySelector('#appointments-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        this.appointments.forEach(appointment => {
            const row = document.createElement('tr');
            const appointmentTime = new Date(appointment.appointment_date).toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            row.innerHTML = `
                <td>${appointmentTime}</td>
                <td>${appointment.pet_name || 'N/A'}</td>
                <td>${appointment.owner_name || 'N/A'}</td>
                <td>${appointment.purpose || 'Checkup'}</td>
                <td><span class="badge ${this.getAppointmentStatusBadgeClass(appointment.status)}">${appointment.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="vetDashboard.viewAppointment(${appointment.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${appointment.status === 'scheduled' ? `
                        <button class="btn btn-sm btn-success" onclick="vetDashboard.startAppointment(${appointment.id})">
                            <i class="fas fa-play"></i>
                        </button>
                    ` : ''}
                </td>
            `;
            tbody.appendChild(row);
        });

        if (this.appointments.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No appointments scheduled for today</td></tr>';
        }
    }

    updateMedicalRecordsTable() {
        const tbody = document.querySelector('#medical-records-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        this.medicalRecords.forEach(record => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${record.pet_name || 'N/A'}</td>
                <td>${record.treatment_type || 'N/A'}</td>
                <td>${new Date(record.date).toLocaleDateString()}</td>
                <td><span class="badge ${this.getTreatmentStatusBadgeClass(record.status)}">${record.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="vetDashboard.viewMedicalRecord(${record.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="vetDashboard.editMedicalRecord(${record.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    setupEventListeners() {
        // Add Medical Record button
        const addRecordBtn = document.getElementById('add-medical-record-btn');
        if (addRecordBtn) {
            addRecordBtn.addEventListener('click', () => this.showAddMedicalRecordModal());
        }

        // Schedule Appointment button
        const scheduleAppointmentBtn = document.getElementById('schedule-appointment-btn');
        if (scheduleAppointmentBtn) {
            scheduleAppointmentBtn.addEventListener('click', () => this.showScheduleAppointmentModal());
        }

        // Medical record form submission
        const medicalRecordForm = document.getElementById('addMedicalRecordForm');
        if (medicalRecordForm) {
            medicalRecordForm.addEventListener('submit', (e) => this.handleAddMedicalRecord(e));
        }
    }

    async handleAddMedicalRecord(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const recordData = {};
        formData.forEach((value, key) => {
            recordData[key] = value;
        });

        try {
            const response = await window.apiClient.createMedicalRecord(recordData);
            if (response.success) {
                this.showSuccess('Medical record added successfully!');
                this.hideAddMedicalRecordModal();
                await this.loadRecentMedicalRecords();
            } else {
                this.showError(response.message || 'Failed to add medical record');
            }
        } catch (error) {
            console.error('Error adding medical record:', error);
            this.showError('Failed to add medical record');
        }
    }

    async viewAppointment(appointmentId) {
        try {
            const response = await window.apiClient.getAppointmentById(appointmentId);
            if (response.success) {
                this.showAppointmentDetailsModal(response.appointment);
            }
        } catch (error) {
            console.error('Error loading appointment:', error);
            this.showError('Failed to load appointment details');
        }
    }

    async startAppointment(appointmentId) {
        try {
            const response = await window.apiClient.updateAppointmentStatus(appointmentId, 'in_progress');
            if (response.success) {
                this.showSuccess('Appointment started successfully!');
                await this.loadTodayAppointments();
            }
        } catch (error) {
            console.error('Error starting appointment:', error);
            this.showError('Failed to start appointment');
        }
    }

    async viewMedicalRecord(recordId) {
        try {
            const response = await window.apiClient.getMedicalRecordById(recordId);
            if (response.success) {
                this.showMedicalRecordDetailsModal(response.medical_record);
            }
        } catch (error) {
            console.error('Error loading medical record:', error);
            this.showError('Failed to load medical record details');
        }
    }

    async editMedicalRecord(recordId) {
        try {
            const response = await window.apiClient.getMedicalRecordById(recordId);
            if (response.success) {
                this.showEditMedicalRecordModal(response.medical_record);
            }
        } catch (error) {
            console.error('Error loading medical record:', error);
            this.showError('Failed to load medical record details');
        }
    }

    showAddMedicalRecordModal() {
        const modal = new bootstrap.Modal(document.getElementById('addMedicalRecordModal'));
        modal.show();
    }

    hideAddMedicalRecordModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('addMedicalRecordModal'));
        if (modal) {
            modal.hide();
        }
    }

    showScheduleAppointmentModal() {
        const modal = new bootstrap.Modal(document.getElementById('scheduleAppointmentModal'));
        modal.show();
    }

    showAppointmentDetailsModal(appointment) {
        console.log('Show appointment details modal', appointment);
    }

    showMedicalRecordDetailsModal(record) {
        console.log('Show medical record details modal', record);
    }

    showEditMedicalRecordModal(record) {
        console.log('Show edit medical record modal', record);
    }

    getAppointmentStatusBadgeClass(status) {
        const statusMap = {
            'scheduled': 'bg-warning',
            'in_progress': 'bg-primary',
            'completed': 'bg-success',
            'cancelled': 'bg-danger'
        };
        return statusMap[status] || 'bg-secondary';
    }

    getTreatmentStatusBadgeClass(status) {
        const statusMap = {
            'completed': 'bg-success',
            'ongoing': 'bg-warning',
            'pending': 'bg-info'
        };
        return statusMap[status] || 'bg-secondary';
    }

    updateUI() {
        // Update welcome message
        const welcomeMsg = document.querySelector('.welcome-user');
        if (welcomeMsg && window.authManager.user) {
            welcomeMsg.textContent = `Welcome back, Dr. ${window.authManager.user.last_name}!`;
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

        const container = document.querySelector('.container') || document.body;
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Initialize vet dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.vetDashboard = new VetDashboard();
});
