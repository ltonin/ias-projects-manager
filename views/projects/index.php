<?php
declare(strict_types=1);
use App\Support\View;
$paginationQuery=$filters+['per_page'=>$page->perPage];
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1">Projects</h1><p class="text-secondary mb-0"><?= $page->total ?> result<?= $page->total===1?'':'s' ?></p></div><?php if($canCreate):?><a class="btn btn-primary" href="<?= View::escape($urls->to('/projects/create')) ?>">Create project</a><?php endif;?></div>
<?php if($missingPerson):?><div class="alert alert-warning"><?= View::escape(App\Auth\ProjectPolicy::MISSING_PERSON_MESSAGE) ?></div><?php endif;?>
<form class="card card-body mb-4" method="get" action="<?= View::escape($urls->to('/projects')) ?>"><div class="row g-3 align-items-end">
<div class="col-lg-4"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= View::escape($filters['search']) ?>"></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All</option><?php foreach($statusLabels as $v=>$l):?><option value="<?= $v ?>" <?= $filters['status']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach;?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="manager_person_id">Manager</label><select class="form-select" id="manager_person_id" name="manager_person_id"><option value="">All</option><?php foreach($managerOptions as $o):?><option value="<?= $o->id ?>" <?= $filters['manager_person_id']===(string)$o->id?'selected':'' ?>><?= View::escape($o->name) ?></option><?php endforeach;?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="funding_agency">Funding agency</label><input class="form-control" id="funding_agency" name="funding_agency" value="<?= View::escape($filters['funding_agency']) ?>"></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="funding_programme">Programme</label><input class="form-control" id="funding_programme" name="funding_programme" value="<?= View::escape($filters['funding_programme']) ?>"></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Apply</button> <a class="btn btn-outline-secondary" href="<?= View::escape($urls->to('/projects')) ?>">Reset</a></div>
</div></form>
<div class="table-responsive bg-white border rounded"><table class="table table-striped align-middle mb-0"><thead><tr><th>Acronym</th><th>Title</th><th>Status</th><th>Funding</th><th>Period</th><th>Responsible person</th><th>Budget</th><th>Actions</th></tr></thead><tbody>
<?php foreach($page->items as $project):?><tr><td><a href="<?= View::escape($urls->to('/projects/'.$project->id)) ?>"><?= View::escape($project->acronym) ?></a></td><td><?= View::escape($project->title) ?></td><td><?= View::escape($project->statusLabel()) ?></td><td><?= View::escape(implode(' — ',array_filter([$project->fundingAgency,$project->fundingProgramme]))) ?: '—' ?></td><td><?= View::escape($project->period()) ?></td><td><?= View::escape($project->managerName??'Unassigned') ?></td><td><?= View::escape($project->formattedBudget()) ?></td><td><?php if($policy->canEdit($currentUser,$person,$project)):?><a class="btn btn-sm btn-outline-primary" href="<?= View::escape($urls->to('/projects/'.$project->id.'/edit')) ?>">Edit</a><?php endif;?></td></tr><?php endforeach;?>
<?php if($page->items===[]):?><tr><td colspan="8" class="text-center text-secondary py-4">No projects match these criteria.</td></tr><?php endif;?>
</tbody></table></div>
<?php if($page->pageCount()>1):?><nav class="mt-4"><ul class="pagination"><?php for($n=1;$n<=$page->pageCount();$n++):?><li class="page-item <?= $n===$page->page?'active':'' ?>"><a class="page-link" href="<?= View::escape($urls->to('/projects',$paginationQuery+['page'=>$n])) ?>"><?= $n ?></a></li><?php endfor;?></ul></nav><?php endif;?>
