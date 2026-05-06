
document.addEventListener('DOMContentLoaded', function()
{
	// Inline revalidation on my-account 2FA settings page
	var revalidateWrap = document.getElementById('wc-2fa-revalidate-wrap');
	if (revalidateWrap) {
		var revalidateBtn   = document.getElementById('wc-2fa-revalidate-btn');
		var revalidateCode  = document.getElementById('wc-2fa-revalidate-code');
		var revalidateMsg   = document.getElementById('wc-2fa-revalidate-msg');
		var revalidateNonce = document.getElementById('wc-2fa-revalidate-nonce');

		function doRevalidate() {
			revalidateMsg.textContent = '';
			revalidateBtn.disabled = true;

			var data = new FormData();
			data.append('nonce', revalidateNonce.value);
			data.append('authcode', revalidateCode.value.trim());

			fetch(WC_2FA.revalidate_url, {
				method: 'POST',
				body: data,
				credentials: 'same-origin'
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					window.location.reload();
				} else {
					revalidateMsg.textContent = (res.data && res.data.message) ? res.data.message : 'Verification failed.';
					revalidateBtn.disabled = false;
				}
			})
			.catch(function() {
				revalidateMsg.textContent = 'An error occurred. Please try again.';
				revalidateBtn.disabled = false;
			});
		}

		revalidateBtn.addEventListener('click', doRevalidate);
		revalidateCode.addEventListener('keydown', function(e) {
			if (e.key === 'Enter') doRevalidate();
		});
	}

	var form = document.querySelector('.woocommerce-form-login');
	if (!form) return;

	var twofaWrap = document.getElementById('two-factor-2fa-wrap');
	var submitBtn = form.querySelector('button[type=submit]');

	// Hide the authcode field by default when JS is available; it is shown only
	// after the AJAX pre-check confirms 2FA is required for this account.
	// Without JS the field stays visible so no-JS users with 2FA can still log in.
	// Using a visually-hidden class (not display:none) keeps the input in the DOM
	// so iOS AutoFill / Bitwarden can detect autocomplete="one-time-code" at page load.
	twofaWrap.classList.add('two-fa-visually-hidden');

	form.addEventListener('submit', function handler(e){
		if (twofaWrap.classList.contains('two-fa-visually-hidden')) {
			// Check for empty fields first
			var username = form.querySelector('input[name="username"]').value.trim();
			var password = form.querySelector('input[name="password"]').value.trim();

			if (!username || !password) {
				return; // Let WooCommerce show its own validation error
			}

			e.preventDefault();

			var data = new FormData(form);
			data.delete('two_factor_authcode');

			fetch(WC_2FA.ajax_url, {
				method: 'POST',
				body: data,
				credentials: 'same-origin'
			})
			.then(r => r.json())
			.then(res => {
				if (res.two_factor_required) {
					// Show the authcode field and wait for the user to re-submit
					twofaWrap.classList.remove('two-fa-visually-hidden');
					twofaWrap.querySelector('input').focus();
				} else {
					// No 2FA needed (or rate-limited): let WooCommerce handle the real
					// login — this correctly sets the session cookie on success or shows
					// credential errors on failure.
					form.removeEventListener('submit', handler);
					submitBtn.click();
				}
			})
			.catch(function() {
				// Network error: fall back to the normal WooCommerce form submit
				form.removeEventListener('submit', handler);
				submitBtn.click();
			});
		}
	});

});
