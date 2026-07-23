<?php

declare(strict_types=1);

use App\Support\View;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Users</h1>
    <a class="btn btn-primary" href="<?= View::escape($urls->to('/admin/users/create')) ?>">Create user</a>
</div>
<div class="table-responsive bg-white border rounded">
    <table class="table table-striped align-middle mb-0">
        <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= View::escape($user->id) ?></td>
                <td><?= View::escape($user->username) ?></td>
                <td><?= View::escape($user->fullName()) ?></td>
                <td><?= View::escape($user->email) ?></td>
                <td><?= View::escape($user->roleLabel()) ?></td>
                <td><span class="badge <?= $user->isActive ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $user->isActive ? 'Active' : 'Inactive' ?></span></td>
                <td><?= View::escape($user->lastLoginAt?->format('Y-m-d H:i') ?? 'Never') ?></td>
                <td><?= View::escape($user->createdAt->format('Y-m-d')) ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="<?= View::escape($urls->to('/admin/users/' . $user->id . '/edit')) ?>">Edit</a>
                        <form method="post" action="<?= View::escape($urls->to('/admin/users/' . $user->id . '/' . ($user->isActive ? 'deactivate' : 'activate'))) ?>">
                            <input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>">
                            <button class="btn btn-sm btn-outline-<?= $user->isActive ? 'danger' : 'success' ?>" type="submit"><?= $user->isActive ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
