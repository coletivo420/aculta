<?php
/** Read-only local diagnostics; never prints settings or credentials. */
echo 'Contact enabled: ' . (int) \Drupal::moduleHandler()->moduleExists('contact') . PHP_EOL;
echo 'Contact entity known: ' . (int) \Drupal::entityTypeManager()->hasDefinition('contact_form') . PHP_EOL;
$query = \Drupal::database()->select('watchdog', 'w')->fields('w', ['variables'])->condition('type', 'php')->orderBy('wid', 'DESC')->range(0, 1);
foreach ($query->execute() as $row) {
  $variables = unserialize($row->variables, ['allowed_classes' => FALSE]);
  echo ($variables['%message'] ?? '') . PHP_EOL;
  echo ($variables['@backtrace_string'] ?? '') . PHP_EOL;
}
