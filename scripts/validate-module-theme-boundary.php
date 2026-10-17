<?php

/**
 * Barreira módulo x tema (aculta_portal x aculta420). Somente leitura; não inicia o Drupal.
 *
 * Regra (AGENTS.md, "Fronteira módulo x tema"):
 * - MÓDULO (web/modules/custom/aculta_portal/**) = comportamento e dados: rotas, controllers, forms e #ajax, validação,
 *   endpoints, JS de comportamento, checkout panes, access, traduções. Não depende do tema: não referencia o tema
 *   aculta420, suas bibliotecas nem seus caminhos, e não declara CSS (visual é do tema).
 * - TEMA (web/themes/custom/aculta420/**) = apresentação: Twig, CSS, layout, ícones, preprocess de apresentação.
 *   Não tem lógica de negócio, rotas, form_alter, serviços nem dependência de código do módulo.
 * - Dívida conhecida só entra na lista de exceções com o motivo e o item do registro; o cliquet da marcação de
 *   apresentação no módulo não pode subir.
 *
 * Reprova se alguma regra for violada. Exceções: ver $allowedThemeReferences e $presentationBaseline.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$module = $root . '/web/modules/custom/aculta_portal';
$theme = $root . '/web/themes/custom/aculta420';
$failures = [];
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$ok) {
    $failures[] = $message;
  }
};

/** Arquivos do módulo que citam o tema por motivo registrado (dívida, não padrão). */
$allowedThemeReferences = [
  // Verificação de ambiente: o relatório de requisitos mostra se o tema está instalado. Não é dependência visual.
  'src/Controller/PortalRequirementsController.php',
];

/** Cliquet: marcação de apresentação (classes Bootstrap/utilitárias) dentro de atributos de classe do módulo. */
$presentationBaseline = 0;

$tokenPattern = '/\b(btn|btn-[a-z-]+|alert|alert-[a-z-]+|badge|col|col-[a-z0-9-]+|row|g-[0-9]|d-[a-z-]+|m[trblxy]?-[0-9a-z]+|p[trblxy]?-[0-9a-z]+|text-[a-z-]+|bi-[a-z0-9-]+|visually-hidden|form-[a-z-]+|card[a-z-]*|list-group[a-z-]*|nav-[a-z-]+|justify-content-[a-z-]+|align-items-[a-z-]+|collapse|offcanvas[a-z-]*|pb-[0-9]|fw-[a-z0-9]+)\b/';

$files = static function (string $dir, array $exts): array {
  $out = [];
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if ($file->isFile() && in_array($file->getExtension(), $exts, TRUE)) {
      $out[] = $file->getPathname();
    }
  }
  return $out;
};

// 1. Módulo não referencia o tema (nome, caminho ou biblioteca aculta420/...), salvo exceções registradas.
$moduleCode = $files($module, ['php', 'yml', 'twig', 'js', 'inc', 'module']);
foreach ($moduleCode as $path) {
  $rel = substr($path, strlen($module) + 1);
  if (str_starts_with($rel, 'tests/') || str_starts_with($rel, 'modules/aculta_deployer/')) {
    continue;
  }
  $text = (string) file_get_contents($path);
  $hit = str_contains($text, 'aculta420') || str_contains($text, 'themes/custom/');
  $assert(!$hit || in_array($rel, $allowedThemeReferences, TRUE), "Módulo depende do tema: $rel cita aculta420 ou themes/custom/ (ver docs, Fronteira módulo x tema).");
}

// 2. Biblioteca do módulo não declara CSS: estilo é do tema.
$libraries = (string) file_get_contents($module . '/aculta_portal.libraries.yml');
$assert(!preg_match('/^\s+css:\s*$/m', $libraries), 'aculta_portal.libraries.yml declara CSS: o estilo deve ficar no tema aculta420.');

// 3. Módulo não depende de biblioteca do tema (dependencies ou attached).
foreach (['aculta_portal.libraries.yml', 'aculta_portal.module', 'aculta_portal.info.yml'] as $name) {
  if (is_file($module . '/' . $name)) {
    $assert(!str_contains((string) file_get_contents($module . '/' . $name), 'aculta420/'), "$name referencia biblioteca do tema.");
  }
}

// 4. Tema não tem arquivos de comportamento do Drupal (serviços, rotas, módulo, instalação).
foreach (['services.yml', 'routing.yml', 'module', 'install', 'post_update.php'] as $suffix) {
  $found = glob($theme . '/*.' . $suffix) ?: [];
  $assert($found === [], 'Tema contém arquivo de comportamento (*.' . $suffix . '): ' . implode(', ', array_map('basename', $found)));
}

// 5. Código PHP do tema: só hooks de preprocess (apresentação), sem form_alter, rota, serviço ou módulo.
foreach ($files($theme . '/src', ['php']) as $path) {
  $text = (string) file_get_contents($path);
  $rel = substr($path, strlen($theme) + 1);
  preg_match_all("/#\[Hook\('([^']+)'\)\]/", $text, $hooks);
  foreach ($hooks[1] as $hook) {
    $assert(str_starts_with($hook, 'preprocess_'), "Tema registra hook não-apresentação ($hook) em $rel.");
  }
  $assert(!str_contains($text, 'Drupal::'), "Tema chama serviço estático (Drupal::) em $rel.");
  $assert(!str_contains($text, 'Drupal\\aculta_portal'), "Tema depende de classe do módulo em $rel.");
}
$themeFile = glob($theme . '/*.theme') ?: [];
foreach ($themeFile as $path) {
  $assert(!preg_match('/function\s+aculta420_(form|route|entity|cron|install|update|mail|menu|block|preprocess_(?!page|node|field|image|views))/', (string) file_get_contents($path)), 'Arquivo .theme do tema define hook de comportamento.');
}

// 6. Tema não referencia serviços, bibliotecas, configuração ou classes do módulo (css/js/twig/src).
foreach (array_merge($files($theme . '/templates', ['twig']), $files($theme . '/js', ['js']), $files($theme . '/css', ['css']), $files($theme . '/components', ['twig', 'js', 'css']), $files($theme . '/src', ['php'])) as $path) {
  $text = (string) file_get_contents($path);
  $rel = substr($path, strlen($theme) + 1);
  $assert(!str_contains($text, 'aculta_portal/') && !str_contains($text, "drupalSettings.acultaPortal") && !str_contains($text, 'aculta_portal.'), "Tema referencia o módulo aculta_portal em $rel.");
}

// 7. Cliquet: marcação de apresentação no módulo (só dentro de atributos de classe) não aumenta.
$tokens = 0;
foreach (array_merge($files($module . '/src', ['php']), $files($module . '/templates', ['twig']), $files($module . '/js', ['js'])) as $path) {
  foreach (file($path) as $line) {
    // Linhas com data-portal-ui (token semântico) ou role (valor ARIA) não são classe de design.
    if (str_contains($line, 'data-portal-ui') || str_contains($line, "'role'")) {
      continue;
    }
    if (preg_match("/'class'|class=\"|className|classList|'#attributes'|add\\(|addClass/", $line)) {
      preg_match_all($tokenPattern, $line, $m);
      $tokens += count($m[0]);
    }
  }
}
$assert($tokens <= $presentationBaseline, "Marcação de apresentação no módulo subiu: $tokens > linha de base $presentationBaseline (mover para o tema ou reduzir).");

// 8. Código no Aculta Portal, Design no ACULTA420: templates do módulo não têm classes (apresentação é do tema).
foreach ($files($module . '/templates', ['twig']) as $path) {
  $text = (string) file_get_contents($path);
  $assert(!preg_match('/\sclass\s*=/', $text), 'Template do módulo com class= (apresentação deve ficar no tema): ' . basename($path));
  // Todo template do módulo tem um correspondente no tema (ou é só estrutura com fallback neutro).
  $themeTemplate = $theme . '/templates/' . basename($path);
  // Template estrutural sem design (fallback de elemento genérico): o tema não precisa sobrescrever.
  $structural = ['portal-element.html.twig'];
  $assert(is_file($themeTemplate) || in_array(basename($path), $structural, TRUE), 'Template do módulo sem correspondente no tema (ACULTA420 deve sobrescrever): ' . basename($path));
}

