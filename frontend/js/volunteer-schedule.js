// Volunteer Schedule Management JavaScript

class VolunteerScheduleManager {
    constructor() {
        this.currentDate = new Date();
        this.currentView = 'month';
        this.shifts = [];
        this.availability = [];
        this.init();
    }

    async init() {
        try {
            await this.checkAuth();
            await this.loadScheduleData();
            this.setupEventListeners();
            this.renderCalendar();
        } catch (error) {
            console.error('Failed to initialize volunteer schedule:', error);
            this.showAlert('Failed to load schedule', 'danger');
        }
    }

    async checkAuth() {
        if (!authManager.isAuthenticated()) {
            window.location.href = '../auth/login.html';
            return;
        }

        const user = authManager.getCurrentUser();
        if (user.role !== 'volunteer') {
            window.location.href = '../index.html';
            return;
        }
    }

    async loadScheduleData() {
        try {
            this.showLoading();
            
            // Load volunteer's shifts
            const shiftsResponse = await apiClient.get('/volunteer/shifts');
            if (shiftsResponse.success) {
                this.shifts = shiftsResponse.data || [];
            }

            // Load available shifts to sign up for
            const availableResponse = await apiClient.get('/volunteer/available-shifts');
            if (availableResponse.success) {
                this.availability = availableResponse.data || [];
            }
            
        } catch (error) {
            console.error('Error loading schedule data:', error);
            this.showAlert('Failed to load schedule data', 'danger');
        } finally {
            this.hideLoading();
        }
    }

