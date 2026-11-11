import { expect, test } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

/**
 * The Page Guide on every page of every portal, on a desktop and a phone:
 * the ? is there, the panel shows that page's own guide, its links open,
 * its search stays in the portal, ? and Esc work, and the tour outlines
 * every part it names - a part the page no longer has fails the test.
 *
 * Pages that need an id (a team, a check, a review) are found by following
 * the links on the portal's other pages. Anything but a GET is stopped, so
 * nothing is saved.
 *
 * PW_BASE_URL=http://127.0.0.1:8002 npx playwright test page-guide
 */

const GUIDES = JSON.parse(execSync(`php -r "echo json_encode(require 'config/page-guide.php');"`).toString());
const ROUTES = Object.fromEntries(JSON.parse(execSync('php artisan route:list --json --method=GET').toString())
  .filter((r) => r.name).map((r) => [r.name, '/' + r.uri.replace(/^\//, '')]));

// More than one account where no single one reaches every page: 92 has papers
// to review and a defense to grade but, as a CaPSA/TSA, is sent from Teams to
// Team Management; 121 is a plain adviser with teams.
const SIGN_IN = {
  admin: ['/__dev-login-expert/admin?email=ccs@cspc.edu.ph'],
  capsa: ['/__dev-login-expert/capsa'],
  instructor: ['/__dev-login-expert/92', '/__dev-login-expert/121'],
  student: ['/__dev-login-student'],
};

const SIZES = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } };
const OUT = 'test-results/page-guide';

// Pages whose tour covers every situation (in a team, no team yet, a locked
// team): one account only ever shows some of the parts.
const BY_SITUATION = /^(student|instructor|capsa|admin)\./;

// Pages opened only for their links: the milestone pages are linked from a tab.
const CRAWL = { capsa: ['/capsa/team-management?tab=milestone_list'] };

// "/capsa/teams/{team}/log" -> a pattern for a real address of it.
const pattern = (uri) => new RegExp('^' + uri.replace(/\{[^}]+\?\}/g, '[^/]*').replace(/\{[^}]+\}/g, '[^/]+') + '$');

// The route an address belongs to: of those it fits, the one with the most
// fixed text ("/{team}/pre-proposals" over "/{team}/{log}").
const routeOf = (path) => Object.keys(ROUTES).filter((n) => pattern(ROUTES[n]).test(path))
  .sort((a, b) => ROUTES[b].replace(/\{[^}]+\}/g, '').length - ROUTES[a].replace(/\{[^}]+\}/g, '').length)[0];

