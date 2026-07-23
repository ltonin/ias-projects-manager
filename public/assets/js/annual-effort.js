'use strict';
(function(){
    const root=document.getElementById('annual-effort-grid');
    if(!root)return;
    root.classList.add('annual-effort-enhanced');

    const decimal=window.AnnualEffortDecimal;if(!decimal)return;

    const form=root.querySelector('[data-effort-form]');
    const inputs=()=>Array.from(root.querySelectorAll('.effort-cell'));
    const stateKey=`annual-effort:${root.dataset.projectId}:${root.dataset.year}`;
    let submitting=false;
    const dirtyInputs=()=>inputs().filter(input=>input.dataset.dirty==='1');
    const visible=input=>input.offsetParent!==null&&input.closest('details')?.open!==false;

    function setCellState(input){
        const parsed=decimal.parse(input.value),cell=input.closest('td'),description=cell.querySelector('[data-cell-description]');
        input.classList.toggle('is-invalid',!parsed.valid);input.setAttribute('aria-invalid',parsed.valid?'false':'true');
        cell.classList.remove('state-null','state-zero','state-value');
        const state=parsed.cents===null?'null':parsed.cents===0?'zero':'value';cell.classList.add(`state-${state}`);
        if(description)description.textContent=!parsed.valid?'Invalid decimal; server validation will reject it.':state==='null'?'Empty editable value':state==='zero'?'Explicit zero':'Non-zero value';
        const dirty=!decimal.equivalent(input.dataset.initial,input.value);input.dataset.dirty=dirty?'1':'0';input.setAttribute('aria-label',(input.labels[0]?.textContent??'Effort cell')+(dirty?' (modified)':''));
        cell.classList.toggle('cell-dirty',dirty);
        return parsed.valid?(parsed.cents??0):0;
    }
    function recalculateRow(input){
        const row=input.closest('[data-participant-row]'),rowInputs=Array.from(row.querySelectorAll('.effort-cell'));
        const total=rowInputs.reduce((sum,item)=>sum+setCellState(item),0);
        const target=row.querySelector('[data-participant-annual]');if(target)target.textContent=decimal.format(total);
        row.dataset.dirty=rowInputs.some(item=>item.dataset.dirty==='1')?'1':'0';
    }
    function recalculateWp(section){
        const wp=section.dataset.wpId;let annual=0;
        for(let month=1;month<=12;month++){
            let monthly=0;section.querySelectorAll(`.effort-cell[data-month="${month}"]`).forEach(input=>monthly+=decimal.parse(input.value).valid?(decimal.parse(input.value).cents??0):0);
            const target=section.querySelector(`[data-wp-month="${wp}-${month}"]`);if(target)target.textContent=decimal.format(monthly);annual+=monthly;
        }
        section.querySelectorAll(`[data-wp-annual-total="${wp}"],[data-wp-annual="${wp}"]`).forEach(target=>target.textContent=decimal.format(annual));
        const dirty=section.querySelector('.effort-cell[data-dirty="1"]')!==null;section.dataset.dirty=dirty?'1':'0';section.querySelector('[data-wp-dirty]')?.classList.toggle('d-none',!dirty);
    }
    function recalculateProject(){
        let annual=0;
        for(let month=1;month<=12;month++){
            let monthly=0;root.querySelectorAll(`[data-wp-month$="-${month}"]`).forEach(target=>{const parsed=decimal.parse(target.textContent);monthly+=parsed.cents??0;});
            root.querySelector(`[data-project-month="${month}"]`).textContent=decimal.format(monthly);annual+=monthly;
        }
        root.querySelector('[data-project-annual]').textContent=decimal.format(annual);
        const factor=decimal.parse(root.dataset.hoursPerPm).cents;root.querySelector('[data-project-pm]').textContent=`${decimal.pm(annual,factor)} PM`;
        const count=dirtyInputs().length;root.querySelector('[data-dirty-count]')?.replaceChildren(String(count));
        root.querySelector('[data-save-button]')?.toggleAttribute('disabled',count===0);
        root.querySelector('[data-reset-changes]')?.toggleAttribute('disabled',count===0);
        root.querySelector('[data-provisional-notice]')?.classList.toggle('d-none',count===0);
        root.querySelectorAll('[data-provisional-label]').forEach(label=>label.textContent=count?'Provisional total':'');
    }
    function refresh(input){
        recalculateRow(input);recalculateWp(input.closest('[data-wp-id]'));recalculateProject();applyFilters();
    }
    root.addEventListener('input',event=>{if(event.target.matches('.effort-cell'))refresh(event.target);});
    root.addEventListener('blur',event=>{
        if(!event.target.matches('.effort-cell'))return;
        const parsed=decimal.parse(event.target.value);if(parsed.valid&&parsed.cents!==null)event.target.value=parsed.canonical;refresh(event.target);
    },true);

    function move(input,key,shift){
        const row=Array.from(input.closest('tr').querySelectorAll('.effort-cell')).filter(visible),index=row.indexOf(input);
        let target=null;
        if(key==='Enter'||key==='ArrowRight')target=row[index+(shift?-1:1)]??null;
        if(key==='ArrowLeft')target=row[index-1]??null;
        if(key==='ArrowUp'||key==='ArrowDown'){
            const rows=Array.from(input.closest('tbody').querySelectorAll('[data-participant-row]')).filter(row=>row.offsetParent!==null),rowIndex=rows.indexOf(input.closest('tr')),candidate=rows[rowIndex+(key==='ArrowUp'?-1:1)];
            target=candidate?.querySelector(`.effort-cell[data-month="${input.dataset.month}"]`)??null;
        }
        if(target&&visible(target)){target.focus();target.select();return true;}return false;
    }
    root.addEventListener('keydown',event=>{
        const input=event.target;if(!input.matches('.effort-cell')||event.altKey||event.ctrlKey||event.metaKey)return;
        const atStart=input.selectionStart===0&&input.selectionEnd===0,atEnd=input.selectionStart===input.value.length&&input.selectionEnd===input.value.length;
        const eligible=event.key==='Enter'||(event.key==='ArrowLeft'&&atStart)||(event.key==='ArrowRight'&&atEnd)||((event.key==='ArrowUp'||event.key==='ArrowDown')&&(atStart||atEnd));
        if(eligible&&move(input,event.key,event.shiftKey))event.preventDefault();
    });

    function warnIfDirty(){if(dirtyInputs().length===0)return true;if(!window.confirm('You have unsaved effort changes. Leave this page and discard them?'))return false;submitting=true;return true;}
    window.addEventListener('beforeunload',event=>{if(!submitting&&dirtyInputs().length){event.preventDefault();event.returnValue='';}});
    root.addEventListener('click',event=>{const link=event.target.closest('a[href]');if(!link)return;const url=new URL(link.href,location.href);if(url.pathname===location.pathname&&url.search===location.search&&url.hash)return;if(!warnIfDirty())event.preventDefault();});
    root.querySelector('[data-year-form]')?.addEventListener('submit',event=>{if(!warnIfDirty())event.preventDefault();});
    form?.addEventListener('submit',()=>{submitting=true;saveContext();});
    root.querySelector('[data-reset-changes]')?.addEventListener('click',()=>{
        if(!dirtyInputs().length||!window.confirm('Restore every changed cell to its server-rendered value?'))return;
        inputs().forEach(input=>input.value=input.dataset.initial);inputs().forEach(recalculateRow);root.querySelectorAll('[data-wp-id]').forEach(recalculateWp);recalculateProject();
    });

    const monthSelect=root.querySelector('[data-month-focus]');
    function setMonth(month){
        month=Math.max(1,Math.min(12,Number(month)||1));monthSelect.value=String(month);
        root.querySelectorAll('[data-month-column]').forEach(cell=>cell.dataset.activeMonth=cell.dataset.monthColumn===String(month)?'1':'0');
        sessionStorage.setItem(`${stateKey}:month`,String(month));
    }
    monthSelect?.addEventListener('change',()=>setMonth(monthSelect.value));
    root.querySelector('[data-month-previous]')?.addEventListener('click',()=>setMonth(Number(monthSelect.value)-1));
    root.querySelector('[data-month-next]')?.addEventListener('click',()=>setMonth(Number(monthSelect.value)+1));

    root.querySelector('[data-expand-all]')?.addEventListener('click',()=>{root.querySelectorAll('[data-wp-id]').forEach(section=>section.open=true);saveContext();});
    root.querySelector('[data-collapse-all]')?.addEventListener('click',()=>{root.querySelectorAll('[data-wp-id]').forEach(section=>section.open=false);saveContext();});
    root.querySelector('[data-wp-jump]')?.addEventListener('change',event=>{const section=document.getElementById(event.target.value);if(section){section.open=true;section.scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});}});
    root.addEventListener('toggle',event=>{if(event.target.matches('[data-wp-id]'))saveContext();},true);

    function applyFilters(){
        const participant=(root.querySelector('[data-filter-participant]')?.value??'').trim().toLowerCase(),role=root.querySelector('[data-filter-role]')?.value??'',wpText=(root.querySelector('[data-filter-wp]')?.value??'').trim().toLowerCase(),wpActive=root.querySelector('[data-filter-wp-active]')?.value??'',state=root.querySelector('[data-filter-state]')?.value??'';
        root.querySelectorAll('[data-wp-id]').forEach(section=>{
            let visibleRows=0;section.querySelectorAll('[data-participant-row]').forEach(row=>{
                const dirty=row.dataset.dirty==='1',matches=(!participant||row.dataset.participantName.includes(participant))&&(!role||row.dataset.participantRole===role)&&(!state||(state==='effort'&&row.dataset.hasEffort==='1')||(state==='capacity'&&row.dataset.capacityWarning==='1')||(state==='dirty'&&dirty));
                row.hidden=!matches&&!dirty;if(!row.hidden)visibleRows++;
            });
            section.hidden=(!section.dataset.wpText.includes(wpText)||(wpActive&&section.dataset.wpActive!==wpActive)||visibleRows===0)&&section.dataset.dirty!=='1';
        });
    }
    root.querySelector('[data-grid-filters]')?.addEventListener('input',applyFilters);
    root.querySelector('[data-grid-filters]')?.addEventListener('change',applyFilters);
    root.querySelector('[data-reset-filters]')?.addEventListener('click',()=>{root.querySelectorAll('[data-grid-filters] input,[data-grid-filters] select').forEach(control=>control.value='');applyFilters();});

    function saveContext(){
        const open=Array.from(root.querySelectorAll('[data-wp-id][open]')).map(section=>section.dataset.wpId);
        sessionStorage.setItem(`${stateKey}:open`,JSON.stringify(open));sessionStorage.setItem(`${stateKey}:scroll`,String(window.scrollY));
    }
    function restoreContext(){
        try{const open=JSON.parse(sessionStorage.getItem(`${stateKey}:open`)??'null');if(Array.isArray(open))root.querySelectorAll('[data-wp-id]').forEach(section=>section.open=open.includes(section.dataset.wpId));}catch(_){}
        setMonth(sessionStorage.getItem(`${stateKey}:month`)??new Date().getMonth()+1);
        const scroll=Number(sessionStorage.getItem(`${stateKey}:scroll`));if(scroll>0)requestAnimationFrame(()=>window.scrollTo(0,scroll));
    }
    inputs().forEach(recalculateRow);root.querySelectorAll('[data-wp-id]').forEach(recalculateWp);recalculateProject();restoreContext();applyFilters();
    if(root.dataset.hasError==='1'){const target=dirtyInputs()[0]??root.querySelector('[data-grid-error]');target?.closest('details')?.setAttribute('open','');target?.focus();}
})();
