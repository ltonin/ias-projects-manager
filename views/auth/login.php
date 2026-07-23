<?php

declare(strict_types=1);

use App\Support\View;
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-4">Login</h1>
                <?php if (isset($errors['credentials'])): ?>
                    <div class="alert alert-danger" role="alert"><?= View::escape($errors['credentials']) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= View::escape($urls->to('/login')) ?>" novalidate>
                    <input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>">
                    <input type="hidden" name="redirect" value="<?= View::escape($redirect) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="identifier">Email or username</label>
                        <input class="form-control<?= isset($errors['credentials']) ? ' is-invalid' : '' ?>" id="identifier" name="identifier" value="<?= View::escape($identifier) ?>" maxlength="254" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" maxlength="4096" autocomplete="current-password" required>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= View::escape($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
