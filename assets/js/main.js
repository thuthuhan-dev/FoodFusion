document.addEventListener('DOMContentLoaded', function () {

  /* Mobile nav toggle */
  var navToggle = document.getElementById('navToggle');
  var navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      var isOpen = navLinks.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  /* Password show / hide toggles */
  document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var wrap = btn.closest('.pw-wrap');
      if (!wrap) return;
      var input = wrap.querySelector('input');
      if (!input) return;
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.classList.toggle('showing', !showing);
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      input.focus();
    });
  });

  /* Modals (Join Us + Log in).
     A trigger uses data-open-modal="<modalId>". An EMPTY value keeps the old
     behaviour and opens the Join Us modal, so existing buttons still work. */
  var lastFocused = null;

  function openModalById(id) {
    var target = document.getElementById(id);
    if (!target) return;
    // Close any modal already open, so the two can switch between each other.
    document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
      m.classList.remove('open');
    });
    lastFocused = document.activeElement;
    target.classList.add('open');
    var firstField = target.querySelector('input');
    if (firstField) firstField.focus();
    document.body.style.overflow = 'hidden';
  }

  function closeAllModals() {
    document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
      m.classList.remove('open');
    });
    document.body.style.overflow = '';
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }

  // Exposed so a page can auto-open a modal after a server-side redirect
  // (e.g. login.php redirecting back with an error). See includes/footer.php.
  window.openModalById = openModalById;

  document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var id = btn.getAttribute('data-open-modal') || 'joinModal';
      if (!document.getElementById(id)) return; // no modal here: let the link work
      e.preventDefault();
      openModalById(id);
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
    btn.addEventListener('click', closeAllModals);
  });

  document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeAllModals();
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.querySelector('.modal-overlay.open')) closeAllModals();
  });

  /* Repeatable "add another step" rows — used by the Community Cookbook
     composers (Cooking Tip / Culinary Experience) and the Edit Post form.
     Not delegated: the buttons this targets are all present at page load
     on every page that uses it, none are added dynamically afterwards. */
  document.querySelectorAll('[data-add-step]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var container = document.getElementById(btn.getAttribute('data-add-step'));
      if (!container) return;
      var row = document.createElement('div');
      row.className = 'step-input-row';
      row.style.marginTop = '8px';
      var input = document.createElement('input');
      input.type = 'text';
      input.name = 'step[]';
      input.placeholder = 'Next step';
      row.appendChild(input);
      container.appendChild(row);
      input.focus();
    });
  });

  /* Generic "add" panel toggle — used across Resources and Educational
     Resources (7 forms). One delegated handler instead of seven near-
     identical named toggles. */
  document.querySelectorAll('[data-toggle-panel]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = document.getElementById(btn.getAttribute('data-toggle-panel'));
      if (!panel) return;
      var isOpen = panel.classList.toggle('open');
      btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
  document.querySelectorAll('[data-close-panel]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = document.getElementById(btn.getAttribute('data-close-panel'));
      if (panel) panel.classList.remove('open');
    });
  });

  /* Confirm before an irreversible action (post/comment deletion). */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  /* Like / repost without reloading the page.
     The forms still work normally if this script does not run. */
  document.querySelectorAll('form[data-ajax-action]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var btn = form.querySelector('button[type="submit"]');
      if (!btn || btn.disabled) return;

      e.preventDefault();
      btn.disabled = true;

      var body = new URLSearchParams(new FormData(form));

      fetch('post-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, data: j }; }); })
        .then(function (res) {
          var data = res.data;

          if (!data.ok) {
            // Not logged in: fall back to the normal submit so the user
            // sees the usual message rather than nothing happening.
            if (data.needsLogin) { form.submit(); return; }
            btn.disabled = false;
            return;
          }

          btn.classList.toggle('active', !!data.active);

          var count = form.querySelector('.action-count');
          if (count) count.textContent = data.count;

          var label = form.querySelector('.action-label');
          if (label) label.textContent = data.active ? 'Reposted' : 'Repost';

          // Filled heart when liked, outline when not.
          var svg = form.querySelector('svg');
          if (svg && form.getAttribute('data-ajax-action') === 'toggle_like') {
            svg.setAttribute('fill', data.active ? 'currentColor' : 'none');
          }

          if (form.getAttribute('data-ajax-action') === 'toggle_repost') {
            btn.style.color = data.active ? 'var(--zellige)' : '';
          }

          btn.disabled = false;
        })
        .catch(function () {
          // Network problem: let the normal form submit take over.
          btn.disabled = false;
          form.submit();
        });
    });
  });

  /* Edit-post panel toggle (post-detail.php) */
  var editPostToggle = document.getElementById('editPostToggle');
  var editPostPanel  = document.getElementById('editPostPanel');
  if (editPostToggle && editPostPanel) {
    editPostToggle.addEventListener('click', function () {
      var isOpen = editPostPanel.classList.toggle('open');
      editPostToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) editPostPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
  var cancelEditPost = document.getElementById('cancelEditPost');
  if (cancelEditPost && editPostPanel) {
    cancelEditPost.addEventListener('click', function () {
      editPostPanel.classList.remove('open');
      if (editPostToggle) editPostToggle.setAttribute('aria-expanded', 'false');
    });
  }

  /* Edit-recipe panel toggle (recipe-detail.php) */
  var editRecipeToggle = document.getElementById('editRecipeToggle');
  var editRecipePanel  = document.getElementById('editRecipePanel');
  if (editRecipeToggle && editRecipePanel) {
    editRecipeToggle.addEventListener('click', function () {
      var isOpen = editRecipePanel.classList.toggle('open');
      editRecipeToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) editRecipePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
  var cancelEditRecipe = document.getElementById('cancelEditRecipe');
  if (cancelEditRecipe && editRecipePanel) {
    cancelEditRecipe.addEventListener('click', function () {
      editRecipePanel.classList.remove('open');
      if (editRecipeToggle) editRecipeToggle.setAttribute('aria-expanded', 'false');
    });
  }

  /* Add-recipe panel: repeatable ingredient and method rows */
  var addRecipeToggle = document.getElementById('addRecipeToggle');
  var addRecipePanel  = document.getElementById('addRecipePanel');

  if (addRecipeToggle && addRecipePanel) {
    addRecipeToggle.addEventListener('click', function () {
      var isOpen = addRecipePanel.classList.toggle('open');
      addRecipeToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        addRecipePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var first = document.getElementById('recipe_name');
        if (first) first.focus();
      }
    });
  }

  var cancelAddRecipe = document.getElementById('cancelAddRecipe');
  if (cancelAddRecipe && addRecipePanel) {
    cancelAddRecipe.addEventListener('click', function () {
      addRecipePanel.classList.remove('open');
      if (addRecipeToggle) addRecipeToggle.setAttribute('aria-expanded', 'false');
    });
  }

  function renumberSteps() {
    var rows = document.querySelectorAll('#methodRows .step-row');
    rows.forEach(function (row, i) {
      var num = row.querySelector('.step-num');
      if (num) num.textContent = i + 1;
    });
  }

  var addIngredientRow = document.getElementById('addIngredientRow');
  var ingredientRows   = document.getElementById('ingredientRows');

  /* Toggle between the ingredient dropdown and a free-text field when
     "+ Add a new ingredient…" is chosen. Delegated so it works on rows
     cloned later by "Add another ingredient" without extra wiring. */
  document.addEventListener('change', function (e) {
    if (!e.target.classList || !e.target.classList.contains('ingredient-select')) return;
    var pick = e.target.closest('.ingredient-pick');
    if (!pick) return;
    var nameInput = pick.querySelector('.ingredient-new-name');
    if (!nameInput) return;

    if (e.target.value === '__new__') {
      e.target.style.display = 'none';
      nameInput.style.display = '';
      nameInput.focus();
    } else {
      e.target.style.display = '';
      nameInput.style.display = 'none';
      nameInput.value = '';
    }
  });

  if (addIngredientRow && ingredientRows) {
    addIngredientRow.addEventListener('click', function () {
      var first = ingredientRows.querySelector('.ingredient-row');
      if (!first) return;
      var clone = first.cloneNode(true);
      clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; s.style.display = ''; });
      clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      var clonedNameInput = clone.querySelector('.ingredient-new-name');
      if (clonedNameInput) clonedNameInput.style.display = 'none';
      ingredientRows.appendChild(clone);
      var sel = clone.querySelector('select');
      if (sel) sel.focus();
    });
  }

  var addMethodRow = document.getElementById('addMethodRow');
  var methodRows   = document.getElementById('methodRows');
  if (addMethodRow && methodRows) {
    addMethodRow.addEventListener('click', function () {
      var first = methodRows.querySelector('.step-row');
      if (!first) return;
      var clone = first.cloneNode(true);
      clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      methodRows.appendChild(clone);
      renumberSteps();
      var inp = clone.querySelector('input');
      if (inp) { inp.placeholder = 'Describe the next step'; inp.focus(); }
    });
  }

  /* Removing a row — delegated so it also works on cloned rows.
     The last remaining row is cleared rather than deleted, so the
     form always keeps one usable row. */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.row-remove') : null;
    if (!btn) return;

    var row = btn.closest('.ingredient-row') || btn.closest('.step-row');
    if (!row) return;

    var container = row.parentElement;
    var siblings = container.querySelectorAll('.ingredient-row, .step-row');

    if (siblings.length > 1) {
      row.remove();
    } else {
      row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      row.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; s.style.display = ''; });
      var nameField = row.querySelector('.ingredient-new-name');
      if (nameField) nameField.style.display = 'none';
    }
    renumberSteps();
  });

  /* Add-event panel on the homepage — doubles as the Edit form. */
  var addEventToggle  = document.getElementById('addEventToggle');
  var addEventPanel   = document.getElementById('addEventPanel');
  var eventForm       = document.getElementById('eventForm');
  var eventFormAction = document.getElementById('eventFormAction');
  var eventFormEventId = document.getElementById('eventFormEventId');
  var eventFormHeading = document.getElementById('eventFormHeading');
  var eventFormSubmit  = document.getElementById('eventFormSubmit');
  var eventImage       = document.getElementById('event_image');
  var eventImageHint   = document.getElementById('eventImageHint');

  function resetEventFormToCreateMode() {
    if (!eventForm) return;
    eventForm.reset();
    if (eventFormAction) eventFormAction.value = 'create_event';
    if (eventFormEventId) eventFormEventId.value = '';
    if (eventFormHeading) eventFormHeading.textContent = 'Add an event';
    if (eventFormSubmit) eventFormSubmit.textContent = 'Add event';
    if (eventImage) eventImage.required = true;
    if (eventImageHint) eventImageHint.textContent = 'JPG, PNG, GIF or WEBP · max 3 MB';
  }

  function openEventFormInEditMode(data) {
    if (!eventForm || !addEventPanel) return;
    eventFormAction.value = 'edit_event';
    eventFormEventId.value = data.id;
    document.getElementById('event_title').value = data.title;
    document.getElementById('event_date').value = data.dateRaw;
    document.getElementById('event_location').value = data.location;
    document.getElementById('event_description').value = data.description;
    if (eventFormHeading) eventFormHeading.textContent = 'Edit event';
    if (eventFormSubmit) eventFormSubmit.textContent = 'Save changes';
    if (eventImage) eventImage.required = false;
    if (eventImageHint) eventImageHint.textContent = 'Leave empty to keep the current photo. JPG, PNG, GIF or WEBP · max 3 MB';

    addEventPanel.classList.add('open');
    if (addEventToggle) addEventToggle.setAttribute('aria-expanded', 'true');
    addEventPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  if (addEventToggle && addEventPanel) {
    addEventToggle.addEventListener('click', function () {
      // Always start a fresh "add" — otherwise clicking this after editing
      // an event would leave the form silently stuck in edit mode.
      var wasOpen = addEventPanel.classList.contains('open');
      resetEventFormToCreateMode();
      var isOpen = !wasOpen;
      addEventPanel.classList.toggle('open', isOpen);
      addEventToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        var first = addEventPanel.querySelector('input');
        if (first) first.focus();
      }
    });
  }

  var cancelEventForm = document.getElementById('cancelEventForm');
  if (cancelEventForm && addEventPanel) {
    cancelEventForm.addEventListener('click', function () {
      addEventPanel.classList.remove('open');
      if (addEventToggle) addEventToggle.setAttribute('aria-expanded', 'false');
      resetEventFormToCreateMode();
    });
  }

  /* Event details modal — fills from the button's data attributes,
     so no extra page load is needed. */
  var eventModal = document.getElementById('eventModal');
  var eventModalEditBtn = document.getElementById('eventModalEditBtn');
  if (eventModal) {
    document.querySelectorAll('.event-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('eventModalTitle').textContent = btn.getAttribute('data-event-title') || '';
        document.getElementById('eventModalDate').textContent = btn.getAttribute('data-event-date') || '';
        document.getElementById('eventModalLocation').textContent = btn.getAttribute('data-event-location') || '';

        var desc = btn.getAttribute('data-event-description') || '';
        var descEl = document.getElementById('eventModalDescription');
        descEl.textContent = desc;
        descEl.style.display = desc ? '' : 'none';

        var img = document.getElementById('eventModalImage');
        var src = btn.getAttribute('data-event-image') || '';
        if (src) { img.src = src; img.alt = btn.getAttribute('data-event-title') || ''; img.style.display = ''; }
        else { img.style.display = 'none'; }

        if (eventModalEditBtn) {
          var canEdit = btn.getAttribute('data-can-edit') === '1';
          eventModalEditBtn.style.display = canEdit ? '' : 'none';
          eventModalEditBtn.onclick = canEdit ? function () {
            closeAllModals();
            openEventFormInEditMode({
              id: btn.getAttribute('data-event-id'),
              title: btn.getAttribute('data-event-title') || '',
              dateRaw: btn.getAttribute('data-event-date-raw') || '',
              location: btn.getAttribute('data-event-location') || '',
              description: btn.getAttribute('data-event-description') || ''
            });
          } : null;
        }

        openModalById('eventModal');
      });
    });
  }

  /* Events carousel */
  var track = document.getElementById('eventsTrack');
  var prevBtn = document.getElementById('carouselPrev');
  var nextBtn = document.getElementById('carouselNext');
  setupAutoCarousel(track, prevBtn, nextBtn, '.event-card-v2');

  /* Recipe carousel (horizontal scroll) */
  var recipeTrack = document.getElementById('recipeTrack');
  var recipePrev = document.getElementById('recipeCarouselPrev');
  var recipeNext = document.getElementById('recipeCarouselNext');
  setupAutoCarousel(recipeTrack, recipePrev, recipeNext, '.recipe-card-h');

  function setupAutoCarousel(trackEl, prevEl, nextEl, cardSelector) {
    if (!trackEl) return;
    var amount = function () {
      var card = trackEl.querySelector(cardSelector);
      if (!card) return 280;
      // Read the real gap from CSS instead of assuming 20px — the gap
      // shrinks on mobile, and a wrong step leaves cards half-scrolled.
      var styles = window.getComputedStyle(trackEl);
      var gap = parseFloat(styles.columnGap || styles.gap || '20');
      if (isNaN(gap)) gap = 20;
      return card.getBoundingClientRect().width + gap;
    };
    var advance = function () {
      var atEnd = trackEl.scrollLeft + trackEl.clientWidth >= trackEl.scrollWidth - 4;
      if (atEnd) {
        trackEl.scrollTo({ left: 0, behavior: 'smooth' });
      } else {
        trackEl.scrollBy({ left: amount(), behavior: 'smooth' });
      }
    };
    var rewind = function () {
      trackEl.scrollBy({ left: -amount(), behavior: 'smooth' });
    };
    if (prevEl) prevEl.addEventListener('click', function () { rewind(); restart(); });
    if (nextEl) nextEl.addEventListener('click', function () { advance(); restart(); });

    var timer = null;
    function start() { timer = setInterval(advance, 4500); }
    function stop() { if (timer) clearInterval(timer); }
    function restart() { stop(); start(); }

    start();
    trackEl.addEventListener('mouseenter', stop);
    trackEl.addEventListener('mouseleave', start);
    trackEl.addEventListener('touchstart', stop, { passive: true });
    trackEl.addEventListener('touchend', start, { passive: true });
  }

  /* Cookie consent */
  var cookieBar = document.getElementById('cookieBar');
  var cookieAccept = document.getElementById('cookieAccept');
  var cookieDecline = document.getElementById('cookieDecline');
  var CONSENT_KEY = 'foodfusion_cookie_consent';

  if (cookieBar) {
    var existingConsent = null;
    try { existingConsent = localStorage.getItem(CONSENT_KEY); } catch (e) { existingConsent = null; }

    if (!existingConsent) {
      cookieBar.classList.add('open');
    }

    function setConsent(value) {
      try { localStorage.setItem(CONSENT_KEY, value); } catch (e) { /* storage unavailable, banner will reappear next visit */ }
      cookieBar.classList.remove('open');
    }

    if (cookieAccept) cookieAccept.addEventListener('click', function () { setConsent('accepted'); });
    if (cookieDecline) cookieDecline.addEventListener('click', function () { setConsent('declined'); });
  }

  /* Hero carousel */
  var heroSlides = document.querySelectorAll('.hero-slide');
  var heroDots = document.querySelectorAll('.hero-dot');
  var heroPrev = document.getElementById('heroPrev');
  var heroNext = document.getElementById('heroNext');
  var heroIndex = 0;
  var heroTimer = null;

  function showHeroSlide(i) {
    if (!heroSlides.length) return;
    heroIndex = (i + heroSlides.length) % heroSlides.length;
    heroSlides.forEach(function (s, idx) { s.classList.toggle('active', idx === heroIndex); });
    heroDots.forEach(function (d, idx) { d.classList.toggle('active', idx === heroIndex); });
  }

  function startHeroAutoplay() {
    stopHeroAutoplay();
    heroTimer = setInterval(function () { showHeroSlide(heroIndex + 1); }, 6000);
  }
  function stopHeroAutoplay() {
    if (heroTimer) clearInterval(heroTimer);
  }

  if (heroSlides.length) {
    showHeroSlide(0);
    startHeroAutoplay();

    heroDots.forEach(function (dot, idx) {
      dot.addEventListener('click', function () { showHeroSlide(idx); startHeroAutoplay(); });
    });
    if (heroPrev) heroPrev.addEventListener('click', function () { showHeroSlide(heroIndex - 1); startHeroAutoplay(); });
    if (heroNext) heroNext.addEventListener('click', function () { showHeroSlide(heroIndex + 1); startHeroAutoplay(); });

    var heroCarouselEl = document.querySelector('.hero-carousel');
    if (heroCarouselEl) {
      heroCarouselEl.addEventListener('mouseenter', stopHeroAutoplay);
      heroCarouselEl.addEventListener('mouseleave', startHeroAutoplay);
    }
  }

  /* Favorite / Like — AJAX, no page reload, stays exactly where you are */
  function updateFavoriteButtons(recipeId, favorited, count) {
    document.querySelectorAll('.js-favorite-form[data-recipe-id="' + recipeId + '"]').forEach(function (form) {
      var btn = form.querySelector('.js-favorite-btn');
      if (!btn) return;
      btn.classList.toggle('active', favorited);
      var svg = btn.querySelector('svg');
      if (svg) svg.setAttribute('fill', favorited ? 'currentColor' : 'none');
      var countEl = btn.querySelector('.js-favorite-count');
      if (countEl) countEl.textContent = count;
      var labelEl = btn.querySelector('.js-favorite-label');
      if (labelEl) labelEl.textContent = favorited ? 'Saved' : 'Save recipe';
    });
  }

  function updateLikeButtons(postId, liked, count) {
    document.querySelectorAll('.js-like-form[data-post-id="' + postId + '"]').forEach(function (form) {
      var btn = form.querySelector('.js-like-btn');
      if (!btn) return;
      btn.classList.toggle('active', liked);
      var svg = btn.querySelector('svg');
      if (svg) svg.setAttribute('fill', liked ? 'currentColor' : 'none');
      var countEl = btn.querySelector('.js-like-count');
      if (countEl) countEl.textContent = count;
    });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;

    if (form.classList.contains('js-favorite-form')) {
      e.preventDefault();
      var recipeId = form.getAttribute('data-recipe-id');
      var body = new FormData(form);
      fetch('toggle-favorite.php', {
        method: 'POST',
        body: body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok) {
            if (result.data.error === 'login_required') window.location.href = 'login.php';
            return;
          }
          updateFavoriteButtons(recipeId, result.data.favorited, result.data.count);
        })
        .catch(function () { /* network hiccup — button just stays as it was */ });
    }

    if (form.classList.contains('js-like-form')) {
      e.preventDefault();
      var postId = form.getAttribute('data-post-id');
      var body2 = new FormData(form);
      fetch('toggle-like.php', {
        method: 'POST',
        body: body2,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok) {
            if (result.data.error === 'login_required') window.location.href = 'login.php';
            return;
          }
          updateLikeButtons(postId, result.data.liked, result.data.count);
        })
        .catch(function () { /* network hiccup — button just stays as it was */ });
    }
  });

  /* User account menu — click to open, not hover (hover has a dead zone that's hard to hit) */
  var userMenu = document.querySelector('.user-menu');
  if (userMenu) {
    var userChip = userMenu.querySelector('.user-chip');
    var userDropdownMenu = userMenu.querySelector('.user-dropdown');
    if (userChip && userDropdownMenu) {
      userChip.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = userDropdownMenu.classList.toggle('open');
        userChip.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
      document.addEventListener('click', function (e) {
        if (!userMenu.contains(e.target)) {
          userDropdownMenu.classList.remove('open');
          userChip.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          userDropdownMenu.classList.remove('open');
          userChip.setAttribute('aria-expanded', 'false');
        }
      });
    }
  }

  /* Close the mobile menu automatically after tapping a link */
  if (navLinks) {
    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navLinks.classList.remove('open');
        if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* Share panel toggle (recipe detail page) */
  var shareToggle = document.getElementById('shareToggleBtn');
  var sharePanel = document.getElementById('sharePanel');
  if (shareToggle && sharePanel) {
    shareToggle.addEventListener('click', function () {
      sharePanel.classList.toggle('open');
      if (sharePanel.classList.contains('open')) {
        var ta = sharePanel.querySelector('textarea');
        if (ta) ta.focus();
      }
    });
  }

  /* Generic tab switcher (Community Cookbook: Recipes / Cooking Tips / Culinary Experiences) */
  document.querySelectorAll('[data-tab-group]').forEach(function (group) {
    var groupName = group.getAttribute('data-tab-group');
    var buttons = group.querySelectorAll('.cookbook-tab-btn');
    var panels = document.querySelectorAll('[data-tab-panel-group="' + groupName + '"]');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-tab-target');
        buttons.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        panels.forEach(function (p) {
          p.classList.toggle('active', p.getAttribute('data-tab-panel') === target);
        });
        if (history.replaceState) {
          var url = new URL(window.location.href);
          url.searchParams.set('tab', target);
          history.replaceState(null, '', url);
        }
      });
    });
    var params = new URLSearchParams(window.location.search);
    var wanted = params.get('tab');
    if (wanted) {
      var wantedBtn = group.querySelector('.cookbook-tab-btn[data-tab-target="' + wanted + '"]');
      if (wantedBtn) wantedBtn.click();
    }
  });

});