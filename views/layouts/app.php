<?php

declare(strict_types=1);

use App\Support\View;

/** @var App\Support\UrlGenerator $urls */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::escape($title !== '' ? $title . ' · Research Project Manager' : 'Research Project Manager') ?></title>
    <link rel="stylesheet" href="<?= View::escape($urls->asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= View::escape($urls->asset('css/app.css')) ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?= View::escape($urls->to('/')) ?>">Research Project Manager</a>
        <div class="d-flex align-items-center gap-3 text-white">
            <?php if ($currentUser === null): ?>
                <a class="nav-link text-white" href="<?= View::escape($urls->to('/login')) ?>">Login</a>
            <?php else: ?>
                <a class="nav-link text-white" href="<?= View::escape($urls->to('/projects')) ?>">Projects</a>
                <?php if ($currentUser->isAdmin()): ?>
                    <a class="nav-link text-white" href="<?= View::escape($urls->to('/admin/users')) ?>">Users</a>
                    <a class="nav-link text-white" href="<?= View::escape($urls->to('/admin/people')) ?>">People</a>
                <?php endif; ?>
                <span class="small">
                    <span class="d-block"><?= View::escape($currentUser->fullName()) ?></span>
                    <span class="text-white-50"><?= View::escape($currentUser->username) ?></span>
                    <span class="badge text-bg-light"><?= View::escape($currentUser->roleLabel()) ?></span>
                </span>
                <form method="post" action="<?= View::escape($urls->to('/logout')) ?>">
                    <input type="hidden" name="_csrf" value="<?= View::escape($globalCsrfToken) ?>">
                    <button class="btn btn-sm btn-outline-light" type="submit">Logout</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container py-5 flex-grow-1">
    <?php foreach ($flashMessages as $message): ?>
        <div class="alert alert-<?= View::escape($message['type']) ?>" role="alert"><?= View::escape($message['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="border-top bg-white py-3">
    <div class="container text-secondary small">University research project administration foundation</div>
</footer>
<script src="<?= View::escape($urls->asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= View::escape($urls->asset('js/app.js')) ?>"></script>
</body>
</html>
