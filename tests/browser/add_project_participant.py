#!/usr/bin/env python3
"""Authenticated acceptance test for adding a participant from annual effort.

Use disposable fixtures: this test intentionally creates one participation row.
"""
import json, os, shutil, subprocess, tempfile, time
from milestone11_1_layout import Cdp, wait_json

base=os.environ.get("PARTICIPANT_BROWSER_BASE_URL","http://localhost:8080")
project=int(os.environ["PARTICIPANT_BROWSER_PROJECT"])
eligible=int(os.environ["PARTICIPANT_BROWSER_ELIGIBLE_PERSON"])
existing=int(os.environ["PARTICIPANT_BROWSER_EXISTING_PERSON"])
year=int(os.environ.get("PARTICIPANT_BROWSER_YEAR","2026"))
profile=tempfile.mkdtemp(prefix="participant-chrome-");port=9336
chrome=subprocess.Popen(["google-chrome","--headless=new","--no-sandbox","--disable-gpu",
    f"--remote-debugging-port={port}","--remote-allow-origins=*",f"--user-data-dir={profile}","about:blank"],
    stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)

def login(c,user,password):
    c.call("Network.enable");c.call("Network.clearBrowserCookies");c.navigate(base+"/login")
    c.evaluate(f"document.querySelector('#identifier').value={json.dumps(user)};document.querySelector('#password').value={json.dumps(password)};document.querySelector('form').submit()")
    time.sleep(.4)

try:
    target=next(x for x in wait_json(f"http://127.0.0.1:{port}/json") if x.get("type")=="page")
    c=Cdp(target["webSocketDebuggerUrl"]);c.call("Page.enable")
    login(c,os.environ["PARTICIPANT_BROWSER_USER"],os.environ["PARTICIPANT_BROWSER_PASSWORD"])
    c.navigate(f"{base}/projects/{project}?year={year}")
    overview=c.evaluate("""({add:[...document.querySelectorAll('a')].some(a=>a.textContent.trim()==='Add participant'),
      doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth]})""")
    assert overview["add"] and overview["doc"][0]==overview["doc"][1],overview
    c.navigate(f"{base}/projects/{project}/configure?year={year}")
    tabs=c.evaluate("[...document.querySelectorAll('.configuration-nav a')].map(a=>a.textContent.trim())")
    assert tabs==["Project details","Work Packages","Participants"],tabs
    c.navigate(f"{base}/projects/{project}/work-packages/create?year={year}")
    c.evaluate("""document.querySelector('#code').value='WP99';document.querySelector('#title').value='Browser Added WP';
      document.querySelector('form.card').submit()""");time.sleep(.4)
    wp_created=c.evaluate("""({path:location.pathname,query:location.search,success:document.body.textContent.includes('was added to'),
      visible:document.body.textContent.includes('Browser Added WP')})""")
    assert wp_created["path"]==f"/projects/{project}/work-packages" and wp_created["query"]==f"?year={year}" and wp_created["success"] and wp_created["visible"],wp_created
    edit_path=c.evaluate("[...document.querySelectorAll('tr')].find(r=>r.textContent.includes('Browser Added WP'))?.querySelector('a[href*=\"/edit\"]')?.pathname")
    assert edit_path
    c.navigate(base+edit_path+f"?year={year}");c.evaluate("document.querySelector('#title').value='Browser Updated WP';document.querySelector('form.card').submit()");time.sleep(.4)
    assert c.evaluate("document.body.textContent.includes('Browser Updated WP') && document.body.textContent.includes('was updated')")
    c.navigate(f"{base}/projects/{project}/participants/create?year={year}&return=effort")
    choices=c.evaluate("[...document.querySelectorAll('#person_id option')].map(o=>o.value)")
    assert str(eligible) in choices and str(existing) not in choices,choices
    c.evaluate(f"""document.querySelector('#person_id').value={json.dumps(str(eligible))};
      document.querySelector('#project_role').value='researcher';
      document.querySelector('form[action$="/projects/{project}/participants"]').submit()""")
    time.sleep(.5)
    created=c.evaluate("""({path:location.pathname,query:location.search,success:document.body.textContent.includes('was added to'),
      visible:document.querySelectorAll(`[data-participant-id]`).length>0 && document.body.textContent.includes('Browser Eligible')})""")
    assert created["path"]==f"/projects/{project}" and created["query"]==f"?year={year}" and created["success"] and created["visible"],created
    c.navigate(f"{base}/projects/{project}/effort/edit?year={year}")
    empty=c.evaluate(f"""(() => {{const rows=[...document.querySelectorAll('[data-participant-row]')].filter(r=>r.dataset.participantId);
      const row=rows.find(r=>r.textContent.includes('Browser Eligible'));if(!row)return null;
      const inputs=[...row.querySelectorAll('input.effort-cell')];return {{count:inputs.length,values:inputs.map(i=>i.value)}}}})()""")
    assert empty and empty["count"]>=12 and set(empty["values"])=={""},empty
    c.navigate(f"{base}/projects/{project}/participants/create?year={year}&return=effort")
    duplicate=c.evaluate(f"""(async()=>{{const form=document.querySelector('form[action$="/projects/{project}/participants"]'),fd=new FormData(form);
      fd.set('person_id',{json.dumps(str(eligible))});const response=await fetch(form.action,{{method:'POST',body:fd}});
      return {{status:response.status,body:await response.text()}}}})()""")
    assert duplicate["status"]==422 and "already participates" in duplicate["body"],duplicate["status"]
    login(c,os.environ["PARTICIPANT_BROWSER_VIEWER"],os.environ["PARTICIPANT_BROWSER_VIEWER_PASSWORD"])
    c.navigate(f"{base}/projects/{project}?year={year}")
    assert not c.evaluate("[...document.querySelectorAll('a')].some(a=>a.textContent.trim()==='Add participant')")
    denied_get=c.evaluate(f"(async()=>{{const r=await fetch('/projects/{project}/participants/create');return r.status}})()")
    token=c.evaluate("document.querySelector('input[name=_csrf]')?.value")
    denied_post=c.evaluate(f"""(async()=>{{const fd=new FormData();fd.set('_csrf',{json.dumps(token)});fd.set('person_id',{eligible});
      const r=await fetch('/projects/{project}/participants',{{method:'POST',body:fd}});return r.status}})()""")
    denied_config=c.evaluate(f"(async()=>{{const r=await fetch('/projects/{project}/configure');return r.status}})()")
    denied_wp=c.evaluate(f"(async()=>{{const r=await fetch('/projects/{project}/work-packages/create');return r.status}})()")
    assert denied_get==403 and denied_post==403 and denied_config==403 and denied_wp==403,(denied_get,denied_post,denied_config,denied_wp)
    responsive=[]
    for width in (1440,390):
        c.call("Emulation.setDeviceMetricsOverride",{"width":width,"height":900,"deviceScaleFactor":1,"mobile":False})
        c.navigate(f"{base}/projects/{project}?year={year}")
        responsive.append(c.evaluate("""({width:innerWidth,doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth],
          actions:getComputedStyle(document.querySelector('.project-actions')).flexWrap})"""))
    assert all(x["doc"][0]==x["doc"][1] and x["actions"]=="wrap" for x in responsive),responsive
    print(json.dumps({"overview":overview,"tabs":tabs,"workPackage":wp_created,"created":created,"emptyEffort":empty,"unauthorized":[denied_get,denied_post,denied_config,denied_wp],"responsive":responsive},indent=2))
finally:
    chrome.terminate();chrome.wait(timeout=5);shutil.rmtree(profile,ignore_errors=True)
