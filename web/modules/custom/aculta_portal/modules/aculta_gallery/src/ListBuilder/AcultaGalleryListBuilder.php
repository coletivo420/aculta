<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\ListBuilder;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\media_gallery\MediaGalleryListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative gallery list with canonical Portuguese alias links.
 */
final class AcultaGalleryListBuilder extends MediaGalleryListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    private readonly AliasManagerInterface $aliasManager,
  ) {
    parent::__construct($entity_type, $entity_type_manager, $date_formatter);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('path_alias.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);
    $path = $this->aliasManager->getAliasByPath('/media_gallery/' . $entity->id());
    if ($path !== '/media_gallery/' . $entity->id()) {
      $row['title'] = Link::fromTextAndUrl((string) $entity->label(), Url::fromUserInput($path));
    }
    else {
      $row['title'] = ['data' => ['#plain_text' => (string) $entity->label()]];
    }
    return $row;
  }

}
