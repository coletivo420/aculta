<?php

namespace Drupal\aculta_mail_confirmer\ParamConverter;

use Symfony\Component\Routing\Route;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Email confirmation UUID param converter.
 */
class UuidConverter implements ParamConverterInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct an email confirmation UUID param converter.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $confirmation = NULL;
    if (Uuid::isValid($value)
      && $storage = $this->entityTypeManager->getStorage('aculta_mail_confirmer')) {
      $entities = $storage->loadByProperties(['uuid' => $value]);
      $confirmation = ($entities) ? reset($entities) : NULL;
    }

    return $confirmation;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] == 'aculta-mail-confirmer-uuid');
  }

}
