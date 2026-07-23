#!/usr/bin/env python3
"""Authenticated acceptance checks for annual overview switching and global capacity."""
import json,os,pathlib,shutil,subprocess,tempfile,time,urllib.parse
from milestone11_1_layout import Cdp,wait_json

base=os.environ.get("CORRECTIVE_BASE_URL","http://localhost:8080")
username=os.environ["CORRECTIVE_BROWSER_USER"];password=os.environ["CORRECTIVE_BROWSER_PASSWORD"]
profile=tempfile.mkdtemp(prefix="corrective-chrome-");port=9334
chrome=subprocess.Popen(["google-chrome","--headless=new","--no-sandbox","--disable-gpu",f"--remote-debugging-port={port}","--remote-allow-origins=*",
    f"--user-data-dir={profile}","about:blank"],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
    target=next(x for x in wait_json(f"http://127.0.0.1:{port}/json") if x.get("type")=="page")
    c=Cdp(target["webSocketDebuggerUrl"]);c.call("Page.enable");c.navigate(base+"/login")
    c.evaluate(f"document.querySelector('#identifier').value={json.dumps(username)};document.querySelector('#password').value={json.dumps(password)};document.querySelector('form').submit()");time.sleep(.5)
    def overview(year):
        c.navigate(f"{base}/?year={year}")
        return c.evaluate("""({url:location.search,year:document.querySelector('#overview-year').value,
          projects:[...document.querySelectorAll('.overview-project>summary>span:first-child strong')].map(e=>e.textContent.trim()),
          totals:[...document.querySelectorAll('.project-total-table strong')].map(e=>e.textContent.trim()),
          current:document.querySelectorAll('.current-month').length,empty:document.querySelector('.empty-state')?.textContent.trim()||''})""")
    y25=overview(2025);y26=overview(2026);again=overview(2025)
    assert y25["url"]=="?year=2025" and y25["year"]=="2025" and y25["projects"]==["YBOTH","Y25ONLY"],y25
    assert "TST" in y26["projects"] and "YBOTH" in y26["projects"] and "Y26ONLY" in y26["projects"] and "Y25ONLY" not in y26["projects"],y26
    assert y25["totals"]!=y26["totals"] and again==y25
    assert y25["current"]==0 and y26["current"]>0
    c.navigate(base+"/?year=2024");empty=c.evaluate("document.querySelector('.empty-state')?.textContent||''");assert "2024" in empty
    def capacity(year):
        c.navigate(f"{base}/capacity?year={year}")
        return c.evaluate("""({url:location.search,year:document.querySelector('#capacity-year').value,count:document.querySelectorAll('[data-capacity-person]').length,
          expanded:[...document.querySelectorAll('[data-capacity-toggle]')].map(e=>e.getAttribute('aria-expanded')),
          feb:[...document.querySelectorAll('[data-capacity-person]')].find(e=>e.dataset.personSearch.includes('cap01@example.test'))?.querySelector('tbody tr:first-child td:nth-of-type(2)')?.textContent.trim(),
          doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth]})""")
    cap26=capacity(2026);assert cap26["url"]=="?year=2026" and cap26["year"]=="2026" and cap26["count"]>=10 and set(cap26["expanded"])=={"false"} and cap26["feb"].startswith("0.00"),cap26
    c.evaluate("document.querySelector('[data-capacity-expand-all]').click()");assert set(c.evaluate("[...document.querySelectorAll('[data-capacity-toggle]')].map(e=>e.getAttribute('aria-expanded'))"))=={"true"}
    c.evaluate("document.querySelector('[data-capacity-collapse-all]').click()");assert set(c.evaluate("[...document.querySelectorAll('[data-capacity-toggle]')].map(e=>e.getAttribute('aria-expanded'))"))=={"false"}
    cap27=capacity(2027);assert cap27["feb"].startswith("80.00") and cap27["feb"]!=cap26["feb"],(cap26,cap27)
    responsive=[]
    for width,height in ((1920,1080),(1440,900),(1366,768),(390,844)):
        c.call("Emulation.setDeviceMetricsOverride",{"width":width,"height":height,"deviceScaleFactor":1,"mobile":False})
        for state in (("expanded","collapsed") if width>=768 else ("expanded",)):
            c.evaluate(f"localStorage.setItem('iaspm.sidebar',{json.dumps(state)})");c.navigate(base+"/capacity?year=2026")
            c.evaluate("document.querySelector('[data-capacity-expand-all]').click()")
            row=c.evaluate("""({width:innerWidth,doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth],
                sidebar:document.querySelector('.app-sidebar').getBoundingClientRect().width,
                table:[document.querySelector('.capacity-overview-table').clientWidth,document.querySelector('.capacity-overview-table').scrollWidth]})""")
            assert row["doc"][0]==row["doc"][1],row;responsive.append({"state":state,**row})
    viewer_user=os.environ.get("CORRECTIVE_VIEWER_USER");viewer_password=os.environ.get("CORRECTIVE_VIEWER_PASSWORD");viewer_person=os.environ.get("CORRECTIVE_VIEWER_PERSON")
    viewer={}
    if viewer_user and viewer_password and viewer_person:
        c.call("Network.enable");c.call("Network.clearBrowserCookies");c.navigate(base+"/login")
        c.evaluate(f"document.querySelector('#identifier').value={json.dumps(viewer_user)};document.querySelector('#password').value={json.dumps(viewer_password)};document.querySelector('form').submit()");time.sleep(.5)
        c.navigate(base+"/capacity?year=2026");viewer["global"]=c.evaluate("({status:document.querySelector('.display-1')?.textContent.trim()||'',body:document.body.textContent})")
        assert viewer["global"]["status"]=="403" and "Capacity01" not in viewer["global"]["body"],viewer
        c.navigate(f"{base}/people/{viewer_person}/capacity?year=2026");viewer["own"]=c.evaluate("document.querySelector('h1')?.textContent||''")
        assert "capacity" in viewer["own"].lower(),viewer
    print(json.dumps({"overview2025":y25,"overview2026":y26,"capacity2026":cap26,"capacity2027":cap27,"responsive":responsive,"viewer":viewer},indent=2))
finally:
    chrome.terminate();chrome.wait(timeout=5);shutil.rmtree(profile,ignore_errors=True)
