import { expect, test } from '@playwright/test';
import fs from 'node:fs';

/**
 * Every page of every portal, on a desktop and a phone: no script errors,
 * no stylesheet or script that fails to load, nothing wider than the screen,
 * and every modal a button opens fits, has a background, and closes.
 *
 * Buttons are clicked for real, so anything but a GET is stopped before it
 * leaves the browser - a click can open a modal but cannot save or delete.
 *
 * PW_BASE_URL=http://127.0.0.1:8002 npx playwright test every-page-sweep
 */

const PAGES = {
  admin: ['dashboard', 'analytics', 'activitylog', 'experts', 'students', 'settings'],
  capsa: ['dashboard', 'approval', 'activitylog', 'analytics', 'announcements', 'archive', 'defense-scheduling', 'defenses',
    'evaluations', 'evaluations/new', 'experts', 'financial', 'leave-requests', 'panel_assignment', 'reports', 'resources', 'rubric_management',
    'sections', 'team-management', 'team-rules', 'verify-teams', 'profile'],
  instructor: ['dashboard', 'assignments', 'docreview', 'evaluations', 'grading', 'pending', 'progress',
    'rejected', 'schedule', 'teams', 'profile'],
  student: ['dashboard', 'archive', 'consultation', 'defense-scores', 'documents', 'duplication', 'experts', 'financial', 'personal-info',
    'personal-info/edit', 'profile', 'progress', 'resources', 'review', 'submission', 'submission/next-version', 'team'],
};

const SIGN_IN = {
  admin: '/__dev-login-expert/admin?email=ccs@cspc.edu.ph',
  capsa: '/__dev-login-expert/capsa',
  instructor: '/__dev-login-expert/adviser',
  student: '/__dev-login-student',
};

const SIZES = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } };
const MAX_BUTTONS = 30;
const OUT = 'test-results/every-page-sweep';

// In the page: what is on screen as a modal - a dialog, or anything fixed
// that covers a good part of the screen - and what is wrong with one.
function inPage() {
  const shown = (el) => {
    const s = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity) > 0.05;
  };
  const opaque = (e) => { const c = getComputedStyle(e).backgroundColor; return c && c !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(c); };
  window.__modals = () => {
    const found = [...document.querySelectorAll('body *')].filter((el) => {
      // The tour, and layers that let clicks through (the drawn scrollbar), are not modals.
      if (el.closest('#portal-tour, .gt, .gt-shield') || getComputedStyle(el).pointerEvents === 'none') return false;
      if (el.matches('dialog[open], [role=dialog], [role=alertdialog], [aria-modal=true]')) return shown(el);
      if (getComputedStyle(el).position !== 'fixed' || !shown(el)) return false;
      const r = el.getBoundingClientRect();
      return r.width * r.height > innerWidth * innerHeight * 0.15;
    });
    return found.filter((el) => !found.some((other) => other !== el && other.contains(el)));
  };
  window.__check = (el) => {
    const problems = [];
    // A full-screen backdrop holds the panel; the panel is what has to fit.
    let panel = el;
    const r0 = el.getBoundingClientRect();
    if (r0.width >= innerWidth - 2 && r0.height >= innerHeight - 2) {
      const area = (c) => c.getBoundingClientRect().width * c.getBoundingClientRect().height;
      panel = [...el.querySelectorAll('*')].filter((c) => opaque(c) && shown(c)).sort((a, b) => area(b) - area(a))[0] || el;
    }
    const r = panel.getBoundingClientRect();
    const scrolls = (e) => /(auto|scroll)/.test(getComputedStyle(e).overflowY) && e.scrollHeight > e.clientHeight;
    // The scrolling box can sit between the backdrop and the panel (a side sheet holding a taller card).
    const between = [];
    for (let p = panel.parentElement; p && p !== el; p = p.parentElement) between.push(p);
    const canScroll = [el, panel, ...between, ...panel.querySelectorAll('*')].some(scrolls);
    if (r.left < -1 || r.right > innerWidth + 1) problems.push(`sticks out sideways (${Math.round(r.left)}..${Math.round(r.right)} of ${innerWidth})`);
    if (r.top < -1) problems.push(`top cut off (${Math.round(r.top)})`);
    if (r.bottom > innerHeight + 1 && !canScroll) problems.push(`bottom cut off and cannot scroll (${Math.round(r.bottom)} of ${innerHeight})`);
    if (panel.scrollWidth > panel.clientWidth + 2 && !/(auto|scroll)/.test(getComputedStyle(panel).overflowX)) problems.push('content wider than the modal');
    if (!opaque(panel) && ![...panel.children].some(opaque)) problems.push('no background');
    return problems;
  };
}

