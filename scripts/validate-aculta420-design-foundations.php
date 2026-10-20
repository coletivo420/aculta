<?php

declare(strict_types=1);

/**
 * Static checks for the ACULTA420 0.2-A semantic design foundations.
 *
 * This validator deliberately does not bootstrap Drupal and does not test
 * Portal behavior. It checks the theme's token and ownership boundaries only.
 */

$options = getopt('', ['root:']);
$root_argument = $options['root'] ?? dirname(__DIR__);
require_once __DIR__ . '/lib/Aculta420DesignFoundationsAnalyzer.php';
if (!is_string($root_argument) || !Aculta420DesignFoundationsAnalyzer::isAbsolutePath($root_argument) || !is_dir($root_argument)) {
  fwrite(STDERR, "Validator root must be an existing absolute directory.\n");
  exit(2);
}
$root = realpath($root_argument);
if (!is_string($root)) {
  fwrite(STDERR, "Validator root cannot be resolved safely.\n");
  exit(2);
}
$theme = $root . '/web/themes/custom/aculta420';
$failures = [];
$checks = 0;

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$tokens_path = $theme . '/css/tokens.css';
$tokens_css = is_file($tokens_path) ? file_get_contents($tokens_path) : FALSE;
$check(is_string($tokens_css), 'Semantic token stylesheet exists and is readable.');
$token_analysis = Aculta420DesignFoundationsAnalyzer::analyzeTokens(is_string($tokens_css) ? $tokens_css : '');
foreach ($token_analysis['assertions'] as [$condition, $message]) {
  $check($condition, $message);
}
$dark_contrast_results = $token_analysis['dark_contrast'];
$focus_contrast_results = $token_analysis['focus_contrast'];

$runtime_extensions = ['php', 'module', 'inc', 'theme', 'twig', 'yml', 'yaml', 'js', 'css'];
$runtime_files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
  if (!$file->isFile() || !in_array(strtolower($file->getExtension()), $runtime_extensions, TRUE)) {
    continue;
  }
  $path = $file->getPathname();
  if (str_contains($path, DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR)) {
    continue;
  }
  $runtime_files[] = $path;
}

$auth_pattern = '/social_auth(?:_google)?|social_auth_login|captcha(?:\.settings)?|turnstile|social_auth_google\.settings|GOOGLE_OAUTH_CLIENT_(?:ID|SECRET)|\boauth\b/i';
$domain_pattern = '/DomainInterface|DomainPurposeManager|domain\.negotiator|aculta_portal|HTTP_HOST|SERVER_NAME|\bhostname\b|\bgetHost\s*\(|\bgetHostname\s*\(|(?:getStorage|storage)\s*\(\s*[\'"]domain|\\Drupal\s*::/i';
$structural_dark_overrides = 0;
$dark_twig_branches = 0;
$dark_twig_inline_js_branches = 0;
$twig_inline_premature_scripts = 0;
$dark_php_branches = 0;
$dark_js_layout_behavior = 0;
$theme_info = file_get_contents($theme . '/aculta420.info.yml') ?: '';
$theme_libraries = file_get_contents($theme . '/aculta420.libraries.yml') ?: '';
$check(preg_match($auth_pattern, $theme_info . "\n" . $theme_libraries) !== 1, 'Theme metadata and libraries have no authentication or anti-bot dependencies.');

foreach ($runtime_files as $path) {
  $source = file_get_contents($path);
  if (!is_string($source)) {
    $check(FALSE, 'Runtime file is readable: ' . substr($path, strlen($theme) + 1));
    continue;
  }
  $relative = substr($path, strlen($theme) + 1);
  $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $runtime_source = preg_replace('~/\*.*?\*/|<!--.*?-->|\{#.*?#\}~s', '', $source) ?? $source;
  if (in_array($extension, ['php', 'module', 'inc', 'theme', 'js'], TRUE)) {
    $runtime_source = preg_replace('/^[ \t]*(?:\/\/|#).*$/m', '', $runtime_source) ?? $runtime_source;
  }
  elseif (in_array($extension, ['yml', 'yaml'], TRUE)) {
    $runtime_source = preg_replace('/^[ \t]*#.*$/m', '', $runtime_source) ?? $runtime_source;
  }
  $check(preg_match($auth_pattern, $runtime_source) !== 1, 'No auth/anti-bot integration reference in ' . $relative . '.');
  $check(preg_match($domain_pattern, $runtime_source) !== 1, 'No Domain resolution or Portal service reference in ' . $relative . '.');
}
foreach ($runtime_files as $path) {
  $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $source = file_get_contents($path);
  if (!is_string($source)) {
    continue;
  }
  if ($extension === 'css') {
    $structural_dark_overrides += Aculta420DesignFoundationsAnalyzer::countModeSelectors($source, Aculta420DesignFoundationsAnalyzer::pathsEquivalent($path, $tokens_path));
  }
  elseif ($extension === 'twig') {
    $dark_twig_branches += Aculta420DesignFoundationsAnalyzer::countTwigModeBranches($source);
    $dark_twig_inline_js_branches += Aculta420DesignFoundationsAnalyzer::countTwigEmbeddedJsModeBranches($source);
    $relative = substr($path, strlen($theme) + 1);
    // Envelope de e-mail: clientes de e-mail não carregam tokens.css. As cores são tokens (MailEnvelopeHooks) e o único seletor de modo permitido é o bloco escuro do próprio envelope.
    if ($relative === 'templates/aculta-mailer-wrap.html.twig') {
      $check(Aculta420DesignFoundationsAnalyzer::countTwigInlineCssModeSelectors($source) === 1 && str_contains($source, '{{ d.page }}'), 'Email envelope mode selector is the single dark block fed by token values.');
    }
    else {
      $structural_dark_overrides += Aculta420DesignFoundationsAnalyzer::countTwigInlineCssModeSelectors($source);
    }
    $check(!Aculta420DesignFoundationsAnalyzer::hasTwigInlineRawColorLiteral($source), 'CSS literals in Twig style blocks need semantic tokens: ' . $relative . '.');
    $twig_inline_premature_scripts += Aculta420DesignFoundationsAnalyzer::hasTwigEmbeddedPrematureColorModeScript($source) ? 1 : 0;
  }
  elseif (in_array($extension, ['php', 'module', 'inc', 'theme'], TRUE)) {
    $relative = substr($path, strlen($theme) + 1);
    if ($relative === 'src/Hook/ThemeHooks.php') {
      // Único ramo de modo permitido: a aplicação do contrato aculta_color_mode do Portal (preprocess_html).
      $contract_branches = Aculta420DesignFoundationsAnalyzer::countPhpModeBranches($source);
      $check($contract_branches === 1 && str_contains($source, "'aculta_color_mode'"), 'PHP color-mode branch is only the Portal contract consumer in ThemeHooks.');
      continue;
    }
    $dark_php_branches += Aculta420DesignFoundationsAnalyzer::countPhpModeBranches($source);
  }
  elseif ($extension === 'js') {
    $dark_js_layout_behavior += Aculta420DesignFoundationsAnalyzer::countJsModeBranches($source);
  }
}
$check($structural_dark_overrides === 0, 'Color mode is token-only: no dark selector or mode query exists outside tokens.css.');
$check($dark_twig_branches === 0, 'Twig has no color-mode branch.');
$check($dark_php_branches === 0, 'PHP has no color-mode branch.');
$dark_js_layout_behavior += $dark_twig_inline_js_branches;
$check($dark_js_layout_behavior === 0, 'JavaScript has no color-mode layout behavior, including inline Twig scripts.');
$check($twig_inline_premature_scripts === 0, 'Twig inline scripts do not persist or initialize color mode prematurely.');

