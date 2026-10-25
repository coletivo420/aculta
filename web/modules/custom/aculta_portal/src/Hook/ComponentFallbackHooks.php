<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Provides a semantic fallback for neutral portal components.
 *
 * The ACULTA420 theme maps neutral component names to its SDCs. Other themes
 * have no mapping, so the module-owned fallback keeps titles, slots and
 * actions visible instead of rendering an empty component. When a theme has
 * already supplied a component render array, this hook leaves it untouched.
 */
final class ComponentFallbackHooks {

  /**
   * Builds a plain, semantic component when the active theme did not.
   */
  #[Hook('preprocess_aculta_portal_component')]
  public function preprocessPortalComponent(array &$variables): void {
    if (!empty($variables['component'])) {
      return;
    }
    $name = is_string($variables['name'] ?? NULL) ? $variables['name'] : '';
    if ($name === '') {
      return;
    }
    $props = is_array($variables['props'] ?? NULL) ? $variables['props'] : [];
    $slots = is_array($variables['slots'] ?? NULL) ? $variables['slots'] : [];
    $title = $props['title'] ?? NULL;

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'aculta-component-fallback',
          'aculta-component-fallback--' . str_replace('_', '-', $name),
        ],
      ],
    ];

    switch ($name) {
      case 'context-header':
        if (!empty($props['eyebrow'])) {
          $build['eyebrow'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $props['eyebrow']];
        }
        // The route page-title block owns the page h1 outside ACULTA420.
        if ($title !== NULL && $title !== '') {
          $build['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $title];
        }
        if (!empty($props['subtitle'])) {
          $build['subtitle'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $props['subtitle']];
        }
        foreach (['back_link', 'breadcrumbs', 'actions'] as $slot) {
          if (!empty($slots[$slot])) {
            $build[$slot] = $slots[$slot];
          }
        }
        break;

      case 'content-section':
        if ($title !== NULL && $title !== '') {
          $build['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $title];
        }
        if (!empty($slots['body'])) {
          $build['body'] = $slots['body'];
        }
        break;

      case 'card':
        foreach (['header', 'content', 'footer'] as $slot) {
          if (!empty($slots[$slot])) {
            $build[$slot] = $slots[$slot];
          }
        }
        break;

      case 'empty-state':
        if (!empty($slots['message'])) {
          $build['message'] = $slots['message'];
        }
        break;

      case 'context-tabs':
        $build['#attributes']['class'][] = 'aculta-context-tabs';
        if (!empty($props['label'])) {
          $build['#attributes']['aria-label'] = $props['label'];
        }
        if (!empty($slots['items'])) {
          $build['items'] = $slots['items'];
        }
        break;

      case 'content-grid':
        $build['#attributes']['class'][] = 'aculta-content-grid';
        if (!empty($slots['items'])) {
          $build['items'] = $slots['items'];
        }
        break;

      case 'hero':
        if ($title !== NULL && $title !== '') {
          $build['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $title];
        }
        if (!empty($props['lead'])) {
          $build['lead'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $props['lead']];
        }
        if (!empty($slots['actions'])) {
          $build['actions'] = $slots['actions'];
        }
        break;

      default:
        if ($title !== NULL && $title !== '') {
          $build['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $title];
        }
        foreach ($slots as $key => $slot) {
          if (!empty($slot)) {
            $build[$key] = $slot;
          }
        }
    }

    $variables['component'] = $build;
  }

}
