<?php
declare(strict_types=1);
use App\Support\View;
$monthName=static fn(int$m):string=>(new DateTimeImmutable("2026-$m-01"))->format('M');
$colgroup=static function():void{echo'<colgroup><col class="participant-column">';for($m=1;$m<=12;$m++)echo'<col class="month-column-width">';echo'<col class="annual-column"></colgroup>';};
?>
<header class="page-heading">
    <div><p class="eyebrow">Daily consultation</p><h1>Annual person-hours overview</h1><p class="text-secondary mb-0">Accessible projects, organized by Work Package and participant.</p></div>
    <div class="year-navigation" aria-label="Overview year">
        <a class="btn btn-outline-secondary" href="<?= View::escape($urls->to('/',['year'=>$page->year-1])) ?>">← <?= $page->year-1 ?></a>
        <form method="get"><label class="visually-hidden" for="overview-year">Year</label><input class="form-control" id="overview-year" name="year" value="<?= $page->year ?>" inputmode="numeric"><button class="btn btn-outline-primary">Go</button></form>
        <a class="btn btn-outline-secondary" href="<?= View::escape($urls->to('/',['year'=>$page->year+1])) ?>"><?= $page->year+1 ?> →</a>
    </div>
</header>
<section class="overview-tools" data-overview-tools>
    <label>Project search<input class="form-control" type="search" data-project-filter></label>
    <label>Participant search<input class="form-control" type="search" data-participant-filter></label>
    <label>Work Package search<input class="form-control" type="search" data-wp-filter></label>
    <label>Status<select class="form-select" data-status-filter><option value="">All</option><option value="active">Active/planned</option><option value="completed">Completed/inactive</option></select></label>
    <button class="btn btn-outline-secondary" type="button" data-overview-expand>Expand all</button>
    <button class="btn btn-outline-secondary" type="button" data-overview-collapse>Collapse all</button>
</section>
<?php if($page->projects===[]):?>
<div class="empty-state"><h2>No active projects for <?= $page->year ?></h2><p>No accessible projects are active in <?= $page->year ?>.</p></div>
<?php endif;?>
<div class="global-overview" data-global-overview>
<?php foreach($page->projects as$entry):$project=$entry['project'];$isCompleted=in_array($project->status,['completed','cancelled'],true);?>
<details class="overview-project" data-project-text="<?= View::escape(strtolower($project->displayTitle())) ?>" data-project-status="<?= $isCompleted?'completed':'active' ?>" <?= !$isCompleted?'open':'' ?>>
<summary>
    <span><strong><?= View::escape($project->acronym) ?></strong> — <?= View::escape($project->title) ?></span>
    <span class="project-summary-meta"><?= View::escape($project->managerName??'No manager') ?> · <?= View::escape($project->period()) ?> · <strong><?= View::escape($entry['annualHours']) ?></strong> h
    <?php if($entry['warnings']['divergent']>0):?><span class="badge text-bg-warning"><?= $entry['warnings']['divergent'] ?> divergent</span><?php endif;?>
    <?php if($entry['warnings']['legacy']>0):?><span class="badge text-bg-info"><?= $entry['warnings']['legacy'] ?> unassigned</span><?php endif;?>
    <a class="btn btn-sm btn-primary summary-action" href="<?= View::escape($urls->to('/projects/'.$project->id,['year'=>$page->year])) ?>">Open project</a></span>
</summary>
<div class="overview-project-body">
<?php if($entry['sections']===[]):?><p class="text-secondary p-3">No Work Packages and participants are available for this year.</p><?php endif;?>
<?php foreach($entry['sections']as$section):?>
<details class="overview-wp" data-wp-text="<?= View::escape(strtolower($section['code'].' '.$section['title'])) ?>" open>
<summary><span><strong><?= View::escape($section['code']) ?></strong> — <?= View::escape($section['title']) ?></span><span><?= View::escape($section['annualHours']) ?> h<?php if($section['divergentCount']):?> · <span class="badge text-bg-warning"><?= $section['divergentCount'] ?> divergent</span><?php endif;?></span></summary>
<div class="table-responsive effort-grid"><table class="table table-sm table-bordered effort-table readonly-effort-table"><?php $colgroup();?>
<thead><tr><th scope="col">Participant</th><?php for($m=1;$m<=12;$m++):$current=$page->currentMonth===$m;?><th scope="col" class="month-column<?= $current?' current-month':'' ?>" <?= $current?'aria-label="'.View::escape($monthName($m).', current month').'"':'' ?>><?= $monthName($m) ?></th><?php endfor;?><th scope="col">Annual</th></tr></thead>
<tbody>
<?php foreach($section['participants']as$participant):?>
<tr data-participant-text="<?= View::escape(strtolower($participant['name'])) ?>"><th scope="row"><?= View::escape($participant['name']) ?><small><?= View::escape(ucwords(str_replace('_',' ',$participant['role']))) ?></small></th>
<?php for($m=1;$m<=12;$m++):$value=$participant['months'][$m];?><td class="<?= $page->currentMonth===$m?'current-month':'' ?><?= $value==='divergent'?' state-divergent':'' ?>"><?= $value==='divergent'?'<span aria-label="Divergent value excluded">!</span>':View::escape($value??'—') ?></td><?php endfor;?>
<td><strong><?= View::escape($participant['annualHours']) ?></strong></td></tr>
<?php endforeach;?>
<tr class="wp-total-row"><th scope="row"><?= View::escape($section['code']) ?> total</th><?php for($m=1;$m<=12;$m++):?><td class="<?= $page->currentMonth===$m?'current-month':'' ?>"><?= View::escape($section['monthlyHours'][$m]) ?></td><?php endfor;?><td><strong><?= View::escape($section['annualHours']) ?></strong></td></tr>
</tbody></table></div></details>
<?php endforeach;?>
<div class="table-responsive project-total-table"><table class="table table-bordered effort-table"><?php $colgroup();?><tbody><tr class="table-primary"><th>Project total</th><?php for($m=1;$m<=12;$m++):?><td class="<?= $page->currentMonth===$m?'current-month':'' ?>"><?= View::escape($entry['monthlyHours'][$m]) ?></td><?php endfor;?><td><strong><?= View::escape($entry['annualHours']) ?></strong></td></tr></tbody></table></div>
</div></details>
<?php endforeach;?>
</div>
