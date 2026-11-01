<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmationAccessControlHandler;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Permite a resposta à confirmação de e-mail a qualquer visitante, inclusive anônimo.
 *
 * O módulo email_confirmer restringe pedidos privados ao dono do pedido, o que impede quem clica no link
 * de confirmar a troca quando o pedido foi feito por um usuário logado. O segredo passa a ser o hash da URL
 * (43 caracteres), que o próprio confirm() verifica. A permissão só vale para pedidos pendentes e não expirados.
 * As demais operações seguem o handler original.
 */
final class EmailConfirmationResponseAccessHandler extends AcultaMailConfirmationAccessControlHandler {

  /**
   * Creates the handler with the confirmation service.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, RequestStack $request_stack, private readonly AcultaMailConfirmerManagerInterface $confirmationManager) {
    parent::__construct($entity_type, $config_factory, $request_stack);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type, $container->get('config.factory'), $container->get('request_stack'), $container->get('aculta_mail_confirmer'));
  }

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'response' && $this->confirmationManager->isPending($entity) && !$this->confirmationManager->isExpired($entity)) {
      return AccessResult::allowed()->addCacheableDependency($entity)->setCacheMaxAge(0);
    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
