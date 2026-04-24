<?php
require_once '../includes/session.php';
session_destroy();
header("Location: /casa_gunita/user/login.php");
exit();
?>