<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mostra o cartão do educador no feedback publicado sobre uma resposta.
 *
 * O cartão usa a identidade pública de aculta_user; o rótulo "Educador(a)" é o
 * papel contextual, não um dado do perfil.
 */
final class EducatorFeedbackAuthorCardHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $cardBuilder,
  ) {}

  /**
   * Implements hook_entity_view() for LMS answer feedback comments.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityTypeId() !== 'comment' || $entity->bundle() !== 'lms_answer_comment') {
      return;
    }
    $educator = $entity->getOwner();
    if (!$educator instanceof UserInterface) {
      return;
    }
    $card = $this->cardBuilder->build($educator, 'profile_compact', [], (string) new TranslatableMarkup('Educador(a)'), 'sm');
    if ($card === NULL) {
      return;
    }
    $card['#weight'] = -20;
    $build['author_card'] = $card;
  }

}
