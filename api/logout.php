<?php
/**
 * api/logout.php
 * POST /api/logout.php
 * Clears the session and logs the user out.
 */

require_once '../config/cors.php';
session_start();
session_destroy();
respond(['success' => true, 'message' => 'Logged out successfully.']);
