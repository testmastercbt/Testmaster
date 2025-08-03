/**
 * Enhanced Authentication JavaScript
 * Works with the PHP authentication system
 */

class AuthManager {
    constructor() {
        this.sessionCheckInterval = null;
        this.init();
    }

    init() {
        this.setupFormValidation();
        this.setupSessionMonitoring();
        this.setupRememberMe();
        this.setupDeviceManagement();
    }

    /**
     * Setup form validation for login form
     */
    setupFormValidation() {
        const loginForm = document.getElementById('loginForm');
        if (!loginForm) return;

        // Real-time validation
        const matricField = document.getElementById('matric_number');
        const passwordField = document.getElementById('password');

        if (matricField) {
            matricField.addEventListener('input', this.validateMatricNumber.bind(this));
            matricField.addEventListener('blur', this.validateMatricNumber.bind(this));
        }

        if (passwordField) {
            passwordField.addEventListener('input', this.validatePassword.bind(this));
        }

        // Form submission
        loginForm.addEventListener('submit', this.handleLoginSubmit.bind(this));
    }

    /**
     * Validate matric number format
     */
    validateMatricNumber(event) {
        const matricField = event.target;
        const value = matricField.value.trim();
        const errorElement = document.getElementById('matric-error');

        // Basic matric number validation (adjust pattern as needed)
        const matricPattern = /^[A-Z]{2,3}\/\d{2}\/\d{4}$/i;
        
        if (value && !matricPattern.test(value)) {
            this.showFieldError(matricField, errorElement, 'Invalid matric number format (e.g., CS/20/1234)');
            return false;
        } else {
            this.clearFieldError(matricField, errorElement);
            return true;
        }
    }

    /**
     * Validate password strength
     */
    validatePassword(event) {
        const passwordField = event.target;
        const value = passwordField.value;
        const errorElement = document.getElementById('password-error');

        if (value.length > 0 && value.length < 6) {
            this.showFieldError(passwordField, errorElement, 'Password must be at least 6 characters');
            return false;
        } else {
            this.clearFieldError(passwordField, errorElement);
            return true;
        }
    }

    /**
     * Show field validation error
     */
    showFieldError(field, errorElement, message) {
        field.classList.add('error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }

    /**
     * Clear field validation error
     */
    clearFieldError(field, errorElement) {
        field.classList.remove('error');
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    }

    /**
     * Handle login form submission
     */
    async handleLoginSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const matricField = form.querySelector('#matric_number');
        const passwordField = form.querySelector('#password');

        // Validate fields
        const matricValid = this.validateMatricNumber({ target: matricField });
        const passwordValid = this.validatePassword({ target: passwordField });

        if (!matricValid || !passwordValid) {
            return;
        }

        // Show loading state
        this.setLoadingState(submitBtn, true);

        try {
            const formData = new FormData(form);
            const response = await fetch('enhanced_auth.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (response.redirected) {
                // Successful login - redirect
                window.location.href = response.url;
            } else {
                // Handle error response
                const text = await response.text();
                const errorMatch = text.match(/error=([^&]+)/);
                if (errorMatch) {
                    this.showLoginError(decodeURIComponent(errorMatch[1]));
                }
            }
        } catch (error) {
            this.showLoginError('Network error. Please try again.');
        } finally {
            this.setLoadingState(submitBtn, false);
        }
    }

    /**
     * Set loading state for submit button
     */
    setLoadingState(button, loading) {
        if (!button) return;

        if (loading) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner"></span> Logging in...';
        } else {
            button.disabled = false;
            button.innerHTML = 'Login';
        }
    }

    /**
     * Show login error message
     */
    showLoginError(message) {
        const errorDiv = document.getElementById('login-error') || this.createErrorDiv();
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }

    /**
     * Create error div if it doesn't exist
     */
    createErrorDiv() {
        const errorDiv = document.createElement('div');
        errorDiv.id = 'login-error';
        errorDiv.className = 'error-message';
        
        const form = document.getElementById('loginForm');
        if (form) {
            form.insertBefore(errorDiv, form.firstChild);
        }
        
        return errorDiv;
    }

