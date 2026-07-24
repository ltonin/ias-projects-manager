#!/usr/bin/env python3
"""Authenticated cross-role visual consistency and responsive review."""
import base64,json,os,pathlib,shutil,subprocess,tempfile,time
from milestone11_1_layout import Cdp,wait_json

base=os.environ.get("M17_BASE_URL","http://localhost:18080").rstrip("/")
role=os.environ["M17_ROLE"];routes=json.loads(os.environ["M17_ROUTES"])
output=pathlib.Path(os.environ.get("M17_SCREENSHOT_DIR","/tmp/m17-screens"));output.mkdir(parents=True,exist_ok=True)
profile=tempfile.mkdtemp(prefix=f"m17-{role}-");port={"admin":9341,"manager":9342,"user":9343}[role]
chrome=subprocess.Popen(["google-chrome","--headless=new","--no-sandbox","--disable-gpu",f"--remote-debugging-port={port}",
 f"--remote-allow-origins=*",f"--user-data-dir={profile}","about:blank"],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
 c=Cdp(next(x for x in wait_json(f"http://127.0.0.1:{port}/json") if x.get("type")=="page")["webSocketDebuggerUrl"])
 c.call("Page.enable");c.navigate(base+"/login")
 c.evaluate(f"document.querySelector('#identifier').value={json.dumps(os.environ['M17_USER'])};document.querySelector('#password').value={json.dumps(os.environ['M17_PASSWORD'])};document.querySelector('form').submit()")
 time.sleep(.6)
 assert c.evaluate("location.pathname")!="/login","Authentication failed"
 results=[]
 for width,height in ((1440,900),(768,1024),(390,844)):
  c.call("Emulation.setDeviceMetricsOverride",{"width":width,"height":height,"deviceScaleFactor":1,"mobile":False})
  for name,path in routes:
   c.navigate(base+path)
   c.call("Input.dispatchKeyEvent",{"type":"keyDown","key":"Tab","code":"Tab"})
   c.call("Input.dispatchKeyEvent",{"type":"keyUp","key":"Tab","code":"Tab"})
   state=c.evaluate("""(()=>{const visible=e=>{const s=getComputedStyle(e),r=e.getBoundingClientRect();return s.display!=="none"&&s.visibility!=="hidden"&&r.width>0};
    const buttons=[...document.querySelectorAll(".app-main .btn")].filter(visible).map(e=>({text:e.textContent.trim(),width:e.getBoundingClientRect().width}));
    const headers=[...document.querySelectorAll(".table thead th")].map(e=>getComputedStyle(e).backgroundColor);
    const target=document.activeElement;const focus=target?getComputedStyle(target):null;
    return {path:location.pathname,status:document.querySelector(".display-1")?.textContent.trim()||"",
      doc:[document.documentElement.clientWidth,document.documentElement.scrollWidth],buttons,headers,
      focus:{style:focus?.outlineStyle||"",width:focus?.outlineWidth||""},
      cards:document.querySelectorAll(".app-main .card").length,tables:document.querySelectorAll(".app-main .table").length,
      filters:document.querySelectorAll(".filter-toolbar,.overview-tools,.capacity-overview-tools").length,
      actionMenus:document.querySelectorAll(".table-actions .dropdown-toggle[aria-label]").length};})()""")
   assert state["status"] not in ("403","404","500"),state
   assert state["doc"][1] <= state["doc"][0],state
   assert all(button["width"] < 300 for button in state["buttons"]),state
   assert state["focus"]["style"]!="none" and state["focus"]["width"]!="0px",state
   shot=c.call("Page.captureScreenshot",{"format":"png","captureBeyondViewport":False})["data"]
   (output/f"{role}-{name}-{width}.png").write_bytes(base64.b64decode(shot))
   state.update({"role":role,"name":name,"viewport":width});results.append(state)
 print(json.dumps(results))
finally:
 chrome.terminate();chrome.wait(timeout=5);shutil.rmtree(profile,ignore_errors=True)
