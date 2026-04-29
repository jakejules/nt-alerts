(function () {
	'use strict';

	var POLL_MS = 60000;
	var NAMESPACE = 'nt-alerts/v1';

	// Capture the script element synchronously so we can resolve the API
	// origin even after DOMContentLoaded (when document.currentScript is null).
	var SCRIPT_EL = document.currentScript;

	var CATEGORY_LABEL = {
		detour: 'Detour',
		delay: 'Delay',
		cancelled_trip: 'Cancelled trip',
		stop_closure: 'Stop closure',
		reduced_service: 'Reduced service',
		other: 'Service alert'
	};

	var REASON_LABEL = {
		construction: 'Construction',
		street_closure: 'Street closure',
		weather: 'Weather',
		maintenance: 'Maintenance',
		police_activity: 'Police activity',
		fire: 'Fire',
		evacuation: 'Evacuation',
		terminal_closure: 'Terminal closure',
		collision: 'Collision',
		parade: 'Parade',
		other: 'Other'
	};

	var SEVERITY_LABEL = {
		info: 'Information',
		warning: 'Warning',
		critical: 'Critical'
	};

	var SEVERITY_ICON = {
		info: 'M12 7a1 1 0 100 2 1 1 0 000-2zm-1 4h2v6h-2v-6z',
		warning: 'M12 3l10 17H2L12 3zm0 5v5h0m0 3v1',
		critical: 'M12 2a10 10 0 100 20 10 10 0 000-20zm-1 5h2v7h-2V7zm0 9h2v2h-2v-2z'
	};

	function start() {
		var containers = document.querySelectorAll('#nt-alerts, .nt-alerts');
		for (var i = 0; i < containers.length; i++) {
			initContainer(containers[i]);
		}
	}

	function initContainer(el) {
		if (el.dataset.ntInitialized === '1') return;
		el.dataset.ntInitialized = '1';

		var options = {
			route: el.getAttribute('data-route') || '',
			type: (el.getAttribute('data-type') || 'all').toLowerCase(),
			limit: parseInt(el.getAttribute('data-limit') || '0', 10) || 0,
			theme: (el.getAttribute('data-theme') || 'auto').toLowerCase(),
			apiOverride: el.getAttribute('data-api') || '',
			grouped: el.getAttribute('data-grouped') === 'true',
			shortLabel: el.getAttribute('data-short-label') || 'Right now',
			longLabel: el.getAttribute('data-long-label') || 'Ongoing'
		};

		el.classList.add('nt-alerts-widget');
		el.classList.add('nt-alerts-theme-' + options.theme);
		el.setAttribute('role', 'region');
		el.setAttribute('aria-label', 'Service alerts');
		el.setAttribute('aria-live', 'polite');

		fetchAndRender(el, options);

		var handle = setInterval(function () {
			if (!document.hidden) fetchAndRender(el, options);
		}, POLL_MS);

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) fetchAndRender(el, options);
		});

		// Expose for test pages that want to force a refresh.
		el._ntAlertsRefresh = function () { fetchAndRender(el, options); };
		el._ntAlertsInterval = handle;
	}

	function apiBase(options) {
		if (options.apiOverride) return options.apiOverride.replace(/\/$/, '');
		if (SCRIPT_EL && SCRIPT_EL.src) {
			var src = SCRIPT_EL.src.split('?')[0];
			var idx = src.indexOf('/wp-content/');
			if (idx > -1) return src.slice(0, idx) + '/wp-json/' + NAMESPACE;
		}
		return window.location.origin + '/wp-json/' + NAMESPACE;
	}

	function fetchAndRender(el, options) {
		var url = options.route
			? apiBase(options) + '/alerts/route/' + encodeURIComponent(options.route)
			: apiBase(options) + '/alerts/active';

		fetch(url, { credentials: 'omit', mode: 'cors' })
			.then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			})
			.then(function (data) { render(el, data, options); })
			.catch(function () { renderError(el); });
	}

	function render(el, data, options) {
		el.innerHTML = '';

		if (options.grouped) {
			renderGrouped(el, data, options);
			appendFooter(el, data);
			return;
		}

		var alerts = selectAlerts(data, options);
		if (!alerts.length) {
			el.appendChild(buildEmpty());
			appendFooter(el, data);
			return;
		}

		var list = document.createElement('div');
		list.className = 'nt-alerts-widget__list';
		for (var i = 0; i < alerts.length; i++) {
			list.appendChild(renderAlert(alerts[i], 3));
		}
		el.appendChild(list);
		appendFooter(el, data);
	}

	function renderGrouped(el, data, options) {
		var rawShort = (data && data.alerts && data.alerts.short_term) ? data.alerts.short_term.slice() : [];
		var rawLong  = (data && data.alerts && data.alerts.long_term)  ? data.alerts.long_term.slice()  : [];

		// Honor data-type filter even in grouped mode.
		if (options.type === 'short_term') rawLong = [];
		if (options.type === 'long_term')  rawShort = [];

		// Honor data-limit (per section, since the user cap usually means
		// "max per group" when groups are visible).
		if (options.limit > 0) {
			rawShort = rawShort.slice(0, options.limit);
			rawLong  = rawLong.slice(0,  options.limit);
		}

		if (!rawShort.length && !rawLong.length) {
			el.appendChild(buildEmpty());
			return;
		}

		if (rawShort.length) {
			el.appendChild(buildSection(options.shortLabel, 'short', rawShort));
		}
		if (rawLong.length) {
			el.appendChild(buildSection(options.longLabel, 'long', rawLong));
		}
	}

	function buildSection(label, key, alerts) {
		var section = document.createElement('section');
		section.className = 'nt-alerts-widget__section nt-alerts-widget__section--' + key;

		var heading = document.createElement('h3');
		heading.className = 'nt-alerts-widget__section-title';
		heading.textContent = label + ' (' + alerts.length + ')';
		section.appendChild(heading);

		var list = document.createElement('div');
		list.className = 'nt-alerts-widget__list';
		for (var i = 0; i < alerts.length; i++) {
			list.appendChild(renderAlert(alerts[i], 4));
		}
		section.appendChild(list);
		return section;
	}

	function buildEmpty() {
		var empty = document.createElement('p');
		empty.className = 'nt-alerts-widget__empty';
		empty.textContent = 'No active service alerts.';
		return empty;
	}

	function selectAlerts(data, options) {
		if (!data || !data.alerts) return [];
		var short = data.alerts.short_term || [];
		var long = data.alerts.long_term || [];

		var pool;
		if (options.type === 'short_term') pool = short.slice();
		else if (options.type === 'long_term') pool = long.slice();
		else pool = short.concat(long);

		if (options.limit > 0 && pool.length > options.limit) {
			pool = pool.slice(0, options.limit);
		}
		return pool;
	}

	function renderAlert(alert, headingLevel) {
		var severity = alert.severity || 'info';
		var article = document.createElement('article');
		article.className = 'nt-alerts-widget__alert nt-alerts-widget__alert--' + severity;
		article.setAttribute('aria-labelledby', 'nt-alert-' + alert.id + '-title');

		// Header: severity chip (icon + word). Category lives in the title.
		var header = document.createElement('header');
		header.className = 'nt-alerts-widget__header';

		var chip = document.createElement('span');
		chip.className = 'nt-alerts-widget__severity-chip';
		chip.appendChild(severityIcon(severity));
		var chipText = document.createElement('span');
		chipText.className = 'nt-alerts-widget__severity-chip-text';
		chipText.textContent = SEVERITY_LABEL[severity] || severity;
		chip.appendChild(chipText);
		header.appendChild(chip);

		article.appendChild(header);

		var levelTag = (headingLevel >= 1 && headingLevel <= 6) ? 'h' + headingLevel : 'h3';
		var title = document.createElement(levelTag);
		title.className = 'nt-alerts-widget__title';
		title.id = 'nt-alert-' + alert.id + '-title';
		title.textContent = alert.title || (CATEGORY_LABEL[alert.category] || 'Service alert');
		article.appendChild(title);

		if (alert.description) {
			var desc = document.createElement('p');
			desc.className = 'nt-alerts-widget__description';
			desc.textContent = alert.description;
			article.appendChild(desc);
		}

		if (alert.reason && REASON_LABEL[alert.reason]) {
			var reason = document.createElement('p');
			reason.className = 'nt-alerts-widget__reason';
			var reasonLabel = document.createElement('span');
			reasonLabel.className = 'nt-alerts-widget__reason-label';
			reasonLabel.textContent = 'Reason: ';
			reason.appendChild(reasonLabel);
			reason.appendChild(document.createTextNode(REASON_LABEL[alert.reason]));
			article.appendChild(reason);
		}

		var routesData = alert.routes_detail && alert.routes_detail.length
			? alert.routes_detail
			: (alert.routes || []).map(function (id) { return { id: id }; });
		if (routesData.length) {
			article.appendChild(buildRouteChips(routesData));
		}

		if (alert.closed_stops && alert.closed_stops.length) {
			article.appendChild(buildStopsList('Closed stops', alert.closed_stops, 'closed'));
		}
		if (alert.alternate_stops && alert.alternate_stops.length) {
			article.appendChild(buildStopsList('Use these stops instead', alert.alternate_stops, 'alternate'));
		}

		if (alert.images && alert.images.length) {
			article.appendChild(buildImagesGrid(alert.images));
		}

		article.appendChild(renderTimes(alert));

		if (alert.details_url) {
			var link = document.createElement('a');
			link.className = 'nt-alerts-widget__details';
			link.href = alert.details_url;
			link.rel = 'noopener';
			link.textContent = 'More details';
			article.appendChild(link);
		}

		return article;
	}

	function renderTimes(alert) {
		var p = document.createElement('p');
		p.className = 'nt-alerts-widget__times';

		var posted = document.createElement('time');
		posted.dateTime = alert.posted_at || '';
		posted.textContent = 'Posted ' + formatFriendly(alert.posted_at);
		// Screen readers get the absolute time so "2:42 pm" isn't ambiguous.
		if (alert.posted_at) {
			posted.setAttribute('aria-label', 'Posted ' + formatVerbose(alert.posted_at));
		}
		p.appendChild(posted);

		if (alert.expires_at) {
			var sep = document.createTextNode(' — ');
			p.appendChild(sep);
			var exp = document.createElement('time');
			exp.dateTime = alert.expires_at;
			exp.textContent = 'expires ' + formatFriendly(alert.expires_at);
			exp.setAttribute('aria-label', 'expires ' + formatVerbose(alert.expires_at));
			p.appendChild(exp);
		}

		return p;
	}

	function buildRouteChips(routes) {
		var ul = document.createElement('ul');
		ul.className = 'nt-alerts-widget__route-chips';
		ul.setAttribute('aria-label', 'Routes affected');
		routes.forEach(function (r) {
			if (!r || !r.id) return;
			var li = document.createElement('li');
			li.className = 'nt-alerts-widget__route-chip-item';
			var chip = document.createElement('span');
			chip.className = 'nt-alerts-widget__route-chip';
			chip.textContent = r.id;
			if (r.color) {
				chip.style.backgroundColor = r.color;
				chip.style.borderColor = r.color;
				chip.style.color = pickTextColor(r.color);
			}
			if (r.label) chip.title = r.label;
			li.appendChild(chip);
			ul.appendChild(li);
		});
		return ul;
	}

	function pickTextColor(hex) {
		if (!hex || hex.charAt(0) !== '#') return '#1a1a1a';
		var c = hex.slice(1);
		if (c.length === 3) c = c.split('').map(function (x) { return x + x; }).join('');
		if (c.length !== 6) return '#1a1a1a';
		var r = parseInt(c.slice(0, 2), 16);
		var g = parseInt(c.slice(2, 4), 16);
		var b = parseInt(c.slice(4, 6), 16);
		var yiq = (r * 299 + g * 587 + b * 114) / 1000;
		return yiq >= 150 ? '#1a1a1a' : '#ffffff';
	}

	function buildImagesGrid(images) {
		var grid = document.createElement('div');
		grid.className = 'nt-alerts-widget__images';
		images.forEach(function (img) {
			if (!img || !img.thumbnail) return;
			var a = document.createElement('a');
			a.className = 'nt-alerts-widget__image';
			a.href = img.url || img.thumbnail;
			a.target = '_blank';
			a.rel = 'noopener';
			var im = document.createElement('img');
			im.src = img.thumbnail;
			im.alt = img.alt || '';
			im.loading = 'lazy';
			if (img.width)  im.width  = img.width;
			if (img.height) im.height = img.height;
			a.appendChild(im);
			grid.appendChild(a);
		});
		return grid;
	}

	function buildStopsList(prefix, stops, modifier) {
		var wrap = document.createElement('div');
		wrap.className = 'nt-alerts-widget__stops nt-alerts-widget__stops--' + modifier;

		var label = document.createElement('p');
		label.className = 'nt-alerts-widget__stops-label';
		label.textContent = prefix + ':';
		wrap.appendChild(label);

		var ul = document.createElement('ul');
		ul.className = 'nt-alerts-widget__stops-list';
		stops.forEach(function (s) {
			var name = (s && s.name) ? s.name : (s && s.id) || '';
			if (!name) return;
			var li = document.createElement('li');
			li.textContent = name;
			ul.appendChild(li);
		});
		wrap.appendChild(ul);
		return wrap;
	}

	function formatVerbose(iso) {
		if (!iso) return '';
		var d = new Date(iso);
		if (isNaN(d.getTime())) return iso;
		return d.toLocaleString(undefined, {
			weekday: 'long',
			month: 'long',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function severityIcon(severity) {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('class', 'nt-alerts-widget__icon');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('aria-hidden', 'true');
		svg.setAttribute('focusable', 'false');
		var path = document.createElementNS(ns, 'path');
		path.setAttribute('d', SEVERITY_ICON[severity] || SEVERITY_ICON.info);
		path.setAttribute('fill', 'currentColor');
		svg.appendChild(path);
		return svg;
	}

	function formatFriendly(iso) {
		if (!iso) return '';
		var d = new Date(iso);
		if (isNaN(d.getTime())) return iso;
		var today = new Date();
		var sameDay =
			d.getFullYear() === today.getFullYear() &&
			d.getMonth() === today.getMonth() &&
			d.getDate() === today.getDate();
		var time = d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
		if (sameDay) return time;
		var date = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
		return date + ' ' + time;
	}

	function appendFooter(el, data) {
		if (!data || !data.updated_at) return;
		var foot = document.createElement('p');
		foot.className = 'nt-alerts-widget__updated';
		var t = document.createElement('time');
		t.dateTime = data.updated_at;
		t.textContent = 'Updated ' + formatFriendly(data.updated_at);
		foot.appendChild(document.createTextNode('Service alerts feed · '));
		foot.appendChild(t);
		el.appendChild(foot);
	}

	function renderError(el) {
		el.innerHTML = '';
		var p = document.createElement('p');
		p.className = 'nt-alerts-widget__error';
		p.textContent = 'Service alerts are temporarily unavailable. ';
		var a = document.createElement('a');
		a.href = 'https://niagaratransit.ca/alerts';
		a.rel = 'noopener';
		a.textContent = 'See niagaratransit.ca/alerts for current alerts.';
		p.appendChild(a);
		el.appendChild(p);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
