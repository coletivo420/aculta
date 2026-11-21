<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Form\ForumPollVoteForm;
use Drupal\aculta_forum\Poll\ForumPollManager;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mostra a enquete nativa na página completa do tópico do Fórum.
 */
final class TopicPollRenderHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.poll_manager')]
    private readonly ForumPollManager $pollManager,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Anexa o formulário de voto/resultados ao tópico com enquete.
   *
   * O bloco tem cache desabilitado: o estado de voto é por usuário e muda a cada voto.
   *
   * @param array<mixed> $build
   *   Render array da entidade.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $viewMode): void {
    if ($node->bundle() !== 'aculta_forum_topic' || $viewMode !== 'full' || $node->isNew()) {
      return;
    }
    $poll = $this->pollManager->getPollByTopicId((int) $node->id());
    if ($poll === NULL || !isset($poll['id']) || !is_numeric($poll['id'])) {
      return;
    }
    $build['aculta_forum_poll'] = $this->formBuilder->getForm(ForumPollVoteForm::class, (int) $poll['id']);
    $build['aculta_forum_poll']['#weight'] = 20;
    $build['aculta_forum_poll']['#cache'] = ['max-age' => 0];
  }

}
