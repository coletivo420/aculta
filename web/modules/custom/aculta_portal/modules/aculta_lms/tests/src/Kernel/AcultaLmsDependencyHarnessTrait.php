<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_events\Occurrence\EventDateOccurrences;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManager;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoClientFactory;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoPaymentService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\aculta_portal\Cleanup\UnusedMediaCleaner;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\RecurrenceRequestBuilder;
use Drupal\aculta_pagamentos_mercadopago\Sales\MercadoPagoPaymentPolicy;
use Drupal\aculta_studio\Controller\GanjalabController;
use Drupal\aculta_studio\Experience\GanjalabExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\SocialNetworksInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\comment\Entity\CommentType;
use Drupal\group\Entity\GroupRelationshipType;
use Drupal\group\Entity\GroupType;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\NodeType;
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
   * Installs ACULTA course/class Group types without any LMS contrib modules.
   *
   * The schemas are installed here because the membership role field is a
   * Group relationship field and Drupal 12 requires its entity schema first.
   */
  protected function prepareAcultaLmsGroupTypes(): void {
    // LMS admin Views require system.menu.admin for their menu displays.
    // Some partial Kernels install the module before their test method installs
    // module configuration; prepare it before enabling aculta_lms.
    $this->installConfig(['system']);

    // The LMS installs its answer-feedback Comment type. Keep this shared
    // harness complete even in focused tests that do not exercise comments.
    if (!$this->container->get('module_handler')->moduleExists('comment')) {
      $this->container->get('module_installer')->install(['comment'], FALSE);
    }
    $this->installConfig(['comment']);
    $this->installEntitySchema('comment');

    // Group installs the group_roles field storage from its default config.
    // Install its base table first; Drupal 12 enforces this.
    $this->installEntitySchema('group_relationship');
    $this->installConfig(['group']);
    foreach (['user', 'group', 'group_config_wrapper'] as $entityType) {
      $this->installEntitySchema($entityType);
    }

    foreach (['lms_course' => 'Curso', 'lms_class' => 'Turma'] as $id => $label) {
      if (!GroupType::load($id)) {
        GroupType::create([
          'id' => $id,
          'label' => $label,
          'creator_membership' => FALSE,
          'creator_roles' => [],
        ])->save();
      }
    }

    $this->installRelationshipType('lms_course', 'group_membership');
    $this->installRelationshipType('lms_class', 'group_membership');

    if (!FieldStorageConfig::load('group_relationship.group_roles')) {
      FieldStorageConfig::create([
        'field_name' => 'group_roles',
        'entity_type' => 'group_relationship',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'group_role'],
        'cardinality' => -1,
      ])->save();
    }
    foreach (['lms_course-group_membership', 'lms_class-group_membership'] as $bundle) {
      if (!FieldConfig::load('group_relationship.' . $bundle . '.group_roles')) {
        FieldConfig::create([
          'field_name' => 'group_roles',
          'entity_type' => 'group_relationship',
          'bundle' => $bundle,
          'label' => 'Group roles',
          'settings' => ['handler' => 'default', 'handler_settings' => []],
        ])->save();
      }
    }

    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('group_relation_type.manager')->clearCachedPluginMaps();
  }

  /**
   * Prepares config dependencies required by partial LMS Kernel installs.
   *
   * These tests load the real LMS default config without enabling its full
   * Portal dependency graph. Seed the entity types and fields that the real
   * config depends on before the module installer imports it.
   */
  protected function prepareAcultaLmsPartialConfigDependencies(): void {
    $this->installConfig(['node', 'media', 'comment']);

    if (!NodeType::load('wiki_entry')) {
      NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry'])->save();
    }
    if (!MediaType::load('remote_video')) {
      MediaType::create([
        'id' => 'remote_video',
        'label' => 'Remote video',
        'source' => 'oembed:video',
        'source_configuration' => ['source_field' => 'field_media_oembed_video'],
      ])->save();
    }
    if (!CommentType::load('aculta_discussion_post')) {
      CommentType::create([
        'id' => 'aculta_discussion_post',
        'label' => 'Forum discussion post',
        'target_entity_type_id' => 'node',
      ])->save();
    }
    if (!FieldStorageConfig::load('node.field_aculta_discussion')) {
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => 'field_aculta_discussion',
        'type' => 'comment',
        'settings' => ['comment_type' => 'aculta_discussion_post'],
      ])->save();
    }
    if (!FieldStorageConfig::load('comment.field_aculta_rejected')) {
      FieldStorageConfig::create([
        'entity_type' => 'comment',
        'field_name' => 'field_aculta_rejected',
        'type' => 'boolean',
      ])->save();
    }
    if (!FieldConfig::load('comment.aculta_discussion_post.field_aculta_rejected')) {
      FieldConfig::create([
        'entity_type' => 'comment',
        'bundle' => 'aculta_discussion_post',
        'field_name' => 'field_aculta_rejected',
        'label' => 'Rejected',
      ])->save();
    }
  }

  /**
   * Installs the ACULTA course-to-class Group relation after aculta_lms loads.
   */
  protected function prepareAcultaLmsClassRelationship(): void {
    $this->installRelationshipType('lms_course', 'lms_classes');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('group_relation_type.manager')->clearCachedPluginMaps();
  }

  /**
   * Installs a Group relationship type if it is not already present.
   */
  protected function installRelationshipType(string $groupType, string $pluginId): void {
    $id = $groupType . '-' . $pluginId;
    if (GroupRelationshipType::load($id)) {
      return;
    }
    GroupRelationshipType::create([
      'id' => $id,
      'group_type' => $groupType,
      'relation_type' => $pluginId,
      'plugin_config' => ['group_cardinality' => 0, 'entity_cardinality' => 1],
    ])->save();
  }

  /**
   * Adds the real Portal, Messenger, LMS, and Studio services used by LMS.
   */
  protected function registerLmsDependencyServices(ContainerBuilder $container): void {
    // AgendaEntriesProvider depende de aculta_events.occurrences. Os Kernels
    // parciais do LMS não habilitam aculta_events; a definição real, com as
    // mesmas dependências, evita referência inválida na compilação. Quando o
    // aculta_events está habilitado, o serviço do módulo prevalece.
    $container->register('aculta_events.occurrences', EventDateOccurrences::class)
      ->addArgument(new Reference('entity_type.manager'));
    // CourseDocumentCleanup depende do avaliador de mídia não publicada do
    // Portal; os Kernels parciais do LMS não habilitam o aculta_portal inteiro.
    $container->register('logger.channel.aculta_portal', LoggerChannel::class)
      ->setFactory([new Reference('logger.factory'), 'get'])
      ->addArgument('aculta_portal');
    $container->register('aculta_portal.unused_media_cleaner', UnusedMediaCleaner::class)
      ->addArgument(new Reference('aculta_file_uploader.file_reference_inspector'))
      ->addArgument(new Reference('logger.channel.aculta_portal'));
    $container->register('aculta_pagamentos_mercadopago.client_factory', MercadoPagoClientFactory::class);
    $container->register('aculta_pagamentos_mercadopago.recurrence_request_builder', RecurrenceRequestBuilder::class);
    $container->register('aculta_pagamentos_mercadopago.payment_policy', MercadoPagoPaymentPolicy::class);
    $container->register('aculta_pagamentos_mercadopago.logger', LoggerChannel::class)
      ->setFactory([new Reference('logger.factory'), 'get'])
      ->addArgument('aculta_pagamentos_mercadopago');
    $container->register('aculta_pagamentos_mercadopago.payment_service', MercadoPagoPaymentService::class)
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.client_factory'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.payment_policy'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('lock'));
    $container->register('aculta_pagamentos_mercadopago.recurrence_service', MercadoPagoRecurrenceService::class)
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.client_factory'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.recurrence_request_builder'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'));
    $container->register('aculta_pagamentos_mercadopago.subscription_recorder', MercadoPagoSubscriptionRecorder::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.recurrence_service'))
      ->addArgument(new Reference('aculta_pagamentos_mercadopago.logger'))
      ->addArgument(new Reference('datetime.time'))
      ->addArgument(new Reference('lock'));
    $container->setAlias('aculta_portal.payment_provider.subscription_status', 'aculta_pagamentos_mercadopago.subscription_recorder');
    $container->setAlias('aculta_portal.payment_provider.subscription_operations', 'aculta_pagamentos_mercadopago.subscription_recorder');
    $container->setAlias('aculta_portal.payment_provider.payment_operations', 'aculta_pagamentos_mercadopago.payment_service');
    $container->setAlias('aculta_portal.payment_provider.refund_policy', 'aculta_pagamentos_mercadopago.payment_policy');
    $container->register('aculta_portal.checkout_settings', CheckoutSettings::class)
      ->addArgument(new Reference('config.factory'));
    $container->register('aculta_portal.order_type_checkout_flows', OrderTypeCheckoutFlows::class)
      ->addArgument(new Reference('entity_type.manager'));
    $container->register('aculta_portal.domain_site', DomainSiteManager::class)
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
      ->addArgument(new Reference('aculta_portal.domain_site'))
      ->addArgument(new Reference('aculta_lms.class_activity_deadline_manager'))
      ->addArgument(new Reference('date.formatter'))
      ->addArgument(new Reference('queue'))
      ->addArgument(new Reference('logger.channel.aculta_messenger'))
      ->addArgument(new Reference('aculta_messenger.notice_event_publisher'))
      ->addArgument(new Reference('lock'));
    $container->register('aculta_studio.experience.ganjalab', GanjalabExperienceProvider::class)
      ->addArgument(new Reference('aculta_lms.learner_dashboard_composer', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));
    $container->register('aculta_studio.experience_manager', StudioExperienceManager::class)
      ->addMethodCall('addProvider', [new Reference('aculta_studio.experience.ganjalab')]);
    $container->register('aculta_studio.ganjalab_controller', GanjalabController::class)
      ->addArgument(new Reference('aculta_studio.experience_manager'))
      ->addArgument(new Reference('current_user'));
    $this->registerAuthorCardBuilder($container);
  }

  /**
   * Registers the real author card builder.
   *
   * Partial kernels do not install the full aculta_user configuration graph.
   */
  protected function registerAuthorCardBuilder(ContainerBuilder $container): void {
    $profile_storage = $this->createMock(ProfileStorageInterface::class);
    $profile_storage->method('loadByUser')->willReturn(NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('profile')->willReturn($profile_storage);
    $resolver = new AuthorIdentityResolver(
      $entity_type_manager,
      $this->createMock(PublicProfileLinkInterface::class),
      (new \ReflectionClass(ImageRenderer::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(PublicProfilePolicy::class))->newInstanceWithoutConstructor(),
      $this->createMock(SocialNetworksInterface::class),
      $this->createMock(DateFormatterInterface::class),
    );
    $container->register('aculta_user.author_card_builder', AuthorCardBuilder::class)
      ->addArgument($resolver);
  }

}
