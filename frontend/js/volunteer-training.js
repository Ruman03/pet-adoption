/**
 * Volunteer Training Page Integration
 * Handles training module display, progress tracking, and user interaction
 */

class VolunteerTrainingManager {
    constructor() {
        this.apiClient = new APIClient();
        this.authManager = new AuthManager();
        this.modules = [];
        this.userProgress = {};
        
        this.init();
    }

    async init() {
        try {
            // Check authentication
            const user = await this.authManager.getCurrentUser();
            if (!user) {
                window.location.href = '../auth/login.html';
                return;
            }

            // Update user interface
            this.updateUserInterface(user);
            
            // Load training modules
            await this.loadTrainingModules();
            
        } catch (error) {
            console.error('Training manager initialization failed:', error);
            this.showAlert('Failed to initialize training system', 'danger');
        }
    }    updateUserInterface(user) {
        // Update user dropdown
        const userDropdown = document.querySelector('.dropdown-toggle');
        if (userDropdown) {
            userDropdown.innerHTML = `
                <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                     class="rounded-circle me-2" 
                     alt="${user.name}"
                     style="width: 32px; height: 32px; object-fit: cover;">
                ${user.name}
            `;
        }

        // Add logout functionality
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.authManager.logout();
                window.location.href = '../auth/login.html';
            });
        }
    }

    async loadTrainingModules() {
        try {
            const response = await this.apiClient.request('/training/list_modules.php');
            
            if (response.success && response.data.modules) {
                this.modules = response.data.modules;
                this.renderTrainingModules();
                this.updateOverallProgress();
            } else {
                throw new Error(response.message || 'Failed to load training modules');
            }
        } catch (error) {
            console.error('Error loading training modules:', error);
            this.showAlert('Failed to load training modules', 'danger');        }
    }

    renderTrainingModules() {
        const requiredContainer = document.getElementById('requiredCoursesContainer');
        const optionalContainer = document.getElementById('optionalCoursesContainer');
        
        const requiredModules = this.modules.filter(module => module.is_required);
        const optionalModules = this.modules.filter(module => !module.is_required);
        
        // Render required modules
        if (requiredModules.length > 0) {
            requiredContainer.innerHTML = requiredModules.map(module => 
                this.renderModuleCard(module)).join('');
        } else {
            requiredContainer.innerHTML = this.renderEmptyState('No required training modules found');
        }
        
        // Render optional modules
        if (optionalModules.length > 0) {
            optionalContainer.innerHTML = optionalModules.map(module => 
                this.renderModuleCard(module)).join('');
        } else {
            optionalContainer.innerHTML = this.renderEmptyState('No optional training modules available');
        }
        
        // Attach event listeners
        this.attachEventListeners();
    }

    renderModuleCard(module) {
        const progress = module.progress_percentage || 0;
        const status = module.progress_status || 'not_started';
        
        const statusConfig = this.getStatusConfig(status, progress);
        const difficultyConfig = this.getDifficultyConfig(module.difficulty);
        
        return `
            <div class="col-md-4">
                <div class="card course-card h-100" data-module-id="${module.id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas ${this.getCategoryIcon(module.category)} text-primary fa-2x me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">${module.title}</h5>
                                    <span class="badge ${statusConfig.badgeClass}">${statusConfig.text}</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Progress</small>
                                <strong class="${statusConfig.textClass}">${progress}%</strong>
                            </div>
                        </div>
                        <p class="card-text">${module.description}</p>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Duration: ${module.duration_minutes} minutes</small>
                                <span class="badge ${difficultyConfig.badgeClass}">${difficultyConfig.text}</span>
                            </div>
                            <div class="progress mb-2">
                                <div class="progress-bar ${statusConfig.progressClass}" 
                                     role="progressbar" 
                                     style="width: ${progress}%"></div>
                            </div>
                        </div>
                        ${this.renderModuleActions(module, status, progress)}
                    </div>
                </div>
            </div>
        `;
    }

    renderModuleActions(module, status, progress) {
        const isCompleted = status === 'completed';
        const isInProgress = status === 'in_progress';
        const canStart = this.canStartModule(module);
        
        if (isCompleted) {
            return `
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary review-course-btn" data-module-id="${module.id}">
                        <i class="fas fa-eye me-2"></i>Review Course
                    </button>
                    ${progress < 100 ? `
                        <button class="btn btn-sm btn-success retake-course-btn" data-module-id="${module.id}">
                            <i class="fas fa-redo me-2"></i>Retake Course
                        </button>
                    ` : ''}
                </div>
            `;
        } else if (isInProgress) {
            return `
                <button class="btn btn-primary w-100 continue-course-btn" data-module-id="${module.id}">
                    <i class="fas fa-play me-2"></i>Continue Course
                </button>
            `;
        } else if (canStart) {
            return `
                <button class="btn btn-primary w-100 start-course-btn" data-module-id="${module.id}">
                    <i class="fas fa-play me-2"></i>Start Course
                </button>
            `;
        } else {
            return `
                <button class="btn btn-outline-secondary w-100" disabled>
                    <i class="fas fa-lock me-2"></i>${this.getPrerequisiteText(module)}
                </button>
            `;
        }
    }

    getStatusConfig(status, progress) {
        switch (status) {
            case 'completed':
                return {
                    text: 'Completed',
                    badgeClass: 'bg-success',
                    textClass: 'text-success',
                    progressClass: 'bg-success'
                };
            case 'in_progress':
                return {
                    text: 'In Progress',
                    badgeClass: 'bg-warning',
                    textClass: 'text-warning',
                    progressClass: 'bg-warning'
                };
            default:
                return {
                    text: 'Not Started',
                    badgeClass: 'bg-secondary',
                    textClass: 'text-secondary',
                    progressClass: 'bg-secondary'
                };
        }
    }

    getDifficultyConfig(difficulty) {
        switch (difficulty) {
            case 'beginner':
                return { text: 'Beginner', badgeClass: 'bg-success' };
            case 'intermediate':
                return { text: 'Intermediate', badgeClass: 'bg-warning' };
            case 'advanced':
                return { text: 'Advanced', badgeClass: 'bg-danger' };
            default:
                return { text: 'Basic', badgeClass: 'bg-info' };
        }
    }

    getCategoryIcon(category) {
        const icons = {
            'basic_care': 'fa-paw',
            'safety': 'fa-shield-alt',
            'behavior': 'fa-heart',
            'photography': 'fa-camera',
            'events': 'fa-calendar-alt',
            'advanced_care': 'fa-stethoscope',
            'administration': 'fa-clipboard-list'
        };
        return icons[category] || 'fa-book';
    }

    canStartModule(module) {
        // Check if prerequisites are met
        if (!module.prerequisites) return true;
        
        const prerequisites = JSON.parse(module.prerequisites);
        return prerequisites.every(prereqId => {
            const prereqModule = this.modules.find(m => m.id === prereqId);
            return prereqModule && prereqModule.progress_status === 'completed';
        });
    }

    getPrerequisiteText(module) {
        if (!module.prerequisites) return 'Complete Prerequisites First';
        
        const prerequisites = JSON.parse(module.prerequisites);
        const prereqNames = prerequisites.map(prereqId => {
            const prereqModule = this.modules.find(m => m.id === prereqId);
            return prereqModule ? prereqModule.title : 'Unknown';
        });
        
        return `Complete: ${prereqNames.join(', ')}`;
    }

    updateOverallProgress() {
        const requiredModules = this.modules.filter(module => module.is_required);
        if (requiredModules.length === 0) return;
        
        const totalProgress = requiredModules.reduce((sum, module) => 
            sum + (module.progress_percentage || 0), 0);
        const averageProgress = Math.round(totalProgress / requiredModules.length);
        
        // Update progress bar and text
        const progressBar = document.querySelector('.overall-progress-bar');
        const progressText = document.querySelector('.overall-progress-text');
        
        if (progressBar) {
            progressBar.style.width = `${averageProgress}%`;
            progressBar.setAttribute('aria-valuenow', averageProgress);
        }
        
        if (progressText) {
            progressText.textContent = `${averageProgress}%`;
        }
    }

    attachEventListeners() {
        // Start course buttons
        document.querySelectorAll('.start-course-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const moduleId = parseInt(e.target.dataset.moduleId);
                this.startCourse(moduleId);
            });
        });

        // Continue course buttons
        document.querySelectorAll('.continue-course-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const moduleId = parseInt(e.target.dataset.moduleId);
                this.continueCourse(moduleId);
            });
        });

        // Review course buttons
        document.querySelectorAll('.review-course-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const moduleId = parseInt(e.target.dataset.moduleId);
                this.reviewCourse(moduleId);
            });
        });

        // Retake course buttons
        document.querySelectorAll('.retake-course-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const moduleId = parseInt(e.target.dataset.moduleId);
                this.retakeCourse(moduleId);
            });
        });
    }

    async startCourse(moduleId) {
        try {
            const response = await this.apiClient.request('/training/update_progress.php', {
                method: 'POST',
                body: {
                    module_id: moduleId,
                    status: 'in_progress',
                    progress_percentage: 0
                }
            });

            if (response.success) {
                this.showAlert('Course started successfully!', 'success');
                await this.loadTrainingModules(); // Refresh the display
                
                // Simulate course navigation (in real implementation, would redirect to course content)
                setTimeout(() => {
                    this.simulateCourseProgress(moduleId);
                }, 1000);
            } else {
                throw new Error(response.message || 'Failed to start course');
            }
        } catch (error) {
            console.error('Error starting course:', error);
            this.showAlert('Failed to start course', 'danger');
        }
    }

    async continueCourse(moduleId) {
        // In a real implementation, this would redirect to the course content
        this.simulateCourseProgress(moduleId);
    }

    async reviewCourse(moduleId) {
        const module = this.modules.find(m => m.id === moduleId);
        if (module) {
            this.showAlert(`Opening course: ${module.title}`, 'info');
            // In a real implementation, would redirect to course content
        }
    }

    async retakeCourse(moduleId) {
        try {
            const response = await this.apiClient.request('/training/update_progress.php', {
                method: 'POST',
                body: {
                    module_id: moduleId,
                    status: 'in_progress',
                    progress_percentage: 0
                }
            });

            if (response.success) {
                this.showAlert('Course reset successfully!', 'success');
                await this.loadTrainingModules();
            } else {
                throw new Error(response.message || 'Failed to reset course');
            }
        } catch (error) {
            console.error('Error resetting course:', error);
            this.showAlert('Failed to reset course', 'danger');
        }
    }

    // Simulate course progress for demonstration
    async simulateCourseProgress(moduleId) {
        const module = this.modules.find(m => m.id === moduleId);
        if (!module) return;

        const progressSteps = [25, 50, 75, 100];
        const stepDelay = 2000; // 2 seconds between steps

        this.showAlert(`Starting course: ${module.title}`, 'info');

        for (let i = 0; i < progressSteps.length; i++) {
            await new Promise(resolve => setTimeout(resolve, stepDelay));
            
            const progress = progressSteps[i];
            const status = progress === 100 ? 'completed' : 'in_progress';
            
            try {
                await this.apiClient.request('/training/update_progress.php', {
                    method: 'POST',
                    body: {
                        module_id: moduleId,
                        status: status,
                        progress_percentage: progress
                    }
                });
                
                this.showAlert(`Progress: ${progress}%`, 'info');
                await this.loadTrainingModules(); // Refresh display
                
            } catch (error) {
                console.error('Error updating progress:', error);
                break;
            }
        }
        
        this.showAlert(`Course completed: ${module.title}!`, 'success');
    }

    renderEmptyState(message) {
        return `
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">${message}</h5>
                </div>
            </div>
        `;
    }

    renderErrorState() {
        const containers = ['requiredCoursesContainer', 'optionalCoursesContainer'];
        containers.forEach(containerId => {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5 class="text-muted">Failed to load training modules</h5>
                            <button class="btn btn-primary mt-3" onclick="location.reload()">
                                <i class="fas fa-redo me-2"></i>Try Again
                            </button>
                        </div>
                    </div>
                `;
            }
        });
    }

    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) return;

        const alertId = 'alert-' + Date.now();
        const alertElement = document.createElement('div');
        alertElement.id = alertId;
        alertElement.className = `alert alert-${type} alert-dismissible fade show`;
        alertElement.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        alertContainer.appendChild(alertElement);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new VolunteerTrainingManager();
});
