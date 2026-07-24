'use strict';
(function(){
    const shell=document.querySelector('.app-shell'),toggle=document.querySelector('[data-sidebar-toggle]');
    if(!shell||!toggle)return;
    const key='iaspm.sidebar';
    const stored=()=>{try{return localStorage.getItem(key)==='collapsed'}catch(error){return false}};
    const apply=(collapsed,persist)=>{
        shell.classList.toggle('sidebar-collapsed',collapsed);
        if(collapsed)document.documentElement.dataset.sidebarState='collapsed';else delete document.documentElement.dataset.sidebarState;
        toggle.setAttribute('aria-expanded',collapsed?'false':'true');
        const label=collapsed?'Expand sidebar':'Collapse sidebar';
        toggle.setAttribute('aria-label',label);toggle.dataset.sidebarTooltip=label;
        if(persist)try{localStorage.setItem(key,collapsed?'collapsed':'expanded')}catch(error){}
    };
    apply(stored(),false);
    toggle.addEventListener('click',()=>apply(!shell.classList.contains('sidebar-collapsed'),true));
    document.querySelectorAll('[data-sidebar-reveal]').forEach(control=>control.addEventListener('click',()=>{
        if(shell.classList.contains('sidebar-collapsed'))apply(false,false);
    }));
})();
(function(){
    const form=document.querySelector('[data-participant-form]'),person=form?.querySelector('#person_id');if(!form||!person)return;
    const search=form.querySelector('[data-person-option-search]');
    search?.addEventListener('input',()=>{const needle=search.value.trim().toLowerCase();[...person.options].forEach((option,index)=>{if(index>0)option.hidden=needle!==''&&!option.dataset.personOption.includes(needle)})});
    person.addEventListener('change',()=>{
        const option=person.selectedOptions[0],latest=(a,b)=>!a?b:!b?a:(a>b?a:b),earliest=(a,b)=>!a?b:!b?a:(a<b?a:b);
        form.querySelector('#participation_start').value=latest(form.dataset.projectStart??'',option?.dataset.activeFrom??'');
        form.querySelector('#participation_end').value=earliest(form.dataset.projectEnd??'',option?.dataset.activeTo??'');
    });
})();
(() => {
  const sidebarSearch = document.querySelector('[data-sidebar-project-search]');
  if (sidebarSearch) sidebarSearch.addEventListener('input', () => {
    const needle = sidebarSearch.value.trim().toLowerCase();
    document.querySelectorAll('[data-project-name]').forEach(link => {
      link.hidden = needle !== '' && !link.dataset.projectName.includes(needle);
    });
  });
  const overview = document.querySelector('[data-global-overview]');
  if (!overview) return;
  const applyFilters = () => {
    const projectNeedle = document.querySelector('[data-project-filter]')?.value.trim().toLowerCase() || '';
    const participantNeedle = document.querySelector('[data-participant-filter]')?.value.trim().toLowerCase() || '';
    const wpNeedle = document.querySelector('[data-wp-filter]')?.value.trim().toLowerCase() || '';
    const status = document.querySelector('[data-status-filter]')?.value || '';
    overview.querySelectorAll('.overview-project').forEach(project => {
      const projectMatch = !projectNeedle || project.dataset.projectText.includes(projectNeedle);
      const statusMatch = !status || project.dataset.projectStatus === status;
      let contentMatch = !participantNeedle && !wpNeedle;
      project.querySelectorAll('.overview-wp').forEach(wp => {
        const wpMatch = !wpNeedle || wp.dataset.wpText.includes(wpNeedle);
        let participantMatch = !participantNeedle;
        wp.querySelectorAll('[data-participant-text]').forEach(row => {
          const match = !participantNeedle || row.dataset.participantText.includes(participantNeedle);
          row.hidden = !match;
          participantMatch ||= match;
        });
        wp.hidden = !wpMatch || !participantMatch;
        contentMatch ||= !wp.hidden;
      });
      project.hidden = !projectMatch || !statusMatch || !contentMatch;
    });
  };
  document.querySelectorAll('[data-overview-tools] input,[data-overview-tools] select').forEach(control => control.addEventListener('input', applyFilters));
  document.querySelector('[data-overview-expand]')?.addEventListener('click', () => overview.querySelectorAll('details').forEach(item => item.open = true));
  document.querySelector('[data-overview-collapse]')?.addEventListener('click', () => overview.querySelectorAll('details').forEach(item => item.open = false));
})();
(() => {
  const overview=document.querySelector('[data-capacity-overview]');if(!overview)return;
  overview.classList.add('capacity-overview-enhanced');
  const sections=()=>[...overview.querySelectorAll('[data-capacity-person]')].filter(section=>!section.hidden);
  const set=(section,expanded)=>{const button=section.querySelector('[data-capacity-toggle]'),panel=section.querySelector('[data-capacity-panel]');button.setAttribute('aria-expanded',expanded?'true':'false');panel.hidden=!expanded};
  const initial=overview.dataset.defaultExpanded==='1';overview.querySelectorAll('[data-capacity-person]').forEach(section=>set(section,initial));
  overview.addEventListener('click',event=>{const button=event.target.closest('[data-capacity-toggle]');if(button)set(button.closest('[data-capacity-person]'),button.getAttribute('aria-expanded')!=='true')});
  document.querySelector('[data-capacity-expand-all]')?.addEventListener('click',()=>sections().forEach(section=>set(section,true)));
  document.querySelector('[data-capacity-collapse-all]')?.addEventListener('click',()=>sections().forEach(section=>set(section,false)));
  document.querySelector('[data-capacity-search]')?.addEventListener('input',event=>{const value=event.target.value.trim().toLowerCase();overview.querySelectorAll('[data-capacity-person]').forEach(section=>section.hidden=value!==''&&!section.dataset.personSearch.includes(value))});
})();
document.addEventListener('click',function(event){
  const remove=event.target.closest('[data-remove-wp]');
  if(!remove)return;
  const table=remove.closest('[data-wp-collection]');
  const row=remove.closest('[data-wp-row]');
  if(table&&row)row.remove();
});
(() => {
  const position=document.querySelector('[data-position-type]');
  const annual=document.querySelector('[data-annual-capacity]');
  if(!position||!annual)return;
  let pristine=annual.dataset.pristine==='1';
  annual.addEventListener('input',()=>{pristine=false});
  position.addEventListener('change',()=>{
    if(!pristine)return;
    annual.value=['full_professor','associate_professor','assistant_professor','researcher'].includes(position.value)?'1150.00':'1500.00';
  });
})();
(() => {
  const form=document.querySelector('[data-permanent-delete-form]');if(!form)return;
  const input=form.querySelector('[data-delete-confirmation]'),submit=form.querySelector('[data-delete-submit]');
  const update=()=>{submit.disabled=input.value!==input.dataset.expected};
  input.addEventListener('input',update);update();
})();
