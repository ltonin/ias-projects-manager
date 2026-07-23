<?php
declare(strict_types=1);
use App\Support\View;
$base='/projects/'.$project->id.'/work-packages';
$action=$mode==='create'?$base:$base.'/'.$workPackage->id;
$field=function(string$name,string$label,string$type='text')use($values,$errors){?>
  <div class="mb-3"><label class="form-label" for="<?= $name ?>"><?= $label ?></label><input class="form-control<?= isset($errors[$name])?' is-invalid':'' ?>" type="<?= $type ?>" id="<?= $name ?>" name="<?= $name ?>" value="<?= View::escape((string)($values[$name]??'')) ?>"><?php if(isset($errors[$name])):?><div class="invalid-feedback"><?= View::escape($errors[$name]) ?></div><?php endif;?></div>
<?php };?>
<div class="row justify-content-center"><div class="col-xl-8">
  <p><a href="<?= View::escape($urls->to($base,$configurationYear===null?[]:['year'=>$configurationYear])) ?>">← Work Packages</a></p>
  <h1><?= View::escape($title) ?></h1><p class="text-secondary"><?= View::escape($project->displayTitle()) ?></p>
  <form class="card card-body" method="post" action="<?= View::escape($urls->to($action)) ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape($csrfToken) ?>"><?php if($configurationYear!==null):?><input type="hidden" name="return_year" value="<?= $configurationYear ?>"><?php endif;?>
    <?php $field('code','Code');$field('title','Title');?>
    <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control<?= isset($errors['description'])?' is-invalid':'' ?>" id="description" name="description" rows="4"><?= View::escape((string)($values['description']??'')) ?></textarea><?php if(isset($errors['description'])):?><div class="invalid-feedback"><?= View::escape($errors['description']) ?></div><?php endif;?></div>
    <div class="row"><div class="col-md-6"><?php $field('start_date','Start date','date');?></div><div class="col-md-6"><?php $field('end_date','End date','date');?></div></div>
    <div class="mb-3"><label class="form-label" for="responsible_participant_id">Responsible participant</label><select class="form-select<?= isset($errors['responsible_participant_id'])?' is-invalid':'' ?>" id="responsible_participant_id" name="responsible_participant_id"><option value="">No responsible participant</option><?php foreach($options as$o):?><option value="<?= $o->id ?>" <?= (string)($values['responsible_participant_id']??'')===(string)$o->id?'selected':'' ?>><?= View::escape($o->personName().' — '.$o->roleLabel().(!$o->isActive?' (inactive participation)':'')) ?></option><?php endforeach;?></select><?php if(isset($errors['responsible_participant_id'])):?><div class="invalid-feedback"><?= View::escape($errors['responsible_participant_id']) ?></div><?php endif;?></div>
    <div class="form-check mb-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" <?= (string)($values['is_active']??'0')==='1'?'checked':'' ?>><label class="form-check-label" for="is_active">Active</label></div>
    <div class="mb-3"><label class="form-label" for="notes">Private management notes</label><textarea class="form-control<?= isset($errors['notes'])?' is-invalid':'' ?>" id="notes" name="notes" rows="4"><?= View::escape((string)($values['notes']??'')) ?></textarea><?php if(isset($errors['notes'])):?><div class="invalid-feedback"><?= View::escape($errors['notes']) ?></div><?php endif;?></div>
    <button class="btn btn-primary" type="submit"><?= $mode==='create'?'Add Work Package':'Save changes' ?></button>
  </form>
</div></div>
