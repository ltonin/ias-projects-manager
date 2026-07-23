<?php
declare(strict_types=1);
use App\Support\View;
$base='/projects/'.$project->id.'/participants/'.$participant->id;
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><p class="text-secondary mb-1"><?= View::escape($project->displayTitle()) ?></p><h1><?= View::escape($participant->personName()) ?></h1></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= View::escape($urls->to('/projects/'.$project->id.'/participants')) ?>">All participants</a><?php if($canManage):?><a class="btn btn-primary" href="<?= View::escape($urls->to($base.'/edit')) ?>">Edit</a><?php endif;?></div></div>
<?php if(!$participant->personIsActive||$participant->linkedUserIsActive===false||$project->status!=='active'||!$participant->isActive):?><div class="alert alert-warning">States are independent: project <?= View::escape(strtolower($project->statusLabel())) ?>; participation <?= View::escape(strtolower($participant->activeLabel())) ?>; person <?= $participant->personIsActive?'active':'inactive' ?><?= $participant->linkedUserIsActive===false?'; linked account inactive':'' ?>.</div><?php endif;?>
<div class="card card-body shadow-sm"><dl class="row mb-0">
<?php foreach(['Project'=>$participant->projectName(),'Person'=>$participant->personName(),'Professional position'=>$participant->positionLabel(),'Affiliation'=>$participant->affiliation,'Institutional email'=>$participant->institutionalEmail,'Linked username'=>$participant->linkedUsername,'Project role'=>$participant->roleLabel(),'Participation period'=>$participant->period(),'Participation state'=>$participant->activeLabel()] as $label=>$value):?><dt class="col-md-3"><?= View::escape($label) ?></dt><dd class="col-md-9"><?= View::escape($value??'—') ?></dd><?php endforeach;?>
<?php if($canViewNotes):?><dt class="col-md-3">Internal notes</dt><dd class="col-md-9"><?= nl2br(View::escape($participant->notes??'—')) ?></dd><?php endif;?>
<dt class="col-md-3">Created</dt><dd class="col-md-9"><?= View::escape($participant->createdAt->format('Y-m-d H:i')) ?></dd><dt class="col-md-3">Updated</dt><dd class="col-md-9"><?= View::escape($participant->updatedAt->format('Y-m-d H:i')) ?></dd>
</dl></div>
<p class="text-secondary mt-3">Monthly Person-Month allocations are not configured in this milestone.</p>
<?php if($canManage):?><div class="d-flex gap-2 mt-4">
<form method="post" action="<?= View::escape($urls->to($base.'/'.($participant->isActive?'deactivate':'activate'))) ?>"><input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><button class="btn btn-outline-primary" type="submit"><?= $participant->isActive?'Deactivate':'Activate' ?></button></form>
<a class="btn btn-outline-danger" href="<?= View::escape($urls->to($base.'/remove')) ?>">Remove participation</a>
</div><?php endif;?>
