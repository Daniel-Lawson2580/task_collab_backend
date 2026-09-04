<?php
// Assumes functions.php has already been required by the calling page.

$notifications = [];
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    // We need $conn which is included by functions.php
    $stmt_notif = $conn->prepare("SELECT id, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_notif->bind_param('i', $_SESSION['user_id']);
    $stmt_notif->execute();
    $notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt_unread = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_unread->bind_param('i', $_SESSION['user_id']);
    $stmt_unread->execute();
    $unread_count = $stmt_unread->get_result()->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? h($page_title) . ' — TaskBoard' : 'TaskBoard'; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<!-- Include Phosphor Icons for sleek UI icons -->
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<link rel="stylesheet" href="/task_collab_system/css/style.css?v=<?php echo filemtime(__DIR__.'/../css/style.css'); ?>">
<script>
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
</script>
</head>
<body>
<div class="app-layout <?php echo isset($_SESSION['user_id']) ? '' : 'auth-layout'; ?>">
    <?php if (isset($_SESSION['user_id'])): ?>
    <aside class="sidebar">
        <div class="brand">
            <i class="ph ph-squares-four" style="color: #4372ff; font-size: 1.5rem;"></i>
            <span>SYNCTeams</span>
            <i class="ph ph-caret-line-left collapse-icon"></i>
        </div>
        
        <div class="menu-section">
            <span class="menu-title">MAIN MENU</span>
            <nav class="sidebar-nav">
                <a href="/task_collab_system/dashboard.php"><i class="ph ph-house"></i> Overview</a>
                <a href="/task_collab_system/projects.php"><i class="ph ph-folder"></i> Project</a>
                <a href="/task_collab_system/tasks.php"><i class="ph ph-check-square"></i> Tasks</a>
                <?php if (is_admin()): ?>
                <a href="/task_collab_system/admin_users.php"><i class="ph ph-users-three"></i> User Management</a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="menu-section">
            <span class="menu-title">INSIGHTS MENU</span>
            <nav class="sidebar-nav">
                <a href="/task_collab_system/reports.php"><i class="ph ph-chart-line-up"></i> Report</a>
                <a href="/task_collab_system/settings.php"><i class="ph ph-gear"></i> Settings</a>
            </nav>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <form action="/task_collab_system/search.php" method="GET" class="search-bar" style="margin:0;">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" name="q" placeholder="Search projects or tasks..." value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES); ?>">
                <span class="shortcut">⌘F</span>
            </form>
            <div class="header-actions">
                <i class="ph ph-moon header-icon" id="theme-toggle" title="Toggle Dark Mode" style="cursor: pointer;"></i>
                
                <!-- Notification Bell -->
                <div class="notification-wrapper" style="position: relative;">
                    <i class="ph ph-bell header-icon" id="bell-icon" style="cursor: pointer;"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo (int)$unread_count; ?></span>
                    <?php endif; ?>
                    
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:600;">Notifications</div>
                        <?php if (empty($notifications)): ?>
                            <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:0.9rem;">You have no notifications.</div>
                        <?php else: ?>
                            <div style="max-height:300px; overflow-y:auto;">
                                <?php foreach ($notifications as $n): ?>
                                    <div class="notif-item <?php echo $n['is_read'] ? 'read' : 'unread'; ?>" data-id="<?php echo (int)$n['id']; ?>" data-link="<?php echo h($n['link']); ?>" style="padding:12px 16px; border-bottom:1px solid var(--border-color); cursor:pointer; display:flex; gap:12px; align-items:center;">
                                        <div style="width:8px; height:8px; border-radius:50%; background:<?php echo $n['is_read'] ? 'transparent' : 'var(--primary)'; ?>;"></div>
                                        <div style="flex:1;">
                                            <p style="margin-bottom:4px; font-size:0.85rem; line-height:1.4; color:var(--text-main);"><?php echo h($n['message']); ?></p>
                                            <span style="font-size:0.75rem; color:var(--text-muted);"><?php echo date('M j, g:i A', strtotime($n['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <i class="ph ph-question header-icon" id="help-icon" style="cursor: pointer;" title="Help & Support"></i>
                <div class="user-profile profile-dropdown">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=random" alt="Avatar" class="avatar">
                    <div class="user-info">
                        <span class="user-name"><?php echo h($_SESSION['user_name']); ?></span>
                        <span class="user-role"><?php echo is_admin() ? 'Administrator' : 'User'; ?></span>
                    </div>
                    <div class="profile-menu">
                        <a href="/task_collab_system/settings.php"><i class="ph ph-gear"></i> Settings</a>
                        <a href="/task_collab_system/auth/logout.php"><i class="ph ph-sign-out"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>
    <?php endif; ?>
        <div class="page-content">

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

            // Notification Dropdown Toggle
            const bellIcon = document.getElementById('bell-icon');
            const notifDropdown = document.getElementById('notification-dropdown');
            if (bellIcon && notifDropdown) {
                bellIcon.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('show');
                });
                
                document.addEventListener('click', (e) => {
                    if (!notifDropdown.contains(e.target)) {
                        notifDropdown.classList.remove('show');
                    }
                });
            }

            // Mark notification as read
            function bindNotificationClicks() {
                const notifItems = document.querySelectorAll('.notif-item');
                notifItems.forEach(item => {
                    item.addEventListener('click', () => {
                        const id = item.getAttribute('data-id');
                        const link = item.getAttribute('data-link');
                        if (item.classList.contains('unread')) {
                            fetch('/task_collab_system/api/notifications_mark_read.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ id: id })
                            }).then(() => {
                                if (link) window.location.href = link;
                            }).catch(() => {
                                if (link) window.location.href = link;
                            });
                        } else {
                            if (link) window.location.href = link;
                        }
                    });
                });
            }
            bindNotificationClicks();

            // Auto-refresh notifications every 5 seconds
            setInterval(() => {
                fetch('/task_collab_system/api/fetch_notifications.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const wrapper = document.querySelector('.notification-wrapper');
                        const bellIcon = document.getElementById('bell-icon');
                        
                        // Update Badge
                        let badge = wrapper.querySelector('.notification-badge');
                        if (data.unread_count > 0) {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'notification-badge';
                                bellIcon.after(badge);
                            }
                            badge.textContent = data.unread_count;
                        } else if (badge) {
                            badge.remove();
                        }
                        
                        // Update Dropdown Content
                        const dropdownList = document.getElementById('notification-dropdown');
                        let html = '<div style="padding:16px; border-bottom:1px solid var(--border-color); font-weight:600;">Notifications</div>';
                        
                        if (data.notifications.length === 0) {
                            html += '<div style="padding:24px; text-align:center; color:var(--text-muted); font-size:0.9rem;">You have no notifications.</div>';
                        } else {
                            html += '<div style="max-height:300px; overflow-y:auto;">';
                            data.notifications.forEach(n => {
                                const statusClass = n.is_read == 1 ? 'read' : 'unread';
                                const dotColor = n.is_read == 1 ? 'transparent' : 'var(--primary)';
                                html += `
                                    <div class="notif-item ${statusClass}" data-id="${n.id}" data-link="${n.link || ''}" style="padding:12px 16px; border-bottom:1px solid var(--border-color); cursor:pointer; display:flex; gap:12px; align-items:center;">
                                        <div style="width:8px; height:8px; border-radius:50%; background:${dotColor};"></div>
                                        <div style="flex:1;">
                                            <p style="margin-bottom:4px; font-size:0.85rem; line-height:1.4; color:var(--text-main);">${n.message}</p>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">${n.formatted_date}</span>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }
                        
                        // Only update HTML if it changed to prevent dropdown flickering/closing issues
                        // For a simple implementation, we'll just update it
                        dropdownList.innerHTML = html;
                        bindNotificationClicks();
                    }
                })
                .catch(err => console.error('Error fetching notifications:', err));
            }, 5000);
        });
    </script>
