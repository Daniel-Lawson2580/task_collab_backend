<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$project_id = (int)($_GET['id'] ?? 0);

if (!is_project_member($conn, $project_id, $user_id)) {
    die('You do not have access to this project.');
}

$stmt = $conn->prepare('SELECT p.*, u.name AS owner_name FROM projects p JOIN users u ON u.id = p.owner_id WHERE p.id = ? AND p.deleted_at IS NULL');
$stmt->bind_param('i', $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    die('Project not found.');
}

// Members list (for assigning tasks)
$stmt = $conn->prepare(
    "SELECT u.id, u.name FROM users u
     JOIN project_members pm ON pm.user_id = u.id
     WHERE pm.project_id = ? ORDER BY u.name"
);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Parse GET parameters for board controls
$tab = $_GET['tab'] ?? 'all';
$sort = $_GET['sort'] ?? 'asc';
$filter = $_GET['filter'] ?? 'all';
$view = $_GET['view'] ?? 'board';

$where_clause = "t.project_id = ? AND t.deleted_at IS NULL";
$params = [$project_id];
$types = "i";

if ($tab === 'mine') {
    $where_clause .= " AND t.assigned_to = ?";
    $params[] = $user_id;
    $types .= "i";
} elseif ($tab === 'looked') {
    $where_clause .= " AND t.created_by = ?";
    $params[] = $user_id;
    $types .= "i";
} elseif ($tab === 'closing') {
    $where_clause .= " AND t.status IN ('in_review', 'done')";
}

if ($filter === 'overdue') {
    $where_clause .= " AND t.due_date < CURDATE() AND t.status != 'done'";
}

$order_by = "t.due_date IS NULL, t.due_date ASC, t.created_at DESC";
if ($sort === 'desc') {
    $order_by = "t.due_date IS NULL, t.due_date DESC, t.created_at DESC";
}

