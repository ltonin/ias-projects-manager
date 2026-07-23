<?php

declare(strict_types=1);

use App\Support\View;
?>
<section class="p-4 p-md-5 bg-white border rounded-3 shadow-sm">
    <span class="badge text-bg-success mb-3">Installed</span>
    <h1 class="display-6"><?= View::escape($appName) ?></h1>
    <p class="lead">The technical foundation is running. Project-management features have not been implemented yet.</p>
    <dl class="row mb-4">
        <dt class="col-sm-3">Environment</dt>
        <dd class="col-sm-9"><code><?= View::escape($environment) ?></code></dd>
        <dt class="col-sm-3">Health endpoint</dt>
        <dd class="col-sm-9"><a href="<?= View::escape($urls->to('/health')) ?>"><?= View::escape($urls->to('/health')) ?></a></dd>
    </dl>
    <?php if ($environment !== 'production'): ?>
        <form method="post" action="<?= View::escape($csrfTestUrl) ?>">
            <input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>">
            <button class="btn btn-outline-primary" type="submit">Test CSRF protection</button>
        </form>
    <?php endif; ?>
</section>
