/**
 * API Client for Pet Adoption System
 * Handles all backend API communications
 */

class ApiClient {
    constructor() {
        this.baseUrl = 'http://localhost/pet-adoption-backend/api';
        this.session = null;
    }

    /**
     * Generic API request method
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            credentials: 'same-origin'
        };

        const config = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }    /**
     * Authentication Methods
     */
    async login(username, password) {
        return this.request('/auth/login.php', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
    }

    async register(userData) {
        return this.request('/users/register.php', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }    async logout() {
        return this.request('/auth/logout.php', {
            method: 'POST'
        });
    }

    async getProfile() {
        return this.request('/users/profile.php');
    }

    async updateProfile(userData) {
        return this.request('/users/update_profile.php', {
            method: 'PUT',
            body: JSON.stringify(userData)
        });
    }

    /**
     * Pet Management Methods
     */
    async getPets(filters = {}) {
        const queryParams = new URLSearchParams(filters).toString();
        const endpoint = queryParams ? `/pets/read.php?${queryParams}` : '/pets/read.php';
        return this.request(endpoint);
    }

    async getPet(id) {
        return this.request(`/pets/read_one.php?id=${id}`);
    }

    async getAvailablePets(filters = {}) {
        const queryParams = new URLSearchParams(filters).toString();
        const endpoint = queryParams ? `/pets/list_available.php?${queryParams}` : '/pets/list_available.php';
        return this.request(endpoint);
    }

    async createPet(petData) {
        return this.request('/pets/create.php', {
            method: 'POST',
            body: JSON.stringify(petData)
        });
    }

    async updatePet(id, petData) {
        return this.request(`/pets/update.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(petData)
        });
    }

    async deletePet(id) {
        return this.request(`/pets/delete.php?id=${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Application Methods
     */
    async createApplication(applicationData) {
        return this.request('/applications/create.php', {
            method: 'POST',
            body: JSON.stringify(applicationData)
        });
    }

    async getMyApplications() {
        return this.request('/applications/list_mine.php');
    }

    async getAllApplications() {
        return this.request('/applications/list_all.php');
    }

    async updateApplicationStatus(id, status) {
        return this.request(`/applications/update_status.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }

    /**
     * Foster Record Methods
     */
    async createFosterRecord(fosterData) {
        return this.request('/foster_records/create.php', {
            method: 'POST',
            body: JSON.stringify(fosterData)
        });
    }

    async getMyFosterRecords() {
        return this.request('/foster_records/list_mine.php');
    }

    async getAllFosterRecords() {
        return this.request('/foster_records/list_all.php');
    }

    async updateFosterStatus(id, status) {
        return this.request(`/foster_records/update_status.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }

    /**
     * Medical Records Methods
     */
    async createMedicalRecord(recordData) {
        return this.request('/medical_records/create.php', {
            method: 'POST',
            body: JSON.stringify(recordData)
        });
    }

    async getMedicalRecordsForPet(petId) {
        return this.request(`/medical_records/list_for_pet.php?pet_id=${petId}`);
    }

    async updateMedicalRecord(id, recordData) {
        return this.request(`/medical_records/update.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(recordData)
        });
    }

    async deleteMedicalRecord(id) {
        return this.request(`/medical_records/delete.php?id=${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Volunteer Methods
     */
    async createVolunteerApplication(applicationData) {
        return this.request('/volunteer_applications/create.php', {
            method: 'POST',
            body: JSON.stringify(applicationData)
        });
    }

    async getMyVolunteerApplications() {
        return this.request('/volunteer_applications/list_mine.php');
    }

    async getAllVolunteerApplications() {
        return this.request('/volunteer_applications/list_all.php');
    }

    async updateVolunteerApplicationStatus(id, status) {
        return this.request(`/volunteer_applications/update_status.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }

    async getVolunteerTasks() {
        return this.request('/volunteer_tasks/list.php');
    }

    async getMyVolunteerTasks() {
        return this.request('/volunteer_tasks/list_mine.php');
    }

    async createVolunteerTask(taskData) {
        return this.request('/volunteer_tasks/create.php', {
            method: 'POST',
            body: JSON.stringify(taskData)
        });
    }

    async assignVolunteerTask(taskId, volunteerId) {
        return this.request(`/volunteer_tasks/assign.php`, {
            method: 'POST',
            body: JSON.stringify({ task_id: taskId, volunteer_id: volunteerId })
        });
    }

    /**
     * Shelter Methods
     */
    async getShelters() {
        return this.request('/shelters/list.php');
    }

    async getShelter(id) {
        return this.request(`/shelters/read_one.php?id=${id}`);
    }

    async createShelter(shelterData) {
        return this.request('/shelters/create.php', {
            method: 'POST',
            body: JSON.stringify(shelterData)
        });
    }

    async updateShelter(id, shelterData) {
        return this.request(`/shelters/update.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(shelterData)
        });
    }

    async deleteShelter(id) {
        return this.request(`/shelters/delete.php?id=${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Appointment Methods
     */
    async createAppointment(appointmentData) {
        return this.request('/appointments/create.php', {
            method: 'POST',
            body: JSON.stringify(appointmentData)
        });
    }

    async getAppointments() {
        return this.request('/appointments/list.php');
    }

    async updateAppointmentStatus(id, status) {
        return this.request(`/appointments/update_status.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }

    /**
     * Favorites Methods
     */
    async addToFavorites(petId) {
        return this.request('/favorites/add.php', {
            method: 'POST',
            body: JSON.stringify({ pet_id: petId })
        });
    }

    async getFavorites() {
        return this.request('/favorites/list.php');
    }

    async removeFromFavorites(petId) {
        return this.request('/favorites/remove.php', {
            method: 'DELETE',
            body: JSON.stringify({ pet_id: petId })
        });
    }

    /**
     * Supply Request Methods
     */
    async createSupplyRequest(requestData) {
        return this.request('/supply_requests/create.php', {
            method: 'POST',
            body: JSON.stringify(requestData)
        });
    }

    async getSupplyRequests() {
        return this.request('/supply_requests/list.php');
    }

    async updateSupplyRequestStatus(id, status) {
        return this.request(`/supply_requests/update_status.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }

    /**
     * Training Methods
     */
    async createTrainingModule(moduleData) {
        return this.request('/training/create_module.php', {
            method: 'POST',
            body: JSON.stringify(moduleData)
        });
    }

    async getTrainingModules() {
        return this.request('/training/list_modules.php');
    }

    async updateTrainingProgress(moduleId, progress) {
        return this.request('/training/update_progress.php', {
            method: 'PUT',
            body: JSON.stringify({ module_id: moduleId, progress })
        });
    }

    /**
     * Notification Methods
     */
    async getNotifications() {
        return this.request('/notifications/list.php');
    }

    async markNotificationAsRead(id) {
        return this.request(`/notifications/mark_read.php?id=${id}`, {
            method: 'PUT'
        });
    }

    async deleteNotification(id) {
        return this.request(`/notifications/delete.php?id=${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Reports Methods
     */
    async getDashboardStats() {
        return this.request('/reports/dashboard_stats.php');
    }

    async getUserStats(userId = null) {
        const endpoint = userId ? `/reports/user_stats.php?user_id=${userId}` : '/reports/user_stats.php';
        return this.request(endpoint);
    }

    /**
     * User Management Methods (Admin only)
     */
    async getAllUsers() {
        return this.request('/users/list_all.php');
    }

    async getUser(id) {
        return this.request(`/users/read_one.php?id=${id}`);
    }

    async updateUserRole(id, role) {
        return this.request(`/users/update_role.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ role })
        });
    }

    async deleteUser(id) {
        return this.request(`/users/delete.php?id=${id}`, {
            method: 'DELETE'
        });
    }
}

// Create global API client instance
window.apiClient = new ApiClient();
