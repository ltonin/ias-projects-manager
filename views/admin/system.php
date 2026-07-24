<?php
declare(strict_types=1);
use App\Support\View;
$status=static fn(bool $ok):string=>$ok?'text-bg-success':'text-bg-danger';
$issue=static fn(?int $count):string=>$count===null?'text-bg-secondary':($count===0?'text-bg-success':'text-bg-warning');
$value=static fn(mixed $value):string=>$value===null?'Unavailable':(string)$value;
?>
<header class="page-heading"><div><p class="eyebrow">Administration</p><h1>System</h1><p class="text-secondary mb-0">Deployment, runtime health, diagnostics, and maintenance.</p></div></header>

<section class="system-section mb-4" aria-labelledby="system-application"><h2 class="h4" id="system-application">Application</h2>
<div class="row g-3">
<?php foreach([
    ['Application version',$metadata['version']],['Build version',$metadata['build_version']],['Git commit',$metadata['commit']],
    ['Build date',$metadata['build_date']??'Unavailable'],['Deployment date',$metadata['deployment_date']??'Unavailable'],
    ['Environment',$metadata['environment']],['APP_URL',$applicationUrl],['APP_BASE_PATH',$basePath===''?'/':$basePath],
]as[$label,$item]):?><div class="col-sm-6 col-xl-3"><div class="card card-body h-100 system-metric"><small><?= View::escape($label) ?></small><strong><?= View::escape($item) ?></strong></div></div><?php endforeach;?>
</div></section>

<section class="system-section mb-4" aria-labelledby="system-runtime"><h2 class="h4" id="system-runtime">Runtime</h2>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
<?php foreach([
    ['PHP version',$runtime['phpVersion']],['Web server',$runtime['webServer']],['Database server',$database['version']],
    ['Timezone',$runtime['timezone']],['Server time',$runtime['serverTime']],['Application uptime',$runtime['uptime']],
    ['Session save path',$runtime['sessionPath']],['Log directory',$runtime['logDirectory']],
]as[$label,$item]):?><tr><th scope="row"><?= View::escape($label) ?></th><td><code><?= View::escape($item) ?></code></td></tr><?php endforeach;?>
</tbody></table></div></div>
<div class="table-responsive mt-3"><table class="table table-sm align-middle bg-white border"><thead><tr><th>Writable directory</th><th>Path</th><th>Status</th></tr></thead><tbody><?php foreach($writable as$item):?><tr><td><?= View::escape($item['label']) ?></td><td><code><?= View::escape($item['path']) ?></code></td><td><span class="badge <?= $status($item['writable']) ?>"><?= $item['writable']?'Writable':'Not writable' ?></span></td></tr><?php endforeach;?></tbody></table></div>
</section>

<section class="system-section mb-4" aria-labelledby="system-database"><div class="d-flex align-items-center gap-2"><h2 class="h4 mb-0" id="system-database">Database</h2><span class="badge <?= $status($database['connected']) ?>"><?= View::escape($database['message']) ?></span></div>
<div class="row g-3 mt-0">
<?php foreach([
    ['Database size',$database['size']],['Projects',$statistics['projects']],['Work Packages',$statistics['workPackages']],
    ['People',$statistics['people']],['Users',$statistics['users']],['Allocations',$statistics['allocations']],
    ['Active projects',$statistics['activeProjects']],['Archived projects',$statistics['archivedProjects']],['Deleted projects',$statistics['deletedProjects']],
]as[$label,$item]):?><div class="col-6 col-lg-3"><div class="card card-body h-100 system-metric"><small><?= View::escape($label) ?></small><strong><?= View::escape($value($item)) ?></strong></div></div><?php endforeach;?>
</div></section>

<section class="system-section mb-4" aria-labelledby="system-diagnostics"><h2 class="h4" id="system-diagnostics">Diagnostics</h2>
<div class="row g-2">
<?php foreach([
    ['Missing linked Person',$diagnostics['missingLinkedPerson']],['Duplicate usernames',$diagnostics['duplicateUsernames']],
    ['Duplicate email addresses',$diagnostics['duplicateEmails']],['Orphan allocations',$diagnostics['orphanAllocations']],
    ['Projects without Work Packages',$diagnostics['missingWorkPackages']],['Projects without Participants',$diagnostics['missingParticipants']],
    ['Invalid annual capacity values',$diagnostics['invalidAnnualCapacity']],['Pending migrations',$database['connected']?count($diagnostics['migrations']['pending']):null],
    ['Failed migrations',$database['connected']?count($diagnostics['migrations']['failed']):null],
]as[$label,$count]):?><div class="col-md-6 col-xl-4"><div class="d-flex justify-content-between align-items-center bg-white border rounded p-2"><span><?= View::escape($label) ?></span><span class="badge <?= $issue($count) ?>"><?= View::escape($value($count)) ?></span></div></div><?php endforeach;?>
</div>
<details class="mt-3"><summary>Migration details</summary><div class="card card-body mt-2"><p class="mb-1"><strong>Applied:</strong> <?= View::escape(implode(', ',$diagnostics['migrations']['applied'])?:'None') ?></p><p class="mb-1"><strong>Pending:</strong> <?= View::escape(implode(', ',$diagnostics['migrations']['pending'])?:'None') ?></p><p class="mb-0"><strong>Failed or unknown:</strong> <?= View::escape(implode(', ',$diagnostics['migrations']['failed'])?:'None') ?></p></div></details>
</section>

<section class="system-section" aria-labelledby="system-maintenance"><h2 class="h4" id="system-maintenance">Maintenance</h2>
<div class="card card-body"><div class="d-flex flex-wrap gap-2 align-items-center"><form method="post" action="<?= View::escape($urls->to('/admin/system/backup')) ?>"><input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><button class="btn btn-primary" type="submit">Download SQL Backup</button></form><span class="text-secondary small">Streams a complete UTF-8 SQL export. No backup file is retained on the server.</span></div>
<hr><div class="d-flex flex-wrap gap-2" aria-label="Future maintenance actions"><button class="btn btn-outline-secondary" disabled>Restore database</button><button class="btn btn-outline-secondary" disabled>Clear logs</button><button class="btn btn-outline-secondary" disabled>Clear cache</button><button class="btn btn-outline-secondary" disabled>Download logs</button></div><small class="text-secondary mt-2">These actions are reserved for future milestones.</small></div>
</section>
