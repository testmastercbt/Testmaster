
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
            const roleFilter = document.getElementById('role_filter').value;
            const deptFilter = document.getElementById('department_filter').value;
            const levelFilter = document.getElementById('level_filter').value;
            
            const url = `?search=${encodeURIComponent(search)}&role_filter=${roleFilter}&department_filter=${encodeURIComponent(deptFilter)}&level_filter=${encodeURIComponent(levelFilter)}`;
            window.location.href = url;
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_matric_number').value = user.matric_number || '';
            document.getElementById('edit_role').value = user.role || 'free';
            document.getElementById('edit_department').value = user.department || '';
            document.getElementById('edit_level').value = user.level || '';
            document.getElementById('edit_gender').value = user.gender || '';
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
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

        // Auto-generate password suggestion
        document.getElementById('name').addEventListener('input', function() {
            const matricField = document.getElementById('matric_number');
            if (this.value && !matricField.value) {
                // You can implement auto-suggest logic here
            }
        });