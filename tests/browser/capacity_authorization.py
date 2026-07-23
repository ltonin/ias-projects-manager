#!/usr/bin/env python3
"""Rendered-output authorization acceptance test for global capacity."""
import json,os,shutil,subprocess,tempfile,time
from milestone11_1_layout import Cdp,wait_json
base=os.environ.get("CAPACITY_AUTH_BASE_URL","http://localhost:8080");password=os.environ["CAPACITY_AUTH_PASSWORD"]
profile=tempfile.mkdtemp(prefix="capacity-auth-");port=9335
chrome=subprocess.Popen(["google-chrome","--headless=new","--no-sandbox","--disable-gpu",f"--remote-debugging-port={port}","--remote-allow-origins=*",
 f"--user-data-dir={profile}","about:blank"],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
 c=Cdp(next(x for x in wait_json(f"http://127.0.0.1:{port}/json") if x.get("type")=="page")["webSocketDebuggerUrl"]);c.call("Page.enable");c.call("Network.enable")
 def login(user):
  c.call("Network.clearBrowserCookies");c.navigate(base+"/login");c.evaluate(f"document.querySelector('#identifier').value={json.dumps(user)};document.querySelector('#password').value={json.dumps(password)};document.querySelector('form').submit()");time.sleep(.4)
 login("scope.manager");c.navigate(base+"/capacity?year=2026");manager=c.evaluate("document.body.textContent")
 assert "Scope Manager" in manager and "Allowed Capacity" in manager and "Forbidden Capacity" not in manager
 login("scope.viewer");c.navigate(base+"/capacity?year=2026");denied=c.evaluate("({status:document.querySelector('.display-1')?.textContent.trim(),body:document.body.textContent})")
 assert denied["status"]=="403" and "Allowed Capacity" not in denied["body"] and "Forbidden Capacity" not in denied["body"]
 c.navigate(base+f"/people/{os.environ['CAPACITY_VIEWER_PERSON']}/capacity?year=2026");own=c.evaluate("document.querySelector('h1')?.textContent")
 assert "Scope Viewer capacity" in own
 print(json.dumps({"manager_scope":"self + managed-project participant; unrelated absent","viewer_global":403,"viewer_own":own}))
finally:
 chrome.terminate();chrome.wait(timeout=5);shutil.rmtree(profile,ignore_errors=True)
