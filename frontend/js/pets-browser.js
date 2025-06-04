/**
 * Pets Browse JavaScript
 * Handles browsing available pets, filtering, searching, and adoption applications
 */

class PetsBrowser {
    constructor() {
        this.pets = [];
        this.filteredPets = [];
        this.currentPage = 1;
        this.petsPerPage = 12;
        this.filters = {
            species: '',
            breed: '',
            age: '',
            size: '',
            gender: '',
            location: ''
        };
        this.init();
    }

    async init() {
        await this.loadPets();
        this.setupEventListeners();
        this.setupFilters();
        this.updateUI();
    }

    async loadPets() {
        try {
            const response = await window.apiClient.getAvailablePets();
            if (response.success) {
                this.pets = response.pets;
                this.filteredPets = [...this.pets];
                this.displayPets();
                this.updatePagination();
                this.populateFilterOptions();
            } else {
                this.showError('Failed to load pets');
            }
        } catch (error) {
            console.error('Error loading pets:', error);
            this.showError('Failed to load pets');
        }
    }

    displayPets() {
        const container = document.getElementById('pets-container');
        if (!container) return;

        container.innerHTML = '';

        if (this.filteredPets.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No pets found</h5>
                        <p class="text-muted">Try adjusting your search criteria</p>
                        <button class="btn btn-primary" onclick="petsBrowser.clearFilters()">Clear Filters</button>
                    </div>
                </div>
            `;
            return;
        }

        const startIndex = (this.currentPage - 1) * this.petsPerPage;
        const endIndex = startIndex + this.petsPerPage;
        const petsToShow = this.filteredPets.slice(startIndex, endIndex);

        petsToShow.forEach(pet => {
            const petCard = this.createPetCard(pet);
            container.appendChild(petCard);
        });

        this.updateResultsCount();
    }

    createPetCard(pet) {
        const cardDiv = document.createElement('div');
        cardDiv.className = 'col-md-6 col-lg-4 mb-4';
        
        const ageText = this.calculateAge(pet.birth_date);
        const isFavorite = this.checkIfFavorite(pet.id);
        
        cardDiv.innerHTML = `
            <div class="card h-100 pet-card">
                <div class="position-relative">
                    <img src="${pet.photo_url || 'https://via.placeholder.com/300x200'}" 
                         class="card-img-top" alt="${pet.name}" 
                         style="height: 250px; object-fit: cover;"
                         onclick="petsBrowser.viewPetDetails(${pet.id})">
                    <button class="btn btn-link position-absolute top-0 end-0 m-2 favorite-btn ${isFavorite ? 'favorited' : ''}" 
                            onclick="petsBrowser.toggleFavorite(${pet.id})" 
                            title="${isFavorite ? 'Remove from favorites' : 'Add to favorites'}">
                        <i class="fas fa-heart ${isFavorite ? 'text-danger' : 'text-white'}"></i>
                    </button>
                    <div class="position-absolute bottom-0 start-0 m-2">
                        <span class="badge bg-primary">${pet.species}</span>
                        ${pet.gender ? `<span class="badge bg-secondary">${pet.gender}</span>` : ''}
                    </div>
                </div>
                <div class="card-body">
                    <h5 class="card-title">${pet.name}</h5>
                    <p class="card-text">
                        <strong>Breed:</strong> ${pet.breed}<br>
                        <strong>Age:</strong> ${ageText}<br>
                        ${pet.size ? `<strong>Size:</strong> ${pet.size}<br>` : ''}
                        ${pet.location ? `<strong>Location:</strong> ${pet.location}` : ''}
                    </p>
                    <p class="card-text text-muted">${this.truncateText(pet.description, 100)}</p>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-outline-primary btn-sm" onclick="petsBrowser.viewPetDetails(${pet.id})">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="petsBrowser.applyForAdoption(${pet.id})">
                            <i class="fas fa-heart"></i> Apply to Adopt
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        return cardDiv;
    }

    setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('pet-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchPets(e.target.value);
            });
        }

        // Filter buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('filter-btn')) {
                this.handleFilterClick(e.target);
            }
        });

        // Pagination
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('page-link')) {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                if (page) {
                    this.currentPage = page;
                    this.displayPets();
                    this.updatePagination();
                }
            }
        });

        // Sort dropdown
        const sortSelect = document.getElementById('sort-pets');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.sortPets(e.target.value);
            });
        }
    }

    setupFilters() {
        // Species filter
        const speciesFilter = document.getElementById('species-filter');
        if (speciesFilter) {
            speciesFilter.addEventListener('change', (e) => {
                this.filters.species = e.target.value;
                this.applyFilters();
            });
        }

        // Breed filter
        const breedFilter = document.getElementById('breed-filter');
        if (breedFilter) {
            breedFilter.addEventListener('change', (e) => {
                this.filters.breed = e.target.value;
                this.applyFilters();
            });
        }

        // Age filter
        const ageFilter = document.getElementById('age-filter');
        if (ageFilter) {
            ageFilter.addEventListener('change', (e) => {
                this.filters.age = e.target.value;
                this.applyFilters();
            });
        }

        // Size filter
        const sizeFilter = document.getElementById('size-filter');
        if (sizeFilter) {
            sizeFilter.addEventListener('change', (e) => {
                this.filters.size = e.target.value;
                this.applyFilters();
            });
        }

        // Gender filter
        const genderFilter = document.getElementById('gender-filter');
        if (genderFilter) {
            genderFilter.addEventListener('change', (e) => {
                this.filters.gender = e.target.value;
                this.applyFilters();
            });
        }
    }

    searchPets(query) {
        if (!query.trim()) {
            this.filteredPets = [...this.pets];
        } else {
            const searchTerm = query.toLowerCase();
            this.filteredPets = this.pets.filter(pet => 
                pet.name.toLowerCase().includes(searchTerm) ||
                pet.breed.toLowerCase().includes(searchTerm) ||
                pet.species.toLowerCase().includes(searchTerm) ||
                (pet.description && pet.description.toLowerCase().includes(searchTerm))
            );
        }
        
        this.currentPage = 1;
        this.displayPets();
        this.updatePagination();
    }

    applyFilters() {
        this.filteredPets = this.pets.filter(pet => {
            return (
                (!this.filters.species || pet.species === this.filters.species) &&
                (!this.filters.breed || pet.breed === this.filters.breed) &&
                (!this.filters.age || this.matchesAgeFilter(pet, this.filters.age)) &&
                (!this.filters.size || pet.size === this.filters.size) &&
                (!this.filters.gender || pet.gender === this.filters.gender)
            );
        });

        this.currentPage = 1;
        this.displayPets();
        this.updatePagination();
    }

    matchesAgeFilter(pet, ageFilter) {
        const age = this.calculateAgeInYears(pet.birth_date);
        
        switch(ageFilter) {
            case 'young':
                return age < 2;
            case 'adult':
                return age >= 2 && age < 7;
            case 'senior':
                return age >= 7;
            default:
                return true;
        }
    }

    sortPets(sortBy) {
        switch(sortBy) {
            case 'name':
                this.filteredPets.sort((a, b) => a.name.localeCompare(b.name));
                break;
            case 'age':
                this.filteredPets.sort((a, b) => 
                    this.calculateAgeInYears(a.birth_date) - this.calculateAgeInYears(b.birth_date)
                );
                break;
            case 'species':
                this.filteredPets.sort((a, b) => a.species.localeCompare(b.species));
                break;
            case 'newest':
                this.filteredPets.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                break;
            default:
                // Default order
                break;
        }

        this.displayPets();
    }

    populateFilterOptions() {
        // Populate species filter
        const species = [...new Set(this.pets.map(pet => pet.species))];
        this.populateSelect('species-filter', species);

        // Populate breed filter
        const breeds = [...new Set(this.pets.map(pet => pet.breed))];
        this.populateSelect('breed-filter', breeds);

        // Populate size filter
        const sizes = [...new Set(this.pets.map(pet => pet.size).filter(Boolean))];
        this.populateSelect('size-filter', sizes);
    }

    populateSelect(selectId, options) {
        const select = document.getElementById(selectId);
        if (!select) return;

        // Keep the first option (usually "All")
        const firstOption = select.children[0];
        select.innerHTML = '';
        select.appendChild(firstOption);

        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option;
            optionElement.textContent = option;
            select.appendChild(optionElement);
        });
    }

    clearFilters() {
        // Reset all filters
        this.filters = {
            species: '',
            breed: '',
            age: '',
            size: '',
            gender: '',
            location: ''
        };

        // Reset form elements
        document.querySelectorAll('.filter-select').forEach(select => {
            select.selectedIndex = 0;
        });

        const searchInput = document.getElementById('pet-search');
        if (searchInput) {
            searchInput.value = '';
        }

        // Apply filters (which will show all pets)
        this.filteredPets = [...this.pets];
        this.currentPage = 1;
        this.displayPets();
        this.updatePagination();
    }

    async toggleFavorite(petId) {
        // Check if user is logged in
        if (!window.authManager.isLoggedIn) {
            this.showError('Please log in to save favorites');
            return;
        }

        try {
            const isFavorite = this.checkIfFavorite(petId);
            
            if (isFavorite) {
                const response = await window.apiClient.removeFavorite(petId);
                if (response.success) {
                    this.showSuccess('Removed from favorites');
                    this.updateFavoriteButton(petId, false);
                }
            } else {
                const response = await window.apiClient.addFavorite(petId);
                if (response.success) {
                    this.showSuccess('Added to favorites');
                    this.updateFavoriteButton(petId, true);
                }
            }
        } catch (error) {
            console.error('Error toggling favorite:', error);
            this.showError('Failed to update favorites');
        }
    }

    checkIfFavorite(petId) {
        // This would be checked against user's favorites
        // For now, return false
        return false;
    }

    updateFavoriteButton(petId, isFavorite) {
        const buttons = document.querySelectorAll(`[onclick="petsBrowser.toggleFavorite(${petId})"]`);
        buttons.forEach(button => {
            const icon = button.querySelector('i');
            if (isFavorite) {
                button.classList.add('favorited');
                icon.classList.add('text-danger');
                icon.classList.remove('text-white');
                button.title = 'Remove from favorites';
            } else {
                button.classList.remove('favorited');
                icon.classList.remove('text-danger');
                icon.classList.add('text-white');
                button.title = 'Add to favorites';
            }
        });
    }

    async viewPetDetails(petId) {
        // Navigate to pet details page
        window.location.href = `details.html?id=${petId}`;
    }

    async applyForAdoption(petId) {
        // Check if user is logged in
        if (!window.authManager.isLoggedIn) {
            this.showError('Please log in to apply for adoption');
            return;
        }

        // Navigate to adoption application page
        window.location.href = `../adoption/application.html?petId=${petId}`;
    }

    updatePagination() {
        const totalPages = Math.ceil(this.filteredPets.length / this.petsPerPage);
        const pagination = document.getElementById('pagination');
        
        if (!pagination || totalPages <= 1) {
            if (pagination) pagination.innerHTML = '';
            return;
        }

        let paginationHTML = '';
        
        // Previous button
        paginationHTML += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${this.currentPage - 1}">Previous</a>
            </li>
        `;

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                paginationHTML += `
                    <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Next button
        paginationHTML += `
            <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${this.currentPage + 1}">Next</a>
            </li>
        `;

        pagination.innerHTML = paginationHTML;
    }

    updateResultsCount() {
        const resultsCount = document.getElementById('results-count');
        if (resultsCount) {
            const start = (this.currentPage - 1) * this.petsPerPage + 1;
            const end = Math.min(this.currentPage * this.petsPerPage, this.filteredPets.length);
            resultsCount.textContent = `Showing ${start}-${end} of ${this.filteredPets.length} pets`;
        }
    }

    calculateAge(birthDate) {
        if (!birthDate) return 'Unknown';
        
        const birth = new Date(birthDate);
        const now = new Date();
        const ageInMonths = (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());
        
        if (ageInMonths < 12) {
            return `${ageInMonths} months`;
        } else {
            const years = Math.floor(ageInMonths / 12);
            const months = ageInMonths % 12;
            return months > 0 ? `${years} years, ${months} months` : `${years} years`;
        }
    }

    calculateAgeInYears(birthDate) {
        if (!birthDate) return 0;
        
        const birth = new Date(birthDate);
        const now = new Date();
        return now.getFullYear() - birth.getFullYear();
    }

    truncateText(text, maxLength) {
        if (!text || text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    updateUI() {
        // Update total pets count
        const totalPetsElement = document.getElementById('total-pets-count');
        if (totalPetsElement) {
            totalPetsElement.textContent = this.pets.length;
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

// Initialize pets browser when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.petsBrowser = new PetsBrowser();
});