// Tasks grouped by status
$stmt = $conn->prepare(
    "SELECT t.*, u.name AS assignee_name,
     (SELECT COUNT(*) FROM comments c WHERE c.task_id = t.id) AS comment_count,
     (SELECT COUNT(*) FROM task_attachments a WHERE a.task_id = t.id) AS attachment_count
     FROM tasks t
     LEFT JOIN users u ON u.id = t.assigned_to
     WHERE $where_clause
     ORDER BY $order_by"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$columns = ['todo' => [], 'in_progress' => [], 'in_review' => [], 'done' => []];
while ($task = $result->fetch_assoc()) {
    $columns[$task['status']][] = $task;
}

$page_title = $project['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="project-header-top">
    <div class="project-title-area">
        <h1>Tasks Management</h1>
        <p>Organizing, prioritizing, and tracking tasks.</p>
    </div>
    <div class="project-actions">
        <a href="task_add.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary"><i class="ph ph-plus"></i> Create New Tasks</a>
        <button class="btn btn-outline"><i class="ph ph-dots-three"></i> Action</button>
    </div>
</div>

<?php
function build_url($updates) {
    $params = array_merge($_GET, $updates);
    return '?' . http_build_query($params);
}
?>
<div class="board-controls">
    <div class="tabs">
        <a href="<?php echo build_url(['tab'=>'all']); ?>" class="tab <?php echo $tab === 'all' ? 'active' : ''; ?>">All Tasks</a>
        <a href="<?php echo build_url(['tab'=>'mine']); ?>" class="tab <?php echo $tab === 'mine' ? 'active' : ''; ?>">My Tasks</a>
        <a href="<?php echo build_url(['tab'=>'looked']); ?>" class="tab <?php echo $tab === 'looked' ? 'active' : ''; ?>">Looked Tasks</a>
        <a href="<?php echo build_url(['tab'=>'closing']); ?>" class="tab <?php echo $tab === 'closing' ? 'active' : ''; ?>">Closing Tasks</a>
    </div>
    <div class="filters">
        <a href="<?php echo build_url(['sort'=> $sort==='asc'?'desc':'asc']); ?>" class="filter-btn" style="text-decoration:none;"><i class="ph ph-arrows-down-up"></i> Sort</a>
        <a href="<?php echo build_url(['filter'=> $filter==='all'?'overdue':'all']); ?>" class="filter-btn" style="text-decoration:none; <?php echo $filter==='overdue' ? 'color:var(--primary);' : ''; ?>"><i class="ph ph-funnel"></i> <?php echo $filter==='overdue' ? 'Overdue' : 'Filter'; ?></a>
        <div class="view-toggles">
            <a href="<?php echo build_url(['view'=>'board']); ?>" class="view-toggle <?php echo $view === 'board' ? 'active' : ''; ?>"><i class="ph ph-list-dashes"></i></a>
            <a href="<?php echo build_url(['view'=>'list']); ?>" class="view-toggle <?php echo $view === 'list' ? 'active' : ''; ?>"><i class="ph ph-squares-four"></i></a>
        </div>
    </div>
</div>

<div class="board <?php echo $view === 'list' ? 'board-list' : ''; ?>">
    <?php
    $column_defs = [
        'todo'        => ['label' => 'To Do List', 'dot' => 'dot-todo'],
        'in_progress' => ['label' => 'In Progress List', 'dot' => 'dot-progress'],
        'in_review'   => ['label' => 'In Review', 'dot' => 'dot-review'],
        'done'        => ['label' => 'Completed', 'dot' => 'dot-done'],
    ];

    // Helper for random badges to make it look like the image
    $random_badges = [
        ['Normal', 'Website'],
        ['High', 'Web Application'],
        ['Premium', 'Mobile App'],
        ['High', 'Mobile App']
    ];

    foreach ($column_defs as $status => $def):
    ?>
        <div class="column">
            <div class="column-title">
                <div class="col-title-left">
                    <?php echo $def['label']; ?> <span class="badge">(<?php echo str_pad(count($columns[$status]), 2, '0', STR_PAD_LEFT); ?>)</span>
                </div>
                <i class="ph ph-plus column-add"></i>
            </div>

            <?php if (empty($columns[$status])): ?>
                <!-- <p style="font-size:0.82rem; color:#9A9AA8; text-align:center;">No tasks</p> -->
            <?php endif; ?>

            <div class="task-list" data-status="<?php echo $status; ?>">
                <?php foreach ($columns[$status] as $index => $task):
                    $is_overdue = $task['due_date'] && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'done';
                    $badges = $random_badges[($task['id'] + $index) % count($random_badges)];
                ?>
                    <div class="task-card">
                        <div class="task-tags">
                            <span class="tag tag-<?php echo strtolower($badges[0]); ?>"><?php echo $badges[0]; ?></span>
                            <span class="tag tag-<?php echo strtolower(str_replace(' ', '-', $badges[1])); ?>"><?php echo $badges[1]; ?></span>
                            <div class="task-menu">
                                <form method="POST" action="api/task_update_status.php" class="status-form">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                    <?php echo csrf_field(); ?>
                                    <select name="status" onchange="updateTaskStatus(this)" class="status-select" title="Change Status">
                                        <option value="todo" <?php echo $task['status'] === 'todo' ? 'selected' : ''; ?>>To Do</option>
                                        <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="in_review" <?php echo $task['status'] === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                                        <option value="done" <?php echo $task['status'] === 'done' ? 'selected' : ''; ?>>Done</option>
                                    </select>
                                </form>
                            </div>
                        </div>

                        <h4><a href="task_view.php?id=<?php echo $task['id']; ?>"><?php echo h($task['title']); ?></a></h4>
                        
                        <?php
                        $date_class = 'date-badge-safe';
                        if ($task['due_date']) {
                            $due = strtotime($task['due_date']);
                            $now = time();
                            $diff = $due - $now;
                            if ($diff < 0 && $task['status'] !== 'done') {
                                $date_class = 'date-badge-urgent';
                            } elseif ($diff < (2 * 86400) && $task['status'] !== 'done') {
                                $date_class = 'date-badge-warning';
                            }
                        }
                        ?>
                        <div style="margin: 12px 0;">
                            <?php if ($task['due_date']): ?>
                                <span class="date-badge <?php echo $date_class; ?>">
                                    <i class="ph ph-clock"></i> <?php echo date('d M Y', strtotime($task['due_date'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($status === 'in_progress' || $status === 'in_review'): ?>
                        <div class="task-progress">
                            <div class="progress-bar"><div class="progress-fill fill-<?php echo $status; ?>" style="width: <?php echo rand(40, 80); ?>%;"></div></div>
                        </div>
                        <?php elseif ($status === 'todo'): ?>
                        <div class="task-progress">
                            <div class="progress-bar"><div class="progress-fill fill-todo" style="width: 10%;"></div></div>
                        </div>
                        <?php elseif ($status === 'done'): ?>
                        <div class="task-progress">
                            <div class="progress-bar"><div class="progress-fill fill-done" style="width: 100%;"></div></div>
                        </div>
                        <?php endif; ?>

                        <div class="task-footer" style="display:flex; justify-content:space-between; align-items:center;">
                            <div class="task-metrics" style="display:flex; gap:12px; color:var(--text-muted); font-size:0.85rem; font-weight:500;">
                                <span style="display:flex; align-items:center; gap:4px;">
                                    <i class="ph ph-chat-centered-text"></i> <?php echo $task['comment_count']; ?> Comments
                                </span>
                                <span style="display:flex; align-items:center; gap:4px;">
                                    <i class="ph ph-link"></i> <?php echo $task['attachment_count']; ?> Files
                                </span>
                            </div>
                            <div class="task-avatars">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($task['assignee_name'] ?: 'Unassigned'); ?>&background=random" class="avatar" title="<?php echo h($task['assignee_name'] ?: 'Unassigned'); ?>">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="task_add.php?project_id=<?php echo $project_id; ?>&status=<?php echo $status; ?>" class="add-card-inline">
                <span>Add Card</span>
                <i class="ph ph-plus"></i>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<script>
function updateTaskStatus(selectElement) {
    const form = selectElement.closest('form');
    const formData = new FormData(form);
    
    // Add ajax flag just in case
    formData.append('ajax', '1');
    
    selectElement.disabled = true;

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Move the card to the new column
            const taskCard = form.closest('.task-card');
            const targetList = document.querySelector(`.task-list[data-status="${data.new_status}"]`);
            if (targetList && taskCard) {
                // Optional: apply a smooth transition or simply append
                targetList.appendChild(taskCard);
                
                // Update badge counts (optional enhancement, skip for now or implement simply)
            }
            selectElement.disabled = false;
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'error');
            selectElement.disabled = false;
            // Optionally revert the select value if failed
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        showToast('Failed to update task.', 'error');
        selectElement.disabled = false;
    });
}
</script>

<!-- Floating Chat Widget -->
<div class="chat-widget-container" id="chatWidgetContainer">
    <!-- Chat Icon Button -->
    <button class="chat-toggle-btn" id="chatToggleBtn" title="Project Chat" style="position: relative;">
        <i class="ph ph-chat-centered-dots"></i>
        <span id="chatUnreadBadge" style="display:none; position:absolute; top:-4px; right:-4px; background:#E53E5D; color:white; font-size:0.75rem; font-weight:700; border-radius:50%; min-width:20px; height:20px; display:none; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.2);">0</span>
    </button>
    
    <!-- Chat Window -->
    <div class="chat-window" id="chatWindow" style="display: none;">
        <div class="chat-header">
            <h3>Team Chat</h3>
            <button class="chat-close-btn" id="chatCloseBtn"><i class="ph ph-x"></i></button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <!-- Messages will be injected here via JS -->
        </div>
        <form class="chat-input-area" id="chatForm">
            <input type="text" id="chatInput" placeholder="Type a message..." required autocomplete="off">
            <button type="submit" class="btn btn-primary"><i class="ph ph-paper-plane-right"></i></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chatToggleBtn = document.getElementById('chatToggleBtn');
    const chatCloseBtn = document.getElementById('chatCloseBtn');
    const chatWindow = document.getElementById('chatWindow');
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    
    let isChatOpen = false;
    let lastMessageId = 0;
    let unreadChatCount = 0;
    let isInitialLoad = true;
    const projectId = <?php echo $project_id; ?>;
    const csrfToken = "<?php echo h($_SESSION['csrf_token']); ?>";
    const chatUnreadBadge = document.getElementById('chatUnreadBadge');
    
    // Toggle chat window
    function toggleChat() {
        isChatOpen = !isChatOpen;
        if (isChatOpen) {
            chatWindow.style.display = 'flex';
            chatToggleBtn.style.display = 'none';
            unreadChatCount = 0;
            chatUnreadBadge.style.display = 'none';
            fetchMessages(); // Fetch immediately when opened
            scrollToBottom();
            chatInput.focus();
        } else {
            chatWindow.style.display = 'none';
            chatToggleBtn.style.display = 'flex';
        }
    }
    
    chatToggleBtn.addEventListener('click', toggleChat);
    chatCloseBtn.addEventListener('click', toggleChat);
    
    // Send message
    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const msg = chatInput.value.trim();
        if (!msg) return;
        
        chatInput.value = ''; // clear input early for better UX
        
        fetch('api/chat_send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: projectId,
                message: msg,
                csrf_token: csrfToken
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchMessages();
            } else {
                console.error('Failed to send message:', data.error);
            }
        })
        .catch(err => console.error('Error sending message:', err));
    });
    
    // Fetch messages
    function fetchMessages() {
        fetch(`api/chat_fetch.php?project_id=${projectId}&last_id=${lastMessageId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    let html = '';
                    let newMessagesCount = 0;
                    data.messages.forEach(m => {
                        const msgClass = m.is_mine ? 'msg-mine' : 'msg-other';
                        const author = m.is_mine ? '' : `<div class="msg-author">${escapeHtml(m.name)}</div>`;
                        
                        html += `
                            <div class="chat-msg ${msgClass}">
                                ${author}
                                <div class="msg-bubble">${escapeHtml(m.message)}</div>
                                <div class="msg-time">${m.time}</div>
                            </div>
                        `;
                        lastMessageId = Math.max(lastMessageId, m.id);
                        if (!m.is_mine) newMessagesCount++;
                    });
                    
                    const wasAtBottom = (chatMessages.scrollHeight - chatMessages.scrollTop) <= chatMessages.clientHeight + 50;
                    
                    chatMessages.insertAdjacentHTML('beforeend', html);
                    
                    if (isChatOpen) {
                        if (wasAtBottom) scrollToBottom();
                    } else if (!isInitialLoad && newMessagesCount > 0) {
                        unreadChatCount += newMessagesCount;
                        chatUnreadBadge.textContent = unreadChatCount;
                        chatUnreadBadge.style.display = 'flex';
                    }
                }
                isInitialLoad = false;
            })
            .catch(err => {
                console.error('Error fetching messages:', err);
                isInitialLoad = false;
            });
    }

    // Fetch immediately on page load to sync lastMessageId silently
    fetchMessages();
    

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function escapeHtml(unsafe) {
        return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    
    // Poll every 3 seconds
    setInterval(fetchMessages, 3000);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
