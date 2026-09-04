<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$page_title = 'Settings';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Settings</h1>
</div>

<div style="display:flex; gap:32px; align-items:flex-start;">
    
    <!-- Left Navigation -->
    <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
        <div class="card" style="padding:16px; border-left:3px solid var(--primary); border-radius:0 var(--radius-md) var(--radius-md) 0; font-weight:600; color:var(--primary);">
            General Profile
        </div>
        <div class="card" style="padding:16px; color:var(--text-muted); cursor:pointer;">
            Notifications
        </div>
        <div class="card" style="padding:16px; color:var(--text-muted); cursor:pointer;">
            Appearance & Theme
        </div>
        <div class="card" style="padding:16px; color:var(--text-muted); cursor:pointer;">
            Security
        </div>
    </div>

    <!-- Right Content -->
    <div class="card" style="flex:3;">
        <h2 style="font-size:1.25rem; margin-bottom:24px;">General Profile Settings</h2>
        
        <form onsubmit="event.preventDefault(); alert('Settings saved successfully!');">
            <div class="form-group">
                <label>Avatar</label>
                <div style="display:flex; align-items:center; gap:16px;">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=random" style="width:64px; height:64px; border-radius:50%;">
                    <button type="button" class="btn btn-outline">Change Avatar</button>
                </div>
            </div>
            
            <div style="display:flex; gap:24px;">
                <div class="form-group" style="flex:1;">
                    <label>Full Name</label>
                    <input type="text" value="<?php echo h($_SESSION['user_name'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Role</label>
                    <input type="text" value="<?php echo is_admin() ? 'Administrator' : 'Team Member'; ?>" disabled style="background:var(--bg-color); cursor:not-allowed;">
                </div>
            </div>

            <div class="form-group">
                <label>Email Preference</label>
                <select style="width:100%; padding:10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                    <option>Send me a digest every week</option>
                    <option>Send me all notifications instantly</option>
                    <option>Do not email me</option>
                </select>
            </div>

            <div class="form-group" style="margin-top:24px; padding-top:24px; border-top:1px dashed var(--border-color);">
                <label style="display:flex; align-items:center; gap:12px; font-weight:500; cursor:pointer;">
                    <input type="checkbox" checked style="width:auto; transform:scale(1.2);">
                    Enable Dark Mode Beta
                </label>
                <p style="margin-left:26px; font-size:0.85rem; color:var(--text-muted); margin-top:4px;">Experience the application in a sleek dark theme.</p>
            </div>

            <div style="margin-top:32px; display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn btn-outline">Discard</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
