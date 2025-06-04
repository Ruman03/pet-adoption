# Pet Adoption Website - API Documentation

## Overview
Complete PHP and MySQL backend for a comprehensive pet adoption management system.

## Base URL
`http://localhost/backend/api/`

## Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer <token>
```

## API Endpoints

### Authentication & User Management

#### Users
- `POST /users/register` - Register new user
- `POST /users/login` - User login
- `POST /users/logout` - User logout
- `GET /users/profile` - Get user profile with statistics
- `PUT /users/update_profile` - Update user profile
- `GET /users/read_one?id={id}` - Get user details (admin/staff only)
- `GET /users/list_all` - List all users (admin/staff only)
- `PUT /users/update_role` - Update user role (admin only)
- `DELETE /users/delete` - Delete user (admin only)

### Pet Management

#### Pets
- `GET /pets/list_available` - List available pets
- `GET /pets/read` - List all pets with filters
- `GET /pets/read_one?id={id}` - Get pet details
- `POST /pets/create` - Create new pet (staff/admin only)
- `PUT /pets/update` - Update pet (staff/admin only)
- `DELETE /pets/delete` - Delete pet (admin only)

#### Favorites
- `POST /favorites/add` - Add pet to favorites
- `GET /favorites/list` - List user's favorite pets
- `DELETE /favorites/remove?pet_id={id}` - Remove pet from favorites

### Adoption Process

#### Applications
- `POST /applications/create` - Submit adoption application
- `GET /applications/list_mine` - List user's applications
- `GET /applications/list_all` - List all applications (staff/admin)
- `PUT /applications/update_status` - Update application status (staff/admin)

#### Appointments
- `POST /appointments/create` - Schedule appointment
- `GET /appointments/list` - List appointments
- `PUT /appointments/update_status` - Update appointment status

### Foster Program

#### Foster Records
- `POST /foster_records/create` - Apply for fostering
- `GET /foster_records/list_mine` - List user's foster records
- `GET /foster_records/list_all` - List all foster records (staff/admin)
- `PUT /foster_records/update_status` - Update foster status (staff/admin)

#### Supply Requests
- `POST /supply_requests/create` - Request foster supplies
- `GET /supply_requests/list` - List supply requests
- `PUT /supply_requests/update_status` - Update supply request status (staff/admin)

### Volunteer Management

#### Volunteer Applications
- `POST /volunteer_applications/create` - Submit volunteer application
- `GET /volunteer_applications/list_mine` - List user's volunteer applications
- `GET /volunteer_applications/list_all` - List all volunteer applications (staff/admin)
- `PUT /volunteer_applications/update_status` - Update volunteer application status (staff/admin)

#### Volunteer Tasks
- `POST /volunteer_tasks/create` - Create volunteer task (staff/admin)
- `GET /volunteer_tasks/list` - List available volunteer tasks
- `GET /volunteer_tasks/list_mine` - List assigned tasks
- `PUT /volunteer_tasks/assign` - Assign/update task status

### Training System

#### Training Modules
- `POST /training/create_module` - Create training module (staff/admin)
- `GET /training/list_modules` - List training modules
- `POST /training/update_progress` - Update training progress

### Medical Records

#### Medical Records (Vet/Staff/Admin only)
- `POST /medical_records/create` - Create medical record
- `GET /medical_records/list_for_pet?pet_id={id}` - List pet's medical records
- `PUT /medical_records/update` - Update medical record
- `DELETE /medical_records/delete` - Delete medical record

### Shelter Management

#### Shelters
- `GET /shelters/list` - List all shelters
- `GET /shelters/read_one?id={id}` - Get shelter details
- `POST /shelters/create` - Create shelter (admin only)
- `PUT /shelters/update` - Update shelter (admin only)
- `DELETE /shelters/delete` - Delete shelter (admin only)

### Notifications

#### Notifications
- `GET /notifications/list` - List user notifications
- `PUT /notifications/mark_read` - Mark notifications as read
- `DELETE /notifications/delete?id={id}` - Delete notification

### Reports & Analytics

#### Reports
- `GET /reports/dashboard_stats` - Dashboard statistics (staff/admin/vet)
- `GET /reports/user_stats` - User-specific statistics

## User Roles

### User Permissions
- **user**: Basic user, can adopt, foster, volunteer
- **volunteer**: Can access volunteer tasks and training
- **staff**: Can manage applications, pets, tasks, foster records
- **admin**: Full access to all features
- **vet**: Medical records access plus staff permissions

## Database Schema

### Core Tables
- `users` - User accounts and profiles
- `pets` - Pet information and status
- `shelters` - Shelter locations and details
- `applications` - Adoption applications
- `foster_records` - Foster applications and tracking
- `medical_records` - Pet medical history
- `volunteer_applications` - Volunteer applications
- `volunteer_tasks` - Volunteer task management
- `training_modules` - Training content
- `training_progress` - User training progress
- `appointments` - Appointment scheduling
- `supply_requests` - Foster supply requests
- `favorites` - User favorite pets
- `notifications` - System notifications

## Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request (validation error)
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `409` - Conflict
- `500` - Internal Server Error

## Response Format
All responses follow this format:
```json
{
  "success": true,
  "message": "Description of result",
  "data": {} // Response data
}
```

## Features Implemented

### Complete CRUD Operations
✅ Users (register, login, profile management)
✅ Pets (full lifecycle management)
✅ Adoption Applications (submission to approval)
✅ Foster Records (application to completion)
✅ Medical Records (comprehensive health tracking)
✅ Volunteer Management (applications, tasks, training)
✅ Shelters (location management)
✅ Appointments (scheduling system)
✅ Supply Requests (foster supply management)
✅ Favorites (pet wishlist)
✅ Notifications (system messaging)
✅ Training System (modules and progress)
✅ Reports & Analytics (dashboard statistics)

### Security Features
✅ Role-based access control
✅ Input validation and sanitization
✅ SQL injection prevention
✅ Authentication verification
✅ Authorization checks

### Business Logic
✅ Adoption workflow (application → review → approval → completion)
✅ Foster program management
✅ Volunteer coordination
✅ Medical record tracking
✅ Supply request fulfillment
✅ Training progress tracking
✅ Appointment scheduling
✅ Notification system

## Installation

1. Set up MySQL database using `backend/sql/extended_schema.sql`
2. Configure database connection in `backend/config/db.php`
3. Ensure PHP server is running
4. API endpoints are ready to use

## Note
This is a comprehensive backend system with 60+ API endpoints covering all aspects of pet adoption management including adoption, fostering, volunteering, medical records, training, and administrative functions.
