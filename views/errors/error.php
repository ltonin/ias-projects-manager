<?php

declare(strict_types=1);

use App\Support\View;
?>
<div class="text-center py-5">
    <p class="display-1 fw-semibold text-secondary"><?= View::escape($status) ?></p>
    <h1><?= View::escape($title) ?></h1>
    <p class="text-secondary"><?= View::escape($message) ?></p>
    <a class="btn btn-primary" href="<?= View::escape($urls->to('/')) ?>">Return home</a>
</div>
