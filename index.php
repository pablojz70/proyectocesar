<?php
require_once 'config/database.php';
if (isset($_SESSION['user_id'])) {
    redirect('/dashboard.php');
}
redirect('/login.php');
