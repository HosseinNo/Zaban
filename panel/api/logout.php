<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_post();
$u = current_user();
revoke_current_session();
if ($u) audit('auth.logout', (string)$u['id']);
ok();
