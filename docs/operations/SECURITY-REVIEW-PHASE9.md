# Security Review - Phase 9

Date: 2026-10-05

This document records the Homelab review without storing credentials or
personal data. Security Review was run without autofix.

## Current disposition

| Finding | Disposition |
| --- | --- |
| Alternate administrator and UID 1 | RESOLVED_HOMELAB; UID 1 blocked after HTTP validation of a separate administrator |
| Turnstile account forms | RESOLVED |
| Shared ACCOUNT/COURSES session and global logout | RESOLVED |
| Domain isolation | RESOLVED |
| SQLite functional and concurrency checks | RESOLVED |
| Webform Default text format | ACCEPTED_KNOWN; internal Webform format, not edited |
| Basic HTML permits `img` for authenticated users | EXISTING_REVIEW_ITEM; HTML filter remains enabled, no automatic format changes |
| Custom Views access | RESOLVED; public custom Views require `access content` |
| Security Review Views warning | CONTRIBUTED_VIEWS; findings are in contrib views, unchanged |
| Security Review response headers | RESOLVED; rerun with the Homelab `--uri` found all configured headers present |
| Runtime PHP directory permissions | RESOLVED_HOMELAB_TWIG_STORAGE |
| Drush private .htaccess warning | ACCEPTED_HOMELAB_NGINX |

The final Security Review run used the Homelab URI. It passed the configured
header check and still reported the authenticated `basic_html` tag finding and
contributed Views. The command also emitted missing-result warnings in its own
implementation. Custom project Views were checked separately and have explicit
permission access.

## Administrator and UID 1

A separate administrator was created and authenticated through the normal
Homelab login form. The translated administration dashboard, People page and
Configuration page returned HTTP 200. UID 1 was then blocked through Drupal's
User Entity API and was not deleted. The temporary learner accounts used for
HTTP isolation tests and their LMS progress were removed afterward.

## Text formats and Views

`webform_default` is an internal Webform format. The Phase 9 review does not
mutate it merely to silence Security Review. Security Review also flags the
`img` tag in `basic_html` for authenticated users; Drupal's HTML filter remains
enabled, and this pre-existing format policy was not changed in this phase.

All custom public Views checked for this phase require the `access content`
permission. The Security Review Views warnings are in contributed views; no
contrib configuration was changed.

## Runtime files

The Homelab uses Nginx and the ACULTA PHP-FPM pool runs as `aculta:aculta`;
Nginx workers use `www-data`. The loaded local settings specify
`file_chmod_directory=02770` and `file_chmod_file=0660`. Drupal Core's default
PHP storage hard-codes `0777`, so the Homelab settings select a project-owned
`RestrictedTwigStorage` implementation only for the Twig storage bin. It keeps
the Core mtime-protected storage format and relies on the runtime directory's
setgid bit/default ACL instead of chmodding new cache directories. After a
cache rebuild and real HTTP rendering, Twig directories were `2770
aculta:www-data`; generated Twig PHP files were read-only `0440` for owner/group,
and no directory had `other` permissions. Standard `.htaccess` marker files
remain read-only `0444`. Both the FPM user and development CLI user could write
to the Twig directory. Core and contrib files were not modified.

`private://` resolves to `/home/aculta/private/aculta`, mode `0700`, outside the
Drupal webroot. The FPM user can write there. Nginx has no direct mapping to
that path; HTTP probes for a private-file marker returned 404 and never
returned the marker content. The generic 404 reflects the requested URL, so
the probe used a separate content marker to distinguish URL reflection from
file access. The Drush CLI `.htaccess` warning is **ACCEPTED_HOMELAB_NGINX**
because Nginx does not consume Apache `.htaccess`; permissions were not
broadened. Production uses Apache and must independently validate private-file
protection.

## Production gates

Before production launch:

1. validate administrator and account-recovery procedures in production;
2. review the authenticated `basic_html` finding;
3. validate Apache private/public files protection;
4. configure real Turnstile/SMTP/OAuth/payment secrets outside Git;
5. rerun Security Review and application smoke tests.
