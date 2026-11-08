<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Kernel;

use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\UserSession;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests private revisionable proposal storage and default-deny access. */
#[RunTestsInSeparateProcesses]
final class ContributionEntityKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = ['system', 'user', 'options', 'views', 'aculta_contributions'];

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_contribution');
    $this->installConfig(['system', 'user']);
  }

  /**
   * A proposal retains revisions and is private without explicit permission. */
  public function testRevisionHistoryAndAccess(): void {
    $user_storage = $this->container->get('entity_type.manager')->getStorage('user');
    // Reserve UID 1: Drupal treats it as a superuser and it cannot prove RBAC.
    $user_storage->create(['name' => 'uid-one', 'status' => 1])->save();
    Role::create([
      'id' => 'contribution_viewer',
      'label' => 'Contribution viewer',
      'permissions' => ['view own aculta contributions'],
    ])->save();
    $account = $user_storage->create([
      'name' => 'contributor',
      'status' => 1,
      'roles' => ['contribution_viewer'],
    ]);
    $account->save();
    $outsider = $user_storage->create(['name' => 'outsider', 'status' => 1]);
    $outsider->save();
    $registry = $this->container->get('aculta_contributions.adapter_registry');
    self::assertInstanceOf(ContributionAdapterRegistry::class, $registry);
    $registry->addAdapter(new ContributionTestAdapter($this->container->get('entity_type.manager')));
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_contribution');
    $proposal = $storage->create([
      'uid' => $account->id(),
      'subject' => 'Proposed update',
      'source' => 'test_user',
      'source_module' => 'aculta_user',
      'purpose' => 'account',
      'intent' => 'update',
      'target_type' => 'node',
      'target_uuid' => '00000000-0000-4000-8000-000000000001',
      'base_revision' => 'revision:4',
      'payload' => ['title' => 'Suggested title'],
      'rationale' => 'A correction with a source.',
      'state' => 'submitted',
    ]);
    self::assertInstanceOf(Contribution::class, $proposal);
    $proposal->setNewRevision(TRUE);
    $proposal->save();
    $first_revision = (int) $proposal->getRevisionId();
    $proposal->set('state', 'needs_changes');
    $proposal->setNewRevision(TRUE);
    $proposal->save();

    self::assertNotSame($first_revision, (int) $proposal->getRevisionId());
    self::assertSame('submitted', $storage->loadRevision($first_revision)->get('state')->value);
    self::assertSame(['title' => 'Suggested title'], $proposal->getPayload());

    $anonymous = new UserSession(['uid' => 0, 'roles' => ['anonymous']]);
    $author_without_grant = new UserSession([
      'uid' => (int) $outsider->id(),
      'roles' => ['authenticated'],
    ]);
    $author = new UserSession([
      'uid' => (int) $account->id(),
      'roles' => ['authenticated', 'contribution_viewer'],
    ]);
    self::assertFalse($proposal->access('view', $anonymous));
    self::assertFalse($proposal->access('view', $author_without_grant));
    self::assertTrue($proposal->access('view', $author));
    $unknown_source = $storage->create([
      'uid' => $account->id(),
      'subject' => 'Unregistered source',
      'source' => 'unregistered_adapter',
      'source_module' => 'aculta_user',
      'purpose' => 'account',
      'intent' => 'update',
      'target_type' => 'node',
      'target_uuid' => '00000000-0000-4000-8000-000000000001',
      'base_revision' => 'revision:4',
      'payload' => ['title' => 'Suggested title'],
      'rationale' => 'A correction with a source.',
      'state' => 'submitted',
    ]);
    self::assertFalse($unknown_source->access('view', $author));
  }

  /**
   * The schema update restores missing context fields and is repeatable.
   */
  public function testContextFieldUpdateIsIdempotent(): void {
    $update_manager = $this->container->get('entity.definition_update_manager');
    foreach (['source_data', 'decision_data'] as $field_name) {
      $definition = $update_manager->getFieldStorageDefinition($field_name, 'aculta_contribution');
      self::assertNotNull($definition);
      $update_manager->uninstallFieldStorageDefinition($definition);
    }

    $module_path = $this->container->get('extension.list.module')->getPath('aculta_contributions');
    require_once DRUPAL_ROOT . '/' . $module_path . '/aculta_contributions.install';
    self::assertSame(
      'Installed contribution context fields: source_data, decision_data.',
      aculta_contributions_update_10001(),
    );
    self::assertSame('Contribution context fields already exist.', aculta_contributions_update_10001());
    foreach (['source_data', 'decision_data'] as $field_name) {
      self::assertNotNull($update_manager->getFieldStorageDefinition($field_name, 'aculta_contribution'));
    }
  }

}
