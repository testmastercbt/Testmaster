function startCourse() {
            alert('Redirecting to course materials...');
        }

        function takeExam() {
            alert('Loading exam interface...');
        }

        function viewResults() {
            alert('Showing your exam results...');
        }

        function browseCourses() {
            alert('Browse available courses...');
        }

        // Simulate dynamic content updates
        function updateStats() {
            // This would normally fetch real data from your backend
            console.log('Stats updated');
        }

        // Mobile menu toggle (if needed)
        function toggleMobileMenu() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }

        // Update subscription badge dynamically
        function updateSubscriptionStatus(isPaid) {
            const badge = document.querySelector('.subscription-badge');
            if (isPaid) {
                badge.textContent = 'Premium Plan';
                badge.className = 'subscription-badge paid-plan';
            } else {
                badge.textContent = 'Free Plan';
                badge.className = 'subscription-badge free-plan';
            }
        }

            function startCourse(code) {
        window.location.href = "start-course.php?code=" + code;
    }

    function takeExam() {
        window.location.href = "exam-setup.php";
    }

    function viewResults() {
        window.location.href = "results.php";
    }