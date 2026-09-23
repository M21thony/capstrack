import { chromium } from 'playwright';

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1300, height: 900 } });
const page = await context.newPage();

await page.goto('http://127.0.0.1:8123/__dev-login-expert/1', { waitUntil: 'load', timeout: 30000 });
await page.goto('http://127.0.0.1:8123/capsa/team-management?tab=leave_requests', { waitUntil: 'load', timeout: 30000 });
await page.waitForTimeout(1000);
await page.screenshot({ path: 'shot-capsa-leave-requests.png', fullPage: true });

const bodyText = await page.locator('body').innerText();
console.log('contains "Testing whether":', bodyText.includes('Testing whether'));
console.log('contains "No requests" or similar empty state:', /no.*request|nothing.*wait/i.test(bodyText));

await browser.close();
console.log('done');
