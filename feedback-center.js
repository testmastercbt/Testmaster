/**
 * TestMaster Feedback Management System
 * Enhanced JavaScript with real-time features and advanced interactions
 * Version: 2.0
 */

class FeedbackManager {
    constructor() {
        this.currentTab = 'feedback';
        this.autoRefreshInterval = null;
        this.searchTimeout = null;
        this.lastRefreshTime = Date.now();
        this.notifications = [];
        this.soundEnabled = true;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupAutoRefresh();
        this.setupKeyboardShortcuts();
        this.setupNotificationSystem();
        this.setupSearchEnhancement();
        this.setupTableEnhancements();
        this.loadUserPreferences();
        this.setupTooltips();
    }

    // Event Listeners Setup
    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', (e) => this.handleTabSwitch(e));
        });

        // Search enhancement
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearchInput(e));
            searchInput.addEventListener('keypress', (e) => this.handleSearchKeypress(e));
        }

        // Filter changes
        ['type_filter', 'status_filter'].forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => this.handleFilterChange());
            }
        });

        // Form submissions with validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        });

        // Modal enhancements
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => this.handleModalClose(e));
        });

        // Bulk actions
        this.setupBulkActions();

        // Feedback priority handling
        this.setupPriorityHandling();

        // Export functionality
        this.setupExportFeatures();
    }

    // Tab Management
    handleTabSwitch(e) {
        const tabName = e.target.textContent.toLowerCase().includes('feedback') ? 'feedback' :
                       e.target.textContent.toLowerCase().includes('global') ? 'global-notifications' :
                       e.target.textContent.toLowerCase().includes('individual') ? 'individual-notifications' :
                       'analytics';
        
        this.showTab(tabName);
        this.currentTab = tabName;
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
        
        // Load tab-specific data
        this.loadTabData(tabName);
        
        // Analytics tracking
        this.trackUserAction('tab_switch', { tab: tabName });
    }

    showTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Remove active class from all tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab content
        const targetTab = document.getElementById(tabName);
        if (targetTab) {
            targetTab.classList.add('active');
        }
        
        // Add active class to clicked tab
        document.querySelectorAll('.tab').forEach(tab => {
            if (tab.textContent.toLowerCase().includes(tabName.split('-')[0])) {
                tab.classList.add('active');
            }
        });

        // Trigger tab-specific animations
        this.animateTabTransition(tabName);
    }

    // Enhanced Search Functionality
    handleSearchInput(e) {
        clearTimeout(this.searchTimeout);
        const query = e.target.value.trim();
        
        // Real-time search suggestions
        if (query.length > 2) {
            this.searchTimeout = setTimeout(() => {
                this.performLiveSearch(query);
            }, 300);
        } else if (query.length === 0) {
            this.clearSearchSuggestions();
        }
        
        // Highlight search terms in results
        this.highlightSearchTerms(query);
    }

    performLiveSearch(query) {
        // Show loading indicator
        this.showSearchLoading(true);
        
        // Perform AJAX search
        fetch(`ajax_search_feedback.php?q=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            this.displaySearchSuggestions(data.suggestions);
            this.showSearchLoading(false);
        })
        .catch(error => {
            console.error('Search error:', error);
            this.showSearchLoading(false);
        });
    }

    // Auto-refresh System
    setupAutoRefresh() {
        this.autoRefreshInterval = setInterval(() => {
            this.refreshCurrentTabData();
        }, 30000); // Refresh every 30 seconds
        
        // Page visibility API to pause refresh when tab is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(this.autoRefreshInterval);
            } else {
                this.setupAutoRefresh();
                this.refreshCurrentTabData();
            }
        });
    }

    refreshCurrentTabData() {
        if (Date.now() - this.lastRefreshTime < 10000) return; // Prevent too frequent refreshes
        
        const refreshIndicator = this.showRefreshIndicator();
        
        fetch(`ajax_refresh_data.php?tab=${this.currentTab}&timestamp=${this.lastRefreshTime}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.hasUpdates) {
                this.updateTabContent(data);
                this.showNotification('New updates available', 'info');
                if (this.soundEnabled) {
                    this.playNotificationSound();
                }
            }
            this.lastRefreshTime = Date.now();
            this.hideRefreshIndicator(refreshIndicator);
        })
        .catch(error => {
            console.error('Refresh error:', error);
            this.hideRefreshIndicator(refreshIndicator);
        });
    }

    // Enhanced Form Handling
    handleFormSubmit(e) {
        const form = e.target;
        const formData = new FormData(form);
        
        // Client-side validation
        if (!this.validateForm(form)) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            this.setButtonLoading(submitBtn, true);
        }
        
        // For AJAX forms
        if (form.classList.contains('ajax-form')) {
            e.preventDefault();
            this.submitFormAjax(form, formData);
        }
    }

    submitFormAjax(form, formData) {
        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.refreshCurrentTabData();
                
                // Reset form if specified
                if (data.resetForm) {
                    form.reset();
                }
                
                // Close modal if form is in modal
                const modal = form.closest('.modal');
                if (modal) {
                    this.closeModal(modal.id);
                }
            } else {
                this.showNotification(data.message || 'An error occurred', 'error');
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            this.showNotification('Network error occurred', 'error');
        })
        .finally(() => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                this.setButtonLoading(submitBtn, false);
            }
        });
    }

    // Bulk Actions
    setupBulkActions() {
        // Add select all checkbox
        const feedbackTable = document.querySelector('#feedback .table');
        if (feedbackTable) {
            this.addBulkSelectionControls(feedbackTable);
        }
    }

    addBulkSelectionControls(table) {
        // Add header checkbox
        const headerRow = table.querySelector('thead tr');
        if (headerRow) {
            const checkboxHeader = document.createElement('th');
            checkboxHeader.innerHTML = '<input type="checkbox" id="selectAll" title="Select All">';
            headerRow.insertBefore(checkboxHeader, headerRow.firstChild);
            
            // Add checkboxes to each row
            const bodyRows = table.querySelectorAll('tbody tr');
            bodyRows.forEach((row, index) => {
                if (row.cells.length > 1) { // Skip empty rows
                    const checkboxCell = document.createElement('td');
                    checkboxCell.innerHTML = `<input type="checkbox" class="row-select" value="${index}">`;
                    row.insertBefore(checkboxCell, row.firstChild);
                }
            });
            
            // Setup select all functionality
            document.getElementById('selectAll').addEventListener('change', (e) => {
                const checkboxes = table.querySelectorAll('.row-select');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
                this.updateBulkActionBar();
            });
            
            // Setup individual checkbox events
            table.querySelectorAll('.row-select').forEach(checkbox => {
                checkbox.addEventListener('change', () => this.updateBulkActionBar());
            });
            
            // Add bulk action bar
            this.addBulkActionBar();
        }
    }

    addBulkActionBar() {
        const bulkActionBar = document.createElement('div');
        bulkActionBar.id = 'bulkActionBar';
        bulkActionBar.className = 'bulk-action-bar';
        bulkActionBar.style.display = 'none';
        bulkActionBar.innerHTML = `
            <div class="bulk-actions">
                <span id="selectedCount">0 selected</span>
                <button class="btn btn-success" onclick="feedbackManager.bulkAction('resolve')">Mark as Resolved</button>
                <button class="btn btn-warning" onclick="feedbackManager.bulkAction('pending')">Mark as Pending</button>
                <button class="btn btn-danger" onclick="feedbackManager.bulkAction('delete')">Delete Selected</button>
                <button class="btn btn-info" onclick="feedbackManager.exportSelected()">Export Selected</button>
            </div>
        `;
        
        const feedbackSection = document.getElementById('feedback');
        if (feedbackSection) {
            feedbackSection.insertBefore(bulkActionBar, feedbackSection.querySelector('.table-container'));
        }
    }

    updateBulkActionBar() {
        const selected = document.querySelectorAll('.row-select:checked');
        const bulkActionBar = document.getElementById('bulkActionBar');
        const selectedCount = document.getElementById('selectedCount');
        
        if (selected.length > 0) {
            bulkActionBar.style.display = 'block';
            selectedCount.textContent = `${selected.length} selected`;
        } else {
            bulkActionBar.style.display = 'none';
        }
    }

    bulkAction(action) {
        const selected = document.querySelectorAll('.row-select:checked');
        if (selected.length === 0) {
            this.showNotification('No items selected', 'warning');
            return;
        }
        
        const ids = Array.from(selected).map(cb => {
            const row = cb.closest('tr');
            return row.cells[1].textContent; // Assuming ID is in second column
        });
        
        if (action === 'delete' && !confirm(`Are you sure you want to delete ${ids.length} feedback items?`)) {
            return;
        }
        
        fetch('ajax_bulk_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: action,
                ids: ids
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.refreshCurrentTabData();
                this.clearBulkSelection();
            } else {
                this.showNotification(data.message || 'Bulk action failed', 'error');
            }
        })
        .catch(error => {
            console.error('Bulk action error:', error);
            this.showNotification('Network error occurred', 'error');
        });
    }

    // Advanced Modal System
    viewFeedback(feedback) {
        const modal = document.getElementById('viewFeedbackModal');
        const details = document.getElementById('feedbackDetails');
        
        details.innerHTML = `
            <div class="feedback-detail-grid">
                <div class="detail-item">
                    <label>From:</label>
                    <span>${this.escapeHtml(feedback.name || 'Anonymous')} ${feedback.email ? '(' + this.escapeHtml(feedback.email) + ')' : ''}</span>
                </div>
                <div class="detail-item">
                    <label>Subject:</label>
                    <span>${this.escapeHtml(feedback.subject || 'No Subject')}</span>
                </div>
                <div class="detail-item">
                    <label>Type:</label>
                    <span class="type-badge type-${feedback.type}">${this.capitalize(feedback.type)}</span>
                </div>
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="status-badge status-${feedback.status}">${this.capitalize(feedback.status)}</span>
                </div>
                <div class="detail-item">
                    <label>Priority:</label>
                    <span class="priority-badge priority-${feedback.priority || 'normal'}">${this.capitalize(feedback.priority || 'normal')}</span>
                </div>
                <div class="detail-item">
                    <label>Date:</label>
                    <span>${this.formatDateTime(feedback.created_at)}</span>
                </div>
                <div class="detail-item full-width">
                    <label>Message:</label>
                    <div class="message-content">${this.escapeHtml(feedback.message)}</div>
                </div>
                ${feedback.attachment ? `
                <div class="detail-item full-width">
                    <label>Attachment:</label>
                    <a href="${feedback.attachment}" target="_blank" class="attachment-link">View Attachment</a>
                </div>
                ` : ''}
            </div>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="feedbackManager.respondToFeedback(${feedback.id}, ${feedback.user_id})">Respond</button>
                <button class="btn btn-success" onclick="feedbackManager.markAsResolved(${feedback.id})">Mark Resolved</button>
                <button class="btn btn-info" onclick="feedbackManager.assignPriority(${feedback.id})">Set Priority</button>
            </div>
        `;
        
        this.openModal('viewFeedbackModal');
        this.trackUserAction('view_feedback', { id: feedback.id });
    }

    // Keyboard Shortcuts
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Global shortcuts
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'f':
                        e.preventDefault();
                        document.getElementById('search')?.focus();
                        break;
                    case 'r':
                        e.preventDefault();
                        this.refreshCurrentTabData();
                        break;
                    case 'n':
                        e.preventDefault();
                        if (this.currentTab === 'global-notifications') {
                            document.getElementById('notification_title')?.focus();
                        }
                        break;
                }
            }
            
            // Tab navigation (1-4 keys)
            if (e.key >= '1' && e.key <= '4' && !e.ctrlKey && !e.metaKey) {
                const tabs = ['feedback', 'global-notifications', 'individual-notifications', 'analytics'];
                const tabIndex = parseInt(e.key) - 1;
                if (tabs[tabIndex]) {
                    this.showTab(tabs[tabIndex]);
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    // Notification System
    setupNotificationSystem() {
        // Create notification container
        if (!document.getElementById('notificationContainer')) {
            const container = document.createElement('div');
            container.id = 'notificationContainer';
            container.className = 'notification-container';
            document.body.appendChild(container);
        }
    }

    showNotification(message, type = 'info', duration = 5000) {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        }[type] || 'ℹ️';
        
        notification.innerHTML = `
            <span class="notification-icon">${icon}</span>
            <span class="notification-message">${this.escapeHtml(message)}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        container.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }
        
        return notification;
    }

    // Export Functionality
    setupExportFeatures() {
        // Add export buttons to each tab
        this.addExportButtons();
    }

    addExportButtons() {
        const sections = ['feedback', 'global-notifications', 'individual-notifications'];
        sections.forEach(section => {
            const sectionElement = document.getElementById(section);
            if (sectionElement) {
                const exportBtn = document.createElement('button');
                exportBtn.className = 'btn btn-info export-btn';
                exportBtn.innerHTML = '📊 Export Data';
                exportBtn.onclick = () => this.exportData(section);
                
                const header = sectionElement.querySelector('h2');
                if (header) {
                    header.style.display = 'flex';
                    header.style.justifyContent = 'space-between';
                    header.style.alignItems = 'center';
                    header.appendChild(exportBtn);
                }
            }
        });
    }

    exportData(section, format = 'csv') {
        const data = this.gatherExportData(section);
        
        if (format === 'csv') {
            this.exportToCSV(data, `${section}_export_${this.formatDate(new Date())}.csv`);
        } else if (format === 'json') {
            this.exportToJSON(data, `${section}_export_${this.formatDate(new Date())}.json`);
        }
        
        this.trackUserAction('export_data', { section, format });
    }

    exportToCSV(data, filename) {
        if (!data.length) {
            this.showNotification('No data to export', 'warning');
            return;
        }
        
        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => headers.map(header => `"${(row[header] || '').toString().replace(/"/g, '""')}"`).join(','))
        ].join('\n');
        
        this.downloadFile(csvContent, filename, 'text/csv');
    }

    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    // Utility Functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    formatDateTime(dateString) {
        return new Date(dateString).toLocaleString();
    }

    formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    trackUserAction(action, data = {}) {
        // Analytics tracking
        console.log('Action:', action, data);
        // You can integrate with Google Analytics or other tracking services here
    }

    // Animation helpers
    animateTabTransition(tabName) {
        const tabContent = document.getElementById(tabName);
        if (tabContent) {
            tabContent.style.opacity = '0';
            tabContent.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                tabContent.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                tabContent.style.opacity = '1';
                tabContent.style.transform = 'translateY(0)';
            }, 10);
        }
    }

    // Sound effects
    playNotificationSound() {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjii1O/ETCQGLYbT8Nub');
        audio.volume = 0.1;
        audio.play().catch(() => {}); // Ignore errors if audio can't play
    }

    // User preferences
    loadUserPreferences() {
        const prefs = localStorage.getItem('feedbackManagerPrefs');
        if (prefs) {
            const preferences = JSON.parse(prefs);
            this.soundEnabled = preferences.soundEnabled !== false;
            // Apply other preferences
        }
    }

    saveUserPreferences() {
        const preferences = {
            soundEnabled: this.soundEnabled,
            currentTab: this.currentTab
        };
        localStorage.setItem('feedbackManagerPrefs', JSON.stringify(preferences));
    }

    // Modal management
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = 'auto';
    }

    // Enhanced filtering
    handleFilterChange() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.applyFilters();
        }, 300);
    }

    applyFilters() {
        const search = document.getElementById('search')?.value || '';
        const typeFilter = document.getElementById('type_filter')?.value || '';
        const statusFilter = document.getElementById('status_filter')?.value || '';
        
        const params = new URLSearchParams({
            search: search,
            type_filter: typeFilter,
            status_filter: statusFilter
        });
        
        window.location.href = `?${params.toString()}`;
    }

    // Priority assignment
    assignPriority(feedbackId) {
        const priorities = ['low', 'normal', 'high', 'critical'];
        const select = document.createElement('select');
        select.className = 'form-control';
        
        priorities.forEach(priority => {
            const option = document.createElement('option');
            option.value = priority;
            option.textContent = this.capitalize(priority);
            select.appendChild(option);
        });
        
        // Create a simple modal for priority selection
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        modal.innerHTML = `
            <div class="modal-content">
                <h3>Set Priority</h3>
                <div class="form-group">
                    <label>Priority Level:</label>
                    ${select.outerHTML}
                </div>
                <div class="modal-actions">
                    <button class="btn btn-primary" onclick="feedbackManager.savePriority(${feedbackId}, this.parentElement.parentElement.querySelector('select').value)">Save</button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    // Cleanup on page unload
    destroy() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
        }
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        this.saveUserPreferences();
    }
}

// Initialize the feedback manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.feedbackManager = new FeedbackManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.feedbackManager) {
        window.feedbackManager.destroy();
    }
});

// Legacy function support for existing PHP code
function showTab(tabName) {
    if (window.feedbackManager) {
        window.feedbackManager.showTab(tabName);
    }
}

function applyFilters() {
    if (window.feedbackManager) {
        window.feedbackManager.applyFilters();
    }
}

function viewFeedback(feedback) {
    if (window.feedbackManager) {
        window.feedbackManager.viewFeedback(feedback);
    }
}

function respondToFeedback(feedbackId, userId) {
    if (window.feedbackManager) {
        window.feedbackManager.respondToFeedback(feedbackId, userId);
    }
}

function closeModal(modalId) {
    if (window.feedbackManager) {
        window.feedbackManager.closeModal(modalId);
    }
}