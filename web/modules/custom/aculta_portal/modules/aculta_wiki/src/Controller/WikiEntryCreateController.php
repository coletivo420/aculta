<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Builds the native Node form in the WE SATIVA purpose.
 */
final class WikiEntryCreateController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFormBuilderInterface $wikiFormBuilder,
    private readonly AccountInterface $account,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('current_user'),
    );
  }

  /**
   * Returns the existing Wiki entry Form API form.
   *
   * @return array<string, mixed>
   *   The native Node form render array.
   */
  public function createEntry(): array {
    $access = $this->entities->getAccessControlHandler('node')
      ->createAccess('wiki_entry', $this->account, [], TRUE);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $entry = $this->entities->getStorage('node')->create(['type' => 'wiki_entry']);
    $form = $this->wikiFormBuilder->getForm($entry, 'default');
    CacheableMetadata::createFromObject($access)->applyTo($form);
    return $form;
  }

}
