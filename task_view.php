<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$task_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT t.*, p.name AS project_name, u.name AS assignee_name, c.name AS creator_name
     FROM tasks t
     JOIN projects p ON p.id = t.project_id
     LEFT JOIN users u ON u.id = t.assigned_to
     JOIN users c ON c.id = t.created_by
     WHERE t.id = ?"
);
$stmt->bind_param('i', $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) { die('Task not found.'); }
if (!is_project_member($conn, $task['project_id'], $user_id)) {
    die('You do not have access to this task.');
}

// Handle new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if ($comment !== '') {
        $stmt = $conn->prepare('INSERT INTO comments (task_id, user_id, comment) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $task_id, $user_id, $comment);
        $stmt->execute();
        redirect('/task_collab_system/task_view.php?id=' . $task_id);
    }
}

$stmt = $conn->prepare(
    "SELECT c.*, u.name AS author_name FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.task_id = ? ORDER BY c.created_at ASC"
);
$stmt->bind_param('i', $task_id);
$stmt->execute();
$comments = $stmt->get_result();

// Fetch attachments
$stmt = $conn->prepare(
    "SELECT a.*, u.name AS uploader_name FROM task_attachments a
     JOIN users u ON u.id = a.user_id
     WHERE a.task_id = ? ORDER BY a.created_at DESC"
);
$stmt->bind_param('i', $task_id);
$stmt->execute();
$attachments = $stmt->get_result();

$page_title = $task['title'];
require __DIR__ . '/includes/header.php';
?>

<p><a href="project.php?id=<?php echo $task['project_id']; ?>">&larr; Back to <?php echo h($task['project_name']); ?></a></p>

<div class="card">
    <div class="page-header">
        <h1 style="margin:0;"><?php echo h($task['title']); ?></h1>
        <span class="badge" style="background:#EFEFEA; color:#000; padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:600;">
            <?php echo status_label($task['status']); ?>
        </span>
    </div>
    <p><?php echo nl2br(h($task['description'] ?: 'No description provided.')); ?></p>
    <p style="font-size:0.85rem; color:#6B6D80;">
        Assigned to: <?php echo h($task['assignee_name'] ?? 'Unassigned'); ?><br>
        Due date: <?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'None'; ?><br>
        Created by <?php echo h($task['creator_name']); ?> on <?php echo date('M j, Y', strtotime($task['created_at'])); ?>
    </p>
</div>

<div class="card">
    <h2 style="font-size:1.1rem;">Comments</h2>

    <?php if ($comments->num_rows === 0): ?>
        <p style="color:#6B6D80; font-size:0.9rem;">No comments yet. Start the discussion below.</p>
    <?php endif; ?>

    <?php while ($c = $comments->fetch_assoc()): ?>
        <div class="comment">
            <div class="comment-meta"><strong><?php echo h($c['author_name']); ?></strong> &middot; <?php echo date('M j, Y g:ia', strtotime($c['created_at'])); ?></div>
            <div><?php echo nl2br(h($c['comment'])); ?></div>
        </div>
    <?php endwhile; ?>

    <form method="POST" action="task_view.php?id=<?php echo $task_id; ?>" style="margin-top:16px;">
        <div class="form-group">
            <textarea name="comment" placeholder="Write a comment..." required></textarea>
        </div>
        <button type="submit" class="btn">Post Comment</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1.1rem; margin-bottom:16px;">Attachments</h2>
    
    <div id="attachmentList" style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
        <?php if ($attachments->num_rows === 0): ?>
            <p style="color:#6B6D80; font-size:0.9rem;" id="noAttachmentsMsg">No files attached yet.</p>
        <?php endif; ?>
        
        <?php while ($a = $attachments->fetch_assoc()): ?>
            <div style="display:flex; align-items:center; justify-content:space-between; padding:12px; border:1px solid var(--border-color); border-radius:var(--radius-sm); background:var(--bg-color);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <i class="ph ph-file" style="font-size:1.5rem; color:var(--text-muted);"></i>
                    <div>
                        <div style="font-weight:500; color:var(--text-main); font-size:0.95rem;"><?php echo h($a['original_filename']); ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">
                            <?php echo number_format($a['file_size'] / 1024, 1); ?> KB &middot; Uploaded by <?php echo h($a['uploader_name']); ?>
                        </div>
                    </div>
                </div>
                <a href="api/download_attachment.php?id=<?php echo $a['id']; ?>" class="btn" style="padding:6px 12px; font-size:0.85rem; background:white; color:#000; border:1px solid var(--border-color); display:flex; align-items:center; gap:6px; font-weight:600;">
                    <i class="ph ph-download-simple"></i> Download
                </a>
            </div>
        <?php endwhile; ?>
    </div>

    <div style="border-top:1px solid var(--border-color); padding-top:16px;">
        <form id="uploadForm" style="display:flex; gap:12px; align-items:center;">
            <input type="file" id="fileInput" name="attachment" style="display:none;" required>
            <button type="button" class="btn" onclick="document.getElementById('fileInput').click()" style="background:var(--input-bg); color:var(--text-main); border:1px dashed var(--border-color);">
                <i class="ph ph-upload-simple"></i> Select File
            </button>
            <span id="selectedFileName" style="font-size:0.85rem; color:var(--text-muted);">No file selected</span>
            <button type="submit" class="btn" id="uploadBtn" style="display:none;">Upload</button>
        </form>
        <div id="uploadStatus" style="margin-top:8px; font-size:0.85rem; color:var(--text-muted);"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('fileInput');
    const selectedFileName = document.getElementById('selectedFileName');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadForm = document.getElementById('uploadForm');
    const uploadStatus = document.getElementById('uploadStatus');

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            selectedFileName.textContent = fileInput.files[0].name;
            uploadBtn.style.display = 'inline-block';
        } else {
            selectedFileName.textContent = 'No file selected';
            uploadBtn.style.display = 'none';
        }
    });

    uploadForm.addEventListener('submit', (e) => {
        e.preventDefault();
        if (fileInput.files.length === 0) return;

        const formData = new FormData();
        formData.append('attachment', fileInput.files[0]);
        formData.append('task_id', '<?php echo $task_id; ?>');
        formData.append('csrf_token', '<?php echo h($_SESSION['csrf_token']); ?>');

        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading...';
        uploadStatus.textContent = 'Uploading file, please wait...';
        uploadStatus.style.color = 'var(--text-muted)';

        fetch('api/upload_attachment.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                uploadStatus.textContent = 'Upload successful!';
                uploadStatus.style.color = 'green';
                setTimeout(() => window.location.reload(), 1000);
            } else {
                uploadStatus.textContent = 'Error: ' + data.error;
                uploadStatus.style.color = 'red';
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload';
            }
        })
        .catch(err => {
            uploadStatus.textContent = 'A network error occurred.';
            uploadStatus.style.color = 'red';
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload';
        });
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
