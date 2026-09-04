</div> <!-- end page-content -->
    </main>
</div> <!-- end app-layout -->

<div class="toast-container" id="toast-container"></div>

<!-- Help Slide-over Modal -->
<div class="help-overlay" id="help-overlay"></div>
<div class="help-drawer" id="help-drawer">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding:16px 24px;">
        <h2 style="font-size:1.25rem;">Help & Support</h2>
        <i class="ph ph-x" id="help-close" style="font-size:1.5rem; cursor:pointer; color:var(--text-muted);"></i>
    </div>
    <div style="padding:24px; overflow-y:auto; flex:1;">
        <div style="margin-bottom:32px;">
            <h3 style="margin-bottom:12px; font-size:1rem; display:flex; align-items:center; gap:8px;"><i class="ph ph-keyboard" style="color:var(--primary);"></i> Keyboard Shortcuts</h3>
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;">
                <li style="display:flex; justify-content:space-between;"><span>Global Search</span> <kbd style="background:var(--border-color); padding:2px 6px; border-radius:4px; font-size:0.8rem;">Cmd/Ctrl + F</kbd></li>
            </ul>
        </div>
        
        <div style="margin-bottom:32px;">
            <h3 style="margin-bottom:12px; font-size:1rem; display:flex; align-items:center; gap:8px;"><i class="ph ph-question" style="color:var(--primary);"></i> Frequently Asked Questions</h3>
            <div style="margin-bottom:16px;">
                <strong style="display:block; margin-bottom:4px; font-size:0.95rem;">How do I invite teammates?</strong>
                <p style="font-size:0.85rem; color:var(--text-muted);">Go to a project you own, and click the "Add Member" button or assign them to a task if they are already in the project.</p>
            </div>
            <div style="margin-bottom:16px;">
                <strong style="display:block; margin-bottom:4px; font-size:0.95rem;">Can I recover deleted projects?</strong>
                <p style="font-size:0.85rem; color:var(--text-muted);">Yes, the system uses Soft Deletes. Contact a Super Admin to restore your data.</p>
            </div>
        </div>

        <div>
            <h3 style="margin-bottom:12px; font-size:1rem; display:flex; align-items:center; gap:8px;"><i class="ph ph-envelope-simple" style="color:var(--primary);"></i> Still need help?</h3>
            <button class="btn btn-outline" style="width:100%; justify-content:center;" onclick="showToast('Support request sent! We will email you shortly.', 'success')">Contact Support</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const collapseIcon = document.querySelector('.collapse-icon');
    const sidebar = document.querySelector('.sidebar');
    if (collapseIcon && sidebar) {
        collapseIcon.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }

    // Search shortcut
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
            const searchInput = document.querySelector('.search-bar input');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        }
    });

    // Help Drawer Logic
    const helpIcon = document.getElementById('help-icon');
    const helpDrawer = document.getElementById('help-drawer');
    const helpOverlay = document.getElementById('help-overlay');
    const helpClose = document.getElementById('help-close');

    function toggleHelpDrawer() {
        if (!helpDrawer || !helpOverlay) return;
        helpDrawer.classList.toggle('show');
        helpOverlay.classList.toggle('show');
    }

    if (helpIcon) helpIcon.addEventListener('click', toggleHelpDrawer);
    if (helpClose) helpClose.addEventListener('click', toggleHelpDrawer);
    if (helpOverlay) helpOverlay.addEventListener('click', toggleHelpDrawer);

    // Upgrade existing .alert boxes to toasts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.display = 'none'; // hide the static box
        const type = alert.classList.contains('alert-error') ? 'error' : 'success';
        showToast(alert.textContent.trim(), type);
    });
});

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icon = type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';
    
    toast.innerHTML = `
        <i class="ph ${icon}"></i>
        <div class="toast-message" style="font-weight: 500;">${message}</div>
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
</script>
</body>
</html>
