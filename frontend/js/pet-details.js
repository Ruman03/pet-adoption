/**
 * Pet Details JavaScript Module
 * Handles individual pet detail page functionality including
 * pet information display, adoption applications, and favorites
 */

class PetDetails {
    constructor() {
        this.currentPet = null;
        this.currentUser = null;
        this.petId = null;
        this.init();
    }

    async init() {
        try {
            // Get pet ID from URL parameters
            this.petId = this.getPetIdFromUrl();
            if (!this.petId) {
                this.showPetNotFound();
                return;
            }

            // Check if user is logged in (optional for viewing, required for actions)
            this.currentUser = window.authManager.getCurrentUser();
            
            await this.loadPetDetails();
            this.setupEventListeners();
        } catch (error) {
            console.error('Pet details initialization failed:', error);
            this.showError('Failed to load pet details');
        }
    }

    getPetIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    async loadPetDetails() {
        try {
            // Show loading state
            document.getElementById('loading-state').classList.remove('d-none');
            document.getElementById('pet-details-content').classList.add('d-none');

            // Fetch pet details
            const response = await window.apiClient.request(`/pets/read_one.php?id=${this.petId}`);
            
            if (response.success && response.data) {
                this.currentPet = response.data;
                await this.renderPetDetails();
                await this.loadMedicalRecords();
                await this.loadShelterInfo();
                
                // Hide loading, show content
                document.getElementById('loading-state').classList.add('d-none');
                document.getElementById('pet-details-content').classList.remove('d-none');
            } else {
                this.showPetNotFound();
            }
        } catch (error) {
            console.error('Error loading pet details:', error);
            this.showPetNotFound();
        }
    }

    async renderPetDetails() {
        const pet = this.currentPet;
        
        // Update page title
        document.title = `${pet.name} - Pet Adoption System`;
        
        // Basic pet information
        document.getElementById('pet-name').textContent = pet.name;
        document.getElementById('pet-name-desc').textContent = pet.name;
        document.getElementById('pet-breed').textContent = pet.breed;
        document.getElementById('pet-age').textContent = pet.age;
        document.getElementById('pet-size').textContent = pet.size || 'Not specified';
        document.getElementById('pet-gender').textContent = pet.gender;
        document.getElementById('pet-location').textContent = pet.location || 'Main Shelter';
        document.getElementById('pet-description').textContent = pet.description || 'No description available.';

        // Medical information
        this.updateMedicalBadge('pet-vaccinated', pet.vaccinated);
        this.updateMedicalBadge('pet-spayed', pet.spayed_neutered);
        this.updateMedicalBadge('pet-microchipped', pet.microchipped);
        document.getElementById('pet-special-needs').textContent = pet.special_needs || 'None';

        // Pet images
        this.renderPetImages();

        // Update adoption button based on status
        this.updateAdoptionButton();

        // Check if pet is in user's favorites
        if (this.currentUser) {
            await this.checkFavoriteStatus();
        }
    }

    updateMedicalBadge(elementId, value) {
        const element = document.getElementById(elementId);
        if (value === true || value === 'true' || value === 1 || value === '1') {
            element.textContent = 'Yes';
            element.className = 'badge bg-success';
        } else {
            element.textContent = 'No';
            element.className = 'badge bg-secondary';
        }
    }

    renderPetImages() {
        const pet = this.currentPet;
        const mainImage = document.getElementById('main-pet-image');
        const gallery = document.querySelector('.pet-gallery');

        // Set main image
        const primaryImage = pet.image_url || 'https://via.placeholder.com/600x400?text=No+Image';
        mainImage.src = primaryImage;
        mainImage.alt = pet.name;

        // Create gallery (for demo, we'll create multiple variants of the same image)
        const images = [
            primaryImage,
            // In a real implementation, these would be actual additional photos
            primaryImage + '&variant=1',
            primaryImage + '&variant=2',
            primaryImage + '&variant=3'
        ];

        gallery.innerHTML = images.map((img, index) => `
            <img src="${img}" alt="${pet.name} ${index + 1}" 
                 class="img-thumbnail ${index === 0 ? 'active' : ''}"
                 onclick="petDetails.setMainImage('${img}', this)">
        `).join('');
    }

    setMainImage(src, element) {
        document.getElementById('main-pet-image').src = src;
        
        // Update active state
        document.querySelectorAll('.pet-gallery img').forEach(img => img.classList.remove('active'));
        element.classList.add('active');
    }

