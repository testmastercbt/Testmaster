function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function applyFilters() {
            const search = document.getElementById('search').value;
            const courseFilter = document.getElementById('course_filter').value;
            const url = `?search=${encodeURIComponent(search)}&course_filter=${courseFilter}`;
            window.location.href = url;
        }

        function editQuestion(question) {
            document.getElementById('edit_question_id').value = question.id;
            document.getElementById('edit_course_id').value = question.course_id;
            document.getElementById('edit_question_text').value = question.question_text;
            document.getElementById('edit_option1').value = question.option1;
            document.getElementById('edit_option2').value = question.option2;
            document.getElementById('edit_option3').value = question.option3;
            document.getElementById('edit_option4').value = question.option4;
            document.getElementById('edit_correct_option').value = question.correct_option;
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function grantAccess(regId) {
            if (confirm('Grant access to this student?')) {
                // You can implement AJAX call here or create a form
                console.log('Grant access to registration ID:', regId);
            }
        }

        function revokeAccess(regId) {
            if (confirm('Revoke access for this student?')) {
                // You can implement AJAX call here or create a form
                console.log('Revoke access for registration ID:', regId);
            }
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Enter key search
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });