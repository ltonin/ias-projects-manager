<?php

declare(strict_types=1);

use App\Support\View;

$queryWithoutPage = $filters + ['per_page' => $page->perPage];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h2 mb-1">People</h1><p class="text-secondary mb-0"><?= View::escape($page->total) ?> result<?= $page->total === 1 ? '' : 's' ?></p></div>
    <?php if($canManage):?><a class="btn btn-primary" href="<?= View::escape($urls->to('/admin/people/create')) ?>">Create person</a><?php endif;?>
</div>
<form class="filter-toolbar mb-4" method="get" action="<?= View::escape($urls->to('/admin/people')) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-lg-4"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= View::escape($filters['search']) ?>" placeholder="Name, email, affiliation, or username"></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="active">Status</label><select class="form-select" id="active" name="active"><?php foreach (['active'=>'Active','inactive'=>'Inactive','all'=>'All'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['active']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="internal">Relationship</label><select class="form-select" id="internal" name="internal"><?php foreach (['all'=>'All','internal'=>'Internal','external'=>'External'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['internal']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="position_type">Position</label><select class="form-select" id="position_type" name="position_type"><option value="">All</option><?php foreach ($positionLabels as $value=>$label): ?><option value="<?= View::escape($value) ?>" <?= $filters['position_type']===$value?'selected':'' ?>><?= View::escape($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="linked">Account</label><select class="form-select" id="linked" name="linked"><?php foreach (['all'=>'All','linked'=>'Linked','unlinked'=>'Unlinked'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['linked']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-outline-secondary" href="<?= View::escape($urls->to('/admin/people')) ?>">Reset</a></div>
    </div>
</form>
<div class="table-responsive bg-white border rounded">
    <table class="table table-striped align-middle mb-0">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Affiliation</th><th>Position</th><th>Annual capacity</th><th>Type</th><th>Association</th><th>Status</th><th>Linked user</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($page->items as $person): ?>
            <tr>
                <td><?= View::escape($person->id) ?></td><td><?= View::escape($person->fullName()) ?></td>
                <td><?= View::escape($person->institutionalEmail ?? '—') ?></td><td><?= View::escape($person->affiliation ?? '—') ?></td>
                <td><?= View::escape($person->positionLabel()) ?></td><td><?= View::escape($person->annualCapacityHours) ?> h</td><td><?= View::escape($person->internalLabel()) ?></td>
                <td><?= View::escape($person->associationPeriod()) ?></td>
                <td><?php if($person->isActive):?><span class="badge text-bg-success">Active</span><?php else:?><span class="text-secondary">Inactive</span><?php endif;?></td>
                <td><?= View::escape($person->linkedUsername ?? '—') ?></td>
                <td class="table-actions"><?php if($canManage):?><div class="dropdown"><button class="btn btn-sm btn-quiet dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?= View::escape($person->fullName()) ?>">Actions</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="<?= View::escape($urls->to('/admin/people/'.$person->id.'/edit')) ?>">Edit person</a></li><li><a class="dropdown-item" href="<?= View::escape($urls->to('/people/'.$person->id.'/capacity')) ?>">View capacity</a></li><li><hr class="dropdown-divider"></li><li><form method="post" action="<?= View::escape($urls->to('/admin/people/'.$person->id.'/'.($person->isActive?'deactivate':'activate'))) ?>"><input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><button class="dropdown-item <?= $person->isActive?'text-danger':'' ?>" type="submit"><?= $person->isActive?'Deactivate':'Activate' ?></button></form></li></ul></div><?php endif;?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($page->items === []): ?><tr><td colspan="11" class="text-center text-secondary py-4">No people match these criteria.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($page->pageCount() > 1): ?>
<nav class="mt-4" aria-label="People pages"><ul class="pagination">
    <?php for ($number=1; $number <= $page->pageCount(); $number++): ?><li class="page-item <?= $number===$page->page?'active':'' ?>"><a class="page-link" href="<?= View::escape($urls->to('/admin/people', $queryWithoutPage + ['page'=>$number])) ?>"><?= $number ?></a></li><?php endfor; ?>
</ul></nav>
<?php endif; ?>
