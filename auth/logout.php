<?php
session_start();
session_unset();
session_destroy();
header('Location: /task_collab_system/auth/login.php');
exit;
?>

