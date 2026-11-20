<?php
/**
 * Logout the current customer session.
 */
require_once __DIR__ . '/config/config.php';

unset($_SESSION['user_id']);
session_regenerate_id(true);
flash('success', 'You have been logged out successfully.');
redirect('index.php');
