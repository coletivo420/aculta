# ACULTA deployment runbook — complete configuration sync

This runbook describes future deployment steps. No production access,
deployment, credential setup, or payment was performed in the local phase.

## Configuration policy

- `config/sync` is the complete desired Drupal configuration.
- The Portal account manifest is an additional critical validation subset,
  not the deployment scope.
- Use the regular full configuration import. Partial imports, Config Split,
  and Config Ignore are not the deployment strategy.
- Environment variables and private runtime configuration provide secrets;
  never export their values to config sync.

## Database policy: development States versus production

- Development uses the mutable SQLite Runtime at `var/database/aculta-runtime.sqlite`.
- The immutable SQLite snapshots in `estados/` are integral development States, not production backups or deployment artifacts. Never upload, restore, or point production at one.
- Production remains MariaDB. Production credentials and MariaDB settings come only from private environment configuration on the production host.
- A deployment transfers code, locked Composer dependencies, and full `config/sync`; it does not transfer the Runtime, an Estado, SQL dumps, or uploaded/private files.
- The first Estado contains the source database's complete records and may include personal data and values already persisted in the source database. Keep the repository private before publishing it.

## Multidomain architecture

All public hosts use the same Drupal application, database, configuration, and
Drupal user/session. Configure these seven hosts to the same production
document root (`/home/u670506985/domains/aculta.org/public_html/web`, or the
equivalent root supported by the final hosting layout):

| Purpose | Host | Responsibility |
| --- | --- | --- |
| MAIN | `aculta.org` | Institutional site, central administration, shared technical integrations |
| ACCOUNT | `conta.aculta.org` | Login, recovery, confirmation, account data and private account area |
| SUPPORT | `apoio.aculta.org` | Public support portal and supportable projects |
| Observatório da Maconha Coletivo 420 (Purpose: magazine) | `coletivo420.aculta.org` | Editorial publications, articles, reports, interviews and opinion |
| WIKI420 | `wiki420.aculta.org` | Wiki420 collaborative knowledge |
| SHOP | `loja.aculta.org` | Future shop experience using the shared Commerce installation |
| COURSES | `cursos.aculta.org` | Drupal LMS learning experience |

MAIN is an institutional site, not an aggregator application. It may show
short teasers and links to other purposes; account forms, editorial archives,
support checkout, shop, Wiki420, and course tools belong to their own hosts.
Projects remain institutional content on MAIN; their financial support belongs
to SUPPORT and related reporting belongs to COLETIVO420. ACCOUNT `/apoio` is the
private order history for the current user. Commerce remains the financial
source of truth; no parallel contribution ledger is used.

Create DNS for `@`, `conta`, `apoio`, `coletivo420`, `wiki420`, `loja`, and `cursos`
only after inspecting the actual hosting origin; this runbook does not assume
A versus CNAME records. Obtain valid HTTPS for all seven names before enabling
external identity or payment services. Local development uses exact
`.test:8080` aliases; production uses canonical `.org` Domain entities and
the shared `.aculta.org` session cookie. All subdomains receiving that cookie
must remain under ACULTA's trusted control and the same Drupal application.
Do not share it with an independent or SaaS-hosted subdomain.

## Web server: Homelab versus production

The Homelab uses Apache since the migration of 2026-10-06 (ADR-006). Hostinger production uses Apache.

Do not copy Nginx directives such as `server_name`, `location`, `try_files`
or `add_header` into production. Preserve Drupal's Apache `.htaccess` and
validate the production equivalents using `mod_rewrite`, `mod_headers` or
VirtualHost configuration where Hostinger permits it.

A Homelab header such as `add_header ... always` requires an Apache-specific
review; the semantic equivalent is generally based on `Header always set ...`,
but it must only be added after inspecting the real production context.
Production must not inherit the Homelab noindex policy.

The Courses subsystem is already implemented in code with Drupal LMS and Group.
A future deploy installs the locked dependencies and imports full configuration
before any approved content provisioning or migration.

## Deployment sequence

1. Take a database/files backup and deploy the reviewed code and lockfile.
2. Install locked dependencies with Composer; do not run a global update.
3. Run database updates and import all configuration from the repository
   `config/sync` directory using the regular full import.
4. Only after dependencies and configuration are in place, run the reviewed,
   idempotent LMS pilot provisioner or an explicitly approved content migration.
   Course, lesson, activity, enrollment, and progress data are content, not
   configuration.
