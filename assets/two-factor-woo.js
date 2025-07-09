
document.addEventListener('DOMContentLoaded', function() 
{
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
