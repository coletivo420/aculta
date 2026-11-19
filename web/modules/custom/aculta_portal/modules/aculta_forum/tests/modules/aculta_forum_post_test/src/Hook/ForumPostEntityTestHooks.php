<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_post_test\Hook;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\content_moderation\Entity\Handler\ModerationHandler;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\aculta_forum\Hook\ForumPostAccessHooks;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Registers the real ForumPost definition without enabling Forum integrations.
 */
final class ForumPostEntityTestHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RequestStack $requestStack,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ?DomainSiteManagerInterface $siteManager = NULL,
    private readonly ?DomainNegotiatorInterface $domainNegotiator = NULL,
  ) {}

  /**
   * Declares the fixture's fixed bundle for Content Moderation discovery.
   *
   * @return array<string, array<string, array<string, mixed>>>
   */
  #[Hook('entity_bundle_info')]
  public function entityBundleInfo(): array {
    return [
      'aculta_forum_post' => [
        'aculta_forum_post' => [
          'label' => new TranslatableMarkup('Publicação do Fórum'),
          'translatable' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Implements hook_entity_access() with the production Forum rules.
   *
   * The fixture registers the entity but not the Forum module, so its access
   * hook must be delegated explicitly for realistic Kernel access checks.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    $siteManager = $this->siteManager instanceof DomainSiteManager
      ? $this->siteManager
      : ($this->domainNegotiator instanceof DomainNegotiatorInterface
        ? new DomainSiteManager(
          $this->entityTypeManager,
          $this->domainNegotiator,
          $this->configFactory,
          $this->requestStack,
        )
        : NULL);
    if (!$siteManager instanceof DomainSiteManager) {
      return NULL;
    }
    return (new ForumPostAccessHooks($siteManager))->entityAccess($entity, $operation, $account);
  }

  /**
   * Implements hook_entity_type_alter().
   *
   * @param array<string, \Drupal\Core\Entity\EntityTypeInterface> $entity_types
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $attributes = (new \ReflectionClass(ForumPost::class))->getAttributes(ContentEntityType::class);
    $definition = $attributes[0]->newInstance();
    $definition->setClass(ForumPost::class);
    $definition->setProvider('aculta_forum_post_test');
    $entity_type = $definition->get();
    // Production discovers the entity type before alters, so Content
    // Moderation adds its handler. The fixture adds the type during the alter,
    // after that hook may have run; restore the handler for parity.
    if ($entity_type instanceof EntityTypeInterface && $this->moduleHandler->moduleExists('content_moderation')) {
      $entity_type->setHandlerClass('moderation', ModerationHandler::class);
    }
    if ($entity_type instanceof EntityTypeInterface) {
      $entity_types['aculta_forum_post'] = $entity_type;
    }
  }

}
