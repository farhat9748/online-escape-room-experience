
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/function.php';
require_once __DIR__ . '/../controller/roomcontroller.php';

if (!is_logged_in()) {
    redirect('login.php?redirect=index.php');
}

$controller = new \App\Controllers\RoomController();
$controller->lobby();
exit;
