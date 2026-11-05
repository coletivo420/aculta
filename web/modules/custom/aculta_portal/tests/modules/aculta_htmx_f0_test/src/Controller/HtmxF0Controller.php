<?php

declare(strict_types=1);

namespace Drupal\aculta_htmx_f0_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Provides the test page that opens the dialog containing the form.
 */
final class HtmxF0Controller extends ControllerBase {

  /**
   * Builds the dialog test page.
   */
  public function page(): array {
    $link = [
      '#type' => 'link',
      '#title' => $this->t('Abrir formulário HTMX'),
      '#url' => Url::fromRoute('aculta_htmx_f0_test.form'),
      '#attributes' => [
        'id' => 'htmx-f0-open',
      ],
    ];
    (new Htmx())
      ->get(Url::fromRoute('aculta_htmx_f0_test.form'))
      ->target('#htmx-f0-dialog-content')
      ->swap('innerHTML')
      ->applyTo($link);

    return [
      '#type' => 'container',
      '#attached' => [
        'library' => [
          'core/drupal.htmx',
          'aculta_htmx_f0_test/dialog',
        ],
      ],
      'open' => $link,
      'dialog' => [
        '#type' => 'html_tag',
        '#tag' => 'dialog',
        '#attributes' => [
          'id' => 'htmx-f0-dialog',
          'aria-labelledby' => 'htmx-f0-dialog-title',
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Teste de formulário no diálogo'),
          '#attributes' => ['id' => 'htmx-f0-dialog-title'],
        ],
        'content' => [
          '#type' => 'container',
          '#attributes' => ['id' => 'htmx-f0-dialog-content'],
        ],
        'close' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Fechar'),
          '#attributes' => [
            'type' => 'button',
            'id' => 'htmx-f0-close',
          ],
        ],
      ],
    ];
  }

}