// 9. JS do módulo não usa seletores de classe de design (.portal-*, .aculta-*): o comportamento usa data-* e ids.
foreach ($files($module . '/js', ['js']) as $path) {
  $text = (string) file_get_contents($path);
  preg_match_all("/(querySelector(?:All)?|closest)\(\s*['\"`][^'\"`]*\.(portal|aculta)-[a-z]/", $text, $m);
  $assert($m[0] === [], 'JS do módulo usa seletor de classe de design (use data-portal-* ou data-aculta-*): ' . basename($path));
}

// 10. Zero classes de design no módulo. Exceções: use-ajax (comportamento do Drupal) e is-active (estado da aba,
//     que o tema também estiliza). Lê só o conteúdo de 'class' => [...] e de addClass(...).
// draggable e role-weight: contrato de comportamento do tabledrag do Core (web/core/misc/tabledrag.js), não design.
$allowedClasses = ['use-ajax', 'is-active', 'draggable', 'role-weight'];
// Dívida conhecida fora do Messenger, registrada em docs/operations/DEBT-REGISTER.md (DT-BOUNDARY-GALLERY): a galeria
// de fotos monta a classe do PhotoSwipe. Sai desta lista quando o token data-portal-ui for criado.
$knownDebt = ['modules/aculta_gallery/src/Hook/GalleryViewHooks.php: photoswipe-gallery'];
$classAttrs = [];
// Every src/ of the module, including its submodules (aculta_messenger, aculta_user): a submodule is not exempt.
foreach (array_filter($files($module, ['php']), static fn (string $p): bool => str_contains($p, DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR)) as $path) {
  $text = (string) file_get_contents($path);
  $rel = substr($path, strlen($module) + 1);
  preg_match_all("/'class'\s*=>\s*\[([^\]]*)\]/", $text, $arrays);
  foreach ($arrays[1] as $list) {
    preg_match_all("/'([^']*)'/", $list, $items);
    foreach ($items[1] as $item) {
      if (!in_array($item, $allowedClasses, TRUE)) {
        $classAttrs[] = $rel . ': ' . $item;
      }
    }
  }
  preg_match_all("/addClass\('([^']*)'\)/", $text, $adds);
  foreach ($adds[1] as $item) {
    if (!in_array($item, $allowedClasses, TRUE)) {
      $classAttrs[] = $rel . ': addClass ' . $item;
    }
  }
  preg_match_all("/class=\\?\"/", $text, $raw);
  if ($raw[0] !== []) {
    $classAttrs[] = $rel . ': class="..." em string';
  }
}
$classAttrs = array_values(array_diff($classAttrs, $knownDebt));
$assert($classAttrs === [], 'Classe de design no módulo (use data-portal-ui e mapeie no tema): ' . implode('; ', array_slice($classAttrs, 0, 6)));

// 10b. Todo token de data-portal-ui usado pelo módulo existe no mapa do tema (PortalUiHooks::TOKENS).
$themeMap = (string) file_get_contents($theme . '/src/Hook/PortalUiHooks.php');
preg_match_all("/^\s+'([a-z0-9-]+)' => \[/m", $themeMap, $tm);
$known = array_flip($tm[1]);
$used = [];
foreach (array_merge($files($module . '/src', ['php']), $files($module . '/templates', ['twig'])) as $path) {
  $text = (string) file_get_contents($path);
  preg_match_all("/data-portal-ui' => '([^']*)'/", $text, $m1);
  foreach ($m1[1] as $value) {
    foreach (preg_split('/\s+/', trim($value)) ?: [] as $token) {
      // Token que termina em "-" é prefixo de estado montado em runtime (ex.: 'status-' . $state).
      if ($token !== '' && !str_ends_with($token, '-')) {
        $used[$token] = substr($path, strlen($module) + 1);
      }
    }
  }
  preg_match_all("/'data-portal-ui' => '([a-z0-9-]+) ' \. /", $text, $m2);
  foreach ($m2[1] as $prefix) {
    $used[$prefix] = substr($path, strlen($module) + 1);
  }
}
// Tokens de estado montados com concatenação (status-' . $state): todos precisam existir no tema.
foreach (['status-ok', 'status-warning', 'status-error', 'status-pending', 'status-attention'] as $state) {
  $known[$state] = $known[$state] ?? NULL;
  $used[$state] = $used[$state] ?? 'estado de requisito';
}
foreach ($used as $token => $where) {
  if (in_array($token, ['requirements-status', 'status'], TRUE)) {
    continue;
  }
  $assert(array_key_exists($token, $known), "Token de design \"$token\" usado em $where não existe no mapa do tema (PortalUiHooks::TOKENS).");
}

