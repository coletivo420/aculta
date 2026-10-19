<?php

declare(strict_types=1);

/**
 * Gate for the course card skin against the LMS (DT-T11).
 *
 * - The theme must not define or consume the LMS internal --color-* variables.
 * - Every LMS class the skin selects must exist in the LMS course card markup or stylesheet.
 * - The LMS version is pinned. An upgrade fails this gate until the skin and the reference
 *   capture of the catalog are reviewed; then the pin moves with the review.
 * Run: php scripts/validate-lms-skin.php
 */

const LMS_REVIEWED_VERSION = '1.2.4';

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$themeCard = $root . '/web/themes/custom/aculta420/components/content/course_card';
$skin = $themeCard . '/course_card.css';
$lmsDir = $root . '/web/modules/contrib/lms';
$source = (string) file_get_contents($skin);
$themeMarkup = (string) file_get_contents($themeCard . '/course_card.twig');
$metadata = (string) file_get_contents($themeCard . '/course_card.component.yml');

$checks++;
if (!str_contains($metadata, "replaces: 'lms:course_card'")) {
  $failures[] = 'ACULTA420 course card must replace lms:course_card through SDC negotiation.';
}

$checks++;
if (preg_match('/--color-[a-z0-9-]+/', $source) === 1) {
  $failures[] = 'course-card.css uses LMS internal --color-* variables (DT-T11); set the properties with ACULTA tokens.';
}

// Each LMS class the skin selects must exist in the LMS course card, so a renamed class fails here.
$lmsMarkup = (string) file_get_contents($lmsDir . '/components/course_card/course_card.twig')
  . (string) file_get_contents($lmsDir . '/components/course_card/course_card.css');
preg_match_all('/\.(lms-[a-z0-9_-]+)/', $source, $matches);
foreach (array_unique($matches[1]) as $class) {
  $checks++;
  if (!str_contains($themeMarkup, $class) && !str_contains($lmsMarkup, $class)) {
    $failures[] = "course-card.css selects .$class, which the LMS course card no longer uses.";
  }
}

$checks++;
$info = (string) file_get_contents($lmsDir . '/lms.info.yml');
if (preg_match("/^version:\s*'?([^'\s]+)'?/m", $info, $version) !== 1 || $version[1] !== LMS_REVIEWED_VERSION) {
  $found = $version[1] ?? 'unknown';
  $failures[] = "LMS is $found, but the ACULTA420 card was reviewed against " . LMS_REVIEWED_VERSION . ". Review the SDC replacement and catalog reference (docs/operations/DEBT-REGISTER.md, DT-T11), then update LMS_REVIEWED_VERSION.";
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "LMS SKIN FAIL: $failure\n");
  }
  exit(1);
}

echo sprintf("LMS SKIN: PASS (%d checks; LMS %s reviewed)\n", $checks, LMS_REVIEWED_VERSION);
