#!/usr/bin/env python3
"""Authenticated Chrome DevTools regression for Milestone 11.1.

The temporary test account and representative project must already exist. Credentials
are read from environment variables and are never printed.
"""
from __future__ import annotations
import argparse, json, os, pathlib, shutil, subprocess, tempfile, time, urllib.request
import websocket

def wait_json(url: str, timeout: float = 10):
    end=time.time()+timeout
    while time.time()<end:
        try:
            with urllib.request.urlopen(url,timeout=1) as response:return json.load(response)
        except Exception:time.sleep(.1)
    raise RuntimeError("Chrome DevTools endpoint did not start")

class Cdp:
    def __init__(self,url):
        self.ws=websocket.create_connection(url,timeout=30,origin="http://localhost")
        self.serial=0
    def call(self,method,params=None):
        self.serial+=1;current=self.serial
        self.ws.send(json.dumps({"id":current,"method":method,"params":params or {}}))
        while True:
            message=json.loads(self.ws.recv())
            if message.get("id")==current:
                if "error" in message:raise RuntimeError(message["error"])
                return message.get("result",{})
    def evaluate(self,expression):
        return self.call("Runtime.evaluate",{"expression":expression,"returnByValue":True,"awaitPromise":True})["result"].get("value")
    def navigate(self,url):
        self.call("Page.navigate",{"url":url})
        for _ in range(100):
            if self.evaluate("document.readyState")=="complete":break
            time.sleep(.05)
        time.sleep(.2)

MEASURE=r"""(() => {
 const one=s=>document.querySelector(s), rect=e=>e?({left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height,client:e.clientWidth,scroll:e.scrollWidth}):null;
 const overflow=[...document.querySelectorAll('*')].filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>({tag:e.tagName,id:e.id,cls:e.className,client:e.clientWidth,scroll:e.scrollWidth})).slice(0,30);
 const outside=[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return r.right>document.documentElement.clientWidth+1}).map(e=>{const r=e.getBoundingClientRect();return{tag:e.tagName,id:e.id,cls:e.className,left:r.left,right:r.right,width:r.width}}).slice(0,30);
 const grid=one('[data-grid-scroll],.effort-grid'), table=grid?.querySelector('.effort-table');
 const headers=table?[...table.querySelectorAll('thead th')]:[];
 const sidebar=one('.app-sidebar'),main=one('.app-main'),header=one('.app-header'),logo=one('.app-logo'),title=one('.app-title'),toggle=one('[data-sidebar-toggle]');
 return {url:location.pathname,viewport:{width:innerWidth,height:innerHeight,dpr:devicePixelRatio},
 document:{client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,bodyScroll:document.body.scrollWidth},
 shell:rect(one('.app-shell')),main:rect(main),container:rect(one('.app-main>.container-fluid')),sidebar:rect(sidebar),header:rect(header),logo:rect(logo),
 title:{...rect(title),fontSize:getComputedStyle(title).fontSize,fontWeight:getComputedStyle(title).fontWeight},collapsed:one('.app-shell')?.classList.contains('sidebar-collapsed'),toggleExpanded:toggle?.getAttribute('aria-expanded'),
 grid:rect(grid),table:rect(table),hierarchy:rect(table?.querySelector('col.participant-column')),month:rect(table?.querySelector('col.month-column-width')),annual:rect(table?.querySelector('col.annual-column')),
 monthHeaders:headers.length>=14?12:headers.filter(h=>/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)$/.test(h.textContent.trim())).length,
 december:rect(headers.at(-2)),annualHeader:rect(headers.at(-1)),overflow,outside};
})()"""

