    if (window.innerWidth < 768) {
        alert("You're viewing the desktop version. For a better experience, rotate your screen or view on a larger screen. Mobile view wiil be available soon.");
    }

// Authentication state toggle (demo purposes)
        function toggleAuthState() {
            const body = document.body;
            if (body.classList.contains('logged-out')) {
                body.classList.remove('logged-out');
                body.classList.add('logged-in');
            } else {
                body.classList.remove('logged-in');
                body.classList.add('logged-out');
            }
        }

        // Modal Functions
        function showPremiumModal() {
            document.getElementById('premiumModal').classList.add('active');
        }

        function closePremiumModal() {
            document.getElementById('premiumModal').classList.remove('active');
        }

        function showFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('active');
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.remove('active');
        }

        function showExamModal() {
            document.getElementById('examModal').classList.add('active');
        }

        function closeExamModal() {
            document.getElementById('examModal').classList.remove('active');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll-based animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for scroll animations
        document.addEventListener('DOMContentLoaded', () => {
            const animateElements = document.querySelectorAll('.feature-card, .stat-item');
            animateElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                observer.observe(el);
            });
        });

        // Add dynamic counter animation for stats
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString() + (target >= 3000 ? 'K+' : target === 70 ? '%' : target === 24 ? '/7' : '');
            }, 20);
        }

        // Trigger counter animations when stats section is visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumbers = entry.target.querySelectorAll('.stat-number');
                    const targets = [10, 2000, 70, 24];
                    statNumbers.forEach((num, index) => {
                        setTimeout(() => {
                            animateCounter(num, targets[index]);
                        }, index * 200);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        document.addEventListener('DOMContentLoaded', () => {
            const statsSection = document.querySelector('.stats-section');
            if (statsSection) {
                statsObserver.observe(statsSection);
            }
        });