<?php
declare(strict_types=1);
use App\Support\View;
$base='/projects/'.$project->id.'/participants';
$pagination=$filters+['per_page'=>$page->perPage];
?>
<div class="d-flex justify-content-between align-items-start mb-4">
  <div><p class="text-secondary mb-1"><?= View::escape($project->displayTitle()) ?></p><h1 class="h2 mb-1">Project participants</h1><p class="text-secondary mb-0"><?= $page->total ?> result<?= $page->total===1?'':'s' ?></p></div>
  <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= View::escape($urls->to('/projects/'.$project->id)) ?>">Project details</a><?php if($canManage):?><a class="btn btn-primary" href="<?= View::escape($urls->to($base.'/create')) ?>">Add participant</a><?php endif;?></div>
</div>
<?php if($project->status!=='active'):?><div class="alert alert-warning">This project is <?= View::escape(strtolower($project->statusLabel())) ?>. Participation states are managed independently.</div><?php endif;?>
<form class="card card-body mb-4" method="get" action="<?= View::escape($urls->to($base)) ?>"><div class="row g-3 align-items-end">
  <div class="col-lg-4"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= View::escape($filters['search']) ?>"></div>
  <div class="col-sm-6 col-lg-2"><label class="form-label" for="active">Participation</label><select class="form-select" id="active" name="active"><?php foreach(['all'=>'All','active'=>'Active','inactive'=>'Inactive'] as $v=>$l):?><option value="<?= $v ?>" <?= $filters['active']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach;?></select></div>
  <div class="col-sm-6 col-lg-2"><label class="form-label" for="project_role">Project role</label><select class="form-select" id="project_role" name="project_role"><option value="">All</option><?php foreach($roleLabels as $v=>$l):?><option value="<?= $v ?>" <?= $filters['project_role']===$v?'selected':'' ?>><?= View::escape($l) ?></option><?php endforeach;?></select></div>
  <div class="col-sm-6 col-lg-2"><label class="form-label" for="internal">Person type</label><select class="form-select" id="internal" name="internal"><?php foreach(['all'=>'All','internal'=>'Internal','external'=>'External'] as $v=>$l):?><option value="<?= $v ?>" <?= $filters['internal']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach;?></select></div>
  <div class="col-sm-6 col-lg-2"><label class="form-label" for="person_active">Person state</label><select class="form-select" id="person_active" name="person_active"><?php foreach(['all'=>'All','active'=>'Active','inactive'=>'Inactive'] as $v=>$l):?><option value="<?= $v ?>" <?= $filters['person_active']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach;?></select></div>
  <div class="col-12"><button class="btn btn-primary" type="submit">Apply</button> <a class="btn btn-outline-secondary" href="<?= View::escape($urls->to($base)) ?>">Reset</a></div>
</div></form>
<div class="table-responsive bg-white border rounded"><table class="table table-striped align-middle mb-0"><thead><tr><th>Person</th><th>Position</th><th>Affiliation</th><th>Project role</th><th>Period</th><th>States</th><th>Account</th><th>Actions</th></tr></thead><tbody>
<?php foreach($page->items as $participant):?><tr>
  <td><a href="<?= View::escape($urls->to($base.'/'.$participant->id)) ?>"><?= View::escape($participant->personName()) ?></a><br><small class="text-secondary"><?= View::escape($participant->institutionalEmail??'No institutional email') ?></small></td>
  <td><?= View::escape($participant->positionLabel()) ?></td><td><?= View::escape($participant->affiliation??'—') ?></td><td><?= View::escape($participant->roleLabel()) ?></td><td><?= View::escape($participant->period()) ?></td>
  <td><span class="badge <?= $participant->isActive?'text-bg-success':'text-bg-secondary' ?>"><?= View::escape($participant->activeLabel()) ?></span><?php if(!$participant->personIsActive):?> <span class="badge text-bg-warning">Person inactive</span><?php endif;?></td>
  <td><?= View::escape($participant->linkedUsername??'Not linked') ?><?php if($participant->linkedUserIsActive===false):?><br><small class="text-danger">Account inactive</small><?php endif;?></td>
  <td><?php if($canManage):?><a class="btn btn-sm btn-outline-primary" href="<?= View::escape($urls->to($base.'/'.$participant->id.'/edit')) ?>">Edit</a><?php endif;?></td>
</tr><?php endforeach;?>
<?php if($page->items===[]):?><tr><td colspan="8" class="text-center text-secondary py-4">No participants match these criteria.</td></tr><?php endif;?>
</tbody></table></div>
<?php if($page->pageCount()>1):?><nav class="mt-4" aria-label="Participant pages"><ul class="pagination"><?php for($n=1;$n<=$page->pageCount();$n++):?><li class="page-item <?= $n===$page->page?'active':'' ?>"><a class="page-link" href="<?= View::escape($urls->to($base,$pagination+['page'=>$n])) ?>"><?= $n ?></a></li><?php endfor;?></ul></nav><?php endif;?>
