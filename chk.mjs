import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
const b = await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
const fails=[]; const ok=(c,m)=>{console.log((c?'  ✓ ':'  ✗ ')+m); if(!c)fails.push(m);};
const p = await b.newPage({viewport:{width:1280,height:900}});
const errs=[]; p.on('pageerror',e=>errs.push(e.message));
p.on('console',m=>{if(m.type()==='error')errs.push(m.text());});
const ext=[]; p.on('request',r=>{if(!r.url().startsWith('http://127.0.0.1')&&!r.url().startsWith('data:'))ext.push(r.url());});

await p.goto('http://127.0.0.1:9300/checklist.html',{waitUntil:'networkidle'});
ok((await p.locator('.step').count())===29,'۲۹ گام رندر شد');
ok(ext.length===0,'هیچ درخواست خارجی ('+(ext.join(',')||'صفر')+')');
ok(await p.evaluate(()=>getComputedStyle(document.body).fontFamily.includes('Dana')),'فونت دانا اعمال شد');
ok((await p.locator('#pct').textContent()).trim()==='۰٪','درصد اولیه صفر');

// تیک‌زدن
await p.locator('.step[data-id="s1"] .s-head').click();
await p.waitForTimeout(300);
ok(await p.locator('.step[data-id="s1"]').evaluate(e=>e.classList.contains('done')),'تیک اول خورد');
ok((await p.locator('#pct').textContent()).includes('۳'),'درصد به‌روز شد: '+(await p.locator('#pct').textContent()));

// ماندگاری
await p.reload({waitUntil:'networkidle'}); await p.waitForTimeout(400);
ok(await p.locator('.step[data-id="s1"]').evaluate(e=>e.classList.contains('done')),'بعد از رفرش تیک ماند');

// کپی نباید تیک بزند
const before = await p.locator('.step[data-id="s1"]').evaluate(e=>e.classList.contains('done'));
await p.locator('.step[data-id="s1"] [data-copy]').first().click();
await p.waitForTimeout(300);
ok(await p.locator('.step[data-id="s1"]').evaluate(e=>e.classList.contains('done'))===before,'کلیک روی «کپی» وضعیت تیک را عوض نکرد');

// صفحه‌کلید
await p.locator('.step[data-id="s2"] .s-head').focus();
await p.keyboard.press('Space'); await p.waitForTimeout(250);
ok(await p.locator('.step[data-id="s2"]').evaluate(e=>e.classList.contains('done')),'با کلید Space هم تیک می‌خورد');

// پاک‌کردن
await p.locator('#resetBtn').click(); await p.waitForTimeout(300);
ok((await p.locator('#pct').textContent()).trim()==='۰٪','پاک‌کردن کار کرد');

// سرریز افقی
for(const w of [360,414,768,1024,1440]){
  await p.setViewportSize({width:w,height:900}); await p.waitForTimeout(200);
  const o=await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
  ok(o<=1,'بدون سرریز در '+w+'px'+(o>1?' ('+o+')':''));
}
// تم تاریک
await p.setViewportSize({width:1280,height:900});
await p.locator('#themeBtn').click(); await p.waitForTimeout(300);
const dark = await p.evaluate(()=>getComputedStyle(document.body).backgroundColor);
ok(dark.includes('7, 12, 20')||dark.includes('rgb(7'),'تم تاریک اعمال شد: '+dark);
ok(errs.length===0,'بدون خطا ('+(errs.join(' | ')||'صفر')+')');

console.log('\n'+(fails.length?`✗ ${fails.length} رد شد`:'✓ همه گذشت'));
await b.close(); process.exit(fails.length?1:0);
