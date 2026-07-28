<?php

require 'db.php';
require_once __DIR__ . '/includes/social_click.php';

$channel = (string) ($_GET['c'] ?? '');
social_click_redirect_for($pdo, $channel);
