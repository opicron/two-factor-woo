# Two Factor Woo

Bridges the [Two Factor](https://wordpress.org/plugins/two-factor/) plugin with WooCommerce so customers can set up and use two-factor authentication entirely from the **My Account** area — no wp-admin required.

## Features

- Two-step login flow on the WooCommerce login form: credentials are checked first, then the auth code field appears only for accounts that have 2FA enabled
- **My Account → 2FA Authentication** settings page where customers can enable, disable, and configure their preferred 2FA provider
- Identity revalidation gate before the settings page is editable (prevents session hijacking from changing 2FA config)
- Rate limiting on the AJAX pre-check endpoint (10 attempts per IP / per username per 10-minute window)
- Accounts without 2FA enabled are unaffected and log in normally
- Removes the FIDO U2F and Dummy providers on the frontend (admin can still use them)
- Compatible with TOTP (authenticator app) and Email providers

## Requirements

| Dependency | Version |
|---|---|
| WordPress | 5.9+ (6.1+ recommended) |
| WooCommerce | 5.0+ |
| [Two Factor](https://wordpress.org/plugins/two-factor/) | Latest |

## Installation

1. Install and activate the **Two Factor** plugin from the WordPress plugin directory.
2. Upload the `two-factor-woo` folder to `wp-content/plugins/` and activate it.
3. Customers can now visit **My Account → 2FA Authentication** to enable 2FA on their account.

No additional configuration is required.

## How it works

### Login flow

```
Customer submits WooCommerce login form
        │
        ▼
JS sends credentials to a server-side pre-check (AJAX)
        │
        ├─ 2FA enabled on this account → auth code field appears
        │       │
        │       └─ Customer enters code → form submits normally
        │               │
        │               └─ PHP validates code, stamps session, allows login
        │
        └─ No 2FA (or invalid credentials) → form re-submits normally
                │
                └─ WooCommerce handles login or shows credential error
```

If JavaScript is unavailable, users with 2FA enabled are blocked from logging in without a code. Users without 2FA enabled are unaffected and continue to log in normally.

### Settings page

The **My Account → 2FA Authentication** page renders the Two Factor plugin's own configuration UI. Before changes can be saved, the customer must verify their current auth code (session revalidation). This prevents an attacker with a live unattended session from disabling 2FA.

## Security notes

- The AJAX pre-check endpoint uses rate limiting (transient-based) to limit credential stuffing. It also avoids credential enumeration: the response does not distinguish between wrong credentials and a valid account without 2FA.
- The settings save action is protected by a WordPress nonce and the Two Factor `current_user_can_update_two_factor_options()` check.
- The revalidation AJAX action requires a logged-in session and a nonce.
- Enabling or disabling a 2FA provider destroys all other active sessions for that user.

## Supported providers

The following Two Factor providers work with this plugin:

| Provider | Status |
|---|---|
| TOTP (authenticator app) | Supported |
| Email | Supported |
| FIDO U2F | Hidden on frontend |
| Dummy | Hidden on frontend |

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
