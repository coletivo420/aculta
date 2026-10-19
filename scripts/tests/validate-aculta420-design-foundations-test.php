<?php

declare(strict_types=1);

/**
 * Negative regression fixtures for the ACULTA420 design-foundation validator.
 * All fixtures use a copied stylesheet and synthetic source files in a temp dir.
 */

$repository = dirname(__DIR__, 2);
$validator = $repository . '/scripts/validate-aculta420-design-foundations.php';
$analyzer = $repository . '/scripts/lib/Aculta420DesignFoundationsAnalyzer.php';
$theme_source = $repository . '/web/themes/custom/aculta420';
$temporary_root = sys_get_temp_dir() . '/aculta420-design-gate-' . bin2hex(random_bytes(6));
$temporary_theme = $temporary_root . '/web/themes/custom/aculta420';
$fixtures = 0;
$failures = [];

$copy_file = static function (string $source, string $destination): void {
  if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, TRUE) && !is_dir(dirname($destination))) {
    throw new RuntimeException('Could not create fixture directory.');
  }
  if (!copy($source, $destination)) {
    throw new RuntimeException('Could not copy fixture file.');
  }
};
$remove_tree = static function (string $path) use (&$remove_tree): void {
  if (!is_dir($path)) {
    if (file_exists($path) || is_link($path)) {
      unlink($path);
    }
    return;
  }
  foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
    $remove_tree($entry->getPathname());
  }
  rmdir($path);
};

