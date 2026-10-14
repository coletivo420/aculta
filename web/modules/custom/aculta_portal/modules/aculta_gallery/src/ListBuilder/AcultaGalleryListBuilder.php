<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\ListBuilder;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\media_gallery\MediaGalleryListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Coleção administrativa de galerias com título apontando para a Revista.
 *
 * Álbuns são publicados somente na Revista (D13). O título da coleção é o link de
 * visualização, então ele é gerado no purpose `magazine` via DomainPurposeManager.
 * Edição e exclusão continuam no painel administrativo do MAIN, como manda a política
 * de domínio administrativo (ADMIN-DOMAIN-POLICY.md).
 */
final class AcultaGalleryListBuilder extends MediaGalleryListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    private readonly DomainPurposeManager $purposeManager,
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
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);
    $path = $entity->toUrl()->toString();
    $url = $this->purposeManager->pathUrl('magazine', $path);
    if ($url !== NULL) {
      $row['title'] = Link::fromTextAndUrl((string) $entity->label(), $url);
    }
    return $row;
  }

}
