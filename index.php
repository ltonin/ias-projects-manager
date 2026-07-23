<?php
declare(strict_types=1);
/** Shared-hosting front controller. */
$response=require __DIR__.'/bootstrap/app.php';
$response->send();
