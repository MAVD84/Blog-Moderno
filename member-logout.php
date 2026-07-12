<?php
require_once __DIR__ . '/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/'); }
unset($_SESSION['member_id']);
session_regenerate_id(true);
flash('Sesión cerrada.');
redirect('/');
