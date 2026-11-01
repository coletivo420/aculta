<?php

/** @file Configure the reviewed local ACULTA favicon without changing logo files. */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) {
  throw new RuntimeException('Local only.');
}
$path = 'themes/custom/aculta420/assets/branding/aculta420/web/aculta420-favicon.ico';
if (!is_file(DRUPAL_ROOT . '/' . $path)) {
  throw new RuntimeException('Reviewed favicon asset is missing.');
}
$settings = \Drupal::configFactory()->getEditable('aculta420.settings');
$settings->set('features.favicon', TRUE)
  ->set('favicon.use_default', FALSE)
  ->set('favicon.path', $path)
  ->save();
echo "Local theme favicon configured from the existing ICO asset.\n";
