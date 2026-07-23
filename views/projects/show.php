<?php
declare(strict_types=1);
use App\Support\View;
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><p class="text-secondary mb-1"><?= View::escape($project->statusLabel()) ?></p><h1><?= View::escape($project->displayTitle()) ?></h1></div><div class="d-flex gap-2"><?php if($canEdit):?><a class="btn btn-primary" href="<?= View::escape($urls->to('/projects/'.$project->id.'/edit')) ?>">Edit</a><?php endif;?></div></div>
<div class="card card-body shadow-sm"><dl class="row mb-0">
<?php foreach([
'Description'=>$project->description,'Internal code'=>$project->internalCode,'Grant agreement'=>$project->grantAgreementNumber,
'Funding agency'=>$project->fundingAgency,'Funding programme'=>$project->fundingProgramme,'Coordinator'=>$project->coordinatorOrganization,
'Responsible person'=>$project->managerName,'Project period'=>$project->period(),'Budget'=>$project->formattedBudget()
] as $label=>$value):?><dt class="col-md-3"><?= View::escape($label) ?></dt><dd class="col-md-9"><?= View::escape($value??'—') ?></dd><?php endforeach;?>
<?php if($project->websiteUrl!==null):?><dt class="col-md-3">Website</dt><dd class="col-md-9"><a href="<?= View::escape($project->websiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= View::escape($project->websiteUrl) ?></a></dd><?php endif;?>
<?php if($canViewNotes):?><dt class="col-md-3">Internal notes</dt><dd class="col-md-9"><?= nl2br(View::escape($project->notes??'—')) ?></dd><?php endif;?>
<dt class="col-md-3">Created</dt><dd class="col-md-9"><?= View::escape($project->createdAt->format('Y-m-d H:i')) ?></dd><dt class="col-md-3">Updated</dt><dd class="col-md-9"><?= View::escape($project->updatedAt->format('Y-m-d H:i')) ?></dd>
</dl></div>
<?php if($canStatus):?><form class="card card-body mt-4" method="post" action="<?= View::escape($urls->to('/projects/'.$project->id.'/status')) ?>"><input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><div class="row align-items-end"><div class="col-sm-5"><label class="form-label" for="status">Change status</label><select class="form-select" id="status" name="status"><?php foreach($statusLabels as $v=>$l):?><option value="<?= $v ?>" <?= $project->status===$v?'selected':'' ?>><?= $l ?></option><?php endforeach;?></select></div><div class="col"><button class="btn btn-outline-primary" type="submit">Update status</button></div></div></form><?php endif;?>