5. Provision/migrate the reviewed institutional block content. The Store
   script depends on the canonical institution block with UUID
   `80f3fc02-39b5-4386-8a32-78301b635007`.
6. Run the idempotent Store provisioner:

   ```powershell
   php .\vendor\drush\drush\drush.php php:script scripts/institution/provision-commerce-store.php
   ```

7. Confirm there is exactly one active default Store in BRL, one disabled
   Mercado Pago gateway, no access token in active/exported config, zero
   payments, and no unintended orders.
8. Confirm `drush config:status` reports no differences. Do not enable the
   gateway or open financial support during this deployment.

The Store provisioner validates the current public legal name, CNPJ, public
email, and address. If they changed, it stops without changing the Store so a
responsible person can review the source data first.

## Payment availability

Until external homologation is complete, keep `mercado_pago` disabled and do
not configure `MERCADOPAGO_PUBLIC_KEY` or `MERCADOPAGO_ACCESS_TOKEN`. The
SUPPORT portal at `apoio.aculta.org/` then communicates that financial support
is unavailable and does not link into payment checkout. No real or sandbox payment credentials
belong in Git, configuration sync, logs, or reports.

The installed `commerce_mercado_pago` release is 3.0.0-rc3, a release
candidate outside the Drupal Security Advisory Policy. Its local code uses the
Checkout Pro Preferences API. Preferences is legacy/classic but still
supported; Mercado Pago recommends Orders API for new integrations. This
release keeps Preferences and records a future migration debt. Do not edit the
contrib or add a custom Mercado Pago client.

`aculta_portal` guards the native Commerce notification endpoint before the
contrib controller. It requires POST, an enabled gateway, the environment
Key `MERCADOPAGO_WEBHOOK_SECRET`, `x-signature`, `x-request-id`, and a single
signed `data.id`; after official SDK validation, it normalizes only the
supported `type=payment` event to the legacy query shape expected by this
contrib. Unsigned legacy IPN is rejected. No payment is processed by the
portal. The gateway must stay disabled after deployment until all external
homologation steps below pass and a separate decision approves activation.

The SDK validator is local and does not perform an HTTP request. No Mercado
Pago endpoint was called in the local release-candidate phase. Official
references: [Checkout Pro Orders API](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-orders/create-order)
and [Webhook notifications and signature validation](https://www.mercadopago.com.br/developers/en/docs/links-and-debts/additional-content/your-integrations/notifications/webhooks).

## Post-deploy external homologation — separate phase

1. Verify SMTP2GO sender/DNS and test account activation and password reset.
2. Configure real Turnstile site/secret keys and test server-side Siteverify.
3. Configure Google OAuth and test login, association, disconnection, and
   lockout protections.
4. Provide `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_ACCESS_TOKEN`, and
   `MERCADOPAGO_WEBHOOK_SECRET` through the approved runtime secret mechanism;
   rebuild cache and confirm the gateway is still disabled.
5. Configure the Mercado Pago Webhook URL
   `https://aculta.org/integracoes/pagamentos/mercado_pago/notificacao` (verify
   the route generated by the installed Commerce gateway before registration);
   select only the supported `payment` event. Do not configure legacy IPN.
6. Send the official test webhook and verify signature rejection/acceptance,
   modern payload normalization, duplicate notifications, approved/pending/
   rejected outcomes, browser return, guest and authenticated ownership.
7. Review refund behavior and confirm Commerce idempotency. Only after every
   check passes may a separate human-approved change consider enabling the
   gateway and opening financial support.

None of those external steps is performed by this runbook or was performed in
the local configuration phase.

## Web server: Homelab versus Produção

- Homelab (`toca`) uses Apache since 2026-10-06 (ADR-006). Production at Hostinger uses Apache.
- Do not copy Nginx directives such as `server_name`, `location`, `try_files`,
  `add_header`, or `nginx reload` into production instructions.
- Preserve Drupal's standard `.htaccess` in the Apache document root. Configure
  redirects with `mod_rewrite` or the actual Apache VirtualHost only after
  checking the production hosting context. Use `mod_headers` for response
  headers; where the equivalent of Nginx `add_header ... always` is required,
  Apache's semantic form is `Header always set ...`, after validating that
  directive in the real Apache context.
- Do not add a production `noindex` rule. Production hosts are intended to be
  indexable when published. The Homelab noindex policy is environment-specific.
