<?php
/** Targeted local VVJB timing and group-size adjustment. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$view = \Drupal\views\Entity\View::load('home_editorial_highlights');
$display = $view->get('display');
$options = &$display['default']['display_options']['style']['options'];
$options['items_big'] = 2;
$options['slide_time'] = 3000;
$options['looping'] = TRUE;
$view->set('display', $display)->save();
file_put_contents(dirname(__DIR__) . '/config/sync/views.view.home_editorial_highlights.yml', Yaml::dump(\Drupal::service('config.storage')->read('views.view.home_editorial_highlights'), 12, 2));
echo "VVJB: two large-screen items, 3000 ms, looping enabled.\n";
