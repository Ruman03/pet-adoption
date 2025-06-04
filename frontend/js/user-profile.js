/**
 * User Profile JavaScript
 * Handles user profile functionality including applications, favorites, and profile management
 */

class UserProfile {
    constructor() {
        this.user = null;
        this.applications = [];
        this.favorites = [];
        this.notifications = [];
        this.init();
    }

    async init() {
        // Check if user is logged in
        if (!window.authManager.requireAuth()) {
            return;
        }

        this.user = window.authManager.user;
        await this.loadUserData();
        this.setupEventListeners();
        this.updateUI();
    }

    async loadUserData() {
        try {
            await this.loadApplications();
            await this.loadFavorites();
            await this.loadNotifications();
            await this.refreshUserProfile();
        } catch (error) {
            console.error('Error loading user data:', error);
            this.showError('Failed to load user data');
        }
    }

    async loadApplications() {
        try {
            const response = await window.apiClient.getMyApplications();
            if (response.success) {
                this.applications = response.applications;
                this.updateApplicationsDisplay();
            }
        } catch (error) {
            console.error('Error loading applications:', error);
        }
    }

    async loadFavorites() {
        try {
            const response = await window.apiClient.getFavorites();
            if (response.success) {
                this.favorites = response.favorites;
                this.updateFavoritesDisplay();
            }
        } catch (error) {
            console.error('Error loading favorites:', error);
        }
    }

