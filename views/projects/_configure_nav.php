<?php
declare(strict_types=1);
use App\Support\View;
$configurationYear=$configurationYear??null;
$context=$configurationYear===null?[]:['year'=>$configurationYear];
?>
<nav aria-label="Breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="<?= View::escape($urls->to('/projects')) ?>">Projects</a></li><li class="breadcrumb-item"><a href="<?= View::escape($urls->to('/projects/'.$project->id,$context)) ?>"><?= View::escape($project->acronym) ?></a></li><li class="breadcrumb-item active" aria-current="page">Configure Project</li></ol></nav>
<header class="configuration-header"><div><p class="eyebrow">Configure Project</p><h1 class="h3"><?= View::escape($project->displayTitle()) ?><?= $configurationYear!==null?' · '.$configurationYear:'' ?></h1></div><a class="btn btn-sm btn-outline-secondary" href="<?= View::escape($urls->to('/projects/'.$project->id,$context)) ?>">Back to Project Overview</a></header>
<nav class="configuration-nav mb-4" aria-label="Project configuration">
  <a class="configuration-link <?= $configurationSection==='details'?'active':'' ?>" <?= $configurationSection==='details'?'aria-current="page"':'' ?> href="<?= View::escape($urls->to('/projects/'.$project->id.'/configure',$context)) ?>">Details</a>
  <a class="configuration-link <?= $configurationSection==='work-packages'?'active':'' ?>" <?= $configurationSection==='work-packages'?'aria-current="page"':'' ?> href="<?= View::escape($urls->to('/projects/'.$project->id.'/work-packages',$context)) ?>">Work Packages</a>
  <a class="configuration-link <?= $configurationSection==='participants'?'active':'' ?>" <?= $configurationSection==='participants'?'aria-current="page"':'' ?> href="<?= View::escape($urls->to('/projects/'.$project->id.'/participants',$context)) ?>">Participants</a>
</nav>
