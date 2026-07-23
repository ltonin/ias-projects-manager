<?php
declare(strict_types=1);
namespace App\Support;
use InvalidArgumentException;
final class DecimalHours
{
    public function canonical(string$value):string{$c=$this->cents($value);return$this->format($c);}
    public function cents(string$value):int
    {
        if(preg_match('/^(-?)(0|[1-9]\\d{0,5})(?:\\.(\\d{1,2}))?$/',$value,$m)!==1)throw new InvalidArgumentException('Invalid hour decimal.');
        $cents=(int)$m[2]*100+(int)str_pad($m[3]??'',2,'0');return$m[1]==='-'?-$cents:$cents;
    }
    public function format(int$cents):string{$sign=$cents<0?'-':'';$cents=abs($cents);return$sign.intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);}
    public function subtract(string$left,string$right):string{return$this->format($this->cents($left)-$this->cents($right));}
    public function compare(string$left,string$right):int{return$this->cents($left)<=>$this->cents($right);}
}
