'use strict';
(function(global){
    const decimal={
        parse(value){
            const text=String(value??'').trim();
            if(text==='')return{valid:true,cents:null,canonical:''};
            const match=/^(0|[1-9]\d{0,5})(?:\.(\d{1,2}))?$/.exec(text);
            if(!match)return{valid:false,cents:null,canonical:text};
            const cents=Number(match[1])*100+Number((match[2]??'').padEnd(2,'0'));
            return{valid:true,cents,canonical:`${Math.floor(cents/100)}.${String(cents%100).padStart(2,'0')}`};
        },
        equivalent(left,right){
            const a=this.parse(left),b=this.parse(right);
            return a.valid&&b.valid?a.cents===b.cents:String(left).trim()===String(right).trim();
        },
        format(cents){return`${Math.floor(cents/100)}.${String(cents%100).padStart(2,'0')}`;},
        pm(cents,factorCents){
            const thousandths=Math.floor((cents*1000+Math.floor(factorCents/2))/factorCents);
            return`${Math.floor(thousandths/1000)}.${String(thousandths%1000).padStart(3,'0')}`;
        }
    };
    global.AnnualEffortDecimal=decimal;
})(window);
