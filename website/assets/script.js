(function () {
	var yearEl = document.getElementById('year');
	if (yearEl) {
		yearEl.textContent = new Date().getFullYear();
	}

	var form = document.getElementById('signup-form');
	var note = document.getElementById('signup-note');
	if (!form || !note) {
		return;
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var email = (form.elements.namedItem('email') || {}).value || '';
		note.className = 'cta-note';
		note.textContent = '';

		if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
			note.className = 'cta-note error';
			note.textContent = 'Please enter a valid email address.';
			return;
		}

		fetch('/wp-json/plugindaddy/v1/keys/request', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ email: email })
		})
			.then(function (res) { return res.json().catch(function () { return {}; }); })
			.then(function () {
				note.className = 'cta-note success';
				note.textContent = 'Check your inbox — we just sent your API key.';
				form.reset();
			})
			.catch(function () {
				note.className = 'cta-note error';
				note.textContent = 'Something went wrong. Please try again.';
			});
	});
})();