    setupEventListeners() {
        // Calendar navigation
        const prevBtn = document.getElementById('prevMonthBtn');
        const nextBtn = document.getElementById('nextMonthBtn');
        const todayBtn = document.getElementById('todayBtn');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.renderCalendar();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.renderCalendar();
            });
        }

        if (todayBtn) {
            todayBtn.addEventListener('click', () => {
                this.currentDate = new Date();
                this.renderCalendar();
            });
        }

        // View switcher
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleViewChange(e.target);
            });
        });

        // Sign up for shift modal
        const signupBtn = document.getElementById('signupShiftBtn');
        if (signupBtn) {
            signupBtn.addEventListener('click', () => {
                this.loadAvailableShifts();
            });
        }

        // Save shift signup
        const saveSignupBtn = document.getElementById('saveSignupBtn');
        if (saveSignupBtn) {
            saveSignupBtn.addEventListener('click', () => {
                this.handleShiftSignup();
            });
        }

        // Calendar day clicks
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('calendar-day')) {
                this.handleDayClick(e.target);
            } else if (e.target.classList.contains('calendar-event')) {
                this.handleEventClick(e.target);
            }
        });
    }

    handleViewChange(button) {
        // Update active view button
        document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');

        // Set current view
        this.currentView = button.textContent.toLowerCase();
        this.renderCalendar();
    }

    handleDayClick(dayElement) {
        const day = parseInt(dayElement.textContent.split('\n')[0]);
        const clickedDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), day);
        
        // Show day details modal
        this.showDayDetails(clickedDate);
    }

    handleEventClick(eventElement) {
        const shiftId = eventElement.dataset.shiftId;
        const shift = this.shifts.find(s => s.id == shiftId);
        
        if (shift) {
            this.showShiftDetails(shift);
        }
    }

    async handleShiftSignup() {
        const form = document.getElementById('signupShiftForm');
        const formData = new FormData(form);
        
        const shiftId = formData.get('shiftId');
        const notes = formData.get('notes');

        if (!shiftId) {
            this.showAlert('Please select a shift', 'danger');
            return;
        }

        try {
            const response = await apiClient.post('/volunteer/shifts/signup', {
                shift_id: shiftId,
                notes: notes
            });

            if (response.success) {
                // Add to local shifts
                this.shifts.push(response.data);
                
                // Close modal and refresh calendar
                const modal = bootstrap.Modal.getInstance(document.getElementById('signupShiftModal'));
                modal.hide();
                form.reset();
                
                this.renderCalendar();
                this.showAlert('Successfully signed up for shift', 'success');
            } else {
                throw new Error(response.message || 'Failed to sign up for shift');
            }
        } catch (error) {
            console.error('Error signing up for shift:', error);
            this.showAlert('Failed to sign up for shift', 'danger');
        }
    }

    async loadAvailableShifts() {
        try {
            const response = await apiClient.get('/volunteer/available-shifts');
            if (response.success) {
                this.availability = response.data || [];
                this.renderAvailableShifts();
            }
        } catch (error) {
            console.error('Error loading available shifts:', error);
            this.showAlert('Failed to load available shifts', 'danger');
        }
    }

    renderCalendar() {
        const monthYearElement = document.getElementById('currentMonthYear');
        const calendarGrid = document.getElementById('calendarGrid');
        
        if (!calendarGrid) return;

        // Update month/year header
        if (monthYearElement) {
            monthYearElement.textContent = this.currentDate.toLocaleDateString('en-US', { 
                month: 'long', 
                year: 'numeric' 
            });
        }

        if (this.currentView === 'month') {
            this.renderMonthView(calendarGrid);
        } else if (this.currentView === 'week') {
            this.renderWeekView(calendarGrid);
        } else if (this.currentView === 'day') {
            this.renderDayView(calendarGrid);
        }
    }

    renderMonthView(container) {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        // Get first day of month and number of days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());

        let html = `
            <!-- Days of Week -->
            <div class="row text-center fw-bold py-2">
                <div class="col">Sunday</div>
                <div class="col">Monday</div>
                <div class="col">Tuesday</div>
                <div class="col">Wednesday</div>
                <div class="col">Thursday</div>
                <div class="col">Friday</div>
                <div class="col">Saturday</div>
            </div>
        `;

        // Generate calendar weeks
        let currentDate = new Date(startDate);
        for (let week = 0; week < 6; week++) {
            html += '<div class="row g-0">';
            
            for (let day = 0; day < 7; day++) {
                const isCurrentMonth = currentDate.getMonth() === month;
                const isToday = this.isSameDay(currentDate, new Date());
                const dayShifts = this.getShiftsForDate(currentDate);
                
                const dayClass = `col calendar-day ${!isCurrentMonth ? 'text-muted' : ''} ${isToday ? 'bg-light border-primary' : ''}`;
                
                html += `<div class="${dayClass}" data-date="${currentDate.toISOString().split('T')[0]}">`;
                html += `<div class="p-1">${currentDate.getDate()}</div>`;
                
                // Add shifts for this day
                dayShifts.forEach(shift => {
                    const eventClass = shift.status === 'confirmed' ? 'event-confirmed' : 'event-pending';
                    html += `
                        <div class="calendar-event ${eventClass}" data-shift-id="${shift.id}">
                            ${this.formatTime(shift.start_time)} - ${shift.type}
                        </div>
                    `;
                });
                
                html += '</div>';
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            html += '</div>';
            
            // Stop if we've passed the end of the month and filled the week
            if (currentDate.getMonth() !== month && day === 6) break;
        }

        container.innerHTML = html;
    }

    renderWeekView(container) {
        // Implementation for week view
        container.innerHTML = '<div class="text-center py-5"><p>Week view coming soon...</p></div>';
    }

    renderDayView(container) {
        // Implementation for day view
        container.innerHTML = '<div class="text-center py-5"><p>Day view coming soon...</p></div>';
    }

    renderAvailableShifts() {
        const container = document.getElementById('availableShiftsContainer');
        if (!container) return;

        if (this.availability.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No available shifts at this time</p>
                </div>
            `;
            return;
        }

        let html = '';
        this.availability.forEach(shift => {
            html += `
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="shiftId" value="${shift.id}" id="shift${shift.id}">
                    <label class="form-check-label" for="shift${shift.id}">
                        <div class="fw-bold">${shift.type}</div>
                        <small class="text-muted">
                            ${this.formatDate(shift.date)} • ${this.formatTime(shift.start_time)} - ${this.formatTime(shift.end_time)}
                            <br>
                            ${shift.location} • ${shift.spots_needed} spots needed
                        </small>
                        ${shift.description ? `<div class="mt-1"><small>${shift.description}</small></div>` : ''}
                    </label>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    getShiftsForDate(date) {
        const dateStr = date.toISOString().split('T')[0];
        return this.shifts.filter(shift => shift.date === dateStr);
    }

    showDayDetails(date) {
        const shifts = this.getShiftsForDate(date);
        // Implementation for day details modal
        console.log('Show details for:', date, shifts);
    }

    showShiftDetails(shift) {
        // Implementation for shift details modal
        console.log('Show shift details:', shift);
    }

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString();
    }

    formatTime(timeStr) {
        if (!timeStr) return '';
        const [hours, minutes] = timeStr.split(':');
        const date = new Date();
        date.setHours(parseInt(hours), parseInt(minutes));
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    isSameDay(date1, date2) {
        return date1.toDateString() === date2.toDateString();
    }

    showLoading() {
        const container = document.getElementById('calendarGrid');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading schedule...</p>
                </div>
            `;
        }
    }

    hideLoading() {
        // Loading will be replaced by renderCalendar()
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
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new VolunteerScheduleManager();
});
