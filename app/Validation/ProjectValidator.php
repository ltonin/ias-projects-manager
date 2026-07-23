<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\Project;
use DateTimeImmutable;

final class ProjectValidator
{
    /** @param array<string,mixed> $input @return array<string,string> */
    public function validate(array $input):array
    {
        $e=[];
        $this->requiredLength($e,$input,'acronym','Acronym',50);
        $this->requiredLength($e,$input,'title','Title',255);
        foreach(['internal_code'=>100,'grant_agreement_number'=>100,'funding_agency'=>255,'funding_programme'=>255,'coordinator_organization'=>255,'website_url'=>500] as $f=>$max)$this->optionalLength($e,$input,$f,$max);
        foreach(['description','notes'] as $f)if(strlen(trim((string)($input[$f]??'')))>5000)$e[$f]=ucfirst($f).' must not exceed 5000 characters.';
        if(!array_key_exists((string)($input['status']??''),Project::STATUS_LABELS))$e['status']='Select a valid status.';
        foreach(['start_date','end_date'] as $f){$v=trim((string)($input[$f]??''));if($v!==''&&!$this->date($v))$e[$f]='Enter a valid date.';}
        if(!isset($e['start_date'])&&!isset($e['end_date'])){$from=trim((string)($input['start_date']??''));$to=trim((string)($input['end_date']??''));if($from!==''&&$to!==''&&$to<$from)$e['end_date']='End date must not precede start date.';}
        $manager=trim((string)($input['manager_person_id']??''));if($manager!==''&&filter_var($manager,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)$e['manager_person_id']='Select a valid responsible person.';
        $budget=trim((string)($input['total_budget']??''));$currency=strtoupper(trim((string)($input['currency']??'')));
        if($budget!==''&&preg_match('/^(?:0|[1-9]\d{0,12})(?:\.\d{1,2})?$/',$budget)!==1)$e['total_budget']='Enter a non-negative budget with at most two decimal places.';
        if(($budget==='')!==($currency===''))$e[$budget===''?'total_budget':'currency']='Budget and currency must be provided together.';
        if($currency!==''&&preg_match('/^[A-Z]{3}$/',$currency)!==1)$e['currency']='Currency must contain exactly three letters.';
        $url=trim((string)($input['website_url']??''));if($url!==''&&(!$this->httpUrl($url)))$e['website_url']='Enter a valid absolute HTTP or HTTPS URL.';
        return$e;
    }
    private function requiredLength(array &$e,array $i,string $f,string $label,int $max):void{$v=trim((string)($i[$f]??''));if($v==='')$e[$f]=$label.' is required.';elseif(strlen($v)>$max)$e[$f]="$label must not exceed $max characters.";}
    private function optionalLength(array &$e,array $i,string $f,int $max):void{if(strlen(trim((string)($i[$f]??'')))>$max)$e[$f]=ucfirst(str_replace('_',' ',$f))." must not exceed $max characters.";}
    private function date(string $v):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);return$d!==false&&$d->format('Y-m-d')===$v;}
    private function httpUrl(string $v):bool{$parts=parse_url($v);return filter_var($v,FILTER_VALIDATE_URL)!==false&&is_array($parts)&&in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true);}
}
