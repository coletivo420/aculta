<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManager;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoPaymentService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\RecurrenceRequestBuilder;
use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\aculta_studio\Controller\GanjalabController;
use Drupal\aculta_studio\Experience\GanjalabExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Logger\LoggerChannel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the real cross-module service contract for partial LMS Kernels.
 *
 * These tests do not install the full Portal/Messenger/Studio configuration
 * graph. The definitions below mirror the production classes and arguments
 * needed to compile the LMS integration routes without product stubs.
 */
trait AcultaLmsDependencyHarnessTrait {

  /**
   * Adds the real Portal, Messenger, LMS, and Studio services used by LMS.
   */
  protected function registerLmsDependencyServices(ContainerBuilder $container): void {
    $container->register('aculta_pagamentos_mercadopago.client_factory', MercadoPagoClientFactory::class);
    $container->register('aculta_pagamentos_mercadopago.recurrence_request_builder', RecurrenceRequestBuilder::class);
    $container->register('aculta_pagamentos_mercadopago.payment_policy', MercadoPagoPaymentPolicy::class);
    $container->register('aculta_pagamentos_mercadopago.logger', LoggerChannel::class)
      ->setFactory([new Reference('logger.factory'), 'get'])
      ->addArgument('aculta_pagamentos_mercadopago');
    $container->register('aculta_pagamentos_mercadopago.payment_service', MercadoPagoPaymentService::class)
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.client_factory'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.payment_policy'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'));
    $container->register('aculta_pagamentos_mercadopago.recurrence_service', MercadoPagoRecurrenceService::class)
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.client_factory'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.recurrence_request_builder'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'));
    $container->register('aculta_pagamentos_mercadopago.subscription_recorder', MercadoPagoSubscriptionRecorder::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.recurrence_service'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'))
      ->addArgument(new Reference('datetime.time'));
    $container->setAlias('aculta_portal.payment_provider.subscription_status', 'aculta_pagamentos_mercadopago.subscription_recorder');
    $container->setAlias('aculta_portal.payment_provider.subscription_operations', 'aculta_pagamentos_mercadopago.subscription_recorder');
    $container->setAlias('aculta_portal.payment_provider.payment_operations', 'aculta_pagamentos_mercadopago.payment_service');
    $container->setAlias('aculta_portal.payment_provider.refund_policy', 'aculta_pagamentos_mercadopago.payment_policy');
    $container->register('aculta_portal.checkout_settings', CheckoutSettings::class)
      ->addArgument(new Reference('config.factory'));
    $container->register('aculta_portal.order_type_checkout_flows', OrderTypeCheckoutFlows::class)
      ->addArgument(new Reference('entity_type.manager'));
    $container->register('aculta_portal.domain_purpose', DomainPurposeManager::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('domain.negotiator'))
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('request_stack'));
    $container->register('aculta_lms.class_activity_deadline_access', ClassActivityDeadlineAccessCheck::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('current_user'));
    $container->register('aculta_lms.class_activity_deadline_manager', ClassActivityDeadlineManager::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('aculta_lms.class_activity_deadline_access'));
    $container->register('aculta_messenger.notice_event_publisher', MessengerNoticePublisher::class)
      ->addArgument(new Reference('event_dispatcher'));
    $container->register('logger.channel.aculta_messenger')
      ->setClass(LoggerChannel::class)
      ->setFactory([new Reference('logger.factory'), 'get'])
      ->addArgument('aculta_messenger');
    $container->register('aculta_messenger.lms_notice_manager', LearningNoticeManager::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('aculta_portal.domain_purpose'))
      ->addArgument(new Reference('aculta_lms.class_activity_deadline_manager'))
      ->addArgument(new Reference('date.formatter'))
      ->addArgument(new Reference('queue'))
      ->addArgument(new Reference('logger.channel.aculta_messenger'))
      ->addArgument(new Reference('aculta_messenger.notice_event_publisher'))
      ->addArgument(new Reference('lock'));
    $container->register('aculta_studio.experience.ganjalab', GanjalabExperienceProvider::class)
      ->addArgument(new Reference('aculta_studio.learner_dashboard_composer', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));
    $container->register('aculta_studio.experience_manager', StudioExperienceManager::class)
      ->addMethodCall('addProvider', [new Reference('aculta_studio.experience.ganjalab')]);
    $container->register('aculta_studio.ganjalab_controller', GanjalabController::class)
      ->addArgument(new Reference('aculta_studio.experience_manager'))
      ->addArgument(new Reference('current_user'));
  }

}
