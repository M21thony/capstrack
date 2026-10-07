import { expect, test } from '@playwright/test';

/**
 * A diagram page's words lie see-through over its picture so a reviewer can
 * select them. draw.io gives every label a box the size of the whole drawing,
 * which lets the mouse through; without that, the last label drawn sits over
 * the whole page and is the only one that can be selected.
 */

const drawing = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 300" width="600" height="300">
  ${[['Manage accounts', 40, 60], ['Account activity', 420, 240]].map(([words, x, y]) => `
  <g><switch><foreignObject style="overflow: visible; text-align: left;" pointer-events="none" width="100%" height="100%">
    <div xmlns="http://www.w3.org/1999/xhtml" style="display: flex; align-items: unsafe center; justify-content: unsafe flex-start; width: 1px; height: 1px; padding-top: ${y}px; margin-left: ${x}px;">
      <div style="font-size: 20px; pointer-events: all; white-space: nowrap;">${words}</div>
    </div>
  </foreignObject><text x="${x}" y="${y}">${words}</text></switch></g>`).join('')}
</svg>`;

test('every word on a diagram page can be selected, not only the last label drawn', async ({ page }) => {
  await page.route('**/__test/textLayer.js', (route) =>
    route.fulfill({ path: 'resources/js/component/reader/textLayer.js', contentType: 'text/javascript' }));
  await page.goto('/login');

  await page.evaluate(async (svg) => {
    const { textLayer } = await import('/__test/textLayer.js');
    const box = Object.assign(document.createElement('div'), { id: 'words' });
    box.style.cssText = 'position:fixed;left:0;top:0;width:600px;height:300px;z-index:99999;background:#fff';
    box.append(textLayer(new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement));
    document.body.replaceChildren(box);
  }, drawing);

  await page.mouse.move(42, 60);
  await page.mouse.down();
  await page.mouse.move(150, 62, { steps: 5 });
  await page.mouse.up();

  await expect.poll(() => page.evaluate(() => window.getSelection().toString())).toContain('Manage');
});
