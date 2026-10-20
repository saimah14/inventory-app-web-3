<?php
session_start();
header('Location: ' . (isset($_SESSION['user']) ? 'dashboard.php' : 'login.php'));
exit;