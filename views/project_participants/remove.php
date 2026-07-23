<?php
declare(strict_types=1);
use App\Support\View;
$base='/projects/'.$project->id.'/participants';
?>
<div class="row justify-content-center"><div class="col-lg-7"><h1 class="h2">Remove participant</h1><div class="alert alert-danger">This removes only the participation relationship. It does not remove the person, linked account, project, or responsible project manager.</div>
<div class="card card-body"><dl class="row"><dt class="col-sm-4">Project</dt><dd class="col-sm-8"><?= View::escape($project->displayTitle()) ?></dd><dt class="col-sm-4">Person</dt><dd class="col-sm-8"><?= View::escape($participant->personName()) ?></dd><dt class="col-sm-4">Project role</dt><dd class="col-sm-8"><?= View::escape($participant->roleLabel()) ?></dd><dt class="col-sm-4">Period</dt><dd class="col-sm-8"><?= View::escape($participant->period()) ?></dd></dl>
<div class="d-flex gap-2"><form method="post" action="<?= View::escape($urls->to($base.'/'.$participant->id.'/remove')) ?>"><input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><button class="btn btn-danger" type="submit">Confirm removal</button></form><a class="btn btn-outline-secondary" href="<?= View::escape($urls->to($base.'/'.$participant->id)) ?>">Cancel</a></div></div></div></div>
