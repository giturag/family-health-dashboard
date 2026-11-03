# Security notes

This is a sanitised public snapshot. It contains no database credentials,
local configuration, logs or patient data. The database dump in
`sql/schema.sql` is schema-only.

## Controls implemented

- **Configuration:** credentials are loaded from a gitignored
  `config/config.local.php`, with `FHD_DB_*` environment overrides. No secrets
  are committed.
- **Authentication:** passwords are stored with `password_hash()` and verified
  with `password_verify()`; the session ID is regenerated on sign-in.
- **Sessions:** HttpOnly, SameSite=Lax cookies, with `Secure` enabled when
  HTTPS is detected.
- **Authorisation:** role gates for each portal plus object-level ownership
  checks, so a patient cannot read another patient's records and a doctor can
  only open patients they have an appointment or visit with.
- **CSRF:** per-session tokens on state-changing forms; deletes require POST.
- **Input and output:** prepared statements for user input and HTML escaping on
  output.
- **Errors:** display disabled by default, errors logged, generic messages
  returned to users.
- **Direct access:** `app/` and `config/` are denied through `.htaccess` on
  Apache; use equivalent rules on Nginx.

## Known limitations

- Payment records update status only; there is no payment-gateway integration.
- The pharmacy portal is a dashboard without a dispensing workflow.
- Some legacy reporting pages are incomplete or still target older tables.
- Automated tests and CI are not included in this snapshot.

## Reporting

Please report security issues privately through the repository's Security tab
rather than opening a public issue. If private reporting is unavailable, use
the contact details on [the portfolio](https://giturag.github.io/#contact).
