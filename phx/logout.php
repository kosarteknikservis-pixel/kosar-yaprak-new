<?php
require_once dirname(__DIR__) . '/includes/admin_session.php';

admin_session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit;
