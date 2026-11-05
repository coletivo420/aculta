<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Domain\ActivePurposesState;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Hook\DomainLifecycleHooks;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the neutral active purposes state management and Domain lifecycle hooks.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class ActivePurposesStateKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'domain',
  ];

  private ActivePurposesState $activePurposesState;
  private DomainLifecycleHooks $lifecycleHooks;

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
    $this->activePurposesState = new ActivePurposesState(
      $this->container->get('state'),
      $manager,
    );
    $this->lifecycleHooks = new DomainLifecycleHooks($this->activePurposesState);
  }

  /**
   * Tests that active purposes state includes only active Domain entities with valid paths.
   */
  public function testActivePurposesIncludesOnlyActiveDomains(): void {
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

    $active = $this->activePurposesState->get();
    $this->assertContains('main', $active);
    $this->assertNotContains('wiki', $active);

    // Also verify directly from Core State API
    $this->assertSame($active, $this->container->get('state')->get(ActivePurposesState::STATE_KEY));

    // Activate the inactive domain: onDomainUpdate rebuilds the state.
    $wikiDomain->set('status', TRUE);
    $wikiDomain->save();
    $this->lifecycleHooks->onDomainUpdate($wikiDomain);

    $active = $this->activePurposesState->get();
    $this->assertContains('main', $active);
    $this->assertContains('wiki', $active);

    // Delete the domain: onDomainDelete rebuilds the state.
    $wikiDomain->delete();
    $this->lifecycleHooks->onDomainDelete($wikiDomain);
    $active = $this->activePurposesState->get();
    $this->assertNotContains('wiki', $active);
  }

  /**
   * Tests post_update populates the active purposes state.
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

    // Register active_purposes_state in container for post_update to resolve \Drupal::service()
    $this->container->set('aculta_portal.active_purposes_state', $this->activePurposesState);

    // Clear state directly.
    $this->container->get('state')->delete(ActivePurposesState::STATE_KEY);
    $this->assertSame([], $this->activePurposesState->get());

    $sandbox = [];
    $message = aculta_portal_post_update_active_purposes_state($sandbox);
    $this->assertStringContainsString('shop', (string) $message);
    $this->assertContains('shop', $this->activePurposesState->get());
  }

}
