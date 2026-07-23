<?php

declare(strict_types=1);

use App\Support\View;

$isEdit = $mode === 'edit';
$action = $isEdit ? '/admin/users/' . $user->id : '/admin/users';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0"><?= View::escape($title) ?></h1>
            <a href="<?= View::escape($urls->to('/admin/users')) ?>">Back to users</a>
        </div>
        <?php if (isset($errors['safety'])): ?><div class="alert alert-danger" role="alert"><?= View::escape($errors['safety']) ?></div><?php endif; ?>
        <form class="card card-body shadow-sm" method="post" action="<?= View::escape($urls->to($action)) ?>" novalidate>
            <input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="username" name="username" value="<?= View::escape($values['username'] ?? '') ?>" minlength="3" maxlength="50" required>
                    <div class="form-text">3–50 lowercase letters, numbers, dots, underscores, or hyphens; must start and end with a letter or number.</div>
                    <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= View::escape($errors['username']) ?></div><?php endif; ?>
                </div>
                <?php foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="<?= $field ?>"><?= $label ?></label>
                        <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= $field ?>" name="<?= $field ?>" value="<?= View::escape($values[$field] ?? '') ?>" maxlength="100" required>
                        <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= View::escape($errors[$field]) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-8">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" value="<?= View::escape($values['email'] ?? '') ?>" maxlength="254" required>
                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= View::escape($errors['email']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select<?= isset($errors['role']) ? ' is-invalid' : '' ?>" id="role" name="role" required>
                        <?php foreach ($roles as $role): ?><option value="<?= View::escape($role) ?>" <?= ($values['role'] ?? '') === $role ? 'selected' : '' ?>><?= View::escape(ucfirst($role)) ?></option><?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['role'])): ?><div class="invalid-feedback"><?= View::escape($errors['role']) ?></div><?php endif; ?>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" <?= ($values['is_active'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active account</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password"><?= $isEdit ? 'New password (optional)' : 'Initial password' ?></label>
                    <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" maxlength="4096" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= View::escape($errors['password']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" maxlength="4096" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                    <?php if (isset($errors['password_confirmation'])): ?><div class="invalid-feedback"><?= View::escape($errors['password_confirmation']) ?></div><?php endif; ?>
                </div>
            </div>
            <button class="btn btn-primary mt-4 align-self-start" type="submit"><?= $isEdit ? 'Save changes' : 'Create user' ?></button>
        </form>
    </div>
</div>
