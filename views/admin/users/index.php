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
                <td><?php if($user->isActive):?><span class="badge text-bg-success">Active</span><?php else:?><span class="text-secondary">Inactive</span><?php endif;?></td>
                <td><?= View::escape($user->lastLoginAt?->format('Y-m-d H:i') ?? 'Never') ?></td>
                <td><?= View::escape($user->createdAt->format('Y-m-d')) ?></td>
                <td class="table-actions"><div class="dropdown"><button class="btn btn-sm btn-quiet dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?= View::escape($user->username) ?>">Actions</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="<?= View::escape($urls->to('/admin/users/' . $user->id . '/edit')) ?>">Edit user</a></li><li><hr class="dropdown-divider"></li><li><form method="post" action="<?= View::escape($urls->to('/admin/users/' . $user->id . '/' . ($user->isActive ? 'deactivate' : 'activate'))) ?>"><input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><button class="dropdown-item <?= $user->isActive?'text-danger':'' ?>" type="submit"><?= $user->isActive ? 'Deactivate' : 'Activate' ?></button></form></li></ul></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
