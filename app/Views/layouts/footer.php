<?php
/**
 * views/layouts/footer.php — ROUTER
 * Matches the header router above.
 */
if (isLoggedIn() && isAdmin()) {
    require_once __DIR__ . '/footer_admin.php';
} else {
    require_once __DIR__ . '/footer_frontend.php';
}
