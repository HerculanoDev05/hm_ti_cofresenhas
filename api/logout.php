<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::logout();
jsonResponse(['ok' => true]);
