<?php

declare(strict_types=1);

/** @var \App\Http\Response $response */
$response = require dirname(__DIR__) . '/bootstrap/app.php';
$response->send();
