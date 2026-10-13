<?php

declare(strict_types=1);

/**
 * Positive/negative fixtures for ACULTA420 0.2-B.4 shell boundary.
 */

$repository = dirname(__DIR__, 2);
$themeSource = $repository . '/web/themes/custom/aculta420';
$analyzerSource = $repository . '/scripts/lib/Aculta420ShellContractAnalyzer.php';
$tempRoot = sys_get_temp_dir() . '/aculta420-shell-contract-' . bin2hex(random_bytes(6));
$tempTheme = $tempRoot . '/web/themes/custom/aculta420';
$fixtures = 0;
$failures = [];

$copyTree = static function (string $source, string $destination) use (&$copyTree): void {
  if (is_dir($source)) {
    if (!is_dir($destination) && !mkdir($destination, 0700, TRUE) && !is_dir($destination)) {
      throw new RuntimeException('Could not create fixture directory: ' . $destination);
    }
    foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $entry) {
      $copyTree($entry->getPathname(), $destination . DIRECTORY_SEPARATOR . $entry->getFilename());
    }
    return;
  }

  if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, TRUE) && !is_dir(dirname($destination))) {
    throw new RuntimeException('Could not create fixture parent directory.');
  }
  if (!copy($source, $destination)) {
    throw new RuntimeException('Could not copy fixture file.');
  }
};

$removeTree = static function (string $path) use (&$removeTree): void {
  if (!is_dir($path)) {
    if (file_exists($path) || is_link($path)) {
      unlink($path);
    }
    return;
  }
  foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
    $removeTree($entry->getPathname());
  }
  rmdir($path);
};

$run = static function (string $themeRoot) use ($analyzerSource): array {
  require_once $analyzerSource;
  return Aculta420ShellContractAnalyzer::analyze($themeRoot);
};

$expectClean = static function (string $name, array $findings) use (&$fixtures, &$failures): void {
  $fixtures++;
  if ($findings !== []) {
    $failures[] = $name . ' unexpectedly failed: ' . implode(' | ', $findings);
  }
};

$expectFinding = static function (string $name, array $findings, string $needle) use (&$fixtures, &$failures): void {
  $fixtures++;
  $matched = FALSE;
  foreach ($findings as $finding) {
    if (str_contains($finding, $needle)) {
      $matched = TRUE;
      break;
    }
  }
  if (!$matched) {
    $failures[] = $name . ' did not produce expected finding "' . $needle . '": ' . implode(' | ', $findings);
  }
};