$css_files = array_filter($runtime_files, static fn (string $path): bool => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'css' && $path !== $tokens_path);
foreach ($css_files as $path) {
  $source = file_get_contents($path);
  if (!is_string($source)) {
    continue;
  }
  $relative = substr($path, strlen($theme) + 1);
  $check(!Aculta420DesignFoundationsAnalyzer::hasRawColorLiteral($source), 'CSS literals outside tokens.css need semantic tokens: ' . $relative . '.');
}

// F2 (ícones): os ícones vêm do Bootstrap Icons integrado à biblioteca global do tema base Bootstrap.
// Nenhum template ou componente do tema espalha SVG inline de ícone.
$check(str_contains($theme_libraries, 'bootstrap/global-styling'), 'Bootstrap Icons arrive through the global bootstrap/global-styling library.');
$inline_svg = [];
foreach ($runtime_files as $path) {
  if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['twig', 'html'], TRUE) && preg_match('/<svg[\s>]/i', (string) file_get_contents($path)) === 1) {
    $inline_svg[] = substr($path, strlen($theme) + 1);
  }
}
$check($inline_svg === [], 'Icons use Bootstrap Icons, not inline SVG in theme templates: ' . implode(', ', $inline_svg));

$catch_all = [];
foreach (['style.css', 'responsive.css'] as $name) {
  if (is_file($theme . '/css/' . $name)) {
    $catch_all[] = $name;
  }
}
$check($catch_all === [], 'No catch-all CSS file has been introduced.');

$bootstrap_copies = [];
foreach ($iterator as $file) {
  if ($file->isFile() && preg_match('/^bootstrap(?:\.min)?\.(?:css|js)$/i', $file->getFilename()) === 1) {
    $bootstrap_copies[] = $file->getPathname();
  }
}
$check($bootstrap_copies === [], 'The theme does not bundle a duplicate Bootstrap asset.');

$js_files = array_filter($runtime_files, static fn (string $path): bool => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'js');
foreach ($js_files as $path) {
  $source = file_get_contents($path);
  if (!is_string($source)) {
    continue;
  }
  $relative = substr($path, strlen($theme) + 1);
  $check(!Aculta420DesignFoundationsAnalyzer::hasPrematureColorModeScript($source), 'No premature color-mode script in ' . $relative . '.');
}

if ($failures !== []) {
  fwrite(STDERR, "ACULTA420 DESIGN FOUNDATIONS: FAIL (" . count($failures) . "/$checks checks)\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "- $failure\n");
  }
  exit(1);
}

fwrite(STDOUT, "ACULTA420 DESIGN FOUNDATIONS: PASS ($checks checks)\n");
fwrite(STDOUT, "COLOR MODE TOKEN-ONLY: PASS\n");
fwrite(STDOUT, "STRUCTURAL DARK OVERRIDES: $structural_dark_overrides\n");
fwrite(STDOUT, "DARK TWIG BRANCHES: $dark_twig_branches\n");
fwrite(STDOUT, "DARK PHP BRANCHES: $dark_php_branches\n");
fwrite(STDOUT, "DARK JS LAYOUT BEHAVIOR: $dark_js_layout_behavior\n");
foreach ($dark_contrast_results as $pair => $ratio) {
  fwrite(STDOUT, sprintf("DARK CONTRAST %s: %.2f:1\n", $pair, $ratio));
}
foreach ($focus_contrast_results as $surface => $ratio) {
  fwrite(STDOUT, sprintf("DARK FOCUS CONTRAST %s: %.2f:1\n", $surface, $ratio));
}
