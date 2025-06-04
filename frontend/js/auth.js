/**
 * Authentication utilities for the Pet Adoption System
 */

class AuthManager {
    constructor() {
        this.user = null;
        this.isLoggedIn = false;
        this.init();
    }

    init() {
        // Check if user is logged in from localStorage
        const storedUser = localStorage.getItem('user');
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        
        if (storedUser && isLoggedIn) {
            this.user = JSON.parse(storedUser);
            this.isLoggedIn = true;
        }
    }

    async login(username, password) {
        try {
            const response = await window.apiClient.login(username, password);
            
            if (response.success) {
                this.user = response.user;
                this.isLoggedIn = true;
                
                // Store in localStorage
                localStorage.setItem('user', JSON.stringify(response.user));
                localStorage.setItem('isLoggedIn', 'true');
                
                return response;
            }
            
            return response;
        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    }

    async logout() {
        try {
            await window.apiClient.logout();
        } catch (error) {
            console.error('Logout API error:', error);
        } finally {
            // Clear local storage regardless of API call success
            this.user = null;
            this.isLoggedIn = false;
            localStorage.removeItem('user');
            localStorage.removeItem('isLoggedIn');
            
            // Redirect to login page
            window.location.href = '../auth/login.html';
        }
    }

    requireAuth() {
        if (!this.isLoggedIn) {
            window.location.href = '../auth/login.html';
            return false;
        }
        return true;
    }

    requireRole(requiredRole) {
        if (!this.requireAuth()) {
            return false;
        }

        if (this.user.role !== requiredRole) {
            alert('Access denied. You do not have permission to view this page.');
            window.location.href = this.getDefaultDashboard();
            return false;
        }
        return true;
    }

    requireRoles(requiredRoles) {
        if (!this.requireAuth()) {
            return false;
        }

        if (!requiredRoles.includes(this.user.role)) {
            alert('Access denied. You do not have permission to view this page.');
            window.location.href = this.getDefaultDashboard();
            return false;
        }
        return true;
    }

    getDefaultDashboard() {
        if (!this.user) return '../index.html';

        switch(this.user.role) {
            case 'admin':
                return '../admin/dashboard.html';
            case 'adopter':
                return '../user/profile.html';
            case 'shelter_staff':
                return '../staff/dashboard.html';
            case 'volunteer':
                return '../volunteer/dashboard.html';
            case 'veterinarian':
                return '../vet/dashboard.html';
            case 'foster_parent':
                return '../foster/dashboard.html';
            default:
                return '../index.html';
        }
    }

    updateNavigation() {
        // Update navigation elements based on auth state
        const userNameElements = document.querySelectorAll('.user-name');
        const userRoleElements = document.querySelectorAll('.user-role');
        const logoutButtons = document.querySelectorAll('.logout-btn');

        if (this.isLoggedIn && this.user) {
            userNameElements.forEach(el => {
                el.textContent = `${this.user.first_name} ${this.user.last_name}`;
            });
            
            userRoleElements.forEach(el => {
                el.textContent = this.formatRole(this.user.role);
            });

            logoutButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.logout();
                });
            });
        }
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

    async getCurrentUser() {
        if (!this.isLoggedIn) return null;

        try {
            // Refresh user data from API
            const response = await window.apiClient.getProfile();
            if (response.success) {
                this.user = response.user;
                localStorage.setItem('user', JSON.stringify(response.user));
                return this.user;
            }
        } catch (error) {
            console.error('Error fetching current user:', error);
        }
        
        return this.user;
    }

    hasPermission(permission) {
        if (!this.isLoggedIn || !this.user) return false;

        const rolePermissions = {
            'admin': ['all'],
            'shelter_staff': ['pets', 'applications', 'medical_records', 'shelters'],
            'veterinarian': ['medical_records', 'appointments', 'treatments'],
            'volunteer': ['volunteer_tasks', 'basic_pets'],
            'foster_parent': ['foster_records', 'basic_pets'],
            'adopter': ['applications', 'favorites', 'basic_pets']
        };

        const userPermissions = rolePermissions[this.user.role] || [];
        return userPermissions.includes('all') || userPermissions.includes(permission);
    }
}

// Create global auth manager instance
window.authManager = new AuthManager();

// Initialize navigation updates when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.authManager.updateNavigation();
});
