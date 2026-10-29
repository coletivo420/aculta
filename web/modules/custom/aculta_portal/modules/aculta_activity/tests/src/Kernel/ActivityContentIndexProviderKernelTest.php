<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Kernel;

use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\aculta_activity\Form\ActivityFormCallbacks;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\profile\Entity\ProfileType;
use Drupal\Core\Form\FormState;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\Form\NodeForm;
use Drupal\schema_metatag\SchemaMetatagManager;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies Activity provider discovery and projection in the real container.
 */
#[Group('aculta_activity')]
#[RunTestsInSeparateProcesses]
final class ActivityContentIndexProviderKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'block',
    'comment',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_store',
    'ctools',
    'ctools_views',
    'datetime',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'entity',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flexible_permissions',
    'group',
    'image',
    'inline_entity_form',
    'key',
    'language',
    'link',
    'lms',
    'login_emailusername',
    'media',
    'media_duplicates',
    'metatag',
    'node',
    'options',
    'path',
    'path_alias',
    'physical',
    'profile',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'search_api',
    'search_api_db',
    'social_api',
    'social_auth',
    'state_machine',
    'system',
    'taxonomy',
    'text',
    'token',
    'user',
    'views',
    'vvj_core',
    'vvjs',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'aculta_mailer',
    'aculta_portal',
    'aculta_portal_cep',
    'aculta_user',
    'aculta_index',
    'aculta_activity',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('profile');
    $this->installSchema('aculta_user', ['aculta_user_agreement', 'aculta_user_nickname']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('domain');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['user', 'node', 'system', 'domain', 'domain_source', 'aculta_user']);
    foreach (PerfilDoPapel::TIPOS as $bundle) {
      if (ProfileType::load($bundle) === NULL) {
        continue;
      }
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => $bundle,
        'field_name' => 'field_nickname',
        'label' => 'Nickname',
      ])->save();
    }
    NodeType::create(['type' => 'activity', 'name' => 'Activity'])->save();
    foreach ([
      'field_media_image' => ['entity_reference', ['target_type' => 'media']],
      'field_modality' => ['string', []],
      'field_place_name' => ['string', []],
      'field_online_url' => ['link', []],
      'field_event_start' => ['datetime', ['datetime_type' => 'datetime']],
      'field_event_end' => ['datetime', ['datetime_type' => 'datetime']],
    ] as $name => [$type, $settings]) {
      FieldStorageConfig::create([
        'entity_type' => 'node',
        'field_name' => $name,
        'type' => $type,
        'settings' => $settings,
      ])->save();
      FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => 'activity',
        'field_name' => $name,
        'label' => $name,
      ])->save();
    }
    aculta_index_install();
    foreach (['aculta_org' => 'main', 'cursos_aculta_org' => 'courses'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain(Domain::load('cursos_aculta_org'));
  }

  /**
   * Tests the tagged provider is discovered and projects an Activity node.
   */
  public function testTaggedProviderProjectsActivityNode(): void {
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertTrue($registry->has('activity'));
    $provider = $registry->get('activity');
    self::assertInstanceOf(ContentIndexProviderInterface::class, $provider);

    $node = Node::create([
      'type' => 'activity',
      'title' => 'Encontro de teste',
      'status' => TRUE,
      'created' => 100,
      'changed' => 200,
    ]);
    $node->save();
    $work = $this->container->get('aculta_index.work_manager')
      ->getStatus('activity', 'node', (string) $node->id(), 'en');
    self::assertSame('project', $work['operation']);

    $projection = $provider->project($node);
    self::assertNotNull($projection);
    self::assertSame('node', $projection->entityTypeId);
    self::assertSame('activity', $projection->bundle);
    self::assertSame((string) $node->id(), $projection->entityId);
    self::assertSame('en', $projection->langcode);
    self::assertSame('Encontro de teste', $projection->label);
    self::assertSame([], $projection->relations);
    self::assertSame('main', $provider->getAccessPurpose($node));
  }

  /**
   * Tests backfill, incremental update and deletion through the real worker.
   */
  public function testActivityProjectionFollowsEntityCrud(): void {
    $activity = Node::create([
      'type' => 'activity',
      'title' => 'Atividade inicial',
      'status' => TRUE,
      'created' => 100,
      'changed' => 200,
    ]);
    $activity->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The Activity provider backfill should complete.');
    $this->processActivityWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    self::assertSame('Atividade inicial', $this->entryLabel($generationId, (string) $activity->id()));

    $activity->setTitle('Atividade atualizada');
    $activity->save();
    $this->processActivityWork();
    self::assertSame('Atividade atualizada', $this->entryLabel($generationId, (string) $activity->id()));

    $activity->delete();
    $this->processActivityWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'activity')
      ->condition('entity_id', (string) $activity->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Rechecks Activity access in the canonical main Domain and restores caller.
   */
  public function testIndexFilterUsesMainDomainAndFailsClosedWhenMissing(): void {
    User::create([
      'name' => 'activity-index-root-fixture',
      'mail' => 'activity-index-root@example.invalid',
      'status' => 1,
    ])->save();
    $reader = $this->createUser();
    $authenticated = Role::load(Role::AUTHENTICATED_ID);
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->save();
    $activity = Node::create([
      'type' => 'activity',
      'title' => 'Atividade de acesso contextual',
      'status' => TRUE,
    ]);
    $activity->save();
    $candidate = [[
      'entity_type' => 'node',
      'entity_id' => (string) $activity->id(),
      'langcode' => $activity->language()->getId(),
    ],
    ];
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    self::assertTrue($this->container->get('module_handler')->moduleExists('aculta_portal'));
    self::assertInstanceOf(DomainPurposeManager::class, $this->container->get('aculta_portal.domain_purpose'));
    $filter = $this->container->get('aculta_index.access_filter');
    self::assertInstanceOf(ContentIndexAccessFilter::class, $filter);

    $allowed = $filter->filter($candidate, $reader);
    self::assertCount(1, $allowed->entities, 'Published Activity remains visible under its owner purpose.');
    self::assertSame('cursos_aculta_org', $negotiator->getActiveDomain()?->id());
    self::assertSame('courses', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());

    $activity->setUnpublished()->save();
    self::assertCount(0, $filter->filter($candidate, $reader)->entities, 'The current unpublished state is revalidated.');
    self::assertSame('cursos_aculta_org', $negotiator->getActiveDomain()?->id());

    Domain::load('aculta_org')->delete();
    self::assertCount(0, $filter->filter($candidate, $reader)->entities, 'A missing canonical purpose denies exposure.');
    self::assertSame(
      'cursos_aculta_org',
      $negotiator->getActiveDomain()?->id(),
      'The active purpose remains unchanged when the canonical Domain is missing.',
    );
  }

  /**
   * Tests form grouping and callback dispatch through the Portal hook manager.
   */
  public function testActivityFormHooksAreDispatchedByPortal(): void {
    $node = Node::create(['type' => 'activity', 'title' => 'Atividade de teste']);
    $formObject = NodeForm::create($this->container);
    $formObject->setEntity($node);
    $formState = new FormState();
    $formState->setFormObject($formObject);
    $form = [
      'title' => [],
      'body' => [],
      'field_media_image' => [],
      'field_gallery' => [],
      'field_related_activity' => [],
      'field_meta_tags' => [],
      'author' => [],
      'options' => [],
    ];

    $arguments = [&$form, $formState, 'node_activity_form'];
    $this->container->get('module_handler')->invoke('aculta_activity', 'form_node_form_alter', $arguments);

    self::assertSame('editorial_content', $form['title']['#group']);
    self::assertSame('editorial_image', $form['field_media_image']['#group']);
    self::assertSame('editorial_relations', $form['field_related_activity']['#group']);
    self::assertSame('editorial_seo', $form['field_meta_tags']['#group']);
    self::assertSame('Conta responsável pelo cadastro', (string) $form['author']['#title']);
    self::assertSame(['aculta_activity.form_callbacks:validateActivity'], $form['#validate']);
    self::assertInstanceOf(ActivityFormCallbacks::class, $this->container->get('aculta_activity.form_callbacks'));
  }

  /**
   * Tests conditional errors from submitted values through the real callback.
   */
  public function testActivityFormValidationUsesSubmittedValues(): void {
    $validate = function (array $values): FormState {
      $node = Node::create(['type' => 'activity', 'title' => 'Atividade validada']);
      $formObject = NodeForm::create($this->container);
      $formObject->setEntity($node);
      $formState = new FormState();
      $formState->setFormObject($formObject);
      $formState->setValues($values);
      $formDisplay = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'activity',
        'mode' => 'default',
        'status' => TRUE,
      ]);
      $formObject->setFormDisplay($formDisplay, $formState);
      $form = ['#parents' => []];
      $this->container->get('aculta_activity.form_callbacks')
        ->validateActivity($form, $formState);
      return $formState;
    };

    $onlineWithoutUrl = $validate(['field_modality' => [['value' => 'online']]]);
    self::assertArrayHasKey('field_online_url', $onlineWithoutUrl->getErrors());

    $inPersonWithoutPlace = $validate(['field_modality' => [['value' => 'presencial']]]);
    self::assertArrayHasKey('field_place_name', $inPersonWithoutPlace->getErrors());

    $endBeforeStart = $validate([
      'field_modality' => [['value' => 'online']],
      'field_online_url' => [['uri' => 'https://example.invalid/atividade']],
      'field_event_start' => [['value' => '2030-04-12T19:00:00']],
      'field_event_end' => [['value' => '2030-04-12T18:00:00']],
    ]);
    self::assertArrayHasKey('field_event_end', $endBeforeStart->getErrors());
  }

  /**
   * Tests location schema and explicit overrides through hook dispatch.
   */
  public function testActivityMetatagsHookAddsVirtualLocationAndPreservesOverride(): void {
    $node = Node::create([
      'type' => 'activity',
      'title' => 'Atividade online',
      'field_modality' => 'online',
      'field_online_url' => ['uri' => 'https://example.invalid/atividade'],
    ]);
    $tags = [
      'schema_article_image' => '[node:image]',
      'schema_event_image' => '[node:image]',
      'schema_web_page_image' => '[node:image]',
    ];
    $context = ['entity' => $node];
    $arguments = [&$tags, &$context];
    $this->container->get('module_handler')->invoke('aculta_activity', 'metatags_alter', $arguments);

    self::assertArrayNotHasKey('schema_article_image', $tags);
    self::assertArrayNotHasKey('schema_event_image', $tags);
    self::assertArrayNotHasKey('schema_web_page_image', $tags);
    self::assertSame([
      '@type' => 'VirtualLocation',
      'url' => 'https://example.invalid/atividade',
    ], SchemaMetatagManager::unserialize($tags['schema_event_location']));

    $tags['schema_event_location'] = 'editorial override';
    $this->container->get('module_handler')->invoke('aculta_activity', 'metatags_alter', $arguments);
    self::assertSame('editorial override', $tags['schema_event_location']);
  }

  /**
   * Processes queued work belonging to the Activity provider.
   */
  private function processActivityWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'activity')
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

  /**
   * Returns the label of one Activity entry in a generation.
   */
  private function entryLabel(string $generationId, string $entityId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'activity')
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

}
