/* Botón Claro form: sends to the public REST route and shows the code. No nonce, so cached pages work. */
(function () {
	'use strict';
	var T = {
		need: 'Completá este dato.',
		mail: 'Revisá el correo: parece que le falta algo.',
		net: 'No pudimos enviar tu pedido. Probá de nuevo en un momento. Si sigue sin funcionar, escribinos a %s.'
	};
	function q(root, sel) { return root.querySelector(sel); }
	function setup(box) {
		var form = q(box, '.bc-form'), start = Date.now(), busy = false;
		if (!form) { return; }
		form.hidden = false;
		var err = q(form, '.bc-error'), contact = box.getAttribute('data-contact') || '';
		var scope = form.querySelectorAll('[name=alcance]'), detail = q(form, '.bc-detail');
		Array.prototype.forEach.call(scope, function (r) {
			r.addEventListener('change', function () { if (detail) { detail.hidden = r.value !== 'parte' || !r.checked; } });
		});
		if (document.body.classList.contains('logged-in')) {
			fetch(box.getAttribute('data-yo'), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				if (j && j.success && j.data) {
					['nombre', 'correo'].forEach(function (k) {
						var el = form.elements[k];
						if (el && !el.value && j.data[k]) { el.value = j.data[k]; }
					});
				}
			}).catch(function () {});
		}
		function clear() {
			Array.prototype.forEach.call(form.querySelectorAll('.bc-bad'), function (f) {
				f.classList.remove('bc-bad');
				var m = q(f, '.bc-msg'); if (m) { m.remove(); }
				var i = q(f, 'input,textarea'); if (i) { i.removeAttribute('aria-invalid'); }
			});
			err.hidden = true;
		}
		function mark(name, text) {
			var el = form.elements[name];
			if (!el || !el.closest) { return null; }
			var f = el.closest('.bc-f');
			if (!f) { return null; }
			f.classList.add('bc-bad');
			el.setAttribute('aria-invalid', 'true');
			var m = document.createElement('span');
			m.className = 'bc-msg';
			m.id = el.id + '-e';
			m.textContent = text;
			f.appendChild(m);
			el.setAttribute('aria-describedby', ((el.getAttribute('aria-describedby') || '').replace(el.id + '-e', '') + ' ' + m.id).trim());
			return el;
		}
		function fail(text) {
			err.textContent = text;
			err.hidden = false;
			err.scrollIntoView({ block: 'center' });
		}
		function local() {
			var bad = [], first = null;
			['nombre', 'correo', 'servicio'].forEach(function (k) {
				var el = form.elements[k];
				if (el && !el.value.trim()) { bad.push([k, T.need]); }
			});
			var c = form.elements.correo;
			if (c && c.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.value.trim())) { bad.push(['correo', T.mail]); }
			var part = q(form, '[name=alcance][value=parte]');
			if (part && part.checked && !form.elements.detalle.value.trim()) { bad.push(['detalle', T.need]); }
			bad.forEach(function (b) { var el = mark(b[0], b[1]); first = first || el; });
			if (first) { first.focus(); }
			return !bad.length;
		}
		function done(j) {
			var ok = q(box, '.bc-ok'), parts = String(j.code).match(/^(.*-)([^-]+)$/) || ['', '', j.code];
			q(ok, '[data-bc=code1]').textContent = parts[1];
			q(ok, '[data-bc=code2]').textContent = parts[2];
			q(ok, '[data-bc=email]').textContent = j.email;
			q(ok, '.bc-dup').hidden = !j.dup;
			ok.setAttribute('data-code', j.code);
			q(box, '.bc-head').hidden = true;
			form.hidden = true;
			box.classList.add('bc-done');
			ok.hidden = false;
			ok.scrollIntoView({ block: 'start' });
			ok.focus({ preventScroll: true });
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (busy) { return; }
			clear();
			if (!local()) { return; }
			var data = {}, fd = new FormData(form);
			fd.forEach(function (v, k) { data[k] = v; });
			data.t = Date.now() - start;
			busy = true;
			var btn = q(form, '.bc-send');
			btn.disabled = true;
			fetch(box.getAttribute('data-rest'), {
				method: 'POST',
				credentials: 'omit',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(data)
			}).then(function (r) {
				return r.json().catch(function () { return {}; });
			}).then(function (j) {
				if (j && j.ok && j.code) { done(j); return; }
				if (j && j.error === 'campos' && j.fields) {
					var first = null;
					Object.keys(j.fields).forEach(function (k) { var el = mark(k, j.fields[k]); first = first || el; });
					if (first) { first.focus(); }
					return;
				}
				fail(j && j.message ? j.message : T.net.replace('%s', contact));
			}).catch(function () {
				fail(T.net.replace('%s', contact));
			}).then(function () {
				busy = false;
				btn.disabled = false;
			});
		});
		var copy = q(box, '.bc-copy');
		copy.addEventListener('click', function () {
			var code = q(box, '.bc-ok').getAttribute('data-code'), label = copy.textContent;
			function said() {
				copy.textContent = copy.getAttribute('data-done');
				setTimeout(function () { copy.textContent = label; }, 2500);
			}
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(code).then(said, said);
			} else {
				var ta = document.createElement('textarea');
				ta.value = code;
				ta.setAttribute('readonly', '');
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try { document.execCommand('copy'); } catch (x) {}
				ta.remove();
				said();
			}
		});
	}
	function init() { Array.prototype.forEach.call(document.querySelectorAll('[data-bclaro-form]'), setup); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