    async loadMedicalRecords() {
        try {
            const response = await window.apiClient.request(`/medical_records/list_for_pet.php?pet_id=${this.petId}`);
            
            if (response.success && response.data) {
                this.renderMedicalRecords(response.data);
            }
        } catch (error) {
            console.error('Error loading medical records:', error);
        }
    }

    renderMedicalRecords(records) {
        const container = document.getElementById('medical-records-list');
        
        if (!records || records.length === 0) {
            container.innerHTML = '<p class="text-muted">No medical records available.</p>';
            return;
        }

        container.innerHTML = records.slice(0, 5).map(record => `
            <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
                <div>
                    <h6 class="mb-1">${record.record_type}</h6>
                    <p class="mb-1 small">${record.description}</p>
                    <small class="text-muted">${this.formatDate(record.date_created)}</small>
                </div>
                <span class="badge ${this.getStatusBadgeClass(record.status)}">${record.status}</span>
            </div>
        `).join('');
    }

    async loadShelterInfo() {
        try {
            // For demo purposes, we'll show a default shelter
            // In a real implementation, this would be fetched based on the pet's shelter_id
            const shelterInfo = {
                name: 'Happy Paws Animal Shelter',
                address: '123 Pet Street, City, State 12345',
                phone: '(555) 123-4567',
                email: 'info@happypaws.com'
            };

            this.renderShelterInfo(shelterInfo);
        } catch (error) {
            console.error('Error loading shelter info:', error);
        }
    }

    renderShelterInfo(shelter) {
        const container = document.getElementById('shelter-info');
        container.innerHTML = `
            <h6 class="mb-2">${shelter.name}</h6>
            <p class="mb-1 small"><i class="fas fa-map-marker-alt me-2"></i>${shelter.address}</p>
            <p class="mb-1 small"><i class="fas fa-phone me-2"></i>${shelter.phone}</p>
            <p class="mb-0 small"><i class="fas fa-envelope me-2"></i>${shelter.email}</p>
        `;
    }

    updateAdoptionButton() {
        const adoptButton = document.getElementById('btn-adopt');
        const pet = this.currentPet;

        if (pet.status === 'adopted') {
            adoptButton.textContent = 'Already Adopted';
            adoptButton.className = 'btn btn-secondary btn-lg';
            adoptButton.disabled = true;
        } else if (pet.status === 'pending') {
            adoptButton.textContent = 'Adoption Pending';
            adoptButton.className = 'btn btn-warning btn-lg';
            adoptButton.disabled = true;
        } else {
            adoptButton.innerHTML = '<i class="fas fa-heart me-2"></i>Start Adoption Process';
            adoptButton.className = 'btn btn-primary btn-lg';
            adoptButton.disabled = false;
        }
    }

    async checkFavoriteStatus() {
        try {
            const response = await window.apiClient.request('/favorites/list.php');
            
            if (response.success && response.data) {
                const isFavorite = response.data.some(fav => fav.pet_id == this.petId);
                this.updateFavoriteButton(isFavorite);
            }
        } catch (error) {
            console.error('Error checking favorite status:', error);
        }
    }

    updateFavoriteButton(isFavorite) {
        const favoriteButton = document.getElementById('btn-favorite');
        
        if (isFavorite) {
            favoriteButton.innerHTML = '<i class="fas fa-heart me-2"></i>Remove from Favorites';
            favoriteButton.className = 'btn btn-danger';
            favoriteButton.dataset.action = 'remove';
        } else {
            favoriteButton.innerHTML = '<i class="far fa-heart me-2"></i>Add to Favorites';
            favoriteButton.className = 'btn btn-outline-primary';
            favoriteButton.dataset.action = 'add';
        }
    }

    setupEventListeners() {
        // Adoption button
        document.getElementById('btn-adopt').addEventListener('click', () => {
            this.handleAdoptionClick();
        });

        // Favorite button
        document.getElementById('btn-favorite').addEventListener('click', () => {
            this.handleFavoriteClick();
        });

        // Share button
        document.getElementById('btn-share').addEventListener('click', () => {
            this.handleShareClick();
        });

        // Adoption form
        document.getElementById('adoption-form').addEventListener('submit', (e) => {
            this.handleAdoptionFormSubmit(e);
        });
    }

