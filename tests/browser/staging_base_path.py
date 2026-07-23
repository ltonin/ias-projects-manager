#!/usr/bin/env python3
"""Authenticated non-root BASE_PATH smoke test for Milestone 12."""
import json, os, shutil, subprocess, tempfile, time
from milestone11_1_layout import Cdp, wait_json

origin=os.environ.get("STAGING_BROWSER_ORIGIN","http://localhost:8080").rstrip("/")
base_path=os.environ.get("STAGING_BROWSER_BASE_PATH","/iaslab-projects").rstrip("/")
base=origin+base_path
profile=tempfile.mkdtemp(prefix="staging-basepath-chrome-");port=9337
chrome=subprocess.Popen(["google-chrome","--headless=new","--no-sandbox","--disable-gpu",
    f"--remote-debugging-port={port}","--remote-allow-origins=*",f"--user-data-dir={profile}","about:blank"],
    stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
    target=next(x for x in wait_json(f"http://127.0.0.1:{port}/json") if x.get("type")=="page")
    c=Cdp(target["webSocketDebuggerUrl"]);c.call("Page.enable");c.call("Network.enable")
    c.navigate(base+"/login")
    c.evaluate(f"""document.querySelector('#identifier').value={json.dumps(os.environ["STAGING_BROWSER_USER"])};
      document.querySelector('#password').value={json.dumps(os.environ["STAGING_BROWSER_PASSWORD"])};
      document.querySelector('form').submit()""");time.sleep(.5)
    assert c.evaluate("location.pathname")==base_path+"/"
    c.call("Emulation.setDeviceMetricsOverride",{"width":1440,"height":900,"deviceScaleFactor":1,"mobile":False})
    project=c.evaluate("document.querySelector('.sidebar-project')?.getAttribute('href')")
    assert project and project.startswith(base+"/projects/"),project
    project_path=project[len(origin):]
    project_id=project_path.rstrip("/").split("/")[-1]
    routes=[
        ("overview","/"),
        ("projects","/projects"),
        ("project",f"/projects/{project_id}?year=2026"),
        ("effort",f"/projects/{project_id}/effort/edit?year=2026"),
        ("capacity","/capacity?year=2026"),
        ("people","/admin/people"),
        ("users","/admin/users"),
        ("system","/admin/system"),
    ]
    results=[]
    for name,path in routes:
        c.navigate(base+path)
        state=c.evaluate(f"""(() => {{const resources=performance.getEntriesByType('resource').map(e=>e.name);
          return {{name:{json.dumps(name)},path:location.pathname,login:!!document.querySelector('#identifier'),
          error:document.querySelector('.display-1')?.textContent.trim()||'',system:document.body.textContent.includes('System diagnostics'),
          foreign:resources.filter(u=>u.startsWith({json.dumps(origin)})&&!new URL(u).pathname.startsWith({json.dumps(base_path+"/")})),
          doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth],
          outside:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>document.documentElement.clientWidth+1).slice(0,8).map(e=>({{tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width}}))}}}})()""")
        assert state["path"].startswith(base_path+"/") and not state["login"] and state["error"] not in ("403","404","500"),state
        assert state["foreign"]==[],state
        assert state["doc"][1]<=state["doc"][0],state
        results.append(state)
    assert results[-1]["system"]
    responsive=[]
    for width,height in ((1920,1080),(1440,900),(1366,768),(390,844)):
        c.call("Emulation.setDeviceMetricsOverride",{"width":width,"height":height,"deviceScaleFactor":1,"mobile":False})
        for path in ("/",f"/projects/{project_id}?year=2026",f"/projects/{project_id}/effort/edit?year=2026","/capacity?year=2026","/admin/system"):
            c.navigate(base+path)
            item=c.evaluate("""({path:location.pathname,width:innerWidth,doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth]})""")
            assert item["doc"][1]<=item["doc"][0],item
            responsive.append(item)
    assets=c.evaluate(f"""Promise.all(['css/app.css','js/app.js','img/iaslab-logo.svg'].map(async p=>{{
      const r=await fetch({json.dumps(base+"/assets/")}+p);return [p,r.status,r.url]}}))""")
    assert all(status==200 and url.startswith(base+"/assets/") for _,status,url in assets),assets
    c.navigate(base+"/");c.evaluate("document.querySelector('form[action$=\"/logout\"]').submit()");time.sleep(.4)
    assert c.evaluate("location.pathname")==base_path+"/login"
    print(json.dumps({"routes":results,"assets":assets,"responsive":responsive,"logout":c.evaluate("location.pathname")},indent=2))
finally:
    chrome.terminate();chrome.wait(timeout=5);shutil.rmtree(profile,ignore_errors=True)
