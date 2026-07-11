<?php
require_once __DIR__ . '/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf(); $_SESSION = []; session_destroy(); header('Clear-Site-Data: "cache", "cookies", "storage"'); redirect('index.php');
