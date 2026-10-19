<?php

/**
 * Gate das diretivas próprias do ACULTA no web/.htaccess (ADR-010: Apache no Homelab, LiteSpeed na produção).
 *
 * Reprova se o bloco que serve módulos ES do PDF.js com o MIME type correto sumir ou for alterado. O PDF.js precisa
 * de application/javascript para .mjs; sem ele, o navegador recusa o módulo e o visualizador de PDF quebra.
 * A diretiva fica dentro de <IfModule mod_mime.c>, como as demais do arquivo. A validação real nos dois servidores
 * continua manual (docs/architecture/environments.md).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/web/.htaccess';

if (!is_file($path)) {
  fwrite(STDERR, "FAIL: web/.htaccess não existe.\n");
  exit(1);
}

$text = (string) file_get_contents($path);
$errors = [];

if (!preg_match('#<IfModule\s+mod_mime\.c>\s*AddType\s+application/javascript\s+\.mjs\s*</IfModule>#i', $text)) {
  $errors[] = 'web/.htaccess não tem "<IfModule mod_mime.c>" com "AddType application/javascript .mjs" (PDF.js, módulos ES).';
}

// Uma única declaração: duplicar o bloco em outro arquivo de scaffold ou no mesmo .htaccess deixa dois pontos de verdade.
if (preg_match_all('/AddType\s+application\/javascript\s+\.mjs/i', $text) !== 1) {
  $errors[] = 'A diretiva AddType ... .mjs deve aparecer exatamente uma vez em web/.htaccess.';
}

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, "FAIL: $error\n");
  }
  fwrite(STDERR, sprintf("validate-htaccess-directives: FAIL (%d)\n", count($errors)));
  exit(1);
}

echo "validate-htaccess-directives: PASS (diretiva .mjs em mod_mime.c, uma ocorrência)\n";
exit(0);