const label = async (loc) => ((await loc.getAttribute('aria-label')) || (await loc.innerText().catch(() => '')) || (await loc.getAttribute('title')) || '?')
  .trim().replace(/\s+/g, ' ').slice(0, 50);

for (const [role, pages] of Object.entries(PAGES)) {
  for (const at of pages) {
    for (const [size, viewport] of Object.entries(SIZES)) {
      test(`${role}/${at} @${size}`, async ({ browser }) => {
        test.setTimeout(240_000);
        const context = await browser.newContext({ viewport, isMobile: size === 'phone', hasTouch: size === 'phone' });
        await context.addInitScript(inPage);
        const page = await context.newPage();
        const issues = [];
        const notes = [];

        const signedIn = await page.goto(SIGN_IN[role]);
        test.skip(signedIn?.status() === 404, `no ${role} account`);

        await context.route('**/*', (route) => (['GET', 'HEAD', 'OPTIONS'].includes(route.request().method()) ? route.continue() : route.abort()));
        page.on('dialog', (d) => d.dismiss().catch(() => {}));
        // Saves are blocked above, so a page complaining that its own save did not go through is the sweep's doing.
        page.on('pageerror', (e) => /Failed to fetch|NetworkError/i.test(e.message) || issues.push({ kind: 'script error', detail: e.message.slice(0, 200) }));
        page.on('console', (m) => {
          if (m.type() === 'error' && !/ERR_FAILED|Failed to fetch|NetworkError|aborted|Failed to load resource: net::/i.test(m.text())) {
            issues.push({ kind: 'console error', detail: m.text().slice(0, 200) });
          }
        });
        page.on('response', (r) => {
          const type = r.request().resourceType();
          if (r.status() >= 400 && ['stylesheet', 'script', 'font', 'image'].includes(type)) issues.push({ kind: `${type} failed`, detail: `${r.status()} ${r.url()}` });
        });

        const url = `/${role}/${at}`;
        const opened = async () => {
          const res = await page.goto(url);
          await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
          await page.evaluate(() => {
            document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove());
            document.body.classList.remove('tour-open');
          });
          return res;
        };
        const res = await opened();
        if (!res || res.status() >= 400) issues.push({ kind: 'page failed', detail: `${res?.status()} ${url}` });
        if (/\/login/.test(page.url())) issues.push({ kind: 'page failed', detail: `sent to ${page.url()}` });
        const here = page.url();

        const styles = await page.evaluate(() => ({
          sheets: [...document.styleSheets].filter((s) => { try { return s.cssRules.length > 0; } catch { return true; } }).length,
          font: getComputedStyle(document.body).fontFamily,
          wide: document.documentElement.scrollWidth - innerWidth,
        }));
        if (styles.sheets === 0) issues.push({ kind: 'unstyled', detail: 'no stylesheet loaded' });
        if (/^"?Times New Roman"?$/i.test(styles.font)) issues.push({ kind: 'unstyled', detail: `body font is ${styles.font}` });
        if (styles.wide > 2) issues.push({ kind: 'page wider than screen', detail: `${styles.wide}px` });
        fs.mkdirSync(OUT, { recursive: true });
        const shot = `${role}-${at.replace(/\//g, '_')}-${size}`;
        await page.screenshot({ path: `${OUT}/${shot}.png` });

        const tag = () => page.evaluate((max) => {
          const btns = [...document.querySelectorAll('button, [role=button], a[href="#"], a[href^="javascript"]')].filter((b) => {
            const r = b.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && getComputedStyle(b).visibility !== 'hidden' && !b.disabled && !b.closest('#portal-tour, .gt');
          });
          btns.slice(0, max).forEach((b, i) => b.setAttribute('data-sweep', i));
          return Math.min(btns.length, max);
        }, MAX_BUTTONS);

        const count = await tag();
        let modals = 0;
        for (let i = 0; i < count; i++) {
          let button = page.locator(`[data-sweep="${i}"]`);
          if (!(await button.count())) { await opened(); await tag(); button = page.locator(`[data-sweep="${i}"]`); }
          if (!(await button.isVisible().catch(() => false))) continue;
          const name = await label(button);
          if (/log ?out|sign ?out/i.test(name)) continue;

          await page.evaluate(() => window.__modals().forEach((m) => m.setAttribute('data-sweep-before', '')));
          await button.click({ timeout: 3000 }).catch(() => {});
          await page.waitForTimeout(450);
          if (page.url() !== here) { await opened(); await tag(); continue; }

          const fresh = await page.evaluateHandle(() => window.__modals().filter((m) => !m.hasAttribute('data-sweep-before')));
          const found = await fresh.evaluate((list) => list.map((m) => ({ problems: window.__check(m), id: m.id || String(m.className).slice(0, 40) || m.tagName })));
          if (!found.length) continue;

          modals++;
          for (const m of found) {
            if (m.problems.length) {
              issues.push({ kind: 'modal', detail: `"${name}" -> ${m.id}: ${m.problems.join('; ')}` });
              await page.screenshot({ path: `${OUT}/${shot}-modal-${i}.png` });
            }
          }

          // Closes: Escape, else its own close / cancel button.
          const open = () => fresh.evaluate((list) => list.filter((m) => m.isConnected && window.__modals().includes(m)).length);
          await page.keyboard.press('Escape');
          await page.waitForTimeout(250);
          if (await open()) {
            notes.push(`"${name}" -> ${found[0].id}: Escape does not close it`);
            const hasClose = await fresh.evaluate((list) => {
              const words = /^(close|cancel|×|✕|x|done|back|no|ok|got it)$/i;
              const btn = list.flatMap((m) => [...m.querySelectorAll('button, [role=button], a')])
                .find((b) => words.test(b.textContent.trim()) || /close/i.test(b.getAttribute('aria-label') || '') || /close/i.test(String(b.className)));
              btn?.setAttribute('data-sweep-close', '');
              return !!btn;
            });
            if (hasClose) await page.locator('[data-sweep-close]').first().click({ timeout: 1500 }).catch(() => {});
            await page.evaluate(() => document.querySelectorAll('[data-sweep-close]').forEach((b) => b.removeAttribute('data-sweep-close')));
            await page.waitForTimeout(250);
          }
          if (await open()) {
            // Last way out: a tap on the backdrop, in the corner the panel is least likely to cover.
            await page.mouse.click(viewport.width - 4, viewport.height - 4);
            await page.waitForTimeout(300);
          }
          if (await open()) {
            issues.push({ kind: 'modal', detail: `"${name}" -> ${found[0].id}: does not close with Escape, a close button or a tap outside` });
            await opened();
            await tag();
          } else {
            await page.evaluate(() => document.querySelectorAll('[data-sweep-before]').forEach((m) => m.removeAttribute('data-sweep-before')));
          }
        }

        fs.writeFileSync(`${OUT}/${shot}.json`, JSON.stringify({ role, at, size, buttons: count, modals, issues, notes: [...new Set(notes)] }, null, 2));
        await context.close();
        expect(issues, issues.map((i) => `${i.kind}: ${i.detail}`).join('\n')).toEqual([]);
      });
    }
  }
}
