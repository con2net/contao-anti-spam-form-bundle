# Changelog

All notable changes to this project will be documented in this file.

---

## [1.2.0]

### Added
- **Abuse-E-Mail-Adresse** (`c2n_abuse_email`): per-form fallback address for spam-flagged
  submissions. Additive to the existing "SPAM-Nachrichten nicht senden" checkbox — only takes
  effect when that checkbox is off, redirecting the spam-marked notification to this address
  instead of the normal recipients (NotificationCenter v1 and v2 both supported).
- **FR/IT/ES/PL translations** for the `c2nSpamBlocked` error message, extending the EN/NL
  translations contributed via PR #8 by Klaus Beeck (VisionThinks).
- Compatibility range extended to include **Contao `^5.7`**.

### Changed
- **ALTCHA JS widget** updated from 3.0.4 to 3.2.2.
- Numeric form IDs are now resolved directly from `$form->id` instead of parsing the `auto_form_`
  string prefix — more robust across Contao versions (groundwork from PR #8).
- The session retry-timestamp is now reset consistently across **all** spam-block paths (previously
  inconsistent between the honeypot, min-time and max-time paths).

### Fixed
- **Fix #7**: `timetoken.js` matched forms by the `auto_form_` ID prefix, which only exists for
  forms without a custom alias — a form with a custom alias produces `auto_<alias>` instead, so
  the JS-token check silently never ran for those forms. Now matches the generic `auto_` prefix.
- **Duplicate `FormLoadListener` hook registration** (registered via both a PHP attribute and
  `services.yml`), causing double execution and session conflicts.
- **`Form::addError()` crash on Contao 4.13**: `Form::addError()` only exists in Contao 5.3+;
  calling it unconditionally (as originally proposed in PR #8) crashed on 4.13. Guarded with
  `method_exists()`.
- **Silent SPAM block on Contao 4.13**: the 4.13 fallback above (guarding `Form::addError()`)
  still left 4.13 senders with no visible feedback at all — it fell back to a session flash
  message + redirect, which in practice showed nothing since the target page rarely renders
  flash messages. Blocking now hooks into `validateFormField` and attaches the error to a real
  form field via `Widget::addError()` before Contao commits to the success path — same inline,
  no-redirect experience as Contao 5.3+, using the same mechanism the bundle's own ALTCHA widget
  already relies on. The old redirect remains only as a fallback for forms with no eligible
  visible field (e.g. honeypot + submit button only).

---

## [1.1.0]

### Added
- **Zero-config HMAC key**: The bundle now auto-generates and persists a secure HMAC key in the database (`tl_c2n_settings`). No manual key setup required for new installations.
- **Shared settings table** `tl_c2n_settings`: A namespaced key-value store for all con2net bundles, designed to be reused by future extensions.
- **ALTCHA JS Widget v3.0.4**: Upgraded from v1.3.0. The new widget supports PBKDF2 natively, uses a modern Svelte-based architecture, and improves accessibility.
- **PBKDF2 as default algorithm**: New installations use `PBKDF2/SHA-256` by default — more secure than plain SHA hashing.
- **PBKDF2/SHA-384 and PBKDF2/SHA-512**: Two additional PBKDF2 variants available via `algorithm: 'pbkdf2-sha384'` and `algorithm: 'pbkdf2-sha512'` for higher security requirements.
- **Backwards compatibility for legacy SHA configs**: Existing installations with `algorithm: 'SHA-256'` (or SHA-384/512) in `config.yml` continue to work without any changes. The bundle automatically detects legacy config and uses the V1 challenge format.
- **Optional manual HMAC key** via `.env.local` (`ALTCHA_HMAC_KEY`): Power users and production environments can still set their own key, which always takes precedence over the auto-generated one.
- **Prepared for Argon2id/Scrypt**: Server-side implementation is in place. Full widget integration (including worker registration) is planned for a future release.
- **Resolves Issue #5**: ALTCHA library and widget updated to current versions with improved algorithm support.

### Changed
- **ALTCHA PHP Library** upgraded from `^1.2` to `^2.0` (`altcha-org/altcha`).
- `algorithm` config default changed from `SHA-256` to `pbkdf2`.
- Allowed algorithm values in `config.yml` extended: `pbkdf2`, `pbkdf2-sha384`, `pbkdf2-sha512`, `argon2id`, `scrypt` added alongside legacy `SHA-256`, `SHA-384`, `SHA-512`.
- ALTCHA widget now uses `auto="onfocus"` instead of `auto="onload"`: verification starts when the user interacts with the form, reducing unnecessary CPU usage on page load.

### Removed
- Requirement to manually configure `ALTCHA_HMAC_KEY` in `.env.local`. This is now fully optional.

---

## [1.0.4]

### Fixed
- **Issue #6**: Emails were still sent despite the spam block being triggered. Root cause: `Controller::redirect()` threw an internal exception that was caught upstream. Fixed by using native `header() + exit()` in `blockSpam()`.
- **Issue #2**: JavaScript token validation failed with `mp_forms` (AJAX-based multi-page forms). The CSS selector `form[id^="auto_form_"]` did not match forms without an `id` attribute. Fixed by switching to `input[name="FORM_SUBMIT"][value^="auto_form_"]`. Also added a `MutationObserver` for AJAX compatibility and a guard against double-initialization.

---

## [1.0.3]

### Fixed
- Contao 5.3 compatibility: resolved service container error affecting the spam blocking feature.

---

## [1.0.2]

### Fixed
- GDPR compliance improvements for ALTCHA integration.
- Logging optimizations: reduced noise in production logs.

---

## [1.0.1]

### Fixed
- Minor compatibility fixes for Contao 4.13 and 5.3.

---

## [1.0.0]

### Added
- Initial release.
- 7-layer spam protection: ALTCHA captcha, IP blacklist (StopForumSpam.com), email blacklist, content analysis with pattern matching, honeypot fields (3 variants), time-based validation, JavaScript token validation.
- Contao 4.13 and 5.3 LTS compatibility.
- Score-based content analysis with configurable thresholds.
- Debug mode with detailed logging.
- GDPR-compliant: all assets served locally, no external tracking.