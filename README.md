# Family Health Dashboard

A role-based healthcare records system for families. Patients, doctors, lab
technicians, administrators and pharmacy staff each get a portal scoped to their
role and their own records.

Built with PHP and MySQL on Linux, with HTML, CSS and JavaScript on the front
end.

**[Read the full case study →](https://giturag.github.io/projects/family-health-dashboard.html)**

## Features

- Role-based access for patients (and dependents), doctors, lab technicians,
  administrators and pharmacists
- Appointment scheduling and visit tracking
- Lab test requests, sample collection and result entry
- Prescription management
- Patient vitals, allergies, chronic conditions and medical history
- Billing and payment status
- Authentication, CSRF protection and object-level authorisation

## Screenshots

![Patient dashboard](docs/screenshots/patient-dashboard.png)

![Doctor dashboard](docs/screenshots/doctor-dashboard.png)

More screenshots — landing page, login, consultation, lab results and admin
dashboard — are in the [case study](https://giturag.github.io/projects/family-health-dashboard.html)
and in `docs/screenshots/`.

## Tech stack

PHP · MySQL · HTML · CSS · JavaScript · Apache/Linux

## Requirements

- PHP 8.1+ with the `mysqli` extension (tested on PHP 8.3)
- MySQL 8.0+ (the schema uses the `utf8mb4_0900_ai_ci` collation)
- Apache with `AllowOverride All`, or equivalent Nginx deny rules for `app/`
  and `config/`

## Setup

1. Clone the repository.

   ```sh
   git clone https://github.com/giturag/family-health-dashboard.git
   cd family-health-dashboard
   ```

2. Create the database and tables. The dump creates the `fhddb` database and
   all 17 tables.

   ```sh
   mysql -u root -p < sql/schema.sql
   ```

   Create a MySQL user for the application (or use an existing account) and
   grant it privileges on `fhddb`.

3. Create the local configuration and fill in the database credentials.

   ```sh
   cp config/config.example.php config/config.local.php
   ```

   Alternatively, set `FHD_DB_HOST`, `FHD_DB_NAME`, `FHD_DB_USER` and
   `FHD_DB_PASS` in the environment. Never commit `config/config.local.php`;
   it is already ignored by git.

4. Start the development server from the repository root.

   ```sh
   php -S localhost:8000
   ```

5. Open <http://localhost:8000> and create an account. Self-registration
   supports patient, doctor and lab-technician roles. To create an
   administrator, sign up and then promote the account:

   ```sql
   UPDATE subscribers SET sub_role = 'admin' WHERE email = 'you@example.com';
   ```

   Sign out and back in for the new role to take effect.

The built-in PHP server does not honour `.htaccess`. Application and config PHP
files execute rather than expose source, but deploy behind Apache or Nginx with
the included deny rules (or equivalent) for anything public.

## Project structure

```
app/         bootstrap, authentication, CSRF and shared helpers
admin/       administrator portal
doctor/      doctor and specialist portal
lab/         lab technician portal
myhealth/    patient and dependent portal
pharma/      pharmacy dashboard
config/      config.example.php (config.local.php is gitignored)
migrations/  incremental SQL migrations
sql/         schema.sql
docs/        screenshots and ERD
css/, img/   shared styles and logo
```

## Security

- Database credentials live in `config/config.local.php`, which is gitignored;
  no secrets are committed.
- Passwords are stored with `password_hash()` and checked with
  `password_verify()`.
- Prepared statements for user input, with HTML escaping on output.
- CSRF tokens on state-changing forms and POST-only deletes.
- Role checks plus object-level ownership checks for patient records.
- HttpOnly, SameSite=Lax session cookies; `Secure` when HTTPS is detected.
- Error display is off by default; errors are logged instead.

See [SECURITY.md](SECURITY.md) for the controls and known limitations.

## Status

This is a sanitised public snapshot of a university project. It contains the
application source and a schema-only database dump; no patient data or local
configuration is included. The payment flow records status only (there is no
payment-gateway integration), and the pharmacy dashboard does not yet include a
dispensing workflow. Automated tests and CI are not included.