    async loadNotifications() {
        try {
            const response = await window.apiClient.getNotifications();
            if (response.success) {
                this.notifications = response.notifications;
                this.updateNotificationsDisplay();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    async refreshUserProfile() {
        try {
            const response = await window.apiClient.getProfile();
            if (response.success) {
                this.user = response.user;
                window.authManager.user = response.user;
                localStorage.setItem('user', JSON.stringify(response.user));
                this.updateProfileDisplay();
            }
        } catch (error) {
            console.error('Error refreshing profile:', error);
        }
    }

    updateApplicationsDisplay() {
        const container = document.getElementById('applications-container');
        if (!container) return;

        container.innerHTML = '';

        if (this.applications.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5>No Applications Yet</h5>
                    <p class="text-muted">Start browsing our available pets to submit your first application!</p>
                    <a href="../pets/available.html" class="btn btn-primary">Browse Pets</a>
                </div>
            `;
            return;
        }

        this.applications.forEach(app => {
            const applicationCard = document.createElement('div');
            applicationCard.className = 'card mb-3';
            applicationCard.innerHTML = `
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <img src="${app.pet_photo_url || 'https://via.placeholder.com/80'}" 
                                 alt="${app.pet_name}" class="img-fluid rounded">
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">${app.pet_name}</h5>
                            <p class="card-text">
                                <small class="text-muted">Applied: ${new Date(app.created_at).toLocaleDateString()}</small>
                            </p>
                            <p class="card-text">${app.message || 'No message provided'}</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge ${this.getApplicationStatusBadgeClass(app.status)} mb-2">${app.status}</span>
                            <br>
                            <button class="btn btn-sm btn-outline-primary" onclick="userProfile.viewApplication(${app.id})">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(applicationCard);
        });
    }

    updateFavoritesDisplay() {
        const container = document.getElementById('favorites-container');
        if (!container) return;

        container.innerHTML = '';

        if (this.favorites.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                    <h5>No Favorites Yet</h5>
                    <p class="text-muted">Save pets you're interested in to your favorites!</p>
                    <a href="../pets/available.html" class="btn btn-primary">Browse Pets</a>
                </div>
            `;
            return;
        }

        this.favorites.forEach(fav => {
            const favoriteCard = document.createElement('div');
            favoriteCard.className = 'col-md-6 col-lg-4 mb-4';
            favoriteCard.innerHTML = `
                <div class="card h-100">
                    <img src="${fav.pet_photo_url || 'https://via.placeholder.com/300x200'}" 
                         class="card-img-top" alt="${fav.pet_name}" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title">${fav.pet_name}</h5>
                        <p class="card-text">
                            <span class="badge bg-info">${fav.pet_species}</span>
                            <span class="badge bg-secondary">${fav.pet_breed}</span>
                        </p>
                        <p class="card-text">${fav.pet_description || 'No description available'}</p>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary btn-sm" onclick="userProfile.viewPet(${fav.pet_id})">
                            View Pet
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="userProfile.removeFavorite(${fav.pet_id})">
                            <i class="fas fa-heart-broken"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(favoriteCard);
        });
    }

    updateNotificationsDisplay() {
        const container = document.getElementById('notifications-container');
        if (!container) return;

        container.innerHTML = '';

        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                    <h5>No Notifications</h5>
                    <p class="text-muted">You're all caught up!</p>
                </div>
            `;
            return;
        }

        this.notifications.forEach(notification => {
            const notificationElement = document.createElement('div');
            notificationElement.className = `alert ${notification.is_read ? 'alert-light' : 'alert-info'} alert-dismissible`;
            notificationElement.innerHTML = `
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>${notification.title}</strong>
                        <p class="mb-1">${notification.message}</p>
                        <small class="text-muted">${new Date(notification.created_at).toLocaleString()}</small>
                    </div>
                    <div>
                        ${!notification.is_read ? `
                            <button class="btn btn-sm btn-outline-primary" onclick="userProfile.markNotificationRead(${notification.id})">
                                Mark as Read
                            </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-danger" onclick="userProfile.deleteNotification(${notification.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(notificationElement);
        });
    }

    updateProfileDisplay() {
        if (!this.user) return;

        // Update profile information
        const profileElements = {
            'profile-name': `${this.user.first_name} ${this.user.last_name}`,
            'profile-email': this.user.email,
            'profile-phone': this.user.phone || 'Not provided',
            'profile-address': this.user.address || 'Not provided',
            'profile-member-since': new Date(this.user.created_at).toLocaleDateString()
        };

        Object.entries(profileElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });

        // Update profile form
        this.populateProfileForm();
    }

    populateProfileForm() {
        const form = document.getElementById('profileForm');
        if (!form || !this.user) return;

        const fields = ['first_name', 'last_name', 'email', 'phone', 'address'];
        fields.forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && this.user[field]) {
                input.value = this.user[field];
            }
        });
    }

    setupEventListeners() {
        // Profile form submission
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', (e) => this.handleProfileUpdate(e));
        }

        // Password change form
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', (e) => this.handlePasswordChange(e));
        }

        // Tab navigation
        this.setupTabNavigation();
    }

    async handleProfileUpdate(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const profileData = {};
        formData.forEach((value, key) => {
            profileData[key] = value;
        });

        try {
            const response = await window.apiClient.updateProfile(profileData);
            if (response.success) {
                this.showSuccess('Profile updated successfully!');
                await this.refreshUserProfile();
            } else {
                this.showError(response.message || 'Failed to update profile');
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            this.showError('Failed to update profile');
        }
    }

    async handlePasswordChange(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const currentPassword = formData.get('current_password');
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');

        if (newPassword !== confirmPassword) {
            this.showError('New passwords do not match');
            return;
        }

        try {
            const response = await window.apiClient.changePassword(currentPassword, newPassword);
            if (response.success) {
                this.showSuccess('Password changed successfully!');
                e.target.reset();
            } else {
                this.showError(response.message || 'Failed to change password');
            }
        } catch (error) {
            console.error('Error changing password:', error);
            this.showError('Failed to change password');
        }
    }

    setupTabNavigation() {
        const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabButtons.forEach(button => {
            button.addEventListener('shown.bs.tab', (e) => {
                const target = e.target.getAttribute('data-bs-target');
                if (target === '#applications' && this.applications.length === 0) {
                    this.loadApplications();
                } else if (target === '#favorites' && this.favorites.length === 0) {
                    this.loadFavorites();
                } else if (target === '#notifications' && this.notifications.length === 0) {
                    this.loadNotifications();
                }
            });
        });
    }

    async viewApplication(applicationId) {
        // Navigate to application details or show modal
        console.log('View application:', applicationId);
    }

    async viewPet(petId) {
        // Navigate to pet details
        window.location.href = `../pets/details.html?id=${petId}`;
    }

    async removeFavorite(petId) {
        if (!confirm('Are you sure you want to remove this pet from your favorites?')) {
            return;
        }

        try {
            const response = await window.apiClient.removeFavorite(petId);
            if (response.success) {
                this.showSuccess('Pet removed from favorites');
                await this.loadFavorites();
            }
        } catch (error) {
            console.error('Error removing favorite:', error);
            this.showError('Failed to remove favorite');
        }
    }

    async markNotificationRead(notificationId) {
        try {
            const response = await window.apiClient.markNotificationRead(notificationId);
            if (response.success) {
                await this.loadNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async deleteNotification(notificationId) {
        try {
            const response = await window.apiClient.deleteNotification(notificationId);
            if (response.success) {
                await this.loadNotifications();
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
        }
    }

    getApplicationStatusBadgeClass(status) {
        const statusMap = {
            'pending': 'bg-warning',
            'approved': 'bg-success',
            'rejected': 'bg-danger',
            'under_review': 'bg-info'
        };
        return statusMap[status] || 'bg-secondary';
    }

    updateUI() {
        // Update page title
        const pageTitle = document.querySelector('.profile-title');
        if (pageTitle && this.user) {
            pageTitle.textContent = `${this.user.first_name}'s Profile`;
        }

        // Update stats
        this.updateStatsDisplay();
    }

    updateStatsDisplay() {
        const statsMap = {
            'total-applications': this.applications.length,
            'pending-applications': this.applications.filter(app => app.status === 'pending').length,
            'favorite-pets': this.favorites.length,
            'unread-notifications': this.notifications.filter(notif => !notif.is_read).length
        };

        Object.entries(statsMap).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
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

// Initialize user profile when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.userProfile = new UserProfile();
});
