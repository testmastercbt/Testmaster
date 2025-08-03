// Global variables
let selectedQuestions = new Set();
let allSelected = false;

// Tab functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName).classList.add('active');
    
    // Add active class to clicked tab
    event.target.classList.add('active');
}

// Filter functionality
function applyFilters() {
    const search = document.getElementById('search').value;
    const courseFilter = document.getElementById('course_filter').value;
    const difficultyFilter = document.getElementById('difficulty_filter').value;
    
    // Build URL with filters
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (courseFilter) params.append('course_filter', courseFilter);
    if (difficultyFilter) params.append('difficulty_filter', difficultyFilter);
    params.append('page', '1'); // Reset to first page
    
    window.location.href = '?' + params.toString();
}

// Apply filters on Enter key press in search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
});

// Selection functionality
function updateSelection() {
    const checkboxes = document.querySelectorAll('.select-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const selectedQuestionsInput = document.getElementById('selectedQuestions');
    const selectAllBtn = document.getElementById('selectAllBtn');
    
    selectedQuestions.clear();
    
    checkboxes.forEach(checkbox => {
        const questionCard = checkbox.closest('.question-card');
        const questionId = questionCard.dataset.questionId;
        
        if (checkbox.checked) {
            selectedQuestions.add(questionId);
            questionCard.classList.add('selected');
        } else {
            questionCard.classList.remove('selected');
        }
    });
    
    const count = selectedQuestions.size;
    selectedCount.textContent = `${count} question${count !== 1 ? 's' : ''} selected`;
    selectedQuestionsInput.value = JSON.stringify([...selectedQuestions]);
    
    if (count > 0) {
        bulkActions.classList.add('show');
    } else {
        bulkActions.classList.remove('show');
    }
    
    // Update select all button text
    allSelected = count === checkboxes.length && count > 0;
    selectAllBtn.textContent = allSelected ? 'Deselect All' : 'Select All';
}

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.select-checkbox');
    
    if (allSelected) {
        // Deselect all
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        allSelected = false;
    } else {
        // Select all
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        allSelected = true;
    }
    
    updateSelection();
}

// Edit question modal functionality
let editModal = null;