    handleAdoptionClick() {
        if (!this.currentUser) {
            this.showError('Please log in to start the adoption process');
            setTimeout(() => {
                window.location.href = '../auth/login.html?redirect=' + encodeURIComponent(window.location.href);
            }, 2000);
            return;
        }

        // Pre-fill form with user data
        if (this.currentUser.email) {
            document.querySelector('[name="email"]').value = this.currentUser.email;
        }
        if (this.currentUser.username) {
            document.querySelector('[name="full_name"]').value = this.currentUser.username;
        }

        // Set pet ID in hidden field
        document.getElementById('application-pet-id').value = this.petId;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('adoptionModal'));
        modal.show();
    }

    async handleFavoriteClick() {
        if (!this.currentUser) {
            this.showError('Please log in to add favorites');
            return;
        }

        const button = document.getElementById('btn-favorite');
        const action = button.dataset.action;

        try {
            button.disabled = true;

            if (action === 'add') {
                const response = await window.apiClient.request('/favorites/add.php', {
                    method: 'POST',
                    body: JSON.stringify({ pet_id: this.petId })
                });

                if (response.success) {
                    this.updateFavoriteButton(true);
                    this.showSuccess('Added to favorites');
                } else {
                    this.showError(response.message || 'Failed to add to favorites');
                }
            } else {
                const response = await window.apiClient.request('/favorites/remove.php', {
                    method: 'POST',
                    body: JSON.stringify({ pet_id: this.petId })
                });

                if (response.success) {
                    this.updateFavoriteButton(false);
                    this.showSuccess('Removed from favorites');
                } else {
                    this.showError(response.message || 'Failed to remove from favorites');
                }
            }
        } catch (error) {
            console.error('Error handling favorite:', error);
            this.showError('Failed to update favorites');
        } finally {
            button.disabled = false;
        }
    }

    handleShareClick() {
        if (navigator.share) {
            navigator.share({
                title: `Adopt ${this.currentPet.name}`,
                text: `Check out ${this.currentPet.name}, a ${this.currentPet.breed} looking for a home!`,
                url: window.location.href
            });
        } else {
            // Fallback to copy URL
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.showSuccess('Link copied to clipboard');
            }).catch(() => {
                this.showError('Failed to copy link');
            });
        }
    }

    async handleAdoptionFormSubmit(event) {
        event.preventDefault();

        try {
            const formData = new FormData(event.target);
            const applicationData = {
                pet_id: parseInt(formData.get('pet_id')),
                full_name: formData.get('full_name'),
                email: formData.get('email'),
                phone: formData.get('phone'),
                age: formData.get('age'),
                address: formData.get('address'),
                housing_type: formData.get('housing_type'),
                housing_ownership: formData.get('housing_ownership'),
                experience: formData.get('experience'),
                reason: formData.get('reason')
            };

            const response = await window.apiClient.request('/applications/create.php', {
                method: 'POST',
                body: JSON.stringify(applicationData)
            });

            if (response.success) {
                this.showSuccess('Adoption application submitted successfully!');
                const modal = bootstrap.Modal.getInstance(document.getElementById('adoptionModal'));
                modal.hide();
                
                // Update pet status
                this.currentPet.status = 'pending';
                this.updateAdoptionButton();

                // Redirect to user profile after a delay
                setTimeout(() => {
                    window.location.href = '../user/profile.html';
                }, 3000);
            } else {
                this.showError(response.message || 'Failed to submit application');
            }
        } catch (error) {
            console.error('Error submitting adoption application:', error);
            this.showError('Failed to submit application');
        }
    }

    showPetNotFound() {
        document.getElementById('loading-state').classList.add('d-none');
        document.getElementById('pet-details-content').classList.add('d-none');
        document.getElementById('pet-not-found').classList.remove('d-none');
    }

    getStatusBadgeClass(status) {
        const statusClasses = {
            'active': 'bg-success',
            'pending': 'bg-warning',
            'completed': 'bg-primary',
            'cancelled': 'bg-secondary'
        };
        return statusClasses[status] || 'bg-secondary';
    }

    formatDate(dateString) {
        if (!dateString) return 'Unknown';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    showSuccess(message) {
        this.showAlert(message, 'success');
    }

    showError(message) {
        this.showAlert(message, 'danger');
    }

    showAlert(message, type) {
        const alertContainer = document.getElementById('alert-container');
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
    window.petDetails = new PetDetails();
});