CONTRAST=r"""(() => {
 const rgb=s=>{const m=s.match(/[\d.]+/g).map(Number);return m.slice(0,3)};
 const lum=c=>{c=c/255;return c<=.04045?c/12.92:Math.pow((c+.055)/1.055,2.4)};
 const ratio=(a,b)=>{const A=.2126*lum(a[0])+.7152*lum(a[1])+.0722*lum(a[2]),B=.2126*lum(b[0])+.7152*lum(b[1])+.0722*lum(b[2]);return (Math.max(A,B)+.05)/(Math.min(A,B)+.05)};
 const bg=e=>{while(e){const c=getComputedStyle(e).backgroundColor;if(!c.endsWith(', 0)')&&c!=='rgba(0, 0, 0, 0)'&&c!=='transparent')return c;e=e.parentElement}return 'rgb(255, 255, 255)'};
 const read=(name,selector,pseudo=null)=>{const e=document.querySelector(selector);if(!e)return{name,missing:true};const base=getComputedStyle(e),s=getComputedStyle(e,pseudo),own=base.backgroundColor,b=own==='rgba(0, 0, 0, 0)'||own==='transparent'?getComputedStyle(document.querySelector('.app-sidebar')).backgroundColor:own;return{name,fg:s.color,bg:b,ratio:+ratio(rgb(s.color),rgb(b)).toFixed(2)}};
 return [read('normal','.sidebar-link:not(.active)'),read('project','.sidebar-project:not(.active)'),read('active','.sidebar-link.active,.sidebar-project.active'),read('section','.sidebar-section-heading'),read('search','.sidebar-search'),read('placeholder','.sidebar-search','::placeholder')];
})()"""

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument("--base-url",default="http://localhost:8080")
    parser.add_argument("--project",type=int,required=True)
    parser.add_argument("--assert-fit",action="store_true")
    parser.add_argument("--screenshots",action="store_true")
    args=parser.parse_args()
    username=os.environ["M111_BROWSER_USER"];password=os.environ["M111_BROWSER_PASSWORD"]
    profile=tempfile.mkdtemp(prefix="m111-chrome-");port=9333
    chrome=subprocess.Popen(["google-chrome","--headless=new","--no-sandbox","--disable-gpu","--force-device-scale-factor=1",f"--remote-debugging-port={port}","--remote-allow-origins=*",
        f"--user-data-dir={profile}","about:blank"],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        targets=wait_json(f"http://127.0.0.1:{port}/json")
        target=next(item for item in targets if item.get("type")=="page" and item.get("url")=="about:blank")
        cdp=Cdp(target["webSocketDebuggerUrl"]);cdp.call("Page.enable")
        cdp.navigate(args.base_url+"/login")
        cdp.evaluate(f"""document.querySelector('#identifier').value={json.dumps(username)};document.querySelector('#password').value={json.dumps(password)};document.querySelector('form').submit();""")
        time.sleep(.5)
        results=[]
        for width,height in [(1920,1080),(1440,900),(1366,768),(390,844)]:
            cdp.call("Emulation.setDeviceMetricsOverride",{"width":width,"height":height,"deviceScaleFactor":1,"mobile":False})
            states=("expanded","collapsed") if width>=768 else ("expanded",)
            for state in states:
                cdp.evaluate(f"localStorage.setItem('iaspm.sidebar',{json.dumps(state)})")
                for name,path in [("overview","/"),("read",f"/projects/{args.project}?year=2026"),("edit",f"/projects/{args.project}/effort/edit?year=2026")]:
                    cdp.navigate(args.base_url+path);measurement=cdp.evaluate(MEASURE);measurement.update({"name":name,"state":state,"requested":[width,height]})
                    measurement["sidebarState"]=cdp.evaluate("""(() => {const link=document.querySelector('.sidebar-link,.sidebar-project');link?.focus();const s=link?getComputedStyle(link):null,tip=link?getComputedStyle(link,'::after'):null;const state={focusOutline:s?.outlineStyle,focusWidth:s?.outlineWidth,active:document.querySelectorAll('.sidebar-link.active,.sidebar-project.active').length,currentProject:document.querySelectorAll('.sidebar-project[aria-current="page"]').length,tooltip:tip?.content};link?.blur();return state})()""")
                    if name=="overview":measurement["contrast"]=cdp.evaluate(CONTRAST)
                    if args.screenshots:
                        shot=cdp.call("Page.captureScreenshot",{"format":"png","captureBeyondViewport":False})["data"]
                        pathlib.Path(f"/tmp/m111-{name}-{state}-{width}.png").write_bytes(__import__("base64").b64decode(shot))
                    results.append(measurement)
        print(json.dumps(results,indent=2))
        if args.assert_fit:
            failures=[]
            for item in results:
                width=item["requested"][0];doc=item["document"];grid=item["grid"]
                if doc["scroll"]>doc["client"]:failures.append(f"{item['name']} {width}: document overflow")
                if width>=1440 and grid and grid["scroll"]>grid["client"]+1:failures.append(f"{item['name']} {width}: grid overflow")
                if width>=1440 and item["monthHeaders"]!=12:failures.append(f"{item['name']} {width}: month headers")
                expected=56 if item["state"]=="collapsed" and width>=768 else (248 if width>=768 else item["sidebar"]["width"])
                if width>=768 and abs(item["sidebar"]["width"]-expected)>1:failures.append(f"{item['name']} {width} {item['state']}: sidebar width")
                if width>=768 and item["toggleExpanded"]!=("false" if item["state"]=="collapsed" else "true"):failures.append(f"{item['name']} {width} {item['state']}: aria-expanded")
                if width>=1440 and item["annualHeader"] and item["annualHeader"]["right"]>item["grid"]["right"]+1:failures.append(f"{item['name']} {width}: annual clipped")
                if item["sidebar"] and item["main"] and item["sidebar"]["right"]>item["main"]["left"]+1:failures.append(f"{item['name']} {width}: sidebar overlaps main")
                if item["main"] and item["main"]["right"]>item["viewport"]["width"]+1:failures.append(f"{item['name']} {width}: main exceeds viewport")
                if width>=768 and item["sidebarState"]["focusWidth"] in (None,"0px"):failures.append(f"{item['name']} {width}: sidebar focus invisible")
                if item["name"]!="overview" and item["sidebarState"]["currentProject"]!=1:failures.append(f"{item['name']} {width}: current project state")
                if item["name"]=="overview":
                    for contrast in item.get("contrast",[]):
                        if not contrast.get("missing") and contrast["ratio"]<4.5:failures.append(f"{width}: {contrast['name']} contrast")
            desktop={(item["requested"][0],item["name"],item["state"]):item for item in results if item["requested"][0]>=768}
            for width in (1920,1440,1366):
                for name in ("overview","read","edit"):
                    expanded=desktop[(width,name,"expanded")];collapsed=desktop[(width,name,"collapsed")]
                    if collapsed["main"]["width"]<expanded["main"]["width"]+190:failures.append(f"{name} {width}: main did not expand")
                    if collapsed["grid"] and expanded["grid"] and collapsed["grid"]["client"]<expanded["grid"]["client"]+190:failures.append(f"{name} {width}: grid did not expand")
            cdp.call("Emulation.setDeviceMetricsOverride",{"width":1440,"height":900,"deviceScaleFactor":1,"mobile":False})
            cdp.evaluate("localStorage.setItem('iaspm.sidebar','collapsed')");cdp.navigate(args.base_url+"/")
            reveal=cdp.evaluate("""(() => {document.querySelector('[data-sidebar-reveal]').click();return{collapsed:document.querySelector('.app-shell').classList.contains('sidebar-collapsed'),stored:localStorage.getItem('iaspm.sidebar')}})()""")
            if reveal!={"collapsed":False,"stored":"collapsed"}:failures.append("collapsed Projects control did not temporarily expand")
            cdp.navigate(args.base_url+"/")
            if not cdp.evaluate("document.querySelector('.app-shell').classList.contains('sidebar-collapsed')"):failures.append("collapsed preference was not restored")
            cdp.evaluate("document.querySelector('[data-sidebar-toggle]').focus()")
            cdp.call("Input.dispatchKeyEvent",{"type":"keyDown","key":" ","code":"Space"})
            cdp.call("Input.dispatchKeyEvent",{"type":"keyUp","key":" ","code":"Space"})
            keyboard=cdp.evaluate("""({collapsed:document.querySelector('.app-shell').classList.contains('sidebar-collapsed'),stored:localStorage.getItem('iaspm.sidebar')})""")
            if keyboard!={"collapsed":False,"stored":"expanded"}:failures.append("keyboard toggle did not expand and persist")
            cdp.evaluate("localStorage.setItem('iaspm.sidebar','collapsed')")
            cdp.call("Emulation.setScriptExecutionDisabled",{"value":True});cdp.navigate(args.base_url+"/")
            if abs(cdp.evaluate("document.querySelector('.app-sidebar').getBoundingClientRect().width")-248)>1:failures.append("JavaScript-disabled sidebar was not expanded")
            cdp.call("Emulation.setScriptExecutionDisabled",{"value":False})
            cdp.call("Emulation.setDeviceMetricsOverride",{"width":390,"height":844,"deviceScaleFactor":1,"mobile":False})
            cdp.navigate(args.base_url+"/");cdp.evaluate("document.querySelector('.sidebar-mobile-toggle').click()");time.sleep(.5)
            mobile=cdp.evaluate("""({width:document.querySelector('.app-sidebar').getBoundingClientRect().width,open:document.querySelector('.app-sidebar').classList.contains('show'),doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth]})""")
            if abs(mobile["width"]-248)>1 or not mobile["open"]:failures.append("mobile off-canvas did not open at expanded width")
            if mobile["doc"][1]>mobile["doc"][0]:failures.append("mobile off-canvas introduced document overflow")
            if failures:raise SystemExit("\n".join(failures))
    finally:
        chrome.terminate();chrome.wait(timeout=5);shutil.rmtree(profile,ignore_errors=True)
if __name__=="__main__":main()
