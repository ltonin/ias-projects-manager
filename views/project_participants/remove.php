<?php
declare(strict_types=1);
use App\Support\View;
$hasAllocations=$hasAllocations??false;$responsibleWorkPackageCount=$responsibleWorkPackageCount??0;
$base='/projects/'.$project->id.'/participants';
?>
<div class="row justify-content-center"><div class="col-lg-7"><h1 class="h2">Remove participant</h1><div class="alert alert-danger">This removes only the participation relationship. It does not remove the person, linked account, project, or responsible project manager.</div>
<?php if($hasAllocations):?><div class="alert alert-warning">This participant has historical person-hour allocations and cannot be removed. Deactivate the participation instead.</div><?php endif;?>
<?php if($responsibleWorkPackageCount>0):?><div class="alert alert-warning">This participant is responsible for <?= $responsibleWorkPackageCount ?> Work Package<?= $responsibleWorkPackageCount===1?'':'s' ?> and cannot be removed. Assign another responsible participant, clear the responsibility, or deactivate this participation.</div><?php endif;?>
<div class="card card-body"><dl class="row"><dt class="col-sm-4">Project</dt><dd class="col-sm-8"><?= View::escape($project->displayTitle()) ?></dd><dt class="col-sm-4">Person</dt><dd class="col-sm-8"><?= View::escape($participant->personName()) ?></dd><dt class="col-sm-4">Project role</dt><dd class="col-sm-8"><?= View::escape($participant->roleLabel()) ?></dd><dt class="col-sm-4">Period</dt><dd class="col-sm-8"><?= View::escape($participant->period()) ?></dd></dl>
<div class="d-flex gap-2"><?php if(!$hasAllocations&&$responsibleWorkPackageCount===0):?><form method="post" action="<?= View::escape($urls->to($base.'/'.$participant->id.'/remove')) ?>"><input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><button class="btn btn-danger" type="submit">Confirm removal</button></form><?php endif;?><a class="btn btn-outline-secondary" href="<?= View::escape($urls->to($base.'/'.$participant->id)) ?>">Cancel</a></div></div></div></div>
