<?php

declare(strict_types=1);

namespace Drupal\aculta_faq\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\block_content\BlockContentInterface;
use Drupal\node\NodeInterface;

/**
 * Lista perguntas públicas e atalhos de administração do FAQ.
 *
 * Adaptado de OpenCulturas 3.1.4 (GPL-2.0-or-later),
 * `profile/modules/custom/openculturas_faq/config/optional/views.view.faq.yml`.
 * Reescrito com consultas acessíveis e filtros seguros do Drupal.
 */
final class FaqController extends ControllerBase {

  /**
   * Builds the public list from published FAQ nodes accessible to the visitor.
   */
  public function publicList(): array {
    $ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_faq')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('title')
      ->execute();
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
    $build = $this->entityTypeManager()->getViewBuilder('node')->viewMultiple($nodes, 'default');
    $build['#cache']['tags'] = ['node_list:aculta_faq'];
    return $build;
  }

  /**
   * Provides links to the standard Drupal editorial forms.
   */
  public function admin(): array {
    $items = [
      Link::fromTextAndUrl('Criar pergunta frequente', Url::fromRoute('aculta_faq.question_add'))->toRenderable(),
      Link::fromTextAndUrl('Criar bloco editorial', Url::fromRoute('aculta_faq.block_add'))->toRenderable(),
    ];
    $question_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'aculta_faq')
      ->sort('title')
      ->execute();
    foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($question_ids) as $question) {
      if (!$question instanceof NodeInterface) {
        continue;
      }
      $items[] = Link::fromTextAndUrl(
        (string) $question->label(),
        Url::fromRoute('aculta_faq.question_edit', ['node' => $question->id()]),
      )->toRenderable();
    }
    $ids = $this->entityTypeManager()->getStorage('block_content')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'aculta_editorial')
      ->sort('info')
      ->execute();
    foreach ($this->entityTypeManager()->getStorage('block_content')->loadMultiple($ids) as $block) {
      if (!$block instanceof BlockContentInterface) {
        continue;
      }
      $items[] = Link::fromTextAndUrl(
        (string) $block->label(),
        Url::fromRoute('aculta_faq.block_edit', ['block_content' => $block->id()]),
      )->toRenderable();
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

}
