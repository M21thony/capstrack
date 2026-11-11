import { expect, test } from '@playwright/test';

/**
 * Enter does what the form's own button does, in every portal - the Done or
 * Go key on a phone's keyboard included - but never Cancel, never a delete,
 * never a new line in a long message, and never the Add button beside a
 * search box.
 */

const quiet = async (page) => {
  await page.evaluate(() => {
    document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove());
    document.body.classList.remove('tour-open');
  });
};

test('Enter in the Add a student sheet adds the student', async ({ page }) => {
  const response = await page.goto('/__dev-login-expert/capsa/dashboard');
  test.skip(response.status() === 404, 'there is no CaPSA/TSA account in this database');
  await page.goto('/capsa/sections');
  await quiet(page);
  test.skip(await page.locator('#add-student-scrim').count() === 0, 'no Add a student sheet on this page');

  await page.evaluate(() => {
    window.saveNewStudent = () => { window.__added = (window.__added || 0) + 1; };
    document.getElementById('add-student-scrim').hidden = false;
  });
  const email = page.locator('#add-student-email');
  await email.focus();
  expect(await email.getAttribute('enterkeyhint')).toBe('done');
  await email.press('Enter');

  expect(await page.evaluate(() => window.__added)).toBe(1);
});

test('Enter picks the finishing button and leaves everything else alone', async ({ page }) => {
  const response = await page.goto('/__dev-login-student/dashboard');
  test.skip(response.status() === 404, 'there is no student in this database to sign in as');
  await quiet(page);

  await page.evaluate(() => {
    window.__hits = [];
    const hit = (name) => `window.__hits.push('${name}')`;
    const box = document.createElement('div');
    box.id = 'enter-fixtures';
    box.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#fff;overflow:auto;padding:10px';
    box.innerHTML = `
      <div class="modal-overlay" style="display:block">
        <div class="modal-box">
          <input id="f-name" type="text">
          <div><button class="btn btn-soft" onclick="${hit('cancel')}">Cancel</button>
               <button class="btn btn-primary" onclick="${hit('save')}">Save</button></div>
        </div>
      </div>
      <div class="modal-overlay" style="display:block">
        <textarea id="f-long" rows="4"></textarea>
        <button class="btn btn-solid" onclick="${hit('review')}">Review &amp; Submit</button>
      </div>
      <div class="modal-overlay" style="display:block">
        <textarea id="f-chat" rows="1"></textarea>
        <button class="btn btn-primary" onclick="${hit('send')}">Send</button>
      </div>
      <div class="modal-overlay" style="display:block">
        <input id="f-confirm" type="text">
        <button class="btn btn-danger" onclick="${hit('delete')}">Delete team</button>
      </div>
      <div class="toolbar">
        <input id="f-search" type="text" placeholder="Search students">
        <button class="btn btn-primary" onclick="${hit('add')}">Add Student</button>
      </div>
      <form id="f-form" onsubmit="event.preventDefault(); ${hit('form')}">
        <input id="f-form-field" type="text">
        <button type="button" class="btn btn-primary" onclick="${hit('form-button')}">Save draft</button>
        <button type="submit">Submit</button>
      </form>
      <div class="modal-overlay" style="display:block">
        <input id="f-own" type="text" onkeydown="if (event.key === 'Enter') { event.preventDefault(); ${hit('own')} }">
        <button class="btn btn-primary" onclick="${hit('own-save')}">Save</button>
      </div>`;
    document.body.appendChild(box);
  });

  const hits = () => page.evaluate(() => window.__hits.splice(0));

  await page.locator('#f-name').press('Enter');
  expect(await hits(), 'the primary, not Cancel').toEqual(['save']);

  await page.locator('#f-long').press('Enter');
  expect(await hits(), 'a long message gets its new line').toEqual([]);
  expect(await page.locator('#f-long').inputValue()).toBe('\n');

  const chat = page.locator('#f-chat');
  await chat.focus();
  expect(await chat.getAttribute('enterkeyhint')).toBe('send');
  await chat.press('Shift+Enter');
  expect(await hits(), 'Shift+Enter is a new line in a quick message').toEqual([]);
  await chat.press('Enter');
  expect(await hits(), 'Enter sends a quick message').toEqual(['send']);

  await page.locator('#f-confirm').press('Enter');
  expect(await hits(), 'a delete is never pressed for you').toEqual([]);

  await page.locator('#f-search').press('Enter');
  expect(await hits(), 'a search box does not open the Add form').toEqual([]);

  await page.locator('#f-form-field').press('Enter');
  expect(await hits(), 'a real form submits itself, once').toEqual(['form']);

  await page.locator('#f-own').press('Enter');
  expect(await hits(), 'a field that handles Enter itself keeps it').toEqual(['own']);
});
