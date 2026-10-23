{{-- Financial Records, as the second tab of Team Management. Drawn by
     resources/js/financial/student.js from the JSON on #fx-student; $state is
     FeeBoard::forStudent(). The receipt form is part of the page, not a
     pop-up: the script shows it while there is a receipt to send. --}}
  <div class="fx" id="fx-student"
       data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
       data-urls="{{ json_encode(['upload' => route('api.student.financial.submit')], JSON_UNESCAPED_SLASHES) }}">

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="fx-i-upload" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></symbol>
      <symbol id="fx-i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></symbol>
      <symbol id="fx-i-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></symbol>
      <symbol id="fx-i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></symbol>
      <symbol id="fx-i-info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></symbol>
      <symbol id="fx-i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></symbol>
    </svg>

    {{-- Moved up beside the page's own tabs by student.js; here when it cannot be. --}}
    <div class="fx-sb-fees" role="tablist" aria-label="Defense fee" id="s-fees"></div>

    <div class="fx-sb">
      <section class="fx-sb-card fx-sb-mine" aria-labelledby="s-mine-h">
        <div class="fx-sb-head">
          <h2 id="s-mine-h">Your receipt</h2>
          <span id="s-mine-status"></span>
        </div>
        <ol class="fx-sb-steps" id="s-steps" aria-label="Receipt progress"></ol>
        <div id="s-mine"></div>

        {{-- Both, together: the OR number as typed and the photo it has to
             match. The CaPSA checks one against the other before verifying. --}}
        <form class="fx-sb-form" id="u-form" aria-label="Send your receipt" novalidate hidden>
          <p class="fx-muted" id="u-how"></p>
          <div class="fx-sb-fields">
            <div class="fx-sb-field">
              <label class="fx-sb-lbl" for="u-or">OR number</label>
              <input class="fx-sb-input" id="u-or" autocomplete="off" maxlength="40" placeholder="As printed on the receipt" aria-describedby="u-or-help">
              <span class="fx-sb-help" id="u-or-help">Checked against the number in the photo.</span>
            </div>
            <div class="fx-sb-field">
              <span class="fx-sb-lbl" id="u-photo-l">Photo of the receipt</span>
              {{-- No capture attribute: it made a phone open the camera and
                   nothing else, so a receipt already in the gallery could not
                   be chosen. Without it the phone offers both. --}}
              <label class="fx-sb-drop" id="u-pick" aria-labelledby="u-photo-l">
                <span class="fx-sb-drop-icon" id="u-pick-icon"><svg><use href="#fx-i-camera"/></svg></span>
                <img id="u-preview" alt="" hidden>
                <span class="fx-sb-drop-text"><b id="u-pick-t">Take or choose a photo</b><span id="u-pick-s">JPG or PNG, up to 10 MB</span></span>
                <span class="fx-sb-replace" id="u-replace" hidden>Replace</span>
                <input type="file" id="u-file" accept="image/*" aria-label="Photo of your receipt">
              </label>
            </div>
          </div>
          <div id="u-err" class="fx-sb-err" role="alert" hidden></div>
          <div class="fx-sb-foot">
            <span class="fx-sb-help" id="u-hint">Add the photo to send.</span>
            <button type="submit" class="fx-sb-send" id="u-send" disabled><svg><use href="#fx-i-send"/></svg><span id="u-send-t">Send to CaPSA</span></button>
          </div>
        </form>
      </section>

      <section class="fx-sb-card fx-sb-team" aria-labelledby="s-team-h">
        <div class="fx-sb-head">
          <h2 id="s-team-h">Team payments</h2>
          <span class="fx-sb-count" id="s-count"></span>
        </div>
        <div class="fx-sb-seg" id="s-bar" role="img"></div>
        <div class="fx-sb-legend" aria-hidden="true"><span><i class="is-verified"></i>Verified</span><span><i class="is-pending"></i>Waiting</span><span><i class="is-sent_back"></i>Sent back</span></div>
        <p class="fx-sb-rule" id="s-rule" role="note"></p>
        <ul class="fx-sb-members" id="s-team"></ul>
      </section>
    </div>
  </div>

<dialog id="fx-d-image" class="fx-dialog fx-image" aria-label="Receipt, full size"><img id="fx-img-full" alt=""></dialog>
