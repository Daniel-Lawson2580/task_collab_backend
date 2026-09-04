            </div> <!-- end auth-form-container -->
        </div> <!-- end auth-content -->
        
        <a href="#company-info" class="scroll-down-btn" aria-label="Scroll down to company info">
            <i class="ph ph-arrow-down"></i>
        </a>
    </div> <!-- end auth-layout -->

    <!-- Company Profile Section -->
    <section id="company-info" class="company-profile-section">
        <div class="company-container">
            <div class="company-grid">
                <div class="company-brand-col">
                    <div class="footer-logo">
                        <i class="ph ph-squares-four"></i> SYNCTeams
                    </div>
                    <p>Elevating team productivity to new heights. We provide the intuitive tools your team needs to collaborate, plan, and deliver amazing results without the friction.</p>
                </div>
                <div class="company-col">
                    <h3>Contact Us</h3>
                    <ul class="contact-list">
                        <li><i class="ph ph-envelope-simple"></i> hello@syncteams.com</li>
                        <li><i class="ph ph-phone"></i> +1 (555) 123-4567</li>
                        <li><i class="ph ph-map-pin"></i> 100 Innovation Drive<br>San Francisco, CA 94103</li>
                    </ul>
                </div>
                <div class="company-col">
                    <h3>Connect</h3>
                    <div class="social-links">
                        <a href="#" title="Twitter"><i class="ph ph-twitter-logo"></i></a>
                        <a href="#" title="LinkedIn"><i class="ph ph-linkedin-logo"></i></a>
                        <a href="#" title="GitHub"><i class="ph ph-github-logo"></i></a>
                    </div>
                </div>
            </div>
            <div class="company-footer-bottom">
                &copy; <?php echo date('Y'); ?> SYNCTeams Inc. All rights reserved.
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                if (currentTheme === 'dark') {
                    themeToggle.classList.remove('ph-moon');
                    themeToggle.classList.add('ph-sun');
                }
                
                themeToggle.addEventListener('click', () => {
                    let theme = document.documentElement.getAttribute('data-theme');
                    if (theme === 'dark') {
                        theme = 'light';
                        themeToggle.classList.remove('ph-sun');
                        themeToggle.classList.add('ph-moon');
                    } else {
                        theme = 'dark';
                        themeToggle.classList.remove('ph-moon');
                        themeToggle.classList.add('ph-sun');
                    }
                    document.documentElement.setAttribute('data-theme', theme);
                    localStorage.setItem('theme', theme);
                });
            }
        });

        function togglePasswordVisibility(iconElement, inputId) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                iconElement.classList.remove('ph-eye');
                iconElement.classList.add('ph-eye-slash'); // using ph-eye-slash
            } else {
                input.type = 'password';
                iconElement.classList.remove('ph-eye-slash');
                iconElement.classList.add('ph-eye');
            }
        }
    </script>
</body>
</html>
