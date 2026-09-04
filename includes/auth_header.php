<?php
// We might not need all logic, but it's good to keep consistent
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' - SYNCTeams' : 'SYNCTeams'; ?></title>
    <link rel="stylesheet" href="/task_collab_system/css/style.css?v=<?php echo filemtime(__DIR__.'/../css/style.css'); ?>">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Load theme early to prevent flash
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        } else {
            // Default to dark mode for auth because it looks cooler? Or stick to light.
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
</head>
<body>
    <div class="auth-layout">
        <!-- Left Side: Image -->
        <div class="auth-image">
            <div class="auth-brand-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="3" width="7" height="7" rx="2" fill="currentColor"/>
                    <rect x="14" y="3" width="7" height="7" rx="2" fill="currentColor"/>
                    <rect x="14" y="14" width="7" height="7" rx="2" fill="currentColor"/>
                    <path d="M3 16C3 14.8954 3.89543 14 5 14H10C11.1046 14 12 14.8954 12 16V21H5C3.89543 21 3 20.1046 3 19V16Z" fill="currentColor"/>
                </svg>
                SYNCTeams
            </div>
            <div class="auth-hero-text">
                <h1>Manage your team's work in one place.</h1>
                <p>Collaborate, plan, and deliver on time with our intuitive platform.</p>
            </div>
        </div>

        <!-- Right Side: Content -->
        <div class="auth-content">
            <!-- Theme toggle in the top right -->
            <div style="position: absolute; top: 24px; right: 24px;">
                <i class="ph ph-moon header-icon" id="theme-toggle" title="Toggle Dark Mode" style="cursor: pointer; font-size: 1.5rem; color: var(--text-muted);"></i>
            </div>
            
            <div class="auth-form-container">
