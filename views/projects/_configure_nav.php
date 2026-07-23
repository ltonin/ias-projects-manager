<?php
declare(strict_types=1);
use App\Support\View;
$configurationYear=$configurationYear??null;
$context=$configurationYear===null?[]:['year'=>$configurationYear];
?>
<nav class="configuration-nav nav nav-pills flex-column flex-sm-row gap-2 mb-4" aria-label="Project configuration">
  <a class="nav-link <?= $configurationSection==='details'?'active':'' ?>" <?= $configurationSection==='details'?'aria-current="page"':'' ?> href="<?= View::escape($urls->to('/projects/'.$project->id.'/configure',$context)) ?>">Project details</a>
  <a class="nav-link <?= $configurationSection==='work-packages'?'active':'' ?>" <?= $configurationSection==='work-packages'?'aria-current="page"':'' ?> href="<?= View::escape($urls->to('/projects/'.$project->id.'/work-packages',$context)) ?>">Work Packages</a>
  <a class="nav-link <?= $configurationSection==='participants'?'active':'' ?>" <?= $configurationSection==='participants'?'aria-current="page"':'' ?> href="<?= View::escape($urls->to('/projects/'.$project->id.'/participants',$context)) ?>">Participants</a>
</nav>
