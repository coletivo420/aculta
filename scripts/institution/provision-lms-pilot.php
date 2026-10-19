<?php

/**
 * Backwards-compatible entry point for the canonical LMS pilot content loader.
 *
 * The editorial source is scripts/content/courses/introducao-antiproibicionismo.json.
 * Keep this historical command path as a wrapper so existing local procedures do
 * not maintain a second, divergent copy of course content.
 *
 * Run from the repository root with:
 *   php vendor/drush/drush/drush.php php:script provision-lms-pilot --script-path=scripts/institution
 */

require dirname(__DIR__) . '/content/load-course-introducao-antiproibicionismo.php';

