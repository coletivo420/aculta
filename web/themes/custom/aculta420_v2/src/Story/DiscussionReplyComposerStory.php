<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\aculta_forum\Form\DiscussionReplyPopupLayout;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story executável para o Compositor de Resposta da Discussão.
 *
 * Constrói o formulário real do Drupal com o widget CKEditor 5 ativo
 * para demonstração fiel no UI Patterns Library.
 */
final class DiscussionReplyComposerStory extends StoryDefault implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * @param array<string, mixed> $configuration
   * @param mixed $plugin_definition
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFormBuilderInterface $entityFormBuilder,
    protected RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function props(): array {
    return [
      'target_id' => 'discussion-composer-demo-1',
      'status' => 'editando',
      'standalone_popup' => TRUE,
      'mode' => 'responder',
      'parent_id' => '42',
      'parent_author' => 'Maria Silva',
      'parent_subject' => 'Dúvida sobre o cultivo orgânico',
      'host_title' => 'Guia de Cultivo Sustentável',
      'trigger_label' => 'Responder',
      'dialog_title' => 'Responder à discussão',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function slots(): array {
    $commentStorage = $this->entityTypeManager->getStorage('comment');
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // Tenta obter um tópico real para contextualizar o formulário.
    $topics = $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'aculta_forum_topic')
      ->range(0, 1)
      ->execute();
    $hostId = $topics ? reset($topics) : 1;

    $comment = $commentStorage->create([
      'entity_type' => 'node',
      'entity_id' => $hostId,
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
    ]);

    $form = $this->entityFormBuilder->getForm($comment);
    // Mesmo layout do popup de resposta (fórum).
    DiscussionReplyPopupLayout::apply($form, $comment->isNew());

    // Ajusta título do botão e action para visualização limpa na story.
    $actions = $form['actions'] ?? [];
    if (is_array($actions) && is_array($actions['submit'] ?? NULL)) {
      $submit = $actions['submit'];
      $submit['#value'] = $this->t('Enviar resposta');
      $actions['submit'] = $submit;
      $form['actions'] = $actions;
    }

    // Renderiza o formulário e preserva os anexos produzidos pelo CKEditor 5.
    $rendered = $this->renderer->renderInIsolation($form);
    $attached = $form['#attached'] ?? [];

    return [
      'messages' => [],
      'context' => [],
      'form' => [
        '#markup' => $rendered,
        '#attached' => is_array($attached) ? $attached : [],
      ],
    ];
  }

}
