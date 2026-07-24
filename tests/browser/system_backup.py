#!/usr/bin/env python3
"""Authenticated browser acceptance test for the operations dashboard and SQL backup."""
import json,os,shutil,subprocess,tempfile,time
from milestone11_1_layout import Cdp,wait_json

base=os.environ.get("SYSTEM_BROWSER_BASE_URL","http://localhost:8080").rstrip("/")
profile=tempfile.mkdtemp(prefix="system-backup-");port=9339
chrome=subprocess.Popen(["google-chrome","--headless=new","--no-sandbox","--disable-gpu",f"--remote-debugging-port={port}",
 f"--remote-allow-origins=*",f"--user-data-dir={profile}","about:blank"],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
 c=Cdp(next(x for x in wait_json(f"http://127.0.0.1:{port}/json") if x.get("type")=="page")["webSocketDebuggerUrl"])
 c.call("Page.enable");c.call("Network.enable");c.navigate(base+"/login")
 c.evaluate(f"document.querySelector('#identifier').value={json.dumps(os.environ['SYSTEM_ADMIN_USER'])};document.querySelector('#password').value={json.dumps(os.environ['SYSTEM_ADMIN_PASSWORD'])};document.querySelector('form').submit()");time.sleep(.5)
 c.navigate(base+"/admin/system")
 sections=c.evaluate("[...document.querySelectorAll('section>h2,section>div>h2')].map(e=>e.textContent.trim())")
 assert all(name in sections for name in ("Application","Runtime","Database","Diagnostics","Maintenance")),sections
 result=c.evaluate("""(async()=>{const form=document.querySelector('form[action$="/admin/system/backup"]');
   const body=new FormData(form);const response=await fetch(form.action,{method:"POST",body});
   const sql=await response.text();return {status:response.status,type:response.headers.get("content-type"),
   disposition:response.headers.get("content-disposition"),start:sql.slice(0,500),end:sql.slice(-100)};})()""")
 assert result["status"]==200 and "application/sql" in result["type"],result
 assert "iaslab-projects-" in result["disposition"],result
 assert "SET NAMES utf8mb4" in result["start"] and "SET FOREIGN_KEY_CHECKS=1" in result["end"],result
 print(json.dumps({"sections":sections,"backup":result},indent=2))
finally:
 chrome.terminate();chrome.wait(timeout=5);shutil.rmtree(profile,ignore_errors=True)
