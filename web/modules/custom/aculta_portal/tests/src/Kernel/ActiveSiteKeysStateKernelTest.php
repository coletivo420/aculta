<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Domain\ActiveSiteKeysState;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Drupal\aculta_portal\Hook\DomainLifecycleHooks;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests active site-key state and Domain lifecycle hooks.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class ActiveSiteKeysStateKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'domain',
    'aculta_file_uploader',
  ];

  /**
   * Active site keys state.
   *
   * @var \Drupal\aculta_portal\Domain\ActiveSiteKeysState
   */
  private ActiveSiteKeysState $activeSiteKeysState;
  /**
   * Hooks do ciclo de vida de domínio.
   *
   * @var \Drupal\aculta_portal\Hook\DomainLifecycleHooks
   */
  private DomainLifecycleHooks $lifecycleHooks;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setDefinition(
      'aculta_file_uploader.ingestor',
      (new Definition(\stdClass::class))->setPublic(TRUE),
    );
    foreach ([
      'aculta_file_uploader.unused_file_cleaner',
      'aculta_file_uploader.quarantine',
      'aculta_file_uploader.cleanup',
    ] as $serviceId) {
      $container->setDefinition($serviceId, (new Definition(\stdClass::class))->setPublic(TRUE));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');

    $manager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
    $this->activeSiteKeysState = new ActiveSiteKeysState(
      $this->container->get('state'),
      $manager,
    );
    $this->lifecycleHooks = new DomainLifecycleHooks($this->activeSiteKeysState);
  }

  /**
   * Tests active Domain entities with valid paths only.
   */
  public function testActiveSiteKeysIncludesOnlyActiveDomains(): void {
    // Create an active domain (main -> aculta_org).
    $mainDomain = Domain::create([
      'id' => 'aculta_org',
      'hostname' => 'aculta.example.invalid',
      'name' => 'ACULTA Main',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ]);
    $mainDomain->save();
    $this->lifecycleHooks->onDomainInsert($mainDomain);

    // Create an inactive domain (wiki -> wiki420_aculta_org).
    $wikiDomain = Domain::create([
      'id' => 'wiki420_aculta_org',
      'hostname' => 'wiki.example.invalid',
      'name' => 'Wiki420',
      'scheme' => 'https',
      'weight' => 1,
      'status' => FALSE,
    ]);
    $wikiDomain->save();
    $this->lifecycleHooks->onDomainInsert($wikiDomain);

    $active = $this->activeSiteKeysState->get();
    $this->assertContains('main', $active);
    $this->assertNotContains('wiki', $active);

    // Verifica também diretamente pela State API do core.
    $this->assertSame($active, $this->container->get('state')->get(ActiveSiteKeysState::STATE_KEY));

    // Activate the inactive domain: onDomainUpdate rebuilds the state.
    $wikiDomain->set('status', TRUE);
    $wikiDomain->save();
    $this->lifecycleHooks->onDomainUpdate($wikiDomain);

    $active = $this->activeSiteKeysState->get();
    $this->assertContains('main', $active);
    $this->assertContains('wiki', $active);

    // Delete the domain: onDomainDelete rebuilds the state.
    $wikiDomain->delete();
    $this->lifecycleHooks->onDomainDelete($wikiDomain);
    $active = $this->activeSiteKeysState->get();
    $this->assertNotContains('wiki', $active);
  }

  /**
   * Tests post_update populates the active site keys state.
   */
  public function testPostUpdatePopulatesState(): void {
    require_once dirname(__DIR__, 3) . '/aculta_portal.post_update.php';

    $shopDomain = Domain::create([
      'id' => 'loja_aculta_org',
      'hostname' => 'loja.example.invalid',
      'name' => 'Loja',
      'scheme' => 'https',
      'weight' => 2,
      'status' => TRUE,
    ]);
    $shopDomain->save();

    // Register the state service for the post-update callback.
    $this->container->set('aculta_portal.active_site_keys_state', $this->activeSiteKeysState);

    // Clear state directly.
    $this->container->get('state')->delete(ActiveSiteKeysState::STATE_KEY);
    $this->assertSame([], $this->activeSiteKeysState->get());

    $this->container->get('state')->set(ActiveSiteKeysState::STATE_KEY, ['main', 'invalid', 42]);
    $this->assertSame(['main'], $this->activeSiteKeysState->get());
    $this->container->get('state')->delete(ActiveSiteKeysState::STATE_KEY);

    $sandbox = [];
    $message = aculta_portal_post_update_active_site_keys_state($sandbox);
    $this->assertStringContainsString('shop', (string) $message);
    $this->assertContains('shop', $this->activeSiteKeysState->get());
    self::assertSame(['shop'], $this->activeSiteKeysState->get());
  }

}
