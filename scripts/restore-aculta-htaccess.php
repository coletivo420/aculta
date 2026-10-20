<?php

declare(strict_types=1);

/**
 * Restore the ACULTA block in web/.htaccess after Composer scaffolding.
 *
 * The Drupal scaffold rewrites web/.htaccess on install and update, which removes the
 * ACULTA directive that serves .mjs files (PDF.js) with a JavaScript MIME type. This
 * script re-adds that block when it is missing. It is idempotent.
 */

$file = dirname(__DIR__) . '/web/.htaccess';
$block = <<<'HTACCESS'


# ACULTA: PDF.js ships ES modules with the .mjs extension. Module scripts need
# a JavaScript MIME type; some hosts otherwise serve these files as text/plain.
<IfModule mod_mime.c>
  AddType application/javascript .mjs
</IfModule>
HTACCESS;

if (!is_file($file)) {
  fwrite(STDERR, "web/.htaccess não encontrado.\n");
  exit(1);
}
$content = (string) file_get_contents($file);
if (str_contains($content, 'AddType application/javascript .mjs')) {
  exit(0);
}
if (file_put_contents($file, rtrim($content) . "\n" . $block . "\n") === FALSE) {
  fwrite(STDERR, "Não foi possível gravar web/.htaccess.\n");
  exit(1);
}
echo "Bloco ACULTA do .htaccess restaurado.\n";
