import { expect, test } from '@playwright/test';

/**
 * Meet the Team: a grid of trading cards on a computer and a tablet, and on a
 * phone a deck - swipe the top card away and the next comes up, and past the
 * last the first comes round again. The envelope copies the address and says
 * "Copied", and nothing else.
 */

test('on a phone the cards are a deck that loops', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  await page.goto('/team');
  const count = page.locator('#deck-count');
  await expect(count).toHaveText('1 / 5');

  // A swipe on the top card.
  await page.locator('.people-panel').scrollIntoViewIfNeeded();
  const box = await page.locator('.person-card.is-top').boundingBox();
  const y = box.y + box.height * 0.3;
  await page.mouse.move(box.x + box.width * 0.7, y);
  await page.mouse.down();
  for (let i = 1; i <= 10; i++) await page.mouse.move(box.x + box.width * 0.7 - i * 22, y);
  await page.mouse.up();
  await expect(count).toHaveText('2 / 5');

  // On past the last: back to the first. And back again before it.
  for (const expected of ['3 / 5', '4 / 5', '5 / 5', '1 / 5']) {
    await page.click('#deck-next');
    await expect(count).toHaveText(expected);
  }
  await page.click('#deck-prev');
  await expect(count).toHaveText('5 / 5');

  // Only the top card can be reached; nothing runs off the side.
  expect(await page.evaluate(() => document.querySelectorAll('.person-card:not([inert])').length)).toBe(1);
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBe(0);
  await context.close();
});

test('on a tablet the cards are a grid, not a deck', async ({ page }) => {
  await page.setViewportSize({ width: 820, height: 1180 });
  await page.goto('/team');
  await expect(page.locator('.deck-nav').first()).toBeHidden();
  const tops = await page.evaluate(() => [...document.querySelectorAll('.person-card')].map((c) => Math.round(c.getBoundingClientRect().top)));
  expect(new Set(tops).size, 'two rows of cards').toBe(2);
  expect(await page.evaluate(() => document.querySelectorAll('.person-card[inert]').length)).toBe(0);
});

test('copying an email says "Copied" and nothing else', async ({ browser }) => {
  const context = await browser.newContext({ permissions: ['clipboard-read', 'clipboard-write'] });
  const page = await context.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/team');
  await page.locator('.icon-copy').first().click();
  const toast = page.locator('#copy-toast');
  await expect(toast).toHaveClass(/show/);
  await expect(toast).toHaveText('Copied');
  await context.close();
});
