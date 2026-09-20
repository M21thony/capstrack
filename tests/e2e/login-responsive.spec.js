import { test, expect } from '@playwright/test';

// The sign-in page is laid out three times over - a pulled-up cream sheet
// with the button at the foot of it on a phone, stacked but roomier on a
// tablet, split into two panels from 1024px up. Nothing about that is
// visible to a unit test, so this is where it is held: the whole card fits
// one screen with nothing to scroll for, the button stays thumb-sized and
// within reach, the page never scrolls sideways, and the "How sign-in works"
// panel is folded away only where height is short.

const VIEWPORTS = [
  { name: 'phone', width: 390, height: 844 },
  { name: 'tablet', width: 820, height: 1180 },
  { name: 'desktop', width: 1440, height: 900 },
];

for (const vp of VIEWPORTS) {
  test(`login at ${vp.name} (${vp.width}x${vp.height}) fits one screen and stays tappable`, async ({ page }) => {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.goto('/login');

    // The whole card - hero and sheet together - is meant to read at a
    // glance: nothing here should need scrolling to be seen, on load.
    const { scrollWidth, clientWidth, scrollHeight, clientHeight } = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
      scrollHeight: document.documentElement.scrollHeight,
      clientHeight: document.documentElement.clientHeight,
    }));
    expect(scrollWidth, `${vp.name} overflows sideways by ${scrollWidth - clientWidth}px`).toBeLessThanOrEqual(clientWidth);
    expect(scrollHeight, `${vp.name} needs ${scrollHeight - clientHeight}px of scroll to see everything`).toBeLessThanOrEqual(clientHeight);

    // The one thing every visitor has to hit, visible without scrolling.
    const signIn = page.locator('#google-signin-btn');
    await expect(signIn).toBeVisible();
    await expect(signIn).toBeInViewport();
    const box = await signIn.boundingBox();
    expect(box.height, 'sign-in button is below the 44px touch target').toBeGreaterThanOrEqual(44);
    expect(box.width).toBeLessThanOrEqual(vp.width);
  });
}

test('on a phone the hero is a short, fixed crop - not a scrolling header', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/login');

  const brand = page.locator('.brand-bg').locator('..');
  const sheet = page.locator('.signin-panel');

  // A crop, not the whole first screen: the sheet shows up well before the
  // fold, so "sign in" is on-screen right away, no scrolling to find it.
  const sheetTop = (await sheet.boundingBox()).y;
  expect(sheetTop, 'the sheet should already be in view on load').toBeLessThan(844 * 0.65);
  expect(sheetTop, 'but the hero should still read as a real header, not a sliver').toBeGreaterThan(844 * 0.2);

  // position: static now, not sticky - scrolling the page should move it
  // like any other block, because there is nothing left to scroll past.
  const position = await brand.evaluate((el) => getComputedStyle(el).position);
  expect(position).not.toBe('sticky');
});

test('on a phone the button sits below the note, and above it from md up', async ({ page }) => {
  const note = page.locator('#signin-note');
  const signIn = page.locator('#google-signin-btn');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/login');

  const [notePhone, btnPhone] = [await note.boundingBox(), await signIn.boundingBox()];
  expect(btnPhone.y, 'the button belongs under the note on a phone').toBeGreaterThan(notePhone.y);

  // Adjacent, not separated by the old auto-margin void: a normal gap, not
  // most of a screen's worth of empty cream between the two.
  const gap = btnPhone.y - (notePhone.y + notePhone.height);
  expect(gap, `note-to-button gap was ${gap}px`).toBeLessThan(60);

  // And it is genuinely at the foot of the sheet, not floating mid-page.
  const viewportBottom = await page.evaluate(() => document.documentElement.clientHeight);
  expect(btnPhone.y + btnPhone.height).toBeGreaterThan(viewportBottom * 0.6);

  for (const width of [820, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/login');
    const [noteWide, btnWide] = [await note.boundingBox(), await signIn.boundingBox()];
    expect(btnWide.y, `the button belongs above the note at ${width}px`).toBeLessThan(noteWide.y);
  }
});

test('the sign-in note is folded away on a phone and open from tablet up', async ({ page }) => {
  const summary = page.locator('#signin-note > summary');
  const bullets = page.locator('#signin-note ul');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/login');

  // Phone: a tappable row, with the three bullets behind it.
  await expect(summary).toBeVisible();
  await expect(bullets).toBeHidden();
  expect((await summary.boundingBox()).height).toBeGreaterThanOrEqual(44);

  await summary.click();
  await expect(bullets).toBeVisible();

  // Tablet and desktop have the height to spare, so the row disappears and
  // the bullets are simply there.
  for (const width of [820, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/login');
    await expect(bullets).toBeVisible();
    await expect(summary).toBeHidden();
  }
});

test('the two panels sit side by side on desktop and stack below it', async ({ page }) => {
  const brand = page.locator('.brand-bg').locator('..');
  const signin = page.locator('.signin-panel');

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  const [brandWide, signinWide] = [await brand.boundingBox(), await signin.boundingBox()];
  expect(signinWide.x, 'panels should be side by side at 1440px').toBeGreaterThan(brandWide.x);

  await page.setViewportSize({ width: 820, height: 1180 });
  await page.goto('/login');
  const [brandTall, signinTall] = [await brand.boundingBox(), await signin.boundingBox()];
  expect(signinTall.y, 'panels should stack at 820px').toBeGreaterThan(brandTall.y);
  expect(signinTall.x).toBeCloseTo(brandTall.x, 0);

  // The sheet is pulled up over the header, not butted against it.
  expect(signinTall.y).toBeLessThan(brandTall.y + brandTall.height);
});