function createEditModal() {
    // Create modal HTML
    const modalHTML = `
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h2>Edit Question</h2>
                <form method="POST" id="editQuestionForm">
                    <input type="hidden" name="question_id" id="edit_question_id">
                    <input type="hidden" name="update_question" value="1">
                    
                    <div class="form-group">
                        <label for="edit_course_id">Course</label>
                        <select name="course_id" id="edit_course_id" required>
                            <option value="">Select Course</option>
                            ${getCourseOptions()}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_question_text">Question Text</label>
                        <textarea name="question_text" id="edit_question_text" required placeholder="Enter the question text..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_option1">Option A</label>
                            <input type="text" name="option1" id="edit_option1" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_option2">Option B</label>
                            <input type="text" name="option2" id="edit_option2" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_option3">Option C</label>
                            <input type="text" name="option3" id="edit_option3" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_option4">Option D</label>
                            <input type="text" name="option4" id="edit_option4" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_option5">Option E (Optional)</label>
                            <input type="text" name="option5" id="edit_option5">
                        </div>
                        <div class="form-group">
                            <label for="edit_correct_option">Correct Answer</label>
                            <select name="correct_option" id="edit_correct_option" required>
                                <option value="">Select Correct Answer</option>
                                <option value="1">A</option>
                                <option value="2">B</option>
                                <option value="3">C</option>
                                <option value="4">D</option>
                                <option value="5">E</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_difficulty">Difficulty</label>
                            <select name="difficulty" id="edit_difficulty">
                                <option value="">Select Difficulty</option>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_question_image">Question Image URL (Optional)</label>
                            <input type="url" name="question_image" id="edit_question_image">
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Update Question</button>
                        <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d; color: white;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    editModal = document.getElementById('editModal');
    
    // Close modal when clicking outside
    editModal.addEventListener('click', function(e) {
        if (e.target === editModal) {
            closeEditModal();
        }
    });
}

function getCourseOptions() {
    // This function should return the course options HTML
    // You might want to populate this from PHP or make an AJAX call
    const courseSelect = document.getElementById('course_filter');
    if (courseSelect) {
        return courseSelect.innerHTML;
    }
    return '<option value="">No courses available</option>';
}

function editQuestion(questionData) {
    // Create modal if it doesn't exist
    if (!editModal) {
        createEditModal();
    }
    
    // Populate form with question data
    document.getElementById('edit_question_id').value = questionData.id;
    document.getElementById('edit_course_id').value = questionData.course_id || '';
    document.getElementById('edit_question_text').value = questionData.question_id || '';
    document.getElementById('edit_option1').value = questionData.option1 || '';
    document.getElementById('edit_option2').value = questionData.option2 || '';
    document.getElementById('edit_option3').value = questionData.option3 || '';
    document.getElementById('edit_option4').value = questionData.option4 || '';
    document.getElementById('edit_option5').value = questionData.option5 || '';
    document.getElementById('edit_correct_option').value = questionData.correct_option || '';
    document.getElementById('edit_difficulty').value = questionData.difficulty || '';
    document.getElementById('edit_question_image').value = questionData.question_image || '';
    
    // Show modal
    editModal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeEditModal() {
    if (editModal) {
        editModal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
}

// Form validation for add question form
function validateQuestionForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            field.style.borderColor = '#ddd';
        }
    });
    
    // Check if at least 4 options are filled
    const options = [
        form.querySelector('[name="option1"]'),
        form.querySelector('[name="option2"]'),
        form.querySelector('[name="option3"]'),
        form.querySelector('[name="option4"]')
    ];
    
    const filledOptions = options.filter(option => option && option.value.trim()).length;
    if (filledOptions < 4) {
        alert('Please fill in at least 4 answer options (A, B, C, D).');
        isValid = false;
    }
    
    // Validate correct answer selection
    const correctOption = form.querySelector('[name="correct_option"]');
    if (correctOption && correctOption.value) {
        const optionIndex = parseInt(correctOption.value);
        if (optionIndex > filledOptions) {
            alert('The correct answer must correspond to a filled option.');
            isValid = false;
        }
    }
    
    return isValid;
}

// Real-time search functionality (optional enhancement)
function setupLiveSearch() {
    const searchInput = document.getElementById('search');
    if (!searchInput) return;
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (searchInput.value.length >= 3 || searchInput.value.length === 0) {
                applyFilters();
            }
        }, 500); // Wait 500ms after user stops typing
    });
}

// Image preview functionality
function setupImagePreview() {
    const imageInputs = document.querySelectorAll('input[type="url"][name*="image"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const url = this.value.trim();
            if (url) {
                // Create or update preview
                let preview = this.parentNode.querySelector('.image-preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'image-preview';
                    preview.style.cssText = 'max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 6px; display: block;';
                    this.parentNode.appendChild(preview);
                }
                
                preview.src = url;
                preview.onerror = function() {
                    this.style.display = 'none';
                };
                preview.onload = function() {
                    this.style.display = 'block';
                };
            }
        });
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + A to select all questions
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        toggleSelectAll();
    }
    
    // Escape to close modal
    if (e.key === 'Escape' && editModal && editModal.style.display === 'block') {
        closeEditModal();
    }
});

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Setup image preview
    setupImagePreview();
    
    // Setup live search (optional)
    // setupLiveSearch(); // Uncomment if you want live search
    
    // Add form validation to add question form
    const addQuestionForm = document.querySelector('#add-question form');
    if (addQuestionForm) {
        addQuestionForm.addEventListener('submit', function(e) {
            if (!validateQuestionForm('add-question')) {
                e.preventDefault();
            }
        });
    }
    
    // Initialize selection state
    updateSelection();
    
    // Smooth scrolling for pagination
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
});

// Utility function to show loading state
function showLoading(element, text = 'Loading...') {
    const originalText = element.textContent;
    element.textContent = text;
    element.disabled = true;
    
    return function hideLoading() {
        element.textContent = originalText;
        element.disabled = false;
    };
}

// Enhanced bulk delete with confirmation
function confirmBulkDelete() {
    const count = selectedQuestions.size;
    if (count === 0) {
        alert('No questions selected.');
        return false;
    }
    
    return confirm(`Are you sure you want to delete ${count} selected question${count !== 1 ? 's' : ''}? This action cannot be undone.`);
}

// Auto-save functionality for drafts (optional)
function setupAutoSave() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                // Save to localStorage with a unique key
                const formId = form.id || 'default';
                const key = `draft_${formId}_${input.name}`;
                localStorage.setItem(key, input.value);
            });
        });
    });
}

// Load drafts on page load
function loadDrafts() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            const formId = form.id || 'default';
            const key = `draft_${formId}_${input.name}`;
            const savedValue = localStorage.getItem(key);
            
            if (savedValue && !input.value) {
                input.value = savedValue;
            }
        });
    });
}

// Clear drafts after successful submission
function clearDrafts(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        const key = `draft_${formId}_${input.name}`;
        localStorage.removeItem(key);
    });
}