/**
 * Volunteer Dashboard JavaScript
 * Handles volunteer-specific functionality including tasks, schedule, and training
 */

class VolunteerDashboard {
    constructor() {
        this.tasks = [];
        this.upcomingShifts = [];
        this.stats = {};
        this.init();
    }

    async init() {
        // Check volunteer permissions
        if (!window.authManager.requireRole('volunteer')) {
            return;
        }

        await this.loadDashboardData();
        this.setupEventListeners();
        this.updateUI();
    }

    async loadDashboardData() {
        try {
            await this.loadStats();
            await this.loadMyTasks();
            await this.loadUpcomingShifts();
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showError('Failed to load dashboard data');
        }
    }

    async loadStats() {
        try {
            const response = await window.apiClient.getVolunteerStats();
            if (response.success) {
                this.stats = response.stats;
                this.updateStatsDisplay();
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async loadMyTasks() {
        try {
            const response = await window.apiClient.getMyVolunteerTasks();
            if (response.success) {
                this.tasks = response.tasks;
                this.updateTasksTable();
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
        }
    }

    async loadUpcomingShifts() {
        try {
            // This would be a custom endpoint for volunteer shifts
            // For now, we'll use the tasks as shifts
            this.upcomingShifts = this.tasks.filter(task => 
                task.status === 'assigned' && new Date(task.scheduled_date) > new Date()
            ).slice(0, 5);
            this.updateUpcomingShiftsDisplay();
        } catch (error) {
            console.error('Error loading upcoming shifts:', error);
        }
    }

    updateStatsDisplay() {
        const statsMap = {
            'total-hours': this.stats.total_hours || 0,
            'completed-tasks': this.stats.completed_tasks || 0,
            'pending-tasks': this.stats.pending_tasks || 0,
            'upcoming-shifts': this.upcomingShifts.length || 0
        };

        Object.entries(statsMap).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    updateTasksTable() {
        const tbody = document.querySelector('#tasks-table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        this.tasks.forEach(task => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${task.title}</strong></td>
                <td>${task.description || 'No description'}</td>
                <td>${task.scheduled_date ? new Date(task.scheduled_date).toLocaleDateString() : 'TBD'}</td>
                <td><span class="badge ${this.getTaskStatusBadgeClass(task.status)}">${task.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="volunteerDashboard.viewTask(${task.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${task.status === 'assigned' ? `
                        <button class="btn btn-sm btn-success" onclick="volunteerDashboard.completeTask(${task.id})">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                </td>
            `;
            tbody.appendChild(row);
        });

        if (this.tasks.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No tasks assigned</td></tr>';
        }
    }

    updateUpcomingShiftsDisplay() {
        const container = document.getElementById('upcoming-shifts-list');
        if (!container) return;

        container.innerHTML = '';

        this.upcomingShifts.forEach(shift => {
            const shiftElement = document.createElement('div');
            shiftElement.className = 'border-bottom pb-2 mb-2';
            shiftElement.innerHTML = `
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>${shift.title}</strong>
                        <br>
                        <small class="text-muted">${new Date(shift.scheduled_date).toLocaleDateString()}</small>
                    </div>
                    <span class="badge bg-warning">${shift.status}</span>
                </div>
            `;
            container.appendChild(shiftElement);
        });

        if (this.upcomingShifts.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">No upcoming shifts</p>';
        }
    }

    setupEventListeners() {
        // Apply for tasks button
        const applyTaskBtn = document.getElementById('apply-for-tasks-btn');
        if (applyTaskBtn) {
            applyTaskBtn.addEventListener('click', () => this.showAvailableTasksModal());
        }

        // View schedule button
        const viewScheduleBtn = document.getElementById('view-schedule-btn');
        if (viewScheduleBtn) {
            viewScheduleBtn.addEventListener('click', () => this.showScheduleModal());
        }

        // Training modules button
        const trainingBtn = document.getElementById('training-modules-btn');
        if (trainingBtn) {
            trainingBtn.addEventListener('click', () => window.location.href = 'training.html');
        }
    }

    async viewTask(taskId) {
        try {
            const response = await window.apiClient.getVolunteerTaskById(taskId);
            if (response.success) {
                this.showTaskDetailsModal(response.task);
            }
        } catch (error) {
            console.error('Error loading task:', error);
            this.showError('Failed to load task details');
        }
    }

    async completeTask(taskId) {
        if (!confirm('Are you sure you want to mark this task as completed?')) {
            return;
        }

        try {
            const response = await window.apiClient.updateVolunteerTaskStatus(taskId, 'completed');
            if (response.success) {
                this.showSuccess('Task marked as completed!');
                await this.loadMyTasks();
                await this.loadStats();
            }
        } catch (error) {
            console.error('Error completing task:', error);
            this.showError('Failed to complete task');
        }
    }

    async applyForTask(taskId) {
        try {
            const response = await window.apiClient.assignVolunteerTask(taskId);
            if (response.success) {
                this.showSuccess('Successfully applied for task!');
                await this.loadMyTasks();
            }
        } catch (error) {
            console.error('Error applying for task:', error);
            this.showError('Failed to apply for task');
        }
    }

    showAvailableTasksModal() {
        // Load and show available tasks
        this.loadAvailableTasks();
        const modal = new bootstrap.Modal(document.getElementById('availableTasksModal'));
        modal.show();
    }

    async loadAvailableTasks() {
        try {
            const response = await window.apiClient.getAvailableVolunteerTasks();
            if (response.success) {
                this.displayAvailableTasks(response.tasks);
            }
        } catch (error) {
            console.error('Error loading available tasks:', error);
        }
    }

    displayAvailableTasks(tasks) {
        const container = document.getElementById('available-tasks-list');
        if (!container) return;

        container.innerHTML = '';

        tasks.forEach(task => {
            const taskElement = document.createElement('div');
            taskElement.className = 'card mb-2';
            taskElement.innerHTML = `
                <div class="card-body">
                    <h6 class="card-title">${task.title}</h6>
                    <p class="card-text">${task.description}</p>
                    <p class="text-muted small">
                        <i class="fas fa-calendar"></i> ${new Date(task.scheduled_date).toLocaleDateString()}
                    </p>
                    <button class="btn btn-sm btn-primary" onclick="volunteerDashboard.applyForTask(${task.id})">
                        Apply
                    </button>
                </div>
            `;
            container.appendChild(taskElement);
        });
    }

    showScheduleModal() {
        const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        modal.show();
    }

    showTaskDetailsModal(task) {
        console.log('Show task details modal', task);
    }

    getTaskStatusBadgeClass(status) {
        const statusMap = {
            'assigned': 'bg-warning',
            'in_progress': 'bg-primary',
            'completed': 'bg-success',
            'cancelled': 'bg-danger'
        };
        return statusMap[status] || 'bg-secondary';
    }

    updateUI() {
        // Update welcome message
        const welcomeMsg = document.querySelector('.welcome-user');
        if (welcomeMsg && window.authManager.user) {
            welcomeMsg.textContent = `Welcome back, ${window.authManager.user.first_name}!`;
        }

        // Update stats in welcome card
        const totalHoursElement = document.querySelector('.total-hours');
        if (totalHoursElement) {
            totalHoursElement.textContent = `${this.stats.total_hours || 0} hours`;
        }

        const upcomingShiftsElement = document.querySelector('.upcoming-shifts-count');
        if (upcomingShiftsElement) {
            upcomingShiftsElement.textContent = `You have ${this.upcomingShifts.length} upcoming volunteer shifts this week`;
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

// Initialize volunteer dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.volunteerDashboard = new VolunteerDashboard();
});
