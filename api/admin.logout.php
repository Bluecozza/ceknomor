<?php
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';

admin_logout();
json(['status'=>'ok']);
