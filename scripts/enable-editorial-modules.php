<?php
/** @file Windows-safe module installation through the native Drupal API. */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) { throw new RuntimeException('Local only.'); }
\Drupal::service('module_installer')->install(['schema_metatag', 'schema_organization', 'schema_web_site', 'schema_web_page', 'schema_article', 'schema_event', 'schema_image_object', 'aculta_editorial']);
echo "Required Schema and editorial modules installed through Drupal API.\n";