try {
  $copyTree($themeSource, $tempTheme);

  $expectClean('clean B.4 baseline', $run($tempTheme));

  $pagePath = $tempTheme . '/templates/page.html.twig';
  $hookPath = $tempTheme . '/src/Hook/ThemeHooks.php';
  $originalPage = (string) file_get_contents($pagePath);
  $originalHook = (string) file_get_contents($hookPath);

  file_put_contents(
    $pagePath,
    str_replace(
      '<header class="aculta-header{{',
      "{% if domain_presentation.identity.purpose == 'wiki' %}<div>Wiki</div>{% endif %}\n<header class=\"aculta-header{{",
      $originalPage,
    ),
  );
  $expectFinding(
    'Twig purpose-specific branch',
    $run($tempTheme),
    'Concrete purpose branching is forbidden',
  );
  file_put_contents($pagePath, $originalPage);

  file_put_contents(
    $hookPath,
    str_replace(
      'use Drupal\\Core\\Config\\ConfigFactoryInterface;',
      "use Drupal\\domain\\Entity\\DomainInterface;\nuse Drupal\\Core\\Config\\ConfigFactoryInterface;",
      $originalHook,
    ),
  );
  $expectFinding('Domain object leakage', $run($tempTheme), 'DomainInterface is forbidden');
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      'use Drupal\\Core\\Config\\ConfigFactoryInterface;',
      "use Drupal\\aculta_portal\\Domain\\DomainPurposeManager;\nuse Drupal\\Core\\Config\\ConfigFactoryInterface;",
      $originalHook,
    ),
  );
  $expectFinding('Portal/DomainPurposeManager dependency', $run($tempTheme), 'DomainPurposeManager is forbidden');
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      'use Drupal\\Core\\Config\\ConfigFactoryInterface;',
      "use Drupal\\aculta_portal\\Presentation\\DomainPresentation;\nuse Drupal\\Core\\Config\\ConfigFactoryInterface;",
      $originalHook,
    ),
  );
  $expectFinding('generic Portal namespace dependency', $run($tempTheme), 'Portal dependency is forbidden');
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      'namespace Drupal\\aculta420\\Hook;',
      "namespace Drupal\\aculta420\\Hook;\n\n// Documentation note: DomainPurposeManager stays outside the theme.",
      $originalHook,
    ),
  );
  $expectClean('PHP comment mentioning DomainPurposeManager is ignored', $run($tempTheme));
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      '$variables[\'institutional_home\'] = $this->pathMatcher->isFrontPage();',
      "\\Drupal::service('aculta_portal.domain_purpose');\n    \$variables['institutional_home'] = \$this->pathMatcher->isFrontPage();",
      $originalHook,
    ),
  );
  $findings = $run($tempTheme);
  $expectFinding('Portal service lookup', $findings, 'Portal dependency is forbidden');
  $expectFinding('Portal service locator', $findings, 'service locator is forbidden');
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      '$variables[\'institutional_home\'] = $this->pathMatcher->isFrontPage();',
      "\$host = \\Drupal::request()->getHost();\n    \$variables['institutional_home'] = \$this->pathMatcher->isFrontPage();",
      $originalHook,
    ),
  );
  $findings = $run($tempTheme);
  $expectFinding('hostname lookup', $findings, 'hostname decision is forbidden');
  $expectFinding('service locator lookup', $findings, 'service locator is forbidden');
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      '$variables[\'institutional_home\'] = $this->pathMatcher->isFrontPage();',
      "\$canonical = 'https://aculta.org/';\n    \$variables['institutional_home'] = \$this->pathMatcher->isFrontPage();",
      $originalHook,
    ),
  );
  $expectFinding('hardcoded MAIN hostname', $run($tempTheme), 'hostname decision is forbidden');
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $pagePath,
    str_replace(
      '{{ page.primary_menu }}',
      "{{ domain_presentation.regions.navigation }}\n          {{ page.primary_menu }}",
      $originalPage,
    ),
  );
  $expectFinding('premature navigation region consumption', $run($tempTheme), 'B.4 must not consume deferred region');
  file_put_contents($pagePath, $originalPage);

  file_put_contents(
    $pagePath,
    str_replace(
      '{% if not aculta_has_system_branding and aculta_domain_brand_fallback %}',
      '{% if aculta_domain_brand_fallback %}',
      $originalPage,
    ),
  );
  $expectFinding(
    'fallback ignores canonical system branding block',
    $run($tempTheme),
    'Fallback must be controlled by absence of the canonical system branding block.',
  );
  file_put_contents($pagePath, $originalPage);

  file_put_contents(
    $pagePath,
    str_replace('{{ page.header }}', '', $originalPage),
  );
  $expectFinding(
    'existing header render array is dropped',
    $run($tempTheme),
    'Existing Drupal header render array must always be preserved.',
  );
  file_put_contents($pagePath, $originalPage);

  file_put_contents(
    $hookPath,
    str_replace(
      "\$variables['domain_presentation']['identity']",
      "\$variables['domain_presentation']['wrong_key']",
      $originalHook,
    ),
  );
  $expectFinding(
    'identity contract key regression',
    $run($tempTheme),
    'ThemeHooks must consume domain_presentation.identity.',
  );
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      '$variables[\'institutional_home\'] = $this->pathMatcher->isFrontPage();',
      "if ((\$variables['domain_presentation']['identity']['purpose'] ?? '') === 'shop') { \$variables['x'] = TRUE; }\n    \$variables['institutional_home'] = \$this->pathMatcher->isFrontPage();",
      $originalHook,
    ),
  );
  $expectFinding(
    'PHP concrete purpose branch',
    $run($tempTheme),
    'Concrete purpose branching is forbidden',
  );
  file_put_contents($hookPath, $originalHook);

  file_put_contents(
    $hookPath,
    str_replace(
      '$variables[\'institutional_home\'] = $this->pathMatcher->isFrontPage();',
      "if ('shop' === (\$variables['domain_presentation']['identity']['purpose'] ?? '')) { \$variables['x'] = TRUE; }\n    \$variables['institutional_home'] = \$this->pathMatcher->isFrontPage();",
      $originalHook,
    ),
  );
  $expectFinding(
    'PHP reverse concrete purpose branch',
    $run($tempTheme),
    'Concrete purpose branching is forbidden',
  );
  file_put_contents($hookPath, $originalHook);

  $jsPath = $tempTheme . '/js/b4-purpose-fixture.js';
  if (!is_dir(dirname($jsPath)) && !mkdir(dirname($jsPath), 0700, TRUE) && !is_dir(dirname($jsPath))) {
    throw new RuntimeException('Could not create JavaScript fixture directory.');
  }
  file_put_contents($jsPath, "if (context.purpose === 'wiki') { shell.hidden = true; }\n");
  $expectFinding(
    'JavaScript concrete purpose branch',
    $run($tempTheme),
    'Concrete purpose branching is forbidden',
  );
  unlink($jsPath);

  $expectClean('restored clean fixture', $run($tempTheme));
}
finally {
  $removeTree($tempRoot);
}

if ($failures !== []) {
  fwrite(STDERR, "ACULTA420 B.4 FIXTURES: FAIL\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo 'ACULTA420 B.4 FIXTURES: PASS (' . $fixtures . " fixtures)\n";