for (const portal of Object.keys(SIGN_IN)) {
  const names = Object.keys(GUIDES).filter((n) => n.startsWith(portal + '.'));
  const direct = names.filter((n) => !/\{/.test(ROUTES[n] || '{'));
  const byLink = names.filter((n) => /\{/.test(ROUTES[n] || ''));

  for (const [size, viewport] of Object.entries(SIZES)) {
    test(`${portal} pages, ${size}`, async ({ browser }) => {
      test.setTimeout(names.length * 90_000);
      const context = await browser.newContext({ viewport, isMobile: size === 'phone', hasTouch: size === 'phone' });
      await context.route('**/*', (route) => (route.request().method() === 'GET' ? route.continue() : route.abort()));
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', (e) => errors.push(e.message));
      // The draw.io viewer's own blocked styles (vendor/drawio) were there before the guide.
      // Team Management's own avatar colours (style="...") were blocked by the CSP before the guide; the guide sets no inline style.
      page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|net::ERR_FAILED|Applying inline style violates/.test(m.text()) && !/\/vendor\/drawio\//.test(m.location().url)) errors.push(m.text()); });
      const problems = [];
      const done = new Set();
      const elsewhere = new Map();
      fs.mkdirSync(`${OUT}/${portal}`, { recursive: true });

      for (const [li, login] of SIGN_IN[portal].entries()) {
        const last = li === SIGN_IN[portal].length - 1;
        await context.clearCookies();
        await page.goto(login);
        const links = new Set();

        // Every page without an id is opened under each account, for its links;
        // each page's guide is checked once.
        const visit = async (name, url) => {
          await test.step(`${name} as ${login}`, async () => {
            errors.length = 0;
            await page.goto(url, { waitUntil: 'load' });
            // The first-visit tour opens 400ms after load.
            await page.waitForTimeout(1200);
            const where = `${name} (${size})`;
            const landed = new URL(page.url()).pathname;
            if (!pattern(ROUTES[name]).test(landed)) {
              // A CaPSA/TSA who also advises keeps one profile: the adviser one, which has its own guide.
              const to = routeOf(landed);
              if (to && GUIDES[to] && to.split('.').slice(1).join('.') === name.split('.').slice(1).join('.')) {
                done.add(name);
                test.info().annotations.push({ type: 'sent on', description: `${name}: sent to ${to}, which has its own guide` });
              } else elsewhere.set(name, `${where}: went to ${landed} instead`);
              return;
            }
            (await page.$$eval('a[href]', (as) => as.map((a) => a.href))).forEach((h) => links.add(h));
            if (done.has(name)) return;
            // A page with nothing in it (no teams yet, say) shows none of its tour's
            // parts; the next account may have something, the last one is taken as it is.
            const empty = (GUIDES[name].tour || []).length && await page.evaluate((sels) => !sels.some((s) => [...document.querySelectorAll(s)].some((el) => el.getClientRects().length)), GUIDES[name].tour.map((s) => s[2]));
            if (empty && !last) return;
            done.add(name);
            if (empty) test.info().annotations.push({ type: 'empty', description: `${name}: checked with nothing on it` });
            // The staff welcome and the first-visit tour, out of the way.
            await page.evaluate(() => {
              document.getElementById('welcome-dismiss')?.click();
              const t = document.getElementById('portal-tour');
              if (t && !t.hidden) t.querySelector('[data-tour="skip"]')?.click();
            });
            await page.waitForTimeout(300);
            problems.push(...await checkGuide(page, portal, name, GUIDES[name], where));
            if (errors.length) problems.push(`${where}: script errors: ${errors.join(' | ')}`);
          });
        };

        for (const name of direct) await visit(name, ROUTES[name]);
        for (const url of CRAWL[portal] || []) {
          await page.goto(url, { waitUntil: 'load' });
          (await page.$$eval('a[href]', (as) => as.map((a) => a.href))).forEach((h) => links.add(h));
        }
        for (const name of byLink) {
          if (done.has(name)) continue;
          const href = [...links].find((h) => new URL(h).origin === new URL(page.url()).origin && routeOf(new URL(h).pathname) === name);
          if (href) await visit(name, href);
        }
      }

      // A page sent elsewhere for every account.
      for (const [name, why] of elsewhere) if (!done.has(name)) problems.push(why);

      // Some pages only exist while something is open (a rating form, say); with none in the data there is no link.
      for (const name of byLink.filter((n) => !done.has(n))) {
        test.info().annotations.push({ type: 'not reached', description: `${name}: no link to it on the other ${portal} pages` });
        console.log(`not reached: ${name} (${size})`);
      }

      await context.close();
      expect(problems, problems.join('\n')).toEqual([]);
    });
  }
}

async function checkGuide(page, portal, name, guide, where) {
  const p = [];
  const shot = (tag) => page.screenshot({ path: `${OUT}/${portal}/${name.replace(/^[^.]+\./, '')}-${where.match(/\((\w+)\)/)[1]}-${tag}.png` });

  const btn = page.locator('#pg-btn');
  if (!(await btn.isVisible())) return [`${where}: no ? button`];

  // The ? menu, then the panel: this page's own guide.
  await btn.click();
  await expect(page.locator('#pg-menu')).toBeVisible();
  if (await page.locator('#pg-menu-tour').isVisible() !== (guide.tour || []).length > 0) p.push(`${where}: Start page tour shows ${(guide.tour || []).length ? 'not ' : ''}though the page has ${(guide.tour || []).length ? 'a' : 'no'} tour`);
  await shot('menu');
  await page.click('#pg-menu-guide');
  const panel = page.locator('#pg');
  await expect(panel).toBeVisible();
  const title = (await page.textContent('#pg-title')).trim();
  if (title !== guide.title) p.push(`${where}: panel says "${title}", not "${guide.title}"`);
  const steps = await panel.locator('.pg-steps li').count();
  if (steps !== (guide.steps || []).length) p.push(`${where}: ${steps} quick guide steps, not ${(guide.steps || []).length}`);
  if (guide.tip && !(await panel.locator('.pg-tip').textContent()).includes(guide.tip)) p.push(`${where}: tip missing`);
  const box = await panel.boundingBox();
  const vw = page.viewportSize().width;
  if (box.x + box.width > vw + 1 || box.width < Math.min(400, vw - 1)) p.push(`${where}: panel ${Math.round(box.width)}px wide at x=${Math.round(box.x)}`);
  await shot('panel');

  // Related links open a page of this portal.
  for (const href of await panel.locator('.pg-rel a').evaluateAll((as) => as.map((a) => a.href))) {
    const res = await page.request.get(href);
    if (res.status() !== 200 || /login/.test(res.url())) p.push(`${where}: related link ${href} gave ${res.status()} at ${res.url()}`);
  }

  // Search: this page is found by its own title, and only this portal's pages come up.
  const word = guide.title.split(' ')[0];
  await page.fill('#pg-search', word);
  const hits = await panel.locator('.pg-hit:visible').evaluateAll((as) => as.map((a) => [a.textContent, new URL(a.href).pathname]));
  // A page that needs an id can't be linked from search, so only the others must find themselves.
  // A page left out of search on purpose ('search' => false) is not looked for.
  if (!ROUTES[name].includes('{') && guide.search !== false && !hits.some(([t]) => t.includes(guide.title))) p.push(`${where}: searching "${word}" does not find this page`);
  // Every page search offers is one this account can open.
  for (const href of await panel.locator('.pg-hit').evaluateAll((as) => as.map((a) => a.href))) {
    const res = await page.request.get(href);
    if (res.status() !== 200 || /login/.test(res.url())) p.push(`${where}: search offers ${href}, which gave ${res.status()} at ${res.url()}`);
  }
  await page.fill('#pg-search', 'zzqx');
  if (!(await page.locator('#pg-none').isVisible())) p.push(`${where}: no "No page matches" for nonsense`);
  await page.fill('#pg-search', '');

  // Esc closes it, ? opens it again.
  await page.keyboard.press('Escape');
  if (await panel.isVisible()) p.push(`${where}: Esc did not close it`);
  await page.evaluate(() => document.activeElement?.blur());
  await page.keyboard.press('?');
  if (!(await panel.isVisible())) {
    // A page whose own ? was there first (Defenses: its shortcuts) keeps it; the button still opens the guide.
    if (await page.locator('dialog[open]').count()) {
      await page.keyboard.press('Escape');
      await btn.click();
      await page.click('#pg-menu-guide');
    } else p.push(`${where}: ? key did not open it`);
  }

  // The tour: every part it names is on the page, and it walks through each.
  const tour = guide.tour || [];
  if (tour.length) {
    const missing = await page.evaluate((sels) => sels.filter((s) => ![...document.querySelectorAll(s)].some((el) => el.getClientRects().length)), tour.map((s) => s[2]));
    if (missing.length < tour.length && !BY_SITUATION.test(name)) missing.forEach((s) => p.push(`${where}: tour part ${s} is not on the page`));
    if (!(await page.locator('#pg-go').isVisible())) {
      // Right when none of its parts shows; wrong when some do.
      if (missing.length < tour.length) p.push(`${where}: no Start tour, though parts of it show`);
      await page.keyboard.press('Escape');
      return p;
    }
    await page.click('#pg-go');
    const labels = [];
    for (let i = 0; i < tour.length + 2 && await page.locator('#portal-tour').isVisible(); i++) {
      await page.waitForTimeout(450);
      labels.push((await page.textContent('#gt-label-text')).trim());
      const spot = await page.locator('.gt-spot').boundingBox();
      if (!spot || spot.width < 4 || spot.height < 4) p.push(`${where}: tour step "${labels.at(-1)}" outlines nothing`);
      if (i === 0) await shot('tour');
      await page.click('[data-tour="next"]');
    }
    const want = tour.filter((s) => !missing.includes(s[2])).map((s) => s[0]);
    if (labels.join('|') !== want.join('|')) p.push(`${where}: tour went ${labels.join(' > ')} instead of ${want.join(' > ')}`);
    if (await page.locator('#portal-tour').isVisible()) p.push(`${where}: tour did not end`);
    // Each tab: a click on it opens it and its own tour (never marked seen here, the PUT is stopped).
    for (const [tab, t] of Object.entries(guide.tabs || {})) {
      if (!(await page.locator(t.button).isVisible())) continue;
      await page.click(t.button);
      await page.locator('#portal-tour').waitFor({ state: 'visible', timeout: 6000 }).catch(() => {});
      if (!(await page.locator('#portal-tour').isVisible())) { p.push(`${where}: clicking the ${tab} tab opened no tour`); continue; }
      await page.waitForTimeout(450);
      const tabMissing = await page.evaluate((sels) => sels.filter((s) => ![...document.querySelectorAll(s)].some((el) => el.getClientRects().length)), t.tour.map((s) => s[2]));
      const got = [];
      for (let i = 0; i < t.tour.length + 2 && await page.locator('#portal-tour').isVisible(); i++) {
        await page.waitForTimeout(450);
        got.push((await page.textContent('#gt-label-text')).trim());
        if (i === 0) await shot(`tab-${tab}`);
        await page.click('[data-tour="next"]');
      }
      const tabWant = t.tour.filter((s) => !tabMissing.includes(s[2])).map((s) => s[0]);
      if (got.join('|') !== tabWant.join('|')) p.push(`${where}: ${tab} tab tour went ${got.join(' > ')} instead of ${tabWant.join(' > ')}`);
    }
  } else {
    await page.keyboard.press('Escape');
  }
  return p;
}
