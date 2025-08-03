<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMaster - CBT Platform</title>
    <link rel="stylesheet" href="home.css">
</head>
<body class="logged-out" style="min-width: 1024px;">
    <!-- Animated Background -->
    <div class="bg-decoration">
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>
        <div class="floating-shape shape-3"></div>
    </div>

    <!-- Header -->
    <header class="header">
        <nav class="nav-container">
            <a href="home.php" class="logo">TestMaster</a>
            <ul class="nav-links">
                <li><a href="home.html">Home</a></li>
                <li><a href="examsetup1.php">Take Exams</a></li>
                <li><a href="results.php">Results</a></li>
                <li><a href="feedback.php">Feedback</a></li>
            </ul>
            <div class="auth-section">
                <!-- Logged out buttons -->
                <div class="auth-buttons">
                    <a href="Login.php" class="login-btn">Login</a>
                    <a href="register.php" class="signup-btn">Sign Up</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>TestMaster, CBT Platform</h1>
            <p class="hero-subtitle">Your exam companion. Smart. Simple. Reliable.</p>
            <div class="cta-buttons">
                <a class="btn btn-primary" href="examsetup1.php" >Take Exam</a>
                <a class="btn btn-secondary" href="dashboard.php" >Next Level?</a>
            </div>
            <p style="font-weight: bold; font-size: 1.2rem; color: #004;"><br>Note: Website is stil Under Development</p>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-container">
            <div class="stat-item">
                <div class="stat-number">10+</div>
                <div class="stat-label"> Courses</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">2000+</div>
                <div class="stat-label">Practice Tests</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">70%</div>
                <div class="stat-label">Success Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Support</div>
            </div>
        </div>
    </section>

    <!-- Notice Section -->
    <section class="notice">
        <div class="notice-content">
            <h3>📚 Courses Coming Soon!</h3>
            <p>We're working hard to bring you comprehensive course materials. Stay tuned for updates!</p>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <h2 class="section-title">Why Choose TestMaster?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <span class="feature-icon">🎯</span>
                <h3>Smart Exam System</h3>
                <p>Advanced computer-based testing with intelligent question randomization, real-time scoring, and instant feedback on your performance. Get detailed insights into your strengths and weaknesses.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">📊</span>
                <h3>Detailed Analytics</h3>
                <p>Track your progress with comprehensive performance analytics. Identify areas for improvement with detailed reports, progress charts, and personalized recommendations.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">🚀</span>
                <h3>Premium Features</h3>
                <p>Unlock advanced features like unlimited practice tests, detailed explanations, priority support, and an ad-free experience with our premium subscription plan.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>Platform</h4>
                <a href="about.html">About TestMaster</a>
                <a onclick="showFeedbackModal()">Feedback & Reviews</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms and Conditions</a>
            </div>
            <div class="footer-section">
                <h4>Connect With Us</h4>
                <div class="social-links">
                    <a href="https://youtube.com/@codewithmukhiteee?si=udowWveV42gZD1BF" title="YouTube" target="_blank">📺</a>
                    <a href="https://wa.me/23407059114191" title="WhatsApp" target="_blank">💬</a>
                    <a href="https://www.instagram.com/mukhiteee?igsh=YzljYTk1ODg3Zg==" title="Instagram" target="_blank">💬</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 TestMaster CBT Platform | <a style="text-decoration: none; color: white;" href="https://www.youtube.com/@codewithmukhiteee" target="_blank"> @codewithmukhiteee </a></p>
        </div>
    </footer>

    <!-- Premium Modal -->
    <div id="premiumModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePremiumModal()">&times;</span>
            <h2>🚀 Upgrade to Premium</h2>
            <p>Unlock the full potential of TestMaster with our premium features:</p>
            <ul>
                <li>✅ Unlimited practice tests and mock exams</li>
                <li>✅ Detailed answer explanations and study guides</li>
                <li>✅ Advanced analytics and progress tracking</li>
                <li>✅ Priority customer support and live chat</li>
                <li>✅ Ad-free experience across all devices</li>
                <li>✅ Early access to new features and courses</li>
            </ul>
            <button class="btn btn-primary" style="margin-top: 2rem;">Upgrade Now - $9.99/month</button>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeFeedbackModal()">&times;</span>
            <h2>🐛 Help Us Improve!</h2>
            <p>Found a bug or want to share your experience? We value your feedback and use it to make TestMaster better for everyone.</p>
            <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
                <a href="g-feedback.php" class="btn btn-primary">Report a Bug</a>
                <a href="g-feedback.php" class="btn btn-secondary">Leave a Review</a>
            </div>
        </div>
    </div>

    <!-- Exam Modal -->
    <div id="examModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeExamModal()">&times;</span>
            <h2>🚧 Coming Soon!</h2>
            <p>Our exam system is currently under development. We're working hard to bring you the best computer-based testing experience possible.</p>
            <p style="margin-top: 1.5rem; color: #64748b;">Expected launch: Q3 2025. Stay tuned for updates!</p>
            <button class="btn btn-secondary" style="margin-top: 2rem;" onclick="closeExamModal()">Got it!</button>
        </div>
    </div>

    <script src="home.js"></script>
</body>
</html>