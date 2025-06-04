// Volunteer Tasks Management JavaScript

class VolunteerTasksManager {
    constructor() {
        this.currentFilter = 'my_tasks';
        this.myTasks = [];
        this.availableTasks = [];
        this.currentUser = null;
        this.init();
    }    async init() {
        try {
            await this.checkAuth();
            this.currentUser = authManager.getCurrentUser();
            await this.loadMyTasks();
            await this.loadAvailableTasks();
            this.setupEventListeners();
            this.setupUserInterface();
            this.renderTaskStats();
            this.renderTasks();
        } catch (error) {
            console.error('Failed to initialize volunteer tasks:', error);
            this.showAlert('Failed to load tasks', 'danger');
        }
    }

    setupUserInterface() {
        // Update user dropdown
        const user = authManager.getCurrentUser();
        if (user && user.username) {
            const userNameElement = document.getElementById('userName');
            if (userNameElement) {
                userNameElement.textContent = user.username;
            }
        }        // Setup logout functionality
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                authManager.logout();
                window.location.href = '../auth/test-login.html';
            });
        }
    }    async checkAuth() {
        if (!authManager.isAuthenticated()) {
            window.location.href = '../auth/test-login.html';
            return;
        }

        const user = authManager.getCurrentUser();
        if (user && user.role !== 'volunteer') {
            // For testing, allow any role to access volunteer tasks
            console.warn('User role is not volunteer, but allowing access for testing');
        }
    }async loadMyTasks() {
        try {
            this.showLoading();
            
            // Get volunteer's assigned tasks using the correct backend API
            const response = await apiClient.get('/volunteer_tasks/list_mine.php');
            if (response && Array.isArray(response)) {
                this.myTasks = response;
            } else {
                throw new Error('Failed to load my tasks');
            }
        } catch (error) {
            console.error('Error loading my tasks:', error);
            this.showAlert('Failed to load my tasks', 'danger');
            this.myTasks = [];
        }
    }

    async loadAvailableTasks() {
        try {
            // Get all tasks and filter for open ones
            const response = await apiClient.get('/volunteer_tasks/list.php?status=open');
            if (response && Array.isArray(response)) {
                this.availableTasks = response.filter(task => task.status === 'open');
            } else {
                throw new Error('Failed to load available tasks');
            }
        } catch (error) {
            console.error('Error loading available tasks:', error);
            this.showAlert('Failed to load available tasks', 'danger');
            this.availableTasks = [];
        } finally {
            this.hideLoading();
        }
    }    setupEventListeners() {
        // Filter tabs
        document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleFilterChange(e.target);
            });
        });

        // Task checkboxes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('task-checkbox')) {
                this.handleTaskStatusChange(e.target);
            }
        });

        // Task assignment buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('assign-task-btn')) {
                e.preventDefault();
                this.handleTaskAssignment(e.target);
            }
        });

        // Add new task modal
        const addTaskBtn = document.getElementById('addTaskBtn');
        if (addTaskBtn) {
            addTaskBtn.addEventListener('click', () => {
                this.showAddTaskModal();
            });
        }

        // Save new task
        const saveTaskBtn = document.getElementById('saveTaskBtn');
        if (saveTaskBtn) {
            saveTaskBtn.addEventListener('click', () => {
                this.handleAddTask();
            });        }
    }

    handleFilterChange(tab) {
        // Update active tab
        document.querySelectorAll('.nav-tabs .nav-link').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Set current filter based on data-filter attribute
        this.currentFilter = tab.getAttribute('data-filter') || 'my_tasks';

        // Refresh data when switching to available tasks
        if (this.currentFilter === 'available') {
            this.loadAvailableTasks().then(() => {
                this.renderTasks();
            });
        } else {
            this.renderTasks();
        }
    }

    async handleTaskStatusChange(checkbox) {
        const taskId = checkbox.dataset.taskId;
        const isCompleted = checkbox.checked;
        
        try {
            // Determine the action based on checkbox state
            const action = isCompleted ? 'complete' : 'start';
            
            const response = await apiClient.put(`/volunteer_tasks/assign.php?id=${taskId}`, {
                action: action
            });
            
            if (response && !response.error) {
                // Update local task status
                const task = this.myTasks.find(t => t.id == taskId);
                if (task) {
                    task.status = isCompleted ? 'completed' : 'in_progress';
                    if (isCompleted) {
                        task.completed_at = new Date().toISOString();
                    }
                }
                
                // Refresh the display
                this.renderTaskStats();
                this.renderTasks();
                this.showAlert(`Task ${isCompleted ? 'completed' : 'started'} successfully`, 'success');
            } else {
                throw new Error(response.error || 'Failed to update task status');
            }
        } catch (error) {
            console.error('Error updating task status:', error);
            // Revert checkbox state
            checkbox.checked = !checkbox.checked;
            this.showAlert('Failed to update task status', 'danger');
        }
    }

    async handleTaskAssignment(button) {
        const taskId = button.dataset.taskId;
        
        try {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Assigning...';
            
            const response = await apiClient.put(`/volunteer_tasks/assign.php?id=${taskId}`, {
                action: 'assign'
            });
            
            if (response && !response.error) {
                // Move task from available to my tasks
                const taskIndex = this.availableTasks.findIndex(t => t.id == taskId);
                if (taskIndex !== -1) {
                    const task = this.availableTasks.splice(taskIndex, 1)[0];
                    task.status = 'assigned';
                    task.assigned_to = this.currentUser.id;
                    task.volunteer_username = this.currentUser.username;
                    this.myTasks.push(task);
                }
                
                // Refresh the display
                this.renderTaskStats();
                this.renderTasks();
                this.showAlert('Task assigned successfully', 'success');
            } else {
                throw new Error(response.error || 'Failed to assign task');
            }
        } catch (error) {
            console.error('Error assigning task:', error);
            this.showAlert('Failed to assign task', 'danger');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-hand-paper me-1"></i>Assign to Me';
        }
    }

    async handleAddTask() {
        // Volunteers cannot create tasks - only staff/admin can
        this.showAlert('Only staff members can create new tasks', 'warning');
    }    renderTaskStats() {
        const completed = this.myTasks.filter(t => t.status === 'completed').length;
        const inProgress = this.myTasks.filter(t => t.status === 'in_progress').length;
        const assigned = this.myTasks.filter(t => t.status === 'assigned').length;
        const total = this.myTasks.length;

        // Update stat cards with correct IDs
        const totalCard = document.getElementById('totalTasksCount');
        const completedCard = document.getElementById('completedTasksCount');
        const inProgressCard = document.getElementById('inProgressTasksCount');
        const assignedCard = document.getElementById('assignedTasksCount');

        if (totalCard) totalCard.textContent = total;
        if (completedCard) completedCard.textContent = completed;
        if (inProgressCard) inProgressCard.textContent = inProgress;
        if (assignedCard) assignedCard.textContent = assigned;
    }    renderTasks() {
        const container = document.getElementById('tasksContainer');
        if (!container) return;

        let tasksToShow = [];
        
        // Determine which tasks to show based on current filter
        switch (this.currentFilter) {
            case 'my_tasks':
                tasksToShow = this.myTasks;
                break;
            case 'available':
                tasksToShow = this.availableTasks;
                break;
            case 'in_progress':
                tasksToShow = this.myTasks.filter(task => task.status === 'in_progress');
                break;
            case 'completed':
                tasksToShow = this.myTasks.filter(task => task.status === 'completed');
                break;
            default:
                tasksToShow = this.myTasks;
        }

        // Group tasks by date
        const groupedTasks = this.groupTasksByDate(tasksToShow);
        
        let html = '';
        
        if (Object.keys(groupedTasks).length === 0) {
            const emptyMessage = this.currentFilter === 'available' 
                ? 'No available tasks found' 
                : 'No tasks found';
            const emptyDescription = this.currentFilter === 'available'
                ? 'Check back later for new volunteer opportunities.'
                : 'Tasks will appear here when assigned to you.';
                
            html = `
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">${emptyMessage}</h5>
                    <p class="text-muted">${emptyDescription}</p>
                </div>
            `;
        } else {
            Object.entries(groupedTasks).forEach(([dateGroup, tasks]) => {
                html += `
                    <div class="list-group-item bg-light">
                        <h6 class="mb-0">${dateGroup}</h6>
                    </div>
                `;
                
                tasks.forEach(task => {
                    html += this.renderTaskItem(task);
                });
            });
        }
        
        container.innerHTML = html;
    }

    renderTaskItem(task) {
        const statusBadge = this.getStatusBadge(task.status);
        const isMyTask = this.currentFilter !== 'available';
        
        if (isMyTask) {
            // Render task with checkbox for my tasks
            const isChecked = task.status === 'completed' ? 'checked' : '';
            
            return `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input task-checkbox" 
                                       type="checkbox" 
                                       id="task${task.id}"
                                       data-task-id="${task.id}"
                                       ${isChecked}
                                       ${task.status === 'open' || task.status === 'cancelled' ? 'disabled' : ''}>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-1">${this.escapeHtml(task.title)}</h6>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>${this.formatDateTime(task.task_date)}
                                    ${task.shelter_name ? `<span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i>${this.escapeHtml(task.shelter_name)}</span>` : ''}
                                    ${task.urgency ? `<span class="ms-2 badge badge-sm bg-${this.getPriorityColor(task.urgency)}">${task.urgency}</span>` : ''}
                                </small>
                                ${task.description ? `<div class="mt-1"><small class="text-muted">${this.escapeHtml(task.description)}</small></div>` : ''}
                                ${task.required_skills ? `<div class="mt-1"><small class="text-info"><i class="fas fa-star me-1"></i>Skills: ${this.escapeHtml(task.required_skills)}</small></div>` : ''}
                            </div>
                        </div>
                        ${statusBadge}
                    </div>
                </div>
            `;
        } else {
            // Render available task with assign button
            return `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${this.escapeHtml(task.title)}</h6>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>${this.formatDateTime(task.task_date)}
                                ${task.shelter_name ? `<span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i>${this.escapeHtml(task.shelter_name)}</span>` : ''}
                                ${task.urgency ? `<span class="ms-2 badge badge-sm bg-${this.getPriorityColor(task.urgency)}">${task.urgency}</span>` : ''}
                            </small>
                            ${task.description ? `<div class="mt-1"><small class="text-muted">${this.escapeHtml(task.description)}</small></div>` : ''}
                            ${task.required_skills ? `<div class="mt-1"><small class="text-info"><i class="fas fa-star me-1"></i>Skills Required: ${this.escapeHtml(task.required_skills)}</small></div>` : ''}
                        </div>
                        <div class="d-flex align-items-center">
                            ${statusBadge}
                            <button class="btn btn-primary btn-sm ms-2 assign-task-btn" data-task-id="${task.id}">
                                <i class="fas fa-hand-paper me-1"></i>Assign to Me
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    groupTasksByDate(tasks) {
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        const groups = {
            'Today': [],
            'Tomorrow': [],
            'This Week': [],
            'Later': []
        };        tasks.forEach(task => {
            const taskDate = new Date(task.task_date);
            const diffTime = taskDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (this.isSameDay(taskDate, today)) {
                groups['Today'].push(task);
            } else if (this.isSameDay(taskDate, tomorrow)) {
                groups['Tomorrow'].push(task);
            } else if (diffDays <= 7 && diffDays > 1) {
                groups['This Week'].push(task);
            } else if (diffDays > 7) {
                groups['Later'].push(task);
            }
        });

        // Remove empty groups
        Object.keys(groups).forEach(key => {
            if (groups[key].length === 0) {
                delete groups[key];
            }
        });

        return groups;
    }    getStatusBadge(status) {
        const badges = {
            'open': '<span class="badge bg-secondary">Open</span>',
            'assigned': '<span class="badge bg-info">Assigned</span>',
            'in_progress': '<span class="badge bg-warning">In Progress</span>',
            'completed': '<span class="badge bg-success">Completed</span>',
            'cancelled': '<span class="badge bg-danger">Cancelled</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    }

    getPriorityColor(priority) {
        const colors = {
            'low': 'secondary',
            'medium': 'warning',
            'high': 'danger'
        };
        return colors[priority.toLowerCase()] || 'secondary';
    }    formatDateTime(date) {
        if (!date) return '';
        
        const taskDate = new Date(date);
        const dateStr = taskDate.toLocaleDateString();
        const timeStr = taskDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        return `${dateStr} ${timeStr}`;
    }

    isSameDay(date1, date2) {
        return date1.toDateString() === date2.toDateString();
    }

    showAddTaskModal() {
        const modal = new bootstrap.Modal(document.getElementById('newTaskModal'));
        modal.show();
    }

    showLoading() {
        const container = document.getElementById('tasksContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading tasks...</p>
                </div>
            `;
        }
    }

    hideLoading() {
        // Loading will be replaced by renderTasks()
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
    new VolunteerTasksManager();
});
