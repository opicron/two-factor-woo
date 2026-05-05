
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
	var errorSpan = twofaWrap.querySelector('.two-factor-error');
	var submitBtn = form.querySelector('button[type=submit]');

	form.addEventListener('submit', function handler(e){
		if (twofaWrap.style.display === 'none') {
			// Check for empty fields first!
			var username = form.querySelector('input[name="username"]').value.trim();
			var password = form.querySelector('input[name="password"]').value.trim();

			if (!username || !password) {
				return; // Default Woo logic
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
					twofaWrap.style.display = '';
					errorSpan.style.display = 'none';
					twofaWrap.querySelector('input').focus();
				} else if (res.success) {
					window.location = res.redirect || '/my-account/';
				} else {
					// Remove handler and trigger button click
					form.removeEventListener('submit', handler);
					submitBtn.click();
				}
			});
		}
	});

});