// 11. A regra permanente está escrita no AGENTS.md, no README do módulo e na doc do tema (não se perde em edição).
$rule = 'Código no Aculta Portal, Design no ACULTA420.';
foreach ([$root . '/AGENTS.md', $module . '/README.md', $theme . '/docs/design-system.md'] as $doc) {
  $assert(str_contains((string) file_get_contents($doc), $rule), 'Regra "' . $rule . '" ausente em ' . substr($doc, strlen($root) + 1) . '.');
}

// 12. Ícones de interface: Bootstrap Icons (classe bi bi-*). SVG só como logo de marca de terceiros em assets/brands/.
foreach (array_merge($files($theme . '/templates', ['twig']), $files($theme . '/components', ['twig']), $files($theme . '/css', ['css']), $files($module . '/templates', ['twig']), $files($module . '/src', ['php']), $files($module . '/js', ['js'])) as $path) {
  $text = (string) file_get_contents($path);
  $assert(!preg_match('/<svg[\s>]/i', $text), 'SVG inline de interface (use Bootstrap Icons, classe bi bi-*): ' . substr($path, strlen($root) + 1));
}
foreach ($files($theme, ['svg']) as $svg) {
  $rel = substr($svg, strlen($theme) + 1);
  $assert(str_starts_with($rel, 'assets/brands/'), "Arquivo SVG fora de assets/brands (ícone deve ser Bootstrap Icons): $rel");
}

// 12b. Assets do tema precisam ser legíveis pelo servidor web (modo 0644): arquivo 0600 responde 403 no Apache.
foreach ($files($theme . '/assets', ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif']) as $asset) {
  $assert((fileperms($asset) & 0004) !== 0, 'Asset sem leitura para o servidor web (chmod 644): ' . substr($asset, strlen($root) + 1));
}

// 12c. Cor só em tokens: CSS do tema fora de css/tokens.css não tem cor literal (hex, rgb, hsl, hwb, lab, oklch, color-mix).
foreach ($files($theme . '/css', ['css']) as $css) {
  if (str_ends_with($css, '/tokens.css')) {
    continue;
  }
  $text = (string) file_get_contents($css);
  $assert(!preg_match('/#[0-9a-fA-F]{3,8}\b|\b(rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color-mix)\(/', $text), 'Cor literal fora de css/tokens.css (use token semântico): ' . substr($css, strlen($root) + 1));
}

// 13. Meus pagamentos removido: sem rota de meios de pagamento, sem permissão do Commerce no papel autenticado.
$routes = (string) file_get_contents($module . '/src/EventSubscriber/DomainRouteSubscriber.php');
$assert(!preg_match("/'entity\.commerce_payment_method\.(add_form|edit_form|delete_form|set_default)' => \['\/pagamentos/", $routes), 'DomainRouteSubscriber ainda define rota de meios de pagamento (/pagamentos/novo, editar, excluir, padrão).');
$assert(!str_contains((string) file_get_contents($module . '/src/AccountShellBuilder.php'), 'commerce_payment_method'), 'AccountShellBuilder ainda lista "Meus pagamentos" (commerce_payment_method).');
$assert(!str_contains((string) file_get_contents($root . '/config/sync/user.role.authenticated.yml'), 'commerce_payment_method'), 'Papel autenticado ainda tem permissão de meios de pagamento (config/sync/user.role.authenticated.yml).');
$assert(str_contains($routes, 'PaymentMethodsRedirectController::redirectToOrders'), '/pagamentos deve redirecionar 301 para /pedidos (PaymentMethodsRedirectController).');

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("validate-module-theme-boundary: FAIL (%d de %d verificações)\n", count($failures), $checks));
  exit(1);
}
printf("validate-module-theme-boundary: PASS (%d verificações; tokens de apresentação no módulo: %d, linha de base %d; classes de design no módulo: 0)\n", $checks, $tokens, $presentationBaseline);
