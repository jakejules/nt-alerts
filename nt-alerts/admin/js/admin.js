(function () {
	'use strict';

	var config = window.ntAlertsAdmin || {};
	var feedbackEl;

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function setFeedback(msg, state) {
		if (!feedbackEl) return;
		feedbackEl.textContent = msg || '';
		feedbackEl.classList.remove('is-success', 'is-error');
		if (state) feedbackEl.classList.add('is-' + state);
	}

	/* -----------------------------------------------------------------
	 * Dashboard: End-now button
	 * ----------------------------------------------------------------- */

	function endAlert(id, card) {
		var i18n = config.i18n || {};
		if (!window.confirm(i18n.confirmEnd || 'End this alert now?')) return;

		var url = (config.restUrl || '') + 'alerts/' + encodeURIComponent(id) + '/end';

		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || ''
			}
		})
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			})
			.then(function () {
				setFeedback(i18n.ended || 'Alert ended.', 'success');
				if (card && card.parentNode) {
					card.parentNode.removeChild(card);
				}
				decrementCount('active');
				setTimeout(function () { window.location.reload(); }, 900);
			})
			.catch(function () {
				setFeedback(i18n.endFailed || 'Could not end the alert.', 'error');
			});
	}

	function decrementCount(sectionKey) {
		var sec = document.querySelector('.nt-alerts-section[data-section="' + sectionKey + '"]');
		if (!sec) return;
		var count = sec.querySelector('.nt-alerts-count');
		if (!count) return;
		var n = parseInt(count.textContent, 10);
		if (!isNaN(n) && n > 0) count.textContent = String(n - 1);
	}

	function bindDashboard() {
		document.addEventListener('click', function (e) {
			var endBtn = e.target.closest && e.target.closest('.nt-alerts-end-btn');
			if (endBtn) {
				var endId = endBtn.getAttribute('data-alert-id');
				var endCard = endBtn.closest('.nt-alerts-card');
				if (endId) endAlert(endId, endCard);
				return;
			}

			var extendBtn = e.target.closest && e.target.closest('.nt-alerts-extend-btn');
			if (extendBtn) {
				var card = extendBtn.closest('.nt-alerts-card');
				if (card) openExtendDialog(card, extendBtn);
				return;
			}

			var pickBtn = e.target.closest && e.target.closest('.nt-extend-pick');
			if (pickBtn) {
				var minutes = parseInt(pickBtn.getAttribute('data-minutes'), 10);
				if (!isNaN(minutes)) applyExtend(minutes);
			}
		});
	}

	/* -----------------------------------------------------------------
	 * Dashboard: Extend dialog
	 * ----------------------------------------------------------------- */

	var extendContext = { card: null, trigger: null };

	function openExtendDialog(card, trigger) {
		var dialog = document.getElementById('nt-extend-dialog');
		if (!dialog || typeof dialog.showModal !== 'function') {
			// Fallback: prompt for minutes if <dialog> isn't supported.
			var raw = window.prompt('Extend by how many minutes?', '60');
			var m = parseInt(raw, 10);
			if (!isNaN(m) && m > 0) {
				extendContext.card = card;
				extendContext.trigger = trigger || null;
				applyExtend(m);
			}
			return;
		}

		extendContext.card = card;
		extendContext.trigger = trigger || null;

		var title = card.getAttribute('data-alert-title') || '';
		var endTime = card.getAttribute('data-end-time') || '';

		setField(dialog, 'subject', title);
		setField(dialog, 'current-expiry', endTime ? formatFriendly(endTime) : '—');

		var err = dialog.querySelector('.nt-extend-dialog__error');
		if (err) { err.textContent = ''; err.hidden = true; }

		// Restore focus to the trigger when the dialog is dismissed.
		dialog.addEventListener('close', restoreExtendFocus, { once: true });

		dialog.showModal();
		var first = dialog.querySelector('.nt-extend-pick');
		if (first) first.focus();
	}

	function restoreExtendFocus() {
		var trigger = extendContext.trigger;
		extendContext.card = null;
		extendContext.trigger = null;
		if (trigger && typeof trigger.focus === 'function' && document.body.contains(trigger)) {
			trigger.focus();
		}
	}

	function closeExtendDialog() {
		var dialog = document.getElementById('nt-extend-dialog');
		if (dialog && dialog.open) dialog.close();
	}

	function applyExtend(minutes) {
		var card = extendContext.card;
		if (!card) return;

		var id = card.getAttribute('data-alert-id');
		var existing = card.getAttribute('data-end-time');
		var base = existing ? new Date(existing) : new Date();
		if (isNaN(base.getTime())) base = new Date();

		var newEnd = new Date(base.getTime() + minutes * 60 * 1000);
		var iso = newEnd.toISOString();

		var url = (config.restUrl || '') + 'alerts/' + encodeURIComponent(id);
		var i18n = config.i18n || {};

		fetch(url, {
			method: 'PATCH',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || ''
			},
			body: JSON.stringify({ end_time: iso })
		})
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			})
			.then(function (data) {
				updateCardExpiry(card, data && data.expires_at ? data.expires_at : iso);
				setFeedback(extendFeedback(minutes), 'success');
				closeExtendDialog();
			})
			.catch(function () {
				var dialog = document.getElementById('nt-extend-dialog');
				var err = dialog && dialog.querySelector('.nt-extend-dialog__error');
				if (err) {
					err.textContent = i18n.extendFailed || 'Could not extend the alert. Please try again.';
					err.hidden = false;
				} else {
					setFeedback(i18n.extendFailed || 'Could not extend the alert.', 'error');
				}
			});
	}

	function updateCardExpiry(card, isoEnd) {
		card.setAttribute('data-end-time', isoEnd);
		var display = card.querySelector('[data-role="expiry-display"]');
		var sep     = card.querySelector('[data-role="times-sep"]');
		if (!display) return;

		display.hidden = !isoEnd;
		if (sep) sep.hidden = !isoEnd;

		display.innerHTML = '';
		if (!isoEnd) return;

		var label = document.createTextNode('expires ');
		var time = document.createElement('time');
		time.dateTime = isoEnd;
		time.textContent = formatFriendly(isoEnd);
		display.appendChild(label);
		display.appendChild(time);
	}

	function extendFeedback(minutes) {
		if (minutes >= 60 && minutes % 60 === 0) {
			var hours = minutes / 60;
			return 'Extended by ' + hours + (hours === 1 ? ' hour.' : ' hours.');
		}
		return 'Extended by ' + minutes + ' minutes.';
	}

	/* -----------------------------------------------------------------
	 * New-alert form
	 * ----------------------------------------------------------------- */

	var formState = {
		category: '',
		routes: [],
		duration: '2h',
		reason: '',
		dept: '',
		internalReason: '',
		closedStops: [],
		alternateStops: [],
		images: [],   // [{id, url, thumbnail}]
		editingId: 0  // > 0 in edit mode
	};

	function initNewAlertForm() {
		var form = document.getElementById('nt-new-alert-form');
		if (!form) return;

		var categoryBtns = form.querySelectorAll('.nt-choice--category');
		categoryBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				formState.category = btn.getAttribute('data-category');
				categoryBtns.forEach(function (b) {
					b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
				});
				applyCategoryDefaultDuration();
				updateTitleFromTemplate();
			});
		});

		var chips = form.querySelectorAll('.nt-route-chip');
		chips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				var id = chip.getAttribute('data-route-id');
				toggleRoute(id, chip);
				updateTitleFromTemplate();
			});
		});

		form.querySelectorAll('[data-action="select-all"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				chips.forEach(function (chip) { selectChip(chip, true); });
				syncStateFromChips();
				updateTitleFromTemplate();
			});
		});
		form.querySelectorAll('[data-action="clear"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				chips.forEach(function (chip) { selectChip(chip, false); });
				syncStateFromChips();
				updateTitleFromTemplate();
			});
		});

		form.querySelectorAll('.nt-route-group-toggle').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var slug = btn.getAttribute('data-group-toggle');
				var groupChips = form.querySelectorAll('.nt-route-chip[data-route-group="' + slug + '"]');
				var allOn = Array.prototype.every.call(groupChips, function (c) {
					return c.getAttribute('aria-pressed') === 'true';
				});
				groupChips.forEach(function (c) { selectChip(c, !allOn); });
				syncStateFromChips();
				updateTitleFromTemplate();
			});
		});

		var durationBtns = form.querySelectorAll('.nt-choice--duration');
		durationBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				formState.duration = btn.getAttribute('data-duration');
				formState.userPickedDuration = true;
				durationBtns.forEach(function (b) {
					b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
				});
				syncDurationDetails();
			});
		});
		syncDurationDetails(); // initial state (default 2h)

		bindRadioGroup(form, '.nt-choice--reason', 'data-reason', function (val) {
			formState.reason = val;
		});

		bindRadioGroup(form, '.nt-choice--dept', 'data-dept', function (val) {
			formState.dept = val;
			syncMaintenanceVisibility();
			if (val !== 'maintenance') {
				formState.internalReason = '';
				resetRadioGroup(form, '.nt-choice--internal-reason', 'data-internal-reason', '');
			}
		});

		bindRadioGroup(form, '.nt-choice--internal-reason', 'data-internal-reason', function (val) {
			formState.internalReason = val;
		});

		initStopsPickers(form);
		initImagesPicker(form);
		applyEditingState(form);

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			submitForm(form);
		});
	}

	function selectChip(chip, on) {
		chip.setAttribute('aria-pressed', on ? 'true' : 'false');
	}

	function bindRadioGroup(form, selector, attr, onChange) {
		var btns = form.querySelectorAll(selector);
		btns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var val = btn.getAttribute(attr) || '';
				btns.forEach(function (b) {
					b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
				});
				onChange(val);
			});
		});
	}

	function resetRadioGroup(form, selector, attr, value) {
		var btns = form.querySelectorAll(selector);
		btns.forEach(function (b) {
			b.setAttribute('aria-checked', (b.getAttribute(attr) || '') === value ? 'true' : 'false');
		});
	}

	/* -----------------------------------------------------------------
	 * Edit mode: pre-populate form state from server-rendered JSON
	 * ----------------------------------------------------------------- */

	function readEditingPayload() {
		var el = document.getElementById('nt-alerts-editing-data');
		if (!el) return null;
		try { return JSON.parse(el.textContent || ''); } catch (e) { return null; }
	}

	function applyEditingState(form) {
		var data = readEditingPayload();
		if (!data || !data.id) return;
		formState.editingId = data.id;

		// Category
		if (data.category) {
			formState.category = data.category;
			form.querySelectorAll('.nt-choice--category').forEach(function (b) {
				b.setAttribute('aria-checked', b.getAttribute('data-category') === data.category ? 'true' : 'false');
			});
		}

		// Routes
		if (Array.isArray(data.routes)) {
			formState.routes = data.routes.slice();
			form.querySelectorAll('.nt-route-chip').forEach(function (chip) {
				var on = data.routes.indexOf(chip.getAttribute('data-route-id')) > -1;
				chip.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
		}

		// Duration: default to "custom" with the existing end_time pre-filled.
		// (Presets are computed from "now", which would shorten the alert.)
		formState.duration = 'custom';
		formState.userPickedDuration = true;
		form.querySelectorAll('.nt-choice--duration').forEach(function (b) {
			b.setAttribute('aria-checked', b.getAttribute('data-duration') === 'custom' ? 'true' : 'false');
		});
		var customEl = document.getElementById('nt-custom-end');
		if (customEl && data.expires_at) {
			// datetime-local needs YYYY-MM-DDTHH:mm with no timezone suffix.
			var d = new Date(data.expires_at);
			if (!isNaN(d.getTime())) {
				var pad = function (n) { return String(n).padStart(2, '0'); };
				customEl.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
					'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
			}
		}
		// Show the custom picker (since duration is now 'custom').
		document.querySelectorAll('.nt-new-form__duration-detail').forEach(function (el) {
			if (el.getAttribute('data-role') === 'custom') el.removeAttribute('hidden');
			else el.setAttribute('hidden', '');
		});

		// Reason
		formState.reason = data.reason || '';
		form.querySelectorAll('.nt-choice--reason').forEach(function (b) {
			b.setAttribute('aria-checked', (b.getAttribute('data-reason') || '') === formState.reason ? 'true' : 'false');
		});

		// Title (mark as user-edited so template auto-fill doesn't overwrite)
		var titleEl = document.getElementById('nt-alert-title');
		if (titleEl && data.title) {
			titleEl.value = data.title;
			titleEl.dataset.userEdited = '1';
		}

		// Description
		var descEl = document.getElementById('nt-alert-description');
		if (descEl && data.description) descEl.value = data.description;

		// Stops (chips populate immediately from server-resolved names; the
		// typeahead catalogue still loads in the background for further edits)
		if (Array.isArray(data.closed_stops)) {
			formState.closedStops = data.closed_stops.map(function (s) { return String(s.id || s); });
		}
		if (Array.isArray(data.alternate_stops)) {
			formState.alternateStops = data.alternate_stops.map(function (s) { return String(s.id || s); });
		}
		// Render chips using the names already in the payload, so the user
		// sees them even before /alerts/stops resolves.
		renderEditingStopsChips('closed', data.closed_stops || []);
		renderEditingStopsChips('alternate', data.alternate_stops || []);

		// Images
		if (Array.isArray(data.images)) {
			formState.images = data.images.map(function (img) {
				return {
					id: img.id,
					url: img.url,
					thumbnail: img.thumbnail || img.url
				};
			});
			rerenderImagePreviews();
		}

		// Internal block
		if (data.dept_responsible) {
			formState.dept = data.dept_responsible;
			form.querySelectorAll('.nt-choice--dept').forEach(function (b) {
				b.setAttribute('aria-checked', (b.getAttribute('data-dept') || '') === formState.dept ? 'true' : 'false');
			});
			syncMaintenanceVisibility();
		}
		var vehEl = document.getElementById('nt-vehicle-number');
		if (vehEl && data.vehicle_number) vehEl.value = data.vehicle_number;

		if (data.internal_reason) {
			formState.internalReason = data.internal_reason;
			form.querySelectorAll('.nt-choice--internal-reason').forEach(function (b) {
				b.setAttribute('aria-checked', (b.getAttribute('data-internal-reason') || '') === formState.internalReason ? 'true' : 'false');
			});
		}
	}

	function renderEditingStopsChips(key, list) {
		var picker = document.querySelector('.nt-stops-picker[data-stops-picker="' + key + '"]');
		if (!picker) return;
		var box = picker.querySelector('[data-role="chips"]');
		if (!box) return;
		box.innerHTML = '';
		var stateKey = key === 'closed' ? 'closedStops' : 'alternateStops';
		list.forEach(function (s) {
			var sid = String(s.id || s);
			var chip = document.createElement('span');
			chip.className = 'nt-stops-chip';
			chip.dataset.stopId = sid;
			var label = document.createElement('span');
			label.textContent = s.name || sid;
			chip.appendChild(label);
			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'nt-stops-chip__remove';
			remove.setAttribute('aria-label', 'Remove ' + (s.name || sid));
			remove.textContent = '×';
			remove.addEventListener('click', function () {
				var idx = formState[stateKey].indexOf(sid);
				if (idx > -1) formState[stateKey].splice(idx, 1);
				chip.parentNode && chip.parentNode.removeChild(chip);
			});
			chip.appendChild(remove);
			box.appendChild(chip);
		});
	}

	function rerenderImagePreviews() {
		var picker = document.querySelector('[data-role="images-picker"]');
		if (!picker) return;
		var previews = picker.querySelector('[data-role="image-previews"]');
		var btn = picker.querySelector('[data-role="open-image-picker"]');
		if (!previews) return;
		previews.innerHTML = '';
		var i18n = config.i18n || {};
		formState.images.forEach(function (img, idx) {
			var box = document.createElement('div');
			box.className = 'nt-image-preview';
			var thumb = document.createElement('img');
			thumb.src = img.thumbnail || img.url;
			thumb.alt = '';
			box.appendChild(thumb);
			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'nt-image-preview__remove';
			remove.setAttribute('aria-label', i18n.removeImage || 'Remove image');
			remove.textContent = '×';
			remove.addEventListener('click', function () {
				formState.images.splice(idx, 1);
				rerenderImagePreviews();
			});
			box.appendChild(remove);
			previews.appendChild(box);
		});
		if (btn) {
			btn.textContent = formState.images.length
				? (i18n.replaceImages || 'Add or replace images')
				: (i18n.pickImages || 'Choose images from your computer');
		}
	}

	/* -----------------------------------------------------------------
	 * Stops typeahead picker
	 * ----------------------------------------------------------------- */

	var stopsCache = null;          // [{id, name, code}, ...]
	var stopsCacheById = null;      // Map id -> entry

	function fetchStops() {
		if (stopsCache) return Promise.resolve(stopsCache);
		var url = (config.restUrl || '') + 'stops';
		return fetch(url, { credentials: 'same-origin' })
			.then(function (res) { return res.ok ? res.json() : { stops: [] }; })
			.then(function (data) {
				stopsCache = (data && Array.isArray(data.stops)) ? data.stops : [];
				stopsCacheById = {};
				stopsCache.forEach(function (s) { stopsCacheById[s.id] = s; });
				return stopsCache;
			})
			.catch(function () {
				stopsCache = [];
				stopsCacheById = {};
				return stopsCache;
			});
	}

	function searchStops(q, limit) {
		if (!stopsCache || !q) return [];
		q = q.trim().toLowerCase();
		if (q.length < 2) return [];
		var hits = [];
		for (var i = 0; i < stopsCache.length && hits.length < limit; i++) {
			var s = stopsCache[i];
			var name = (s.name || '').toLowerCase();
			var code = (s.code || '').toLowerCase();
			if (name.indexOf(q) > -1 || code.indexOf(q) > -1 || s.id === q) {
				hits.push(s);
			}
		}
		return hits;
	}

	function initStopsPickers(form) {
		var pickers = form.querySelectorAll('.nt-stops-picker');
		if (!pickers.length) return;
		fetchStops();

		pickers.forEach(function (picker) {
			var key = picker.getAttribute('data-stops-picker');
			var input = picker.querySelector('.nt-stops-picker__input');
			var list = picker.querySelector('.nt-stops-picker__list');
			var combobox = picker.querySelector('.nt-stops-picker__combobox');
			var chipsBox = picker.querySelector('[data-role="chips"]');

			var stateKey = key === 'closed' ? 'closedStops' : 'alternateStops';

			function close() {
				list.hidden = true;
				combobox.setAttribute('aria-expanded', 'false');
				list.innerHTML = '';
			}

			function selectFromHit(stop) {
				if (!formState[stateKey].includes(stop.id)) {
					formState[stateKey].push(stop.id);
					renderChips();
				}
				input.value = '';
				close();
				input.focus();
			}

			function renderResults() {
				var q = input.value;
				var hits = q ? searchStops(q, 8) : [];
				if (!hits.length) { close(); return; }

				list.innerHTML = '';
				hits.forEach(function (s, idx) {
					var li = document.createElement('li');
					li.className = 'nt-stops-picker__option';
					li.setAttribute('role', 'option');
					li.id = 'nt-stops-' + key + '-opt-' + idx;
					li.tabIndex = -1;
					var primary = document.createElement('span');
					primary.className = 'nt-stops-picker__option-name';
					primary.textContent = s.name;
					var meta = document.createElement('span');
					meta.className = 'nt-stops-picker__option-meta';
					meta.textContent = (s.code ? '#' + s.code + ' · ' : '') + 'ID ' + s.id;
					li.appendChild(primary);
					li.appendChild(meta);
					li.addEventListener('mousedown', function (e) {
						e.preventDefault();
						selectFromHit(s);
					});
					list.appendChild(li);
				});
				list.hidden = false;
				combobox.setAttribute('aria-expanded', 'true');
			}

			function renderChips() {
				chipsBox.innerHTML = '';
				formState[stateKey].forEach(function (sid) {
					var stop = stopsCacheById ? stopsCacheById[sid] : null;
					var chip = document.createElement('span');
					chip.className = 'nt-stops-chip';
					chip.dataset.stopId = sid;
					var label = document.createElement('span');
					label.textContent = stop ? stop.name : sid;
					chip.appendChild(label);
					var remove = document.createElement('button');
					remove.type = 'button';
					remove.className = 'nt-stops-chip__remove';
					remove.setAttribute('aria-label', 'Remove ' + (stop ? stop.name : sid));
					remove.textContent = '×';
					remove.addEventListener('click', function () {
						var idx = formState[stateKey].indexOf(sid);
						if (idx > -1) formState[stateKey].splice(idx, 1);
						renderChips();
					});
					chip.appendChild(remove);
					chipsBox.appendChild(chip);
				});
			}

			input.addEventListener('input', renderResults);
			input.addEventListener('focus', renderResults);
			input.addEventListener('blur', function () {
				// Delay close so click handlers on options can fire.
				setTimeout(close, 120);
			});
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') { close(); return; }
				if (e.key === 'ArrowDown') {
					var first = list.querySelector('.nt-stops-picker__option');
					if (first) { e.preventDefault(); first.focus(); }
				}
				if (e.key === 'Enter') {
					var firstHit = list.querySelector('.nt-stops-picker__option');
					if (firstHit) { e.preventDefault(); firstHit.dispatchEvent(new MouseEvent('mousedown')); }
				}
			});

			list.addEventListener('keydown', function (e) {
				var current = document.activeElement;
				if (e.key === 'ArrowDown' && current && current.nextElementSibling) {
					e.preventDefault(); current.nextElementSibling.focus();
				} else if (e.key === 'ArrowUp') {
					e.preventDefault();
					if (current && current.previousElementSibling) current.previousElementSibling.focus();
					else input.focus();
				} else if (e.key === 'Enter' && current && current.classList.contains('nt-stops-picker__option')) {
					e.preventDefault();
					current.dispatchEvent(new MouseEvent('mousedown'));
				} else if (e.key === 'Escape') {
					close(); input.focus();
				}
			});
		});
	}

	/* -----------------------------------------------------------------
	 * Images picker (direct upload to Media Library via REST)
	 * ----------------------------------------------------------------- */

	function initImagesPicker(form) {
		var picker = form.querySelector('[data-role="images-picker"]');
		if (!picker) return;

		var previews = picker.querySelector('[data-role="image-previews"]');
		var btn      = picker.querySelector('[data-role="open-image-picker"]');
		var fileInput = picker.querySelector('[data-role="image-file-input"]');
		var status   = picker.querySelector('[data-role="image-status"]');
		if (!btn || !fileInput) return;

		var max = (config.maxImages || 3);
		var i18n = config.i18n || {};
		// WP core media endpoint, derived from the plugin's REST namespace.
		var mediaUrl = (config.restUrl || '').replace(/nt-alerts\/v1\/?$/, 'wp/v2/media');

		function setStatus(msg, state) {
			if (!status) return;
			status.textContent = msg || '';
			status.classList.remove('is-busy', 'is-error', 'is-success');
			if (state) status.classList.add('is-' + state);
		}

		function renderPreviews() {
			previews.innerHTML = '';
			formState.images.forEach(function (img, idx) {
				var box = document.createElement('div');
				box.className = 'nt-image-preview';
				var thumb = document.createElement('img');
				thumb.src = img.thumbnail || img.url;
				thumb.alt = '';
				box.appendChild(thumb);

				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'nt-image-preview__remove';
				remove.setAttribute('aria-label', i18n.removeImage || 'Remove image');
				remove.textContent = '×';
				remove.addEventListener('click', function () {
					formState.images.splice(idx, 1);
					renderPreviews();
					updateBtnLabel();
				});
				box.appendChild(remove);
				previews.appendChild(box);
			});
		}

		function updateBtnLabel() {
			btn.textContent = formState.images.length
				? (i18n.replaceImages || 'Replace images')
				: (i18n.pickImages || 'Choose images from your computer');
			btn.disabled = false;
		}

		function uploadOne(file) {
			return fetch(mediaUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': config.nonce || '',
					'Content-Disposition': 'attachment; filename="' + encodeURIComponent(file.name) + '"',
					'Content-Type': file.type || 'application/octet-stream'
				},
				body: file
			}).then(function (res) {
				if (!res.ok) {
					return res.json().then(function (err) {
						throw new Error((err && err.message) || ('HTTP ' + res.status));
					}, function () { throw new Error('HTTP ' + res.status); });
				}
				return res.json();
			}).then(function (att) {
				var sizes = (att.media_details && att.media_details.sizes) || {};
				var thumb = sizes.medium || sizes.thumbnail || sizes.large || sizes.full;
				return {
					id: att.id,
					url: att.source_url,
					thumbnail: thumb && thumb.source_url ? thumb.source_url : att.source_url
				};
			});
		}

		btn.addEventListener('click', function () {
			fileInput.click();
		});

		fileInput.addEventListener('change', function () {
			var files = Array.prototype.slice.call(fileInput.files || []);
			if (!files.length) return;

			var room = max - formState.images.length;
			if (room <= 0) {
				setStatus((i18n.tooManyImages || 'Pick up to 3 images.'), 'error');
				fileInput.value = '';
				return;
			}
			if (files.length > room) {
				files = files.slice(0, room);
				setStatus(i18n.tooManyImages || 'Pick up to 3 images.', 'error');
			}

			btn.disabled = true;
			setStatus((i18n.uploading || 'Uploading…') + ' (0/' + files.length + ')', 'busy');

			var done = 0;
			Promise.all(files.map(function (file) {
				return uploadOne(file).then(function (img) {
					formState.images.push(img);
					done++;
					setStatus((i18n.uploading || 'Uploading…') + ' (' + done + '/' + files.length + ')', 'busy');
					renderPreviews();
				});
			})).then(function () {
				setStatus((i18n.uploaded || 'Uploaded.') + ' ' + formState.images.length + '/' + max, 'success');
				updateBtnLabel();
				fileInput.value = '';
			}).catch(function (err) {
				setStatus((i18n.uploadFailed || 'Upload failed:') + ' ' + (err && err.message ? err.message : '?'), 'error');
				updateBtnLabel();
				fileInput.value = '';
			});
		});

		renderPreviews();
		updateBtnLabel();
	}

	function syncMaintenanceVisibility() {
		var box = document.querySelector('[data-role="maintenance"]');
		if (!box) return;
		if (formState.dept === 'maintenance') box.removeAttribute('hidden');
		else box.setAttribute('hidden', '');
	}

	function applyCategoryDefaultDuration() {
		if (formState.userPickedDuration) return;
		var defaults = config.defaultDurations || {};
		var preferred = defaults[formState.category];
		if (!preferred || preferred === formState.duration) return;

		var btns = document.querySelectorAll('.nt-choice--duration');
		btns.forEach(function (b) {
			var match = b.getAttribute('data-duration') === preferred;
			b.setAttribute('aria-checked', match ? 'true' : 'false');
			if (match) formState.duration = preferred;
		});
		syncDurationDetails();
	}

	function toggleRoute(id, chip) {
		var idx = formState.routes.indexOf(id);
		if (idx === -1) {
			formState.routes.push(id);
			selectChip(chip, true);
		} else {
			formState.routes.splice(idx, 1);
			selectChip(chip, false);
		}
	}

	function syncStateFromChips() {
		var chips = document.querySelectorAll('.nt-route-chip[aria-pressed="true"]');
		formState.routes = Array.prototype.map.call(chips, function (c) {
			return c.getAttribute('data-route-id');
		});
	}

	function updateTitleFromTemplate() {
		var titleEl = document.getElementById('nt-alert-title');
		if (!titleEl) return;
		if (titleEl.dataset.userEdited === '1') return;

		var tmpl = (config.categoryTitles || {})[formState.category] || '';
		if (!tmpl) { titleEl.value = ''; return; }

		var routes = formState.routes.length
			? formState.routes.join(', ')
			: (config.i18n || {}).defaultTitleNoRoutes || '{routes}';

		titleEl.value = tmpl.replace('{routes}', routes);
	}

	function markTitleEdited() {
		var titleEl = document.getElementById('nt-alert-title');
		if (titleEl) titleEl.dataset.userEdited = '1';
	}

	function syncDurationDetails() {
		document.querySelectorAll('.nt-new-form__duration-detail').forEach(function (el) {
			var role = el.getAttribute('data-role');
			var show = (role === 'long-term' && formState.duration === 'long_term')
				|| (role === 'custom' && formState.duration === 'custom');
			if (show) el.removeAttribute('hidden');
			else el.setAttribute('hidden', '');
		});
	}

	function computeEndTime(now) {
		switch (formState.duration) {
			case '1h': return new Date(now.getTime() + 60 * 60 * 1000);
			case '2h': return new Date(now.getTime() + 2 * 60 * 60 * 1000);
			case '4h': return new Date(now.getTime() + 4 * 60 * 60 * 1000);
			case 'rest_of_day':
				var end = new Date(now);
				end.setHours(23, 59, 0, 0);
				return end;
			case 'long_term':
				var lt = document.getElementById('nt-long-term-end');
				if (lt && lt.value) {
					var d = new Date(lt.value + 'T23:59:00');
					if (!isNaN(d.getTime())) return d;
				}
				return null; // optional for long-term
			case 'custom':
				var ce = document.getElementById('nt-custom-end');
				if (ce && ce.value) {
					var dc = new Date(ce.value);
					if (!isNaN(dc.getTime())) return dc;
				}
				return null;
		}
		return new Date(now.getTime() + 2 * 60 * 60 * 1000);
	}

	function collectPayload() {
		var now = new Date();
		var end = computeEndTime(now);
		var alertType = formState.duration === 'long_term' ? 'long_term' : 'short_term';

		var titleEl = document.getElementById('nt-alert-title');
		var descEl = document.getElementById('nt-alert-description');

		var vehicleEl = document.getElementById('nt-vehicle-number');
		var dept = formState.dept || '';
		var vehicleNumber = (dept === 'maintenance' && vehicleEl) ? vehicleEl.value.trim() : '';
		var internalReason = (dept === 'maintenance') ? (formState.internalReason || '') : '';

		return {
			alert_type:       alertType,
			category:         formState.category,
			severity:         (config.categorySeverity || {})[formState.category] || 'info',
			routes:           formState.routes.slice(),
			title:            (titleEl ? titleEl.value : '').trim(),
			description:      descEl ? descEl.value.trim() : '',
			start_time:       now.toISOString(),
			end_time:         end ? end.toISOString() : '',
			reason:           formState.reason || '',
			closed_stops:     formState.closedStops.slice(),
			alternate_stops:  formState.alternateStops.slice(),
			images:           formState.images.map(function (img) { return img.id; }),
			dept_responsible: dept,
			vehicle_number:   vehicleNumber,
			internal_reason:  internalReason
		};
	}

	function validate(payload) {
		var i18n = config.i18n || {};
		if (!payload.category) return i18n.categoryRequired || 'Pick what happened.';
		if (!payload.routes.length) return i18n.routesRequired || 'Pick at least one affected route.';
		if (!payload.title) return 'Title is required.';
		if (payload.end_time && payload.end_time !== '' && new Date(payload.end_time) <= new Date()) {
			return i18n.expiryInPast || 'Expiry must be in the future.';
		}
		return null;
	}

	function showFormError(msg) {
		var box = document.querySelector('.nt-new-form__errors');
		if (!box) return;
		box.textContent = msg;
		box.hidden = !msg;
	}

	function submitForm(form) {
		var payload = collectPayload();
		var error = validate(payload);
		if (error) { showFormError(error); return; }
		showFormError('');

		var i18n = config.i18n || {};
		var submit = form.querySelector('.nt-new-form__submit');
		var originalLabel = submit ? submit.textContent : '';
		if (submit) {
			submit.disabled = true;
			submit.textContent = formState.editingId
				? (i18n.saving || 'Saving…')
				: (i18n.submitting || 'Posting…');
		}

		var url, method;
		if (formState.editingId) {
			url = (config.restUrl || '') + 'alerts/' + encodeURIComponent(formState.editingId);
			method = 'PATCH';
		} else {
			url = (config.restUrl || '') + 'alerts';
			method = 'POST';
		}

		fetch(url, {
			method: method,
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || ''
			},
			body: JSON.stringify(payload)
		})
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			})
			.then(function (data) {
				if (formState.editingId) {
					// On successful edit, return to dashboard with a flash flag.
					var dest = (config.dashboardUrl || '') + (config.dashboardUrl && config.dashboardUrl.indexOf('?') > -1 ? '&' : '?') + 'updated=' + encodeURIComponent(formState.editingId);
					window.location.href = dest;
				} else {
					renderConfirmation(form, data);
				}
			})
			.catch(function () {
				showFormError(formState.editingId
					? (i18n.editFailed || 'Could not save changes. Please try again.')
					: (i18n.submitFailed || 'Could not post the alert.'));
				if (submit) {
					submit.disabled = false;
					submit.textContent = originalLabel;
				}
			});
	}

	function renderConfirmation(form, data) {
		var confirmation = document.getElementById('nt-confirmation');
		if (!confirmation) {
			window.location.href = config.dashboardUrl || '.';
			return;
		}

		var title = (data && data.title) || '';
		var routes = (data && Array.isArray(data.routes)) ? data.routes.join(', ') : '';
		var expires = (data && data.expires_at) ? formatFriendly(data.expires_at) : '—';

		setField(confirmation, 'title', title);
		setField(confirmation, 'routes', routes);
		setField(confirmation, 'expires', expires);

		form.hidden = true;
		confirmation.hidden = false;
		confirmation.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function setField(root, role, value) {
		var el = root.querySelector('[data-role="' + role + '"]');
		if (el) el.textContent = value || '—';
	}

	function formatFriendly(iso) {
		if (!iso) return '';
		var d = new Date(iso);
		if (isNaN(d.getTime())) return iso;
		return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
	}

	function bindTitleEditTracking() {
		var titleEl = document.getElementById('nt-alert-title');
		if (!titleEl) return;
		titleEl.addEventListener('input', function () {
			if (titleEl.value && titleEl.value.indexOf('{routes}') === -1) {
				titleEl.dataset.userEdited = '1';
			}
		});
		titleEl.addEventListener('blur', markTitleEdited);
	}

	ready(function () {
		feedbackEl = document.querySelector('.nt-alerts-feedback');
		bindDashboard();
		initNewAlertForm();
		bindTitleEditTracking();
	});
})();
