<?php
/**
 * views/layouts/header.php — ROUTER
 * Automatically selects the correct layout based on user role.
 *   Admin    → header_admin.php   (sidebar, dark nav)
 *   Alumni   → header_frontend.php (topnav, job board)
 *   Company  → header_frontend.php (topnav, job board)
 *   Guest    → header_frontend.php (topnav, minimal)
 */
if (isLoggedIn() && isAdmin()) {
    require_once __DIR__ . '/header_admin.php';
} else {
    require_once __DIR__ . '/header_frontend.php';
}
