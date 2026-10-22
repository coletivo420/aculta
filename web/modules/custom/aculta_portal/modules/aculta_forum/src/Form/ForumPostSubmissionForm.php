<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contextual submission form for public Forum posts.
 */
final class ForumPostSubmissionForm extends ContentEntityForm {

  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    private readonly MessengerInterface $messenger,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $parent = $this->entity->get('parent')->entity;
    $topic = $this->entity->get('topic')->entity;
    if ($parent === NULL) {
      return $form;
    }

    $parentMetadata = CacheableMetadata::createFromObject($parent);
    if (!$parent->isPublished()
      || !$parent->isDefaultRevision()
      || !$topic instanceof NodeInterface
      || (string) $parent->get('topic')->target_id !== (string) $topic->id()) {
      $parentMetadata->applyTo($form);
      return $form;
    }

    $parentAccess = $parent->access('view', $this->currentUser(), TRUE);
    $parentMetadata->addCacheableDependency($parentAccess)->applyTo($form);
    if (!$parentAccess->isAllowed()) {
      return $form;
    }

    $form['reply_context'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => [
        'class' => ['aculta-forum-reply-context'],
      ],
      'description' => [
        '#plain_text' => $this->t('Você está respondendo à publicação: @subject', [
          '@subject' => $parent->label(),
        ]),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Enviar publicação');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $topic = $this->entity->get('topic')->entity;
    if (!$topic instanceof NodeInterface) {
      return $result;
    }

    $message = $this->entity->isPublished()
      ? $this->t('Sua publicação foi adicionada à discussão.')
      : $this->t('Sua publicação foi enviada para moderação e aparecerá na discussão depois da aprovação.');
    $this->messenger->addStatus($message);
    $url = $topic->toUrl('canonical');
    if ($this->entity->isPublished()) {
      $url->setOption('fragment', 'forum-post-' . $this->entity->id());
    }
    $form_state->setRedirectUrl($url);
    return $result;
  }

}
