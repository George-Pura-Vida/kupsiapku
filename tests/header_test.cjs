/* Run: node tests/header_test.cjs. Requires Playwright and its Chromium browser. */
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const files = process.env.HEADER_TEST_FILES ? process.env.HEADER_TEST_FILES.split(',') : [ ...fs.readdirSync(root).filter(f => f.endsWith('.html')), ...fs.readdirSync(path.join(root,'en')).filter(f => f.endsWith('.html')).map(f => 'en/'+f) ];
const types = {'.html':'text/html', '.css':'text/css', '.js':'text/javascript', '.svg':'image/svg+xml'};
const server = http.createServer((req,res) => {
  let p = decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if (p.endsWith('/')) p += 'index.html';
  const file = path.join(root,p);
  if (!file.startsWith(root+path.sep) || !fs.existsSync(file)) { res.writeHead(404); return res.end(); }
  res.setHeader('Content-Type',(types[path.extname(file)] || 'application/octet-stream')+'; charset=utf-8');
  res.end(fs.readFileSync(file));
});
(async () => {
  await new Promise(r => server.listen(0,'127.0.0.1',r));
  const base = process.env.HEADER_TEST_URL || `http://127.0.0.1:${server.address().port}`;
  const browser = await chromium.launch({headless:true});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.addInitScript(() => localStorage.setItem('ksa_cookie','1'));
    const widths = [320,375,768,800,801,1024,1250,1251,1366,1920];
    let checked = 0;
    for (const file of files) {
      await page.goto(base+'/'+file,{waitUntil:'load'});
      assert.equal(await page.locator('.siteHeader').evaluate(h=>Math.round(h.getBoundingClientRect().top)),0,`${file}: header top`);
      assert.equal(await page.locator('.siteHeader').count(),1,file);
      assert.equal(await page.locator('.siteHeader-nav').count(),1,file);
      assert.match(await page.locator('.siteHeader-brand').innerText(),/KUP SI APKU/,file);
      const hrefs=await page.locator('.siteHeader a').evaluateAll(els=>els.map(e=>e.getAttribute('href')));
      for (const href of hrefs) {
        const url = new URL(href,'http://local/'+file);
        const target = url.pathname.endsWith('/') ? url.pathname+'index.html' : url.pathname;
        assert.ok(fs.existsSync(path.join(root,target)),`${file}: missing ${href}`);
        if (url.hash) assert.ok(fs.readFileSync(path.join(root,target),'utf8').includes(`id="${url.hash.slice(1)}"`),`${file}: missing fragment ${href}`);
      }
      for (const width of widths) {
        await page.setViewportSize({width,height:900});
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const toggle=page.locator('.siteHeader-toggle');
        const nav=page.locator('.siteHeader-nav');
        if (width <= 800) {
          await toggle.focus();
          await page.keyboard.press('Enter');
          assert.equal(await toggle.getAttribute('aria-expanded'),'true',file);
          assert.ok(await nav.isVisible(),file);
          await page.keyboard.press('Tab');
          assert.ok(await nav.evaluate(n=>n.contains(document.activeElement)),`${file} @ ${width}: Tab focus ${await page.evaluate(()=>document.activeElement.outerHTML)}`);
          await page.keyboard.press('Escape');
          assert.equal(await toggle.getAttribute('aria-expanded'),'false',file);
          assert.ok(await toggle.evaluate(t=>t===document.activeElement),file);
          assert.ok(!await nav.isVisible(),file);
          await page.keyboard.press('Space');
          assert.ok(await nav.isVisible(),file);
        } else {
          assert.ok(!await toggle.isVisible(),file);
          assert.ok(await nav.isVisible(),file);
          assert.equal(await toggle.getAttribute('aria-expanded'),'false',file);
        }
        const bad = await page.locator('.siteHeader').evaluate(header => {
          const visible=[...header.querySelectorAll('a,button')].filter(el=>el.getClientRects().length);
          const boxes=visible.map(el=>({text:el.textContent.trim(),r:el.getBoundingClientRect()}));
          const problems=[];
          for (let i=0;i<boxes.length;i++) {
            const a=boxes[i];
            if (a.r.left < -1 || a.r.right>innerWidth+1) problems.push('overflow '+a.text);
            for(let j=i+1;j<boxes.length;j++) {
              const b=boxes[j];
              if(Math.min(a.r.right,b.r.right)-Math.max(a.r.left,b.r.left)>1 && Math.min(a.r.bottom,b.r.bottom)-Math.max(a.r.top,b.r.top)>1) problems.push('overlap '+a.text+' / '+b.text);
            }
          }
          return problems;
        });
        assert.deepEqual(bad,[],`${file} @ ${width}`);
        if (width <=800) await page.keyboard.press('Escape');
        checked++;
      }
    }
    await page.setViewportSize({width:375,height:667});
    await page.goto(base+'/index.html');
    await page.locator('.siteHeader-toggle').click();
    await page.locator('.siteHeader-nav a[href="#apps"]').click();
    assert.equal(await page.locator('.siteHeader-toggle').getAttribute('aria-expanded'),'false');
    await page.getByRole('button',{name:'Přidat do košíku'}).first().click();
    assert.equal(await page.locator('#count').innerText(),'1');
    await page.locator('.siteHeader-toggle').click();
    await page.locator('.siteHeader-cart').click();
    assert.equal(await page.locator('.siteHeader-toggle').getAttribute('aria-expanded'),'false');
    await page.goto(base+'/en/index.html#order');
    await page.locator('[data-lang-target="cs"]').click();
    await page.waitForURL(url=>url.hash==='#checkout');
    await page.locator('[data-lang-target="en"]').click();
    await page.waitForURL('**/en/index.html#order');
    // No JavaScript: static navigation must remain usable.
    const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:375,height:667}});
    const fallback=await nojs.newPage();
    await fallback.goto(base+'/doporucit.html');
    assert.ok(await fallback.locator('.siteHeader-nav').isVisible());
    assert.ok(!await fallback.locator('.siteHeader-toggle').isVisible());
    await nojs.close();
    assert.deepEqual(errors,[],'Uncaught JavaScript errors');
    console.log(`PASS: ${files.length} pages, ${checked} viewport checks, header links/fragments, keyboard/ARIA, cart, language sections, no-JS fallback`);
    if (process.env.HEADER_SCREENSHOTS) {
      fs.mkdirSync(process.env.HEADER_SCREENSHOTS,{recursive:true});
      for (const width of [375,1024,1366]) {
        await page.setViewportSize({width,height:900});
        await page.goto(base+'/index.html');
        if(width<=800) await page.locator('.siteHeader-toggle').click();
        await page.screenshot({path:path.join(process.env.HEADER_SCREENSHOTS,`header-${width}.png`)});
      }
    }
  } finally { await browser.close(); server.close(); }
})().catch(e=>{console.error(e); server.close(); process.exitCode=1;});
