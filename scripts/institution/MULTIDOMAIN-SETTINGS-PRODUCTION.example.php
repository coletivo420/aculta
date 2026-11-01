<?php

// Add this allowlist to the production settings.php during controlled deploy.
// Never replace it with a wildcard subdomain pattern.
$settings['trusted_host_patterns'] = [
  '^aculta\.org$',
  '^conta\.aculta\.org$',
  '^apoio\.aculta\.org$',
  '^revista\.aculta\.org$',
  '^wiki\.aculta\.org$',
  '^loja\.aculta\.org$',
  '^cursos\.aculta\.org$',
];

// Cross-subdomain authentication also requires an environment services override
// with session.storage.options.cookie_domain = '.aculta.org' and
// cookie_samesite = 'Lax'. Do not assume a tracked sites/default/services.yml:
// validate the active container parameter during deploy.
