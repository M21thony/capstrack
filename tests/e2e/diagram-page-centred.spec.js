import { expect, test } from '@playwright/test';

/**
 * A diagram sent as the document itself has no PDF around its pages, and
 * still sits in the middle of the reader like a PDF's pages do.
 */
test('a diagram-only document sits in the middle of the reader', async ({ page }) => {
  await page.route('**/__test/reader.css', (route) =>
    route.fulfill({ path: 'resources/js/component/reader/reader.css', contentType: 'text/css' }));
  await page.goto('/login');
  await page.setViewportSize({ width: 1400, height: 800 });

  await page.evaluate(() => {
    document.head.append(Object.assign(document.createElement('link'), { rel: 'stylesheet', href: '/__test/reader.css' }));
    document.body.innerHTML = `<div class="rr-viewer" style="width:1200px;height:700px"><div class="rr-canvas">
      <div class="rr-doc"><div class="rr-page" style="width:600px;height:400px"></div></div></div></div>`;
  });

  await expect.poll(() => page.evaluate(() => {
    const canvas = document.querySelector('.rr-canvas').getBoundingClientRect();
    const sheet = document.querySelector('.rr-page').getBoundingClientRect();
    return Math.round((sheet.left - canvas.left) - (canvas.right - sheet.right));
  })).toBe(0);
});
