<?php
require __DIR__ . '/../helpers/auth.php';
session_destroy();
header('Location: /login.php');
exit;