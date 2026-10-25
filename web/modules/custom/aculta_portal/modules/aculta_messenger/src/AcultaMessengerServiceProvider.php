<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger;

use Drupal\aculta_messenger\Integration\Commerce\AccessCommerceOrderNotice;
use Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManager;
use Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Commerce\Controller\CommerceOrderNoticeOpenController;
use Drupal\aculta_messenger\Integration\Commerce\EventSubscriber\CommerceOrderNoticeConsumer;
use Drupal\aculta_messenger\Integration\Commerce\Mail\CommerceOrderNoticeMailType;
use Drupal\aculta_messenger\Integration\Donation\AccessDonationNotice;
use Drupal\aculta_messenger\Integration\Donation\Controller\DonationNoticeOpenController;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManager;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Donation\EventSubscriber\DonationNoticeEventConsumer;
use Drupal\aculta_messenger\Integration\Donation\Mail\DonationNoticeMailType;
use Drupal\aculta_messenger\Integration\Lms\Access\LearningNoticeAccessCheck;
use Drupal\aculta_messenger\Integration\Lms\Controller\LearningNoticeOpenController;
use Drupal\aculta_messenger\Integration\Lms\EventSubscriber\LearningNoticeEventSubscriber;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManager;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Lms\Mail\LmsTransactionalNoticeMailType;
use Drupal\aculta_messenger\Integration\Wiki\AccessWikiNotice;
use Drupal\aculta_messenger\Integration\Wiki\Controller\WikiNoticeOpenController;
use Drupal\aculta_messenger\Integration\Wiki\EventSubscriber\WikiNoticeEventSubscriber;
use Drupal\aculta_messenger\Integration\Wiki\Mail\WikiNoticeMailType;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManager;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\aculta_messenger\Hook\PersonalNoticeAdapterHooks;
use Drupal\aculta_messenger\Mapper\MessengerPrivateMessageMapper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;

/**
 * Registers core-owned notice integrations for enabled domain modules.
 *
 * The contrib mapper replacement preserves the original service arguments.
 */
final class AcultaMessengerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc} */
  public function register(ContainerBuilder $container): void {
    if ($container->hasDefinition('private_message.mapper')) {
      $container->getDefinition('private_message.mapper')->setClass(MessengerPrivateMessageMapper::class);
    }
  }

  /**
   * {@inheritdoc} */
  public function alter(ContainerBuilder $container): void {
    // Each adapter is owned by this module, while the domain module remains
    // optional. Register definitions only when the producer's services exist.
    if ($container->hasDefinition('aculta_lms.class_activity_deadline_manager')) {
      $this->registerLmsAdapter($container);
    }

    if ($container->hasDefinition('aculta_wiki.proposal_decision')) {
      $this->registerWikiAdapter($container);
    }

    if ($container->hasDefinition('aculta_commerce.checkout_provider')) {
      $this->registerCommerceAdapter($container);
    }

    if ($container->hasDefinition('Drupal\\aculta_donation\\Hook\\DonationNoticeEventPublisher')) {
      $this->registerDonationAdapter($container);
    }

    $optionalAdapters = [];
    if ($container->hasDefinition('aculta_messenger.friendship_notice_manager')) {
      $optionalAdapters['friendship'] = new Reference('aculta_messenger.friendship_notice_manager');
    }
    foreach ([
      'lms' => 'aculta_messenger.lms_notice_manager',
      'wiki' => 'aculta_messenger.wiki_notice_manager',
      'commerce' => 'aculta_messenger.commerce_notice_manager',
      'donation' => 'aculta_messenger.donation_notice_manager',
    ] as $adapter => $service) {
      if ($container->hasDefinition($service)) {
        $optionalAdapters[$adapter] = new Reference($service);
      }
    }
    if ($container->hasDefinition('aculta_messenger.notice_read_state')) {
      $container->getDefinition('aculta_messenger.notice_read_state')
        ->setArgument(1, new ServiceLocatorArgument($optionalAdapters));
    }
    // This hook is discovered from src/Hook by Drupal's HookCollector. Its
    // locator contains only adapters actually registered in this site. The
    // definition must stay public: the collector resolves hook objects by
    // class name through ClassResolver, not through a compile-time reference,
    // so a private definition with no consumer would be removed and the hook
    // would be instantiated with no arguments.
    $container->setDefinition(PersonalNoticeAdapterHooks::class, new Definition(
      PersonalNoticeAdapterHooks::class,
      [
        new ServiceLocatorArgument($optionalAdapters),
        new Reference('logger.channel.aculta_messenger'),
      ],
    ))->setPublic(TRUE);
  }

  /**
   * Registers services for the optional LMS adapter. */
  private function registerLmsAdapter(ContainerBuilder $container): void {
    $container->setDefinition('aculta_messenger.lms_notice_manager', new Definition(
      LearningNoticeManager::class,
      [
        new Reference('entity_type.manager'),
        new Reference('aculta_portal.domain_purpose'),
        new Reference('aculta_lms.class_activity_deadline_manager'),
        new Reference('date.formatter'),
        new Reference('queue'),
        new Reference('logger.channel.aculta_messenger'),
        new Reference('aculta_messenger.notice_event_publisher'),
        new Reference('lock.persistent'),
      ],
    ))->addTag('aculta_messenger.aviso_digest_provider');
    $container->setAlias(LearningNoticeManagerInterface::class, 'aculta_messenger.lms_notice_manager');

    $container->setDefinition('aculta_messenger.lms_notice_event_subscriber', new Definition(
      LearningNoticeEventSubscriber::class,
      [
        new Reference('aculta_messenger.lms_notice_manager'),
        new Reference('aculta_messenger.aviso_preferencias'),
        new Reference('logger.channel.aculta_messenger'),
      ],
    ))->addTag('event_subscriber');
    $container->setDefinition('aculta_messenger.lms_notice_access', new Definition(
      LearningNoticeAccessCheck::class,
      [new Reference('aculta_messenger.lms_notice_manager'), new Reference('current_user')],
    ))->setPublic(TRUE);
    $container->setDefinition('aculta_messenger.lms_notice_open_controller', new Definition(
      LearningNoticeOpenController::class,
      [
        new Reference('aculta_messenger.lms_notice_manager'),
        new Reference('current_user'),
        new Reference('request_stack'),
      ],
    ))->setPublic(TRUE);
    $container->setDefinition(LmsTransactionalNoticeMailType::class, new Definition(
      LmsTransactionalNoticeMailType::class,
      [new Reference('string_translation')],
    ))->addTag('aculta_mailer.type');
  }

  /**
   * Registers services for the optional Wiki adapter. */
  private function registerWikiAdapter(ContainerBuilder $container): void {
    $container->setDefinition('aculta_messenger.wiki_notice_manager', new Definition(
      WikiNoticeManager::class,
      [
        new Reference('entity_type.manager'),
        new Reference('aculta_portal.domain_purpose'),
        new Reference('aculta_messenger.aviso_preferencias'),
        new Reference('queue'),
        new Reference('lock.persistent'),
        new Reference('logger.channel.aculta_messenger'),
      ],
    ))->addTag('aculta_messenger.aviso_digest_provider');
    $container->setAlias(WikiNoticeManagerInterface::class, 'aculta_messenger.wiki_notice_manager');
    $container->setDefinition('aculta_messenger.wiki_notice_event_subscriber', new Definition(
      WikiNoticeEventSubscriber::class,
      [new Reference('aculta_messenger.wiki_notice_manager')],
    ))->addTag('event_subscriber');
    $container->setDefinition('aculta_messenger.wiki_notice_access', new Definition(
      AccessWikiNotice::class,
      [new Reference('aculta_messenger.wiki_notice_manager'), new Reference('current_user')],
    ))->setPublic(TRUE);
    $container->setDefinition('aculta_messenger.wiki_notice_open_controller', new Definition(
      WikiNoticeOpenController::class,
      [
        new Reference('aculta_messenger.wiki_notice_manager'),
        new Reference('current_user'),
        new Reference('request_stack'),
      ],
    ))->setPublic(TRUE);
    $container->setDefinition(WikiNoticeMailType::class, new Definition(
      WikiNoticeMailType::class,
      [new Reference('string_translation')],
    ))->addTag('aculta_mailer.type');
  }

  /**
   * Registers services for the optional Commerce adapter. */
  private function registerCommerceAdapter(ContainerBuilder $container): void {
    $container->setDefinition('aculta_messenger.commerce_notice_manager', new Definition(
      CommerceOrderNoticeManager::class,
      [
        new Reference('entity_type.manager'),
        new Reference('aculta_portal.domain_purpose'),
        new Reference('aculta_portal.checkout_settings'),
        new Reference('aculta_messenger.aviso_preferencias'),
        new Reference('queue'),
        new Reference('lock.persistent'),
        new Reference('logger.channel.aculta_messenger'),
      ],
    ))->addTag('aculta_messenger.aviso_digest_provider');
    $container->setAlias(CommerceOrderNoticeManagerInterface::class, 'aculta_messenger.commerce_notice_manager');
    $container->setDefinition('aculta_messenger.commerce_notice_event_subscriber', new Definition(
      CommerceOrderNoticeConsumer::class,
      [new Reference('aculta_messenger.commerce_notice_manager')],
    ))->addTag('event_subscriber');
    $container->setDefinition('aculta_messenger.commerce_notice_access', new Definition(
      AccessCommerceOrderNotice::class,
      [new Reference('aculta_messenger.commerce_notice_manager'), new Reference('current_user')],
    ))->setPublic(TRUE);
    $container->setDefinition('aculta_messenger.commerce_notice_open_controller', new Definition(
      CommerceOrderNoticeOpenController::class,
      [
        new Reference('aculta_messenger.commerce_notice_manager'),
        new Reference('current_user'),
        new Reference('request_stack'),
      ],
    ))->setPublic(TRUE);
    $container->setDefinition(CommerceOrderNoticeMailType::class, new Definition(
      CommerceOrderNoticeMailType::class,
      [new Reference('string_translation')],
    ))->addTag('aculta_mailer.type');
  }

  /**
   * Registers the optional ACULTA Donation adapter. */
  private function registerDonationAdapter(ContainerBuilder $container): void {
    $container->setDefinition('aculta_messenger.donation_notice_manager', new Definition(
      DonationNoticeManager::class,
      [
        new Reference('entity_type.manager'),
        new Reference('aculta_portal.domain_purpose'),
        new Reference('aculta_messenger.aviso_preferencias'),
        new Reference('queue'),
        new Reference('lock.persistent'),
        new Reference('logger.channel.aculta_messenger'),
      ],
    ))->addTag('aculta_messenger.aviso_digest_provider');
    $container->setAlias(DonationNoticeManagerInterface::class, 'aculta_messenger.donation_notice_manager');
    $container->setDefinition('aculta_messenger.donation_notice_event_consumer', new Definition(
      DonationNoticeEventConsumer::class,
      [new Reference('aculta_messenger.donation_notice_manager')],
    ))->addTag('event_subscriber');
    $container->setDefinition('aculta_messenger.donation_notice_access', new Definition(
      AccessDonationNotice::class,
      [new Reference('aculta_messenger.donation_notice_manager'), new Reference('current_user')],
    ))->setPublic(TRUE);
    $container->setDefinition('aculta_messenger.donation_notice_open_controller', new Definition(
      DonationNoticeOpenController::class,
      [
        new Reference('aculta_messenger.donation_notice_manager'),
        new Reference('current_user'),
        new Reference('request_stack'),
      ],
    ))->setPublic(TRUE);
    $container->setDefinition(DonationNoticeMailType::class, new Definition(
      DonationNoticeMailType::class,
      [new Reference('string_translation')],
    ))->addTag('aculta_mailer.type');
  }

}