try {
  foreach ([
    'css/tokens.css',
    'aculta420.info.yml',
    'aculta420.libraries.yml',
  ] as $relative) {
    $copy_file($theme_source . '/' . $relative, $temporary_theme . '/' . $relative);
  }
  $copy_file($validator, $temporary_root . '/scripts/validate-aculta420-design-foundations.php');
  $copy_file($analyzer, $temporary_root . '/scripts/lib/Aculta420DesignFoundationsAnalyzer.php');

  $run_validator = static function () use ($temporary_root): array {
    $command = [PHP_BINARY, $temporary_root . '/scripts/validate-aculta420-design-foundations.php', '--root=' . $temporary_root];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
      throw new RuntimeException('Could not start validator fixture process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), (string) $stdout . (string) $stderr];
  };
  // A clean baseline proves each failure below is caused by its injected fixture.
  [$baseline_status, $baseline_output] = $run_validator();
  $fixtures++;
  if ($baseline_status !== 0) {
    $failures[] = 'clean fixture baseline did not pass: ' . trim(preg_replace('/\s+/', ' ', $baseline_output) ?? $baseline_output);
  }

  require_once $analyzer;
  foreach ([
    ['/tmp/aculta420-root', 'Unix', TRUE],
    ['relative/path', 'Unix', FALSE],
    ['/tmp/aculta420/../outside', 'Unix', FALSE],
    ['C:\\aculta420\\repo', 'Windows', TRUE],
    ['C:aculta420\\repo', 'Windows', FALSE],
    ['C:\\aculta420\\..\\outside', 'Windows', FALSE],
    ['\\\\server\\share\\aculta420', 'Windows', TRUE],
    ['\\\\server\\share\\..\\outside', 'Windows', FALSE],
  ] as [$path, $platform, $expected]) {
    $fixtures++;
    if (Aculta420DesignFoundationsAnalyzer::isAbsolutePath($path, $platform) !== $expected) {
      $failures[] = 'absolute path validation returned the wrong result for ' . $platform . ' syntax';
    }
  }

  foreach ([
    ['C:\\Repo\\web\\themes\\custom\\aculta420\\css\\tokens.css', 'C:/Repo/web/themes/custom/aculta420/css/tokens.css', 'Windows', TRUE],
    ['C:\\Repo\\web\\themes\\custom\\aculta420\\css\\other.css', 'C:/Repo/web/themes/custom/aculta420/css/tokens.css', 'Windows', FALSE],
    ['/repo/web/themes/custom/aculta420/css/tokens.css', '/repo/web/themes/custom/aculta420/css/tokens.css', 'Unix', TRUE],
  ] as [$first, $second, $platform, $expected]) {
    $fixtures++;
    if (Aculta420DesignFoundationsAnalyzer::pathsEquivalent($first, $second, $platform) !== $expected) {
      $failures[] = 'path equivalence returned the wrong result for ' . $platform . ' syntax';
    }
  }

  $positive_cases = [
    ['commented CSS selector is ignored', 'css/comments.css', "/* [data-theme=\"dark\"] .card { display:none } */\n/* .dark .card { padding:0 } */", 'STRUCTURAL DARK OVERRIDES: 0'],
    ['commented Twig branch is ignored', 'templates/comments.html.twig', '{# {% set x = theme == \'dark\' ? \'a\' : \'b\' %} #}{{ label }}', 'DARK TWIG BRANCHES: 0'],
    ['commented PHP switch is ignored', 'src/Comments.php', "<?php // switch (\$theme) { case 'dark': }\nreturn 1;", 'DARK PHP BRANCHES: 0'],
    ['PHP comment inside unrelated condition is ignored', 'src/InlineComment.php', "<?php if (\$enabled /* theme dark */) { return; }", 'DARK PHP BRANCHES: 0'],
    ['commented JavaScript switch is ignored', 'js/comments.js', "/* switch (theme) { case 'dark': card.hidden = true; } */\nconst label = 'normal';", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['inline JavaScript comment is ignored', 'js/inline-comment.js', "const enabled = true; // if (theme === 'dark') { localStorage.setItem('x', 'y'); }", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['unrelated localStorage use is allowed', 'js/notice-storage.js', "localStorage.setItem('noticeDismissed', '1');", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['unrelated localStorage read is allowed', 'js/notice-storage-read.js', "const dismissed = localStorage.getItem('noticeDismissed');", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['CSS comments do not count as raw colors', 'css/comment-color.css', '/* legacy fallback was #fff; color: red */', ''],
    ['mode-like words inside a string are not a branch', 'js/string-only-mode.js', "if (label === 'dark theme') { renderLabel(); }", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['unrelated Twig conditional text is not a mode branch', 'templates/string-only-mode.html.twig', "{% if label == 'dark theme' %}label{% endif %}", 'DARK TWIG BRANCHES: 0'],
    ['unrelated PHP conditional text is not a mode branch', 'src/StringOnlyMode.php', "<?php if (\$label === 'dark theme') { echo 'label'; }", 'DARK PHP BRANCHES: 0'],
    ['PHP switch on a non-mode value is accepted', 'src/LanguageSwitch.php', "<?php switch (\$locale) { case 'dark': echo 'label'; break; default: break; }", 'DARK PHP BRANCHES: 0'],
    ['PHP alternative switch on a non-mode value is accepted', 'src/AlternativeLanguageSwitch.php', "<?php switch (\$locale): case 'dark': echo 'label'; break; endswitch;", 'DARK PHP BRANCHES: 0'],
    ['JavaScript switch on a non-mode value is accepted', 'js/language-switch.js', "switch (locale) { case 'dark': label.textContent = 'dark'; break; default: break; }", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['JavaScript function switch on a non-mode value is accepted', 'js/language-getter-switch.js', "switch (getLocale()) { case 'dark': label.textContent = 'dark'; break; }", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['unrelated condition with mode-like output is accepted', 'js/unrelated-output.js', "if (isCompact(foo())) { label.textContent = 'dark'; }", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['multiline unrelated ternary result is accepted', 'js/multiline-unrelated-ternary.js', "const label = compact ?\n  'dark' :\n  'plain';", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['PHP switch consequent text is not a case label', 'src/SwitchConsequent.php', "<?php switch (\$theme) { case 'compact': echo 'dark'; break; }", 'DARK PHP BRANCHES: 0'],
    ['PHP semicolon case terminator excludes consequent', 'src/SemicolonCase.php', "<?php switch (\$theme) { case 'compact'; echo 'dark'; break; }", 'DARK PHP BRANCHES: 0'],
    ['JavaScript switch consequent text is not a case label', 'js/switch-consequent.js', "switch (theme) { case 'compact': label.textContent = 'dark'; break; }", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['JavaScript optional chaining case does not consume consequent', 'js/optional-case.js', "switch (theme) { case options?.compact: label.textContent = 'dark'; break; }", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['Twig ternary on a non-mode value is accepted', 'templates/language-ternary.html.twig', "{% set label = locale == 'dark' ? 'a' : 'b' %}", 'DARK TWIG BRANCHES: 0'],
    ['Twig ternary result mentioning a mode is not a mode branch', 'templates/result-mode-ternary.html.twig', "{% set label = compact ? 'dark' : 'plain' %}", 'DARK TWIG BRANCHES: 0'],
    ['Twig conditional ternary result modes are not predicates', 'templates/if-result-ternary.html.twig', "{% if compact ? theme : 'dark' %}visible{% endif %}", 'DARK TWIG BRANCHES: 0'],
    ['parenthesized Twig ternary result mentioning a mode is not a mode branch', 'templates/parenthesized-result-mode-ternary.html.twig', "{% set label = (compact ? theme : 'dark') %}", 'DARK TWIG BRANCHES: 0'],
    ['nested Twig ternary with mode-like results is accepted', 'templates/nested-result-ternary.html.twig', "{% set x = compact ? (label ? 'dark' : 'plain') : 'other' %}", 'DARK TWIG BRANCHES: 0'],
    ['JavaScript ternary result mentioning a mode is accepted', 'js/result-mode-ternary.js', "const label = compact ? theme : 'dark';", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['JavaScript className compound unrelated assignment is accepted', 'js/class-name-unrelated.js', "element.className += ' card';", 'DARK JS LAYOUT BEHAVIOR: 0'],
    ['PHP match result mentioning a mode is not a mode branch', 'src/MatchResult.php', "<?php \$variant = match (\$density) { 'compact' => 'dark', default => 'plain' };", 'DARK PHP BRANCHES: 0'],
    ['brace switch ignores nested alternative switch labels', 'src/BraceWithAlternativeSwitch.php', "<?php switch (\$theme) { case 'compact': switch (\$locale): case 'dark': break; endswitch; break; }", 'DARK PHP BRANCHES: 0'],
    ['unrelated dataset assignment is accepted', 'js/dataset-status.js', "document.documentElement.dataset.status = 'dark';", 'DARK JS LAYOUT BEHAVIOR: 0'],
  ];
  foreach ($positive_cases as [$name, $relative, $contents, $expected_message]) {
    $fixture_path = $temporary_theme . '/' . $relative;
    if (!is_dir(dirname($fixture_path)) && !mkdir(dirname($fixture_path), 0700, TRUE) && !is_dir(dirname($fixture_path))) {
      throw new RuntimeException('Could not create source fixture directory.');
    }
    file_put_contents($fixture_path, $contents);
    [$status, $output] = $run_validator();
    $fixtures++;
    if ($status !== 0 || !str_contains($output, $expected_message)) {
      $failures[] = $name . ' was not accepted by the clean contract: ' . trim(preg_replace('/\s+/', ' ', $output) ?? $output);
    }
    unlink($fixture_path);
  }

  $tokens_path = $temporary_theme . '/css/tokens.css';
  $original_tokens = file_get_contents($tokens_path);
  if (!is_string($original_tokens)) {
    throw new RuntimeException('Could not read token fixture.');
  }
  $light_end = strpos($original_tokens, "\n}");
  if ($light_end === FALSE) {
    throw new RuntimeException('Light token block is missing from fixture.');
  }
  $positive_tokens = substr_replace($original_tokens, "\n  --fixture-safe-token: 1;", $light_end, 0);
  file_put_contents($tokens_path, $positive_tokens);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status !== 0) {
    $failures[] = 'a custom property in a supported mode block was rejected';
  }
  file_put_contents($tokens_path, $original_tokens);

  $cases = [
    ['.dark selector layout rule', 'css/fixtures/dark.css', '.dark .card { padding: 1rem; }', 'Color mode is token-only'],
    ['.dark-theme selector layout rule', 'css/fixtures/dark.css', '.dark-theme .card { padding: 1rem; }', 'Color mode is token-only'],
    ['functional pseudo-class mode selector', 'css/fixtures/dark.css', ':where(.dark) .card { display: none; }', 'Color mode is token-only'],
    ['data-theme selector layout rule', 'css/fixtures/dark.css', '[data-theme="dark"] .card { display: none; }', 'Color mode is token-only'],
    ['quoted CSS comment opener does not hide later mode selector', 'css/quoted-comment-marker.css', '.icon::before { content: "/*"; } [data-bs-theme="dark"] .card { display:none; } /* note */', 'Color mode is token-only'],
    ['case-insensitive theme attribute selector layout rule', 'css/fixtures/dark-attribute-flag.css', '[data-bs-theme="dark" i] .card { display: none; }', 'Color mode is token-only'],
    ['theme attribute word-match selector layout rule', 'css/fixtures/dark-attribute-word-match.css', '[data-bs-theme~="dark"] .card { display: none; }', 'Color mode is token-only'],
    ['theme attribute prefix-match selector layout rule', 'css/fixtures/dark-attribute-prefix-match.css', '[data-bs-theme^="dark"] .card { display: none; }', 'Color mode is token-only'],
    ['partial theme attribute prefix can select dark mode', 'css/fixtures/dark-attribute-prefix-partial.css', '[data-bs-theme^="d"] .card { display: none; }', 'Color mode is token-only'],
    ['unquoted partial theme attribute prefix can select dark mode', 'css/fixtures/dark-attribute-prefix-unquoted.css', '[data-theme^=d] .card { display: none; }', 'Color mode is token-only'],
    ['partial theme attribute suffix can select dark mode', 'css/fixtures/dark-attribute-suffix-partial.css', '[data-theme$="ark"] .card { display: none; }', 'Color mode is token-only'],
    ['partial theme attribute substring can select dark mode', 'css/fixtures/dark-attribute-contains-partial.css', '[data-color-scheme*="ar"] .card { display: none; }', 'Color mode is token-only'],
    ['theme attribute language-match selector layout rule', 'css/fixtures/dark-attribute-language-match.css', '[data-bs-theme|="dark"] .card { display: none; }', 'Color mode is token-only'],
    ['data-bs-theme selector layout rule', 'css/fixtures/dark.css', '[data-bs-theme="dark"] .card { display: none; }', 'Color mode is token-only'],
    ['light selector layout rule', 'css/fixtures/light.css', '.light-theme .card { padding: 2rem; }', 'Color mode is token-only'],
    ['Twig color-mode branch', 'templates/fixture.html.twig', "{% if theme == 'dark' %}dark markup{% endif %}", 'Twig has no color-mode branch'],
    ['Twig color-mode ternary', 'templates/ternary.html.twig', "{{ theme == 'light' ? 'light' : 'dark' }}", 'Twig has no color-mode branch'],
    ['nested Twig ternary mode predicate', 'templates/nested-mode-ternary.html.twig', "{% set x = compact ? (theme == 'dark' ? 'a' : 'b') : 'c' %}", 'Twig has no color-mode branch'],
    ['PHP color-mode branch', 'src/Fixture.php', "<?php if (\$theme === 'dark') { echo 'different'; }", 'PHP has no color-mode branch'],
    ['PHP colorScheme branch', 'src/ColorScheme.php', "<?php if (\$colorScheme === 'dark') { echo 'different'; }", 'PHP has no color-mode branch'],
    ['PHP color-mode match', 'src/MatchFixture.php', "<?php \$variant = match (\$theme) { 'dark' => 'compact', default => 'standard' };", 'PHP has no color-mode branch'],
    ['PHP Portal color-mode contract match', 'src/PortalColorMode.php', "<?php \$themeMode = match (\$variables['aculta_color_mode'] ?? 'light') { 'dark' => 'dark', 'auto' => NULL, default => 'light' };", 'PHP has no color-mode branch'],
    ['PHP switch case arm', 'src/SwitchFixture.php', "<?php switch (\$theme) { case 'dark': \$layout = 'compact'; break; default: \$layout = 'standard'; }", 'PHP has no color-mode branch'],
    ['PHP semicolon case mode arm is detected', 'src/SemicolonModeCase.php', "<?php switch (\$theme) { case 'dark'; \$layout = 'compact'; break; }", 'PHP has no color-mode branch'],
    ['PHP alternative switch case arm', 'src/AlternativeSwitch.php', "<?php switch (\$theme): case 'dark': \$layout = 'compact'; break; endswitch;", 'PHP has no color-mode branch'],
    ['nested alternative PHP switch retains outer case labels', 'src/NestedAlternativeSwitch.php', "<?php switch (\$theme): case 'compact': switch (\$locale): case 'en': break; endswitch; case 'dark': break; endswitch;", 'PHP has no color-mode branch'],
    ['JavaScript color-mode layout branch', 'js/fixture.js', "if (theme === 'dark') { card.style.display = 'none'; }", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript colorScheme branch', 'js/color-scheme.js', "if (colorScheme === 'dark') { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['PHP color-scheme getter branch', 'src/ColorSchemeGetter.php', "<?php if (getColorScheme() === 'dark') { echo 'different'; }", 'PHP has no color-mode branch'],
    ['JavaScript color-scheme getter branch', 'js/color-scheme-getter.js', "if (getColorScheme() === 'dark') { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['Twig color-scheme getter branch', 'templates/color-scheme-getter.html.twig', "{% if getColorScheme() == 'dark' %}different{% endif %}", 'Twig has no color-mode branch'],
    ['Twig enclosing mode predicate around unrelated ternary', 'templates/twig-enclosing-ternary.html.twig', "{% if theme == 'dark' and (compact ? enabled : disabled) %}different{% endif %}", 'Twig has no color-mode branch'],
    ['Twig suffix mode predicate after unrelated ternary', 'templates/twig-suffix-ternary.html.twig', "{% if (compact ? enabled : disabled) and theme == 'dark' %}different{% endif %}", 'Twig has no color-mode branch'],
    ['JavaScript boolean mode branch', 'js/boolean-mode.js', "if (isDarkMode) { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['nested JavaScript ternary mode predicate', 'js/nested-mode-ternary.js', "const x = compact ? (theme === 'dark' ? 'a' : 'b') : 'c';", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript switch case arm', 'js/switch.js', "switch (theme) { case 'dark': card.hidden = true; break; default: card.hidden = false; }", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript switch method discriminant', 'js/switch-method.js', "switch (theme.toLowerCase()) { case 'dark': card.hidden = true; break; }", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript switch getter discriminant', 'js/switch-getter.js', "switch (getTheme()) { case 'dark': card.hidden = true; break; }", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript switch scans mode case after regex brace', 'js/switch-regex.js', "switch (theme) { case 'compact': const closeBrace = /\\}/; break; case 'dark': card.hidden = true; break; }", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript switch scans mode case after return regex brace', 'js/switch-return-regex.js', "switch (theme) { case 'compact': return /\\}/; case 'dark': card.hidden = true; break; }", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript ternary with object arms', 'js/object-arm-ternary.js', "const layout = theme === 'dark' ? { hidden: true } : {};", 'JavaScript has no color-mode layout behavior'],
    ['nested JavaScript if condition is scanned', 'js/nested-if.js', "if (getTheme(foo(bar())) === 'dark') { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['regex parentheses do not truncate JavaScript if condition', 'js/regex-condition.js', "if (/\\)/.test(value) && theme === 'dark') { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['regex URL slashes do not truncate JavaScript if condition', 'js/regex-url-condition.js', "if (/https?:\\/\\//.test(url) && theme === 'dark') { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['multiline JavaScript mode ternary is scanned', 'js/multiline-mode-ternary.js', "const layout = theme === 'dark'\n  ? 'compact'\n  : 'normal';", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript mode ternary preserves semicolons inside strings', 'js/semicolon-string-ternary.js', "const layout = theme === 'dark' ? 'compact;wide' : 'normal';", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript mode ternary preserves escaped quotes before semicolons', 'js/escaped-quote-semicolon-ternary.js', "const layout = theme === 'dark' ? \"compact\\\";wide\" : \"normal\";", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript dataset.theme assignment', 'js/dataset-theme.js', "document.documentElement.dataset.theme = 'dark';", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript dataset.bsTheme assignment', 'js/dataset-bs-theme.js', "document.documentElement.dataset.bsTheme = 'dark';", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript dynamic dataset.theme assignment', 'js/dataset-dynamic.js', "document.documentElement.dataset.bsTheme = getTheme();", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript dataset logical assignment', 'js/dataset-logical.js', "document.documentElement.dataset.bsTheme ??= getTheme();", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript compound className mode assignment', 'js/class-name-compound.js', "document.documentElement.className += ' theme-dark';", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript logical className mode assignment', 'js/class-name-logical.js', "document.documentElement.className ||= 'theme-dark';", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript classList uses every protected mode class', 'js/class-list-is-dark.js', "element.classList.add('is-dark');", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript classList.replace cannot activate mode class', 'js/class-list-replace.js', "element.classList.replace('base', 'dark');", 'JavaScript has no color-mode layout behavior'],
    ['dynamic className mode getter is rejected', 'js/class-name-dynamic.js', "document.documentElement.className = getTheme();", 'JavaScript has no color-mode layout behavior'],
    ['dynamic classList mode getter is rejected', 'js/class-list-dynamic.js', "document.documentElement.classList.add(getTheme());", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript dataset fallback assignment', 'js/dataset-or.js', "document.documentElement.dataset.theme ||= nextMode;", 'JavaScript has no color-mode layout behavior'],
    ['JavaScript bracket dataset assignment', 'js/dataset-bracket.js', "document.documentElement.dataset['theme'] = nextMode;", 'JavaScript has no color-mode layout behavior'],
    ['dynamic data-theme setAttribute is rejected', 'js/set-attribute-theme.js', "element.setAttribute('data-theme', getTheme());", 'JavaScript has no color-mode layout behavior'],
    ['dynamic data-color-mode setAttribute is rejected', 'js/set-attribute-color-mode.js', "element.setAttribute('data-color-mode', nextMode);", 'JavaScript has no color-mode layout behavior'],
    ['dynamic data-color-scheme setAttribute is rejected', 'js/set-attribute-color-scheme.js', "element.setAttribute('data-color-scheme', getScheme());", 'JavaScript has no color-mode layout behavior'],
    ['optional dynamic setAttribute is rejected', 'js/optional-set-attribute.js', "element.setAttribute?.('data-theme', getTheme());", 'JavaScript has no color-mode layout behavior'],
    ['direct className mode assignment is rejected', 'js/class-name-theme.js', "document.documentElement.className = 'theme-dark';", 'JavaScript has no color-mode layout behavior'],
    ['template-literal className mode assignment is rejected', 'js/class-name-template.js', 'document.documentElement.className = `dark`;', 'JavaScript has no color-mode layout behavior'],
    ['Twig statement ternary', 'templates/set-ternary.html.twig', "{% set klass = theme == 'dark' ? 'compact' : 'standard' %}", 'Twig has no color-mode branch'],
    ['Twig colorScheme branch', 'templates/color-scheme.html.twig', "{% if colorScheme == 'dark' %}different{% endif %}", 'Twig has no color-mode branch'],
    ['Twig whitespace-control mode branch', 'templates/whitespace-control.html.twig', "{%- if theme == 'dark' -%}different{%- endif -%}", 'Twig has no color-mode branch'],
    ['Dark selector in tokens stylesheet', 'css/tokens.css', "\n[data-bs-theme=\"dark\"] .fixture { padding: 1rem; }\n", 'Color mode is token-only'],
    ['Structural declaration inside dark token block', 'css/tokens.css', "\n[data-bs-theme=\"dark\"] {\n  display: none;\n}\n", 'custom properties only'],
    ['ordinary structural CSS rule in tokens stylesheet', 'css/tokens.css', "\n.card { color: #ffffff; display: none; }\n", 'Mode selectors and ACULTA/Bootstrap tokens occur only'],
    ['Twig inline script cannot branch on color mode', 'templates/inline-script.html.twig', "<script>if (theme === 'dark') { document.body.hidden = true; }</script>", 'JavaScript has no color-mode layout behavior'],
    ['Twig inline script cannot persist color mode', 'templates/inline-storage.html.twig', "<script>localStorage.setItem('theme', 'dark');</script>", 'Twig inline scripts do not persist or initialize color mode prematurely'],
    ['Twig inline script cannot restore persisted color mode', 'templates/inline-storage-read.html.twig', "<script>const saved = localStorage.getItem('theme'); applyTheme(saved);</script>", 'Twig inline scripts do not persist or initialize color mode prematurely'],
    ['Twig style block cannot add a mode selector', 'templates/inline-style.html.twig', '<style>[data-bs-theme="dark"] .card { display:none; }</style>', 'Color mode is token-only'],
    ['Twig style block cannot use raw named color', 'templates/inline-style-color.html.twig', '<style>.notice { color: red; }</style>', 'CSS literals in Twig style blocks need semantic tokens'],
    ['tokens stylesheet cannot import another stylesheet', 'css/tokens.css', "\n@import url('https://example.invalid/structural.css');\n", 'Token CSS parses within the supported flat-rule subset'],
    ['modern oklch color literal outside tokens is rejected', 'css/fixtures/oklch.css', '.notice { color: oklch(60% 0.2 120); }', 'CSS literals outside tokens.css need semantic tokens'],
    ['modern lab color literal outside tokens is rejected', 'css/fixtures/lab.css', '.notice { color: lab(50% 20 30); }', 'CSS literals outside tokens.css need semantic tokens'],
    ['named color literal outside tokens is rejected', 'css/fixtures/named-color.css', '.notice { color: red; }', 'CSS literals outside tokens.css need semantic tokens'],
    ['side-specific border color literal outside tokens is rejected', 'css/fixtures/border-top-color.css', '.notice { border-top-color: red; }', 'CSS literals outside tokens.css need semantic tokens'],
    ['bracket dataset read in mode branch is rejected', 'js/dataset-read.js', "if (document.documentElement.dataset['theme'] === 'dark') { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['protected getAttribute read in mode branch is rejected', 'js/get-attribute-read.js', "if (document.documentElement.getAttribute('data-color-mode') === 'dark') { card.hidden = true; }", 'JavaScript has no color-mode layout behavior'],
    ['persisted color-mode read is rejected', 'js/storage-read-theme.js', "const saved = localStorage.getItem('theme'); applyTheme(saved);", 'No premature color-mode script'],
    ['property-style localStorage mode write is rejected', 'js/storage-property-theme.js', "localStorage.theme = 'dark';", 'No premature color-mode script'],
    ['bracket-style sessionStorage mode read is rejected', 'js/storage-bracket-mode.js', "const saved = sessionStorage['colorMode'];", 'No premature color-mode script'],
    ['explicit color-mode initializer is rejected', 'js/initializer-call.js', "applyColorMode('dark');", 'No premature color-mode script'],
  ];
  foreach ($cases as [$name, $relative, $contents, $expected_message]) {
    $fixture_path = $temporary_theme . '/' . $relative;
    if ($relative === 'css/tokens.css') {
      $base_tokens = file_get_contents($fixture_path);
      if (!is_string($base_tokens)) {
        throw new RuntimeException('Could not read token fixture before injecting a selector.');
      }
      file_put_contents($fixture_path, $base_tokens . $contents);
      [$status, $output] = $run_validator();
      $fixtures++;
      if ($status === 0 || !str_contains($output, $expected_message)) {
        $failures[] = $name . ' was not rejected for the expected reason: ' . trim(preg_replace('/\s+/', ' ', $output) ?? $output);
      }
      file_put_contents($fixture_path, $base_tokens);
      continue;
    }
    if (!is_dir(dirname($fixture_path)) && !mkdir(dirname($fixture_path), 0700, TRUE) && !is_dir(dirname($fixture_path))) {
      throw new RuntimeException('Could not create source fixture directory.');
    }
    file_put_contents($fixture_path, $contents);
    [$status, $output] = $run_validator();
    $fixtures++;
    if ($status === 0 || !str_contains($output, $expected_message)) {
      $failures[] = $name . ' was not rejected for the expected reason: ' . trim(preg_replace('/\s+/', ' ', $output) ?? $output);
    }
    unlink($fixture_path);
  }

  // Bootstrap base colors must remain connected to their semantic ACULTA
  // source even when both a color and its RGB companion are changed together.
  $bootstrap_mapping_regression = str_replace(
    ['--bs-primary: var(--aculta-green);', '--bs-primary-rgb: 104, 148, 39;'],
    ['--bs-primary: #000000;', '--bs-primary-rgb: 0, 0, 0;'],
    $original_tokens,
  );
  if ($bootstrap_mapping_regression === $original_tokens) {
    throw new RuntimeException('Could not create Bootstrap primary semantic mapping fixture.');
  }
  file_put_contents($tokens_path, $bootstrap_mapping_regression);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, 'Light --bs-primary effectively maps')) {
    $failures[] = 'a coordinated Bootstrap base color and RGB mapping regression was not rejected';
  }
  file_put_contents($tokens_path, $original_tokens);

  $tokens_path = $temporary_theme . '/css/tokens.css';
  $original_tokens = file_get_contents($tokens_path);
  if (!is_string($original_tokens)) {
    throw new RuntimeException('Could not read token fixture.');
  }
  $dark_start = strpos($original_tokens, '[data-bs-theme="dark"]');
  if ($dark_start === FALSE) {
    throw new RuntimeException('Dark token block is missing from fixture.');
  }
  $replace_dark = static function (string $old, string $new, bool $mirror_auto = FALSE) use (&$original_tokens, $dark_start, $tokens_path): void {
    $current_tokens = file_get_contents($tokens_path);
    if (!is_string($current_tokens)) {
      throw new RuntimeException('Could not read current token fixture.');
    }
    $position = strpos($current_tokens, $old, $dark_start);
    if ($position === FALSE) {
      throw new RuntimeException('Expected dark token fixture declaration was not found.');
    }
    $updated = substr_replace($current_tokens, $new, $position, strlen($old));
    if ($mirror_auto) {
      $auto_start = strpos($updated, '@media (prefers-color-scheme: dark)');
      $auto_position = $auto_start === FALSE ? FALSE : strpos($updated, $old, $auto_start);
      if ($auto_position === FALSE) {
        throw new RuntimeException('Expected automatic token fixture declaration was not found.');
      }
      $updated = substr_replace($updated, $new, $auto_position, strlen($old));
    }
    file_put_contents($tokens_path, $updated);
  };
  $restore_tokens = static function () use (&$original_tokens, $tokens_path): void {
    file_put_contents($tokens_path, $original_tokens);
  };
  $token_cases = [
    ['approved page surface rejected when green legacy value returns', '--aculta-surface-page: #171513;', '--aculta-surface-page: #121a16;', 'resolves to approved neutral dark surface'],
    ['intermediate alias to prohibited green surface rejected', '--aculta-surface-page: #171513;', "--aculta-surface-page: var(--fixture-surface);\n  --fixture-surface: #1b2720;", 'resolves to approved neutral dark surface'],
    ['conflicting duplicate token rejected', '--aculta-surface-page: #171513;', "--aculta-surface-page: #171513;\n  --aculta-surface-page: #121a16;", 'one declaration for --aculta-surface-page'],
    ['last duplicate is used for WCAG evaluation', '--aculta-text-primary: #f4efe8;', "--aculta-text-primary: #f4efe8;\n  --aculta-text-primary: #171513;", 'meets WCAG AA'],
    ['circular token references rejected', "--aculta-text-primary: #f4efe8;\n  --aculta-text-secondary: #d8d0c8;", "--aculta-text-primary: var(--aculta-text-secondary);\n  --aculta-text-secondary: var(--aculta-text-primary);", 'resolves without missing references or cycles'],
    ['missing required token rejected', '--aculta-shell-active-text: var(--aculta-interactive-active-text);', '', 'has a dark value'],
    ['new ACULTA token alias must resolve', '--aculta-surface-page: #171513;', "--aculta-surface-page: #171513;\n  --aculta-fixture-new: var(--aculta-does-not-exist);", 'resolves without missing references or cycles'],
    ['translucent text is composited before WCAG', '--aculta-text-primary: #f4efe8;', '--aculta-text-primary: rgba(244, 239, 232, 0.01);', 'meets WCAG AA'],
    ['malformed rgba alpha is rejected', '--bs-border-color-translucent: rgba(216, 208, 200, 0.22);', '--bs-border-color-translucent: rgba(216, 208, 200, nope);', 'color token --bs-border-color-translucent'],
    ['dark translucent Bootstrap border remains on approved value', '--bs-border-color-translucent: rgba(216, 208, 200, 0.22);', '--bs-border-color-translucent: #000000;', 'Dark --bs-border-color-translucent effectively maps'],
    ['mixed legacy commas and modern slash alpha are invalid', '--aculta-text-primary: #f4efe8;', '--aculta-text-primary: rgb(244, 239, 232 / 1);', 'color token --aculta-text-primary'],
  ];
  foreach ($token_cases as [$name, $old, $new, $expected_message]) {
    $replace_dark($old, $new);
    [$status, $output] = $run_validator();
    $fixtures++;
    if ($status === 0 || !str_contains($output, $expected_message)) {
      $failures[] = $name . ' was not rejected for the expected reason: ' . trim(preg_replace('/\s+/', ' ', $output) ?? $output);
    }
    $restore_tokens();
  }

  $dark_only_token = $original_tokens . "\n[data-bs-theme=\"dark\"] {\n  --aculta-dark-only-fixture: #171513;\n}\n";
  file_put_contents($tokens_path, $dark_only_token);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, 'Light token --aculta-dark-only-fixture resolves')) {
    $failures[] = 'dark-only token was not required to resolve in light mode';
  }
  file_put_contents($tokens_path, $original_tokens);

  $uppercase_missing_alias = str_replace(
    '--aculta-shadow-hover: var(--aculta-shadow-raised);',
    '--aculta-shadow-hover: VAR(--aculta-missing-shadow);',
    $original_tokens,
  );
  file_put_contents($tokens_path, $uppercase_missing_alias);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, 'token --aculta-shadow-hover resolves without missing references')) {
    $failures[] = 'case-insensitive VAR() with missing alias was not rejected';
  }
  file_put_contents($tokens_path, $original_tokens);

  $replace_dark('--aculta-text-primary: #f4efe8;', '--aculta-text-primary: rgba(244, 239, 232, 1);', TRUE);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status !== 0) {
    $failures[] = 'fully opaque rgba foreground should retain its valid contrast: ' . trim(preg_replace('/\s+/', ' ', $output) ?? $output);
  }
  $restore_tokens();

  $replace_dark('--aculta-surface-page: #171513;', '--aculta-surface-page: rgb(23, 21, 19);', TRUE);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status !== 0) {
    $failures[] = 'numerically equivalent rgb() dark surface should remain approved: ' . trim(preg_replace('/\s+/', ' ', $output) ?? $output);
  }
  $restore_tokens();

  foreach ([
    ['physical ACULTA color token must parse', '--aculta-red: #d4452d;', '--aculta-red: nope;', 'color token --aculta-red'],
    ['Bootstrap color alias must parse', '--bs-danger: var(--aculta-red);', '--bs-danger: nope;', 'color token --bs-danger'],
    ['Bootstrap RGB companion matches its base color', '--bs-primary-rgb: 104, 148, 39;', '--bs-primary-rgb: 0, 0, 0;', 'matches --bs-primary'],
  ] as [$name, $old, $new, $expected_message]) {
    $current_tokens = file_get_contents($tokens_path);
    if (!is_string($current_tokens) || !str_contains($current_tokens, $old)) {
      throw new RuntimeException('Could not create color token fixture.');
    }
    file_put_contents($tokens_path, preg_replace('/' . preg_quote($old, '/') . '/', $new, $current_tokens, 1));
    [$status, $output] = $run_validator();
    $fixtures++;
    if ($status === 0 || !str_contains($output, $expected_message)) {
      $failures[] = $name . ' was not rejected for the expected reason';
    }
    file_put_contents($tokens_path, $original_tokens);
  }

  $link_rgb_mismatch = str_replace(
    '--aculta-link: var(--aculta-text-primary);',
    '--aculta-link: var(--aculta-text-secondary);',
    $original_tokens,
  );
  if ($link_rgb_mismatch === $original_tokens) {
    throw new RuntimeException('Could not create Bootstrap link RGB companion fixture.');
  }
  file_put_contents($tokens_path, $link_rgb_mismatch);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, '--bs-link-color-rgb matches --bs-link-color')) {
    $failures[] = 'Bootstrap link RGB companion mismatch was not rejected';
  }
  file_put_contents($tokens_path, $original_tokens);

  $surface_rgb_mismatch = str_replace(
    ['--aculta-surface-page-rgb: 242, 247, 240;', '--aculta-surface-page-rgb: 23, 21, 19;'],
    ['--aculta-surface-page-rgb: 0, 0, 0;', '--aculta-surface-page-rgb: 0, 0, 0;'],
    $original_tokens,
  );
  file_put_contents($tokens_path, $surface_rgb_mismatch);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, '--aculta-surface-page-rgb matches --aculta-surface-page')) {
    $failures[] = 'ACULTA surface RGB companion mismatch was not rejected';
  }
  file_put_contents($tokens_path, $original_tokens);

  $invalid_rgb_slash = str_replace(
    '--bs-primary: var(--aculta-green);',
    '--bs-primary: rgb(104 / 148 39);',
    $original_tokens,
  );
  file_put_contents($tokens_path, $invalid_rgb_slash);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, 'color token --bs-primary')) {
    $failures[] = 'modern RGB slash alpha before the third channel was not rejected';
  }
  file_put_contents($tokens_path, $original_tokens);

  $equivalent_rgb_primary = str_replace(
    '--bs-primary: var(--aculta-green);',
    '--bs-primary: rgb(104, 148, 39);',
    $original_tokens,
  );
  if ($equivalent_rgb_primary === $original_tokens) {
    throw new RuntimeException('Could not create equivalent RGB primary mapping fixture.');
  }
  file_put_contents($tokens_path, $equivalent_rgb_primary);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status !== 0) {
    $failures[] = 'equivalent rgb() and hexadecimal semantic colors should compare equal';
  }
  file_put_contents($tokens_path, $original_tokens);

  $modern_rgb_primary = str_replace(
    '--bs-primary: var(--aculta-green);',
    '--bs-primary: rgb(104 148 39 / 1);',
    $original_tokens,
  );
  file_put_contents($tokens_path, $modern_rgb_primary);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status !== 0) {
    $failures[] = 'valid modern space-separated rgb() with trailing slash alpha should pass';
  }
  file_put_contents($tokens_path, $original_tokens);

  $dark_start = strpos($original_tokens, '[data-bs-theme="dark"]');
  $dark_contents = substr($original_tokens, $dark_start === FALSE ? 0 : $dark_start);
  $dark_contents = str_replace(
    '--bs-body-bg: var(--aculta-surface-page);',
    '--bs-body-bg: var(--aculta-surface-muted);',
    $dark_contents,
  );
  $dark_mapping = $dark_start === FALSE ? $original_tokens : substr($original_tokens, 0, $dark_start) . $dark_contents;
  if ($dark_start === FALSE || $dark_contents === substr($original_tokens, $dark_start)) {
    throw new RuntimeException('Could not create dark Bootstrap mapping fixture.');
  }
  file_put_contents($tokens_path, $dark_mapping);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, 'Dark --bs-body-bg effectively maps')) {
    $failures[] = 'a dark-only Bootstrap body mapping regression was not rejected';
  }
  file_put_contents($tokens_path, $original_tokens);

  if ($failures === []) {
    fwrite(STDOUT, 'DESIGN FOUNDATION FIXTURES: PASS (' . $fixtures . " expected outcomes)\n");
  }
}
finally {
  $remove_tree($temporary_root);
}

if ($failures !== []) {
  fwrite(STDERR, 'DESIGN FOUNDATION FIXTURES: FAIL (' . count($failures) . '/' . $fixtures . " expected outcomes)\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
  }
  exit(1);
}