    /**
     * Setup session monitoring for authenticated users
     */
    setupSessionMonitoring() {
        if (!this.isAuthenticated()) return;

        // Check session validity every 5 minutes
        this.sessionCheckInterval = setInterval(() => {
            this.checkSessionValidity();
        }, 5 * 60 * 1000);

        // Check on page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkSessionValidity();
            }
        });
    }

    /**
     * Check if user is authenticated (basic check)
     */
    isAuthenticated() {
        return document.body.classList.contains('authenticated') || 
               document.querySelector('[data-user-id]') !== null;
    }

    /**
     * Check session validity with server
     */
    async checkSessionValidity() {
        try {
            const response = await fetch('check_session.php', {
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (!data.valid) {
                this.handleSessionExpired();
            }
        } catch (error) {
            console.warn('Session check failed:', error);
        }
    }

    /**
     * Handle expired session
     */
    handleSessionExpired() {
        clearInterval(this.sessionCheckInterval);
        
        // Show session expired message
        const modal = this.createSessionExpiredModal();
        document.body.appendChild(modal);
        
        // Auto-redirect after 10 seconds
        setTimeout(() => {
            window.location.href = 'login.php?session=expired';
        }, 10000);
    }

    /**
     * Create session expired modal
     */
    createSessionExpiredModal() {
        const modal = document.createElement('div');
        modal.className = 'session-modal';
        modal.innerHTML = `
            <div class="session-modal-content">
                <h3>Session Expired</h3>
                <p>Your session has expired. You will be redirected to login page.</p>
                <button onclick="window.location.href='login.php'">Login Now</button>
            </div>
        `;
        return modal;
    }

    /**
     * Setup Remember Me functionality
     */
    setupRememberMe() {
        const rememberCheckbox = document.getElementById('remember_me');
        if (!rememberCheckbox) return;

        // Show warning about device security
        rememberCheckbox.addEventListener('change', (e) => {
            const warning = document.getElementById('remember-warning');
            if (e.target.checked) {
                if (warning) warning.style.display = 'block';
            } else {
                if (warning) warning.style.display = 'none';
            }
        });
    }

    /**
     * Setup device management features
     */
    setupDeviceManagement() {
        // Handle device logout buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('logout-device-btn')) {
                this.handleDeviceLogout(e.target.dataset.deviceToken);
            }
        });

        // Load device list if on settings page
        if (document.getElementById('device-list')) {
            this.loadDeviceList();
        }
    }

    /**
     * Handle device-specific logout
     */
    async handleDeviceLogout(deviceToken) {
        if (!confirm('Are you sure you want to logout this device?')) return;

        try {
            const response = await fetch('logout_device.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ device_token: deviceToken }),
                credentials: 'same-origin'
            });

            if (response.ok) {
                // Reload device list
                this.loadDeviceList();
                this.showNotification('Device logged out successfully');
            }
        } catch (error) {
            this.showNotification('Error logging out device', 'error');
        }
    }

    /**
     * Load and display device list
     */
    async loadDeviceList() {
        const deviceList = document.getElementById('device-list');
        if (!deviceList) return;

        try {
            const response = await fetch('get_devices.php', {
                credentials: 'same-origin'
            });
            const devices = await response.json();

            deviceList.innerHTML = devices.map(device => `
                <div class="device-item ${device.is_current ? 'current' : ''}">
                    <div class="device-info">
                        <strong>${this.getDeviceIcon(device.device_type)} ${device.device_type}</strong>
                        <p>Last seen: ${this.formatDate(device.last_seen)}</p>
                        <p>IP: ${device.ip_address}</p>
                    </div>
                    ${!device.is_current ? `
                        <button class="logout-device-btn" data-device-token="${device.device_token}">
                            Logout
                        </button>
                    ` : '<span class="current-device">Current Device</span>'}
                </div>
            `).join('');
        } catch (error) {
            deviceList.innerHTML = '<p>Error loading devices</p>';
        }
    }

    /**
     * Get device icon based on type
     */
    getDeviceIcon(deviceType) {
        const icons = {
            mobile: '📱',
            desktop: '🖥️',
            tablet: '📱'
        };
        return icons[deviceType] || '💻';
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minutes ago`;
        if (diffHours < 24) return `${diffHours} hours ago`;
        if (diffDays < 7) return `${diffDays} days ago`;
        
        return date.toLocaleDateString();
    }

    /**
     * Show notification message
     */
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    /**
     * Cleanup when page unloads
     */
    cleanup() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.authManager = new AuthManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.authManager) {
        window.authManager.cleanup();
    }
});

// Utility functions for other scripts
window.AuthUtils = {
    /**
     * Make authenticated AJAX request
     */
    async authFetch(url, options = {}) {
        const defaultOptions = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        // Check if session expired
        if (response.status === 401) {
            window.authManager.handleSessionExpired();
            throw new Error('Session expired');
        }
        
        return response;
    },

    /**
     * Logout current user
     */
    async logout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    }
};