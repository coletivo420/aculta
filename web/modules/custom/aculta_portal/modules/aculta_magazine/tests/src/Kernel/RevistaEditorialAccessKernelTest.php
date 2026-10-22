<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Kernel;

use Drupal\aculta_magazine\Hook\RevistaFormHooks;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Proves the editorial contracts of the Revista against real Drupal access.
 *
 * The checks run over the real `revista` workflow, the real `node` access
 * handler, content moderation and node_co_authors, with the permissions of the
 * roles in config/sync. A static test cannot replace Entity Access: this test
 * asks Drupal itself.
 *
 * Kernel scope: the Drupal Domain module and the ACULTA theme are not enabled,
 * so the cross-domain URL scenario is covered by the HTTP smoke of the
 * homelab, not here.
 */
#[Group('aculta_magazine')]
#[RunTestsInSeparateProcesses]
final class RevistaEditorialAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'workflows',
    'content_moderation',
    'node_co_authors',
  ];

  /**
   * Editorial actor with the content_editor role.
   */
  private UserInterface $editor;

  /**
   * Authenticated user without any editorial permission.
   */
  private UserInterface $plainUser;

  /**
   * E-mail confirmed user, co-author of the editor's draft.
   */
  private UserInterface $coauthor;

  /**
   * Second content_editor, also co-author of the editor's draft.
   */
  private UserInterface $editorCoauthor;

  /**
   * E-mail confirmed user who is not a co-author.
   */
  private UserInterface $confirmed;

  /**
   * Draft owned by the editor, with two co-authors.
   */
  private NodeInterface $editorDraft;

  /**
   * Draft owned by the authenticated user.
   */
  private NodeInterface $plainDraft;

  /**
   * Published node owned by the editor.
   */
  private NodeInterface $published;

  /**
   * Published node with a pending draft revision, co-authored by $coauthor.
   */
  private NodeInterface $pending;

  /**
   * Published node owned by $coauthor, with a pending draft revision.
   */
  private NodeInterface $ownPending;

  /**
   * Users built by the test, keyed by user name.
   *
   * @var array<string, \Drupal\Core\Session\AccountInterface>
   */
  private array $accounts = [];

  /**
   * Next user id. It starts at 2 so uid 1 (superuser) is never a test actor.
   */
  private int $nextUid = 2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig([
      'system',
      'user',
      'filter',
      'field',
      'node',
      'workflows',
      'content_moderation',
    ]);

    // The node type and the workflow come first: the role permissions are
    // filtered against the permissions currently defined by the installed
    // modules, and the bundle and transition permissions only exist after
    // both are saved.
    $this->installEditorialConfiguration();
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installCreditsField();
    $this->installRoles();

    $this->editor = $this->createUser('autor_a', 'content_editor');
    $this->plainUser = $this->createUser('autor_b', NULL);
    $this->coauthor = $this->createUser('coautor_c', 'email_confirmed');
    $this->editorCoauthor = $this->createUser('coeditor_d', 'content_editor');
    $this->confirmed = $this->createUser('confirmado_e', 'email_confirmed');
    foreach ([$this->editor, $this->plainUser, $this->coauthor, $this->editorCoauthor, $this->confirmed] as $user) {
      $this->accounts[$user->getAccountName()] = new UserSession([
        'uid' => (int) $user->id(),
        'name' => $user->getAccountName(),
        'roles' => $user->getRoles(),
      ]);
    }

    $this->editorDraft = $this->createDraft($this->editor, 'Rascunho da autora', [
      $this->coauthor,
      $this->editorCoauthor,
    ]);
    $this->plainDraft = $this->createDraft($this->plainUser, 'Rascunho de outro autor');
    $this->published = $this->publish($this->createDraft($this->editor, 'Publicação'));
    $this->pending = $this->publish($this->createDraft($this->editor, 'Com revisão pendente'));
    $this->addPendingRevision($this->pending, [$this->coauthor]);
    $this->ownPending = $this->publish($this->createDraft($this->coauthor, 'Do coautor'));
    $this->addPendingRevision($this->ownPending, []);
  }

  /**
   * Loads the real role permissions from config/sync into the test site.
   */
  private function installRoles(): void {
    foreach (['anonymous', 'authenticated'] as $id) {
      $role = Role::load($id);
      self::assertNotNull($role);
      $role->set('permissions', $this->permissionsOf($id))->save();
    }
    foreach (['content_editor', 'email_confirmed'] as $id) {
      Role::create([
        'id' => $id,
        'label' => $id,
        'permissions' => $this->permissionsOf($id),
      ])->save();
    }
  }

  /**
   * Returns the permissions configured on the repository for a role.
   *
   * @return string[]
   *   The permission names, in configuration order.
   */
  private function permissionsOf(string $role_id): array {
    $file = dirname(DRUPAL_ROOT) . '/config/sync/user.role.' . $role_id . '.yml';
    self::assertFileExists($file);
    $data = Yaml::decode((string) file_get_contents($file));
    self::assertIsArray($data);
    return $data['permissions'] ?? [];
  }

  /**
   * Creates the real node type and the real editorial workflow.
   */
  private function installEditorialConfiguration(): void {
    NodeType::create([
      'type' => 'revista',
      'name' => 'Revista',
      'new_revision' => TRUE,
      'preview_mode' => 1,
      'display_submitted' => FALSE,
    ])->save();
    Workflow::create([
      'id' => 'revista',
      'label' => 'Editorial da Revista',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'archived' => [
            'label' => 'Arquivado',
            'weight' => 5,
            'published' => FALSE,
            'default_revision' => TRUE,
          ],
          'draft' => [
            'label' => 'Rascunho',
            'weight' => -5,
            'published' => FALSE,
            'default_revision' => FALSE,
          ],
          'published' => [
            'label' => 'Publicado',
            'weight' => 0,
            'published' => TRUE,
            'default_revision' => TRUE,
          ],
          'review' => [
            'label' => 'Em revisão',
            'weight' => 1,
            'published' => FALSE,
            'default_revision' => FALSE,
          ],
        ],
        'transitions' => [
          'archive' => [
            'label' => 'Arquivar',
            'from' => ['published'],
            'to' => 'archived',
            'weight' => 2,
          ],
          'archived_draft' => [
            'label' => 'Voltar ao rascunho',
            'from' => ['archived'],
            'to' => 'draft',
            'weight' => 3,
          ],
          'archived_published' => [
            'label' => 'Republicar',
            'from' => ['archived'],
            'to' => 'published',
            'weight' => 4,
          ],
          'create_new_draft' => [
            'label' => 'Criar novo rascunho',
            'from' => ['draft', 'published'],
            'to' => 'draft',
            'weight' => 0,
          ],
          'draft_to_review' => [
            'label' => 'Enviar para revisão',
            'from' => ['draft'],
            'to' => 'review',
            'weight' => 2,
          ],
          'publish' => [
            'label' => 'Publicar',
            'from' => ['published', 'draft'],
            'to' => 'published',
            'weight' => 1,
          ],
          'review_to_draft' => [
            'label' => 'Devolver ao rascunho',
            'from' => ['review'],
            'to' => 'draft',
            'weight' => 3,
          ],
          'review_to_published' => [
            'label' => 'Aprovar e publicar',
            'from' => ['review'],
            'to' => 'published',
            'weight' => 4,
          ],
        ],
        'entity_types' => ['node' => ['revista']],
        'default_moderation_state' => 'draft',
      ],
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Creates a field so the secondary group of the form has real content.
   */
  private function installCreditsField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_credits',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
      'translatable' => FALSE,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_credits',
      'entity_type' => 'node',
      'bundle' => 'revista',
      'label' => 'Créditos',
    ])->save();
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'revista', 'default')
      ->setComponent('field_credits', ['type' => 'string_textfield', 'weight' => 20])
      ->save();
  }

  /**
   * Creates a user with an optional role.
   */
  private function createUser(string $name, ?string $role): UserInterface {
    $values = [
      'uid' => $this->nextUid++,
      'name' => $name,
      'mail' => $name . '@example.com',
      'status' => 1,
    ];
    if ($role !== NULL) {
      $values['roles'] = [$role];
    }
    $user = User::create($values);
    $user->save();
    return $user;
  }

  /**
   * Creates a draft publication owned by a user.
   *
   * @param \Drupal\user\UserInterface $owner
   *   Owner of the publication.
   * @param string $title
   *   Publication title.
   * @param \Drupal\user\UserInterface[] $coauthors
   *   Co-authors recorded on the revision.
   */
  private function createDraft(UserInterface $owner, string $title, array $coauthors = []): NodeInterface {
    $node = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'revista',
      'title' => $title,
      'uid' => $owner->id(),
    ]);
    $this->setCoauthors($node, $coauthors);
    $node->save();
    self::assertSame('draft', $node->get('moderation_state')->value);
    return $node;
  }

  /**
   * Publishes a publication through the moderation state of the workflow.
   */
  private function publish(NodeInterface $node): NodeInterface {
    $node->set('moderation_state', 'published');
    $node->save();
    self::assertTrue($node->isPublished());
    return $node;
  }

  /**
   * Adds a pending draft revision on top of a published publication.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Published publication, updated in place.
   * @param \Drupal\user\UserInterface[] $coauthors
   *   Co-authors recorded on the new revision.
   */
  private function addPendingRevision(NodeInterface $node, array $coauthors): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    $revision = $storage->load($node->id());
    self::assertInstanceOf(NodeInterface::class, $revision);
    $this->setCoauthors($revision, $coauthors);
    $revision->set('moderation_state', 'draft');
    $revision->save();
    self::assertFalse($revision->isDefaultRevision());
  }

  /**
   * Replaces the co-authors of a revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Publication being prepared.
   * @param \Drupal\user\UserInterface[] $coauthors
   *   Co-authors to record.
   */
  private function setCoauthors(NodeInterface $node, array $coauthors): void {
    $node->set('co_authors', array_map(
      static fn (UserInterface $user): array => ['target_id' => (int) $user->id()],
      $coauthors,
    ));
  }

  /**
   * Returns the session account of a user created by this test.
   */
  private function account(UserInterface $user): AccountInterface {
    return $this->accounts[$user->getAccountName()];
  }

  /**
   * Returns the anonymous session.
   */
  private function anonymous(): AccountInterface {
    return new UserSession(['uid' => 0, 'roles' => ['anonymous']]);
  }

  /**
   * Anonymous visitors only see published content, never the work in progress.
   */
  public function testAnonymousAccess(): void {
    $anonymous = $this->anonymous();
    self::assertTrue($this->published->access('view', $anonymous));
    self::assertFalse($this->editorDraft->access('view', $anonymous));
    // The default revision of $pending is published; the pending draft on top
    // of it is not.
    self::assertTrue($this->pending->access('view', $anonymous));
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $pendingDraft = $storage->loadRevision($storage->getLatestRevisionId($this->pending->id()));
    self::assertInstanceOf(NodeInterface::class, $pendingDraft);
    self::assertFalse($pendingDraft->access('view', $anonymous));
    self::assertFalse($this->published->access('update', $anonymous));
    self::assertFalse($this->published->access('view revision', $anonymous));
    self::assertFalse($this->published->access('view all revisions', $anonymous));
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('node');
    self::assertFalse($handler->createAccess('revista', $anonymous));
    self::assertFalse(Url::fromRoute('entity.node.edit_form', ['node' => $this->published->id()])->access($anonymous));
    self::assertFalse(Url::fromRoute('entity.node.version_history', ['node' => $this->published->id()])->access($anonymous));
  }

  /**
   * The editor creates and edits the publication, but cannot delete it.
   */
  public function testEditorialRoleCreateAndEditOwn(): void {
    $editor = $this->account($this->editor);
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('node');
    self::assertTrue($handler->createAccess('revista', $editor));
    self::assertTrue($this->editorDraft->access('view', $editor));
    self::assertTrue($this->editorDraft->access('update', $editor));
    self::assertTrue(Url::fromRoute('entity.node.edit_form', ['node' => $this->editorDraft->id()])->access($editor));
    self::assertFalse($this->editorDraft->access('delete', $editor));
    self::assertFalse(Url::fromRoute('entity.node.delete_form', ['node' => $this->editorDraft->id()])->access($editor));
    $role = Role::load('content_editor');
    self::assertNotNull($role);
    self::assertFalse($role->hasPermission('delete any revista content'));
    self::assertFalse($role->hasPermission('delete own revista content'));
  }

  /**
   * Editing any publication is a role decision, not an ownership one.
   */
  public function testEditAnyAndForeignAuthor(): void {
    $editor = $this->account($this->editor);
    $plain = $this->account($this->plainUser);
    self::assertTrue($this->plainDraft->access('view', $editor));
    self::assertTrue($this->plainDraft->access('update', $editor));
    // The authenticated role has no editorial permission, not even as owner.
    self::assertFalse($this->plainDraft->access('view', $plain));
    self::assertFalse($this->plainDraft->access('update', $plain));
    self::assertFalse(Url::fromRoute('entity.node.edit_form', ['node' => $this->plainDraft->id()])->access($plain));
  }

  /**
   * Co-authorship grants access to the content, never to the revision.
   */
  public function testCoauthorWithConfirmedEmail(): void {
    $coauthor = $this->account($this->coauthor);
    $confirmed = $this->account($this->confirmed);
    self::assertTrue($this->editorDraft->access('view', $coauthor));
    self::assertFalse($this->editorDraft->access('update', $coauthor));
    self::assertFalse($this->editorDraft->access('view revision', $coauthor));
    self::assertFalse($this->editorDraft->access('view all revisions', $coauthor));
    self::assertFalse($this->editorDraft->access('view', $confirmed));
    $validation = $this->container->get('content_moderation.state_transition_validation');
    self::assertSame([], $validation->getValidTransitions($this->editorDraft, $coauthor));
  }

  /**
   * A co-author who is already an editor gains nothing from co-authorship.
   */
  public function testCoauthorWithEditorRole(): void {
    $editorCoauthor = $this->account($this->editorCoauthor);
    self::assertTrue($this->editorDraft->access('update', $editorCoauthor));
    self::assertFalse($this->editorDraft->access('delete', $editorCoauthor));
    $validation = $this->container->get('content_moderation.state_transition_validation');
    $coauthored = array_keys($validation->getValidTransitions($this->editorDraft, $editorCoauthor));
    $foreign = array_keys($validation->getValidTransitions($this->plainDraft, $editorCoauthor));
    sort($coauthored);
    sort($foreign);
    self::assertSame($foreign, $coauthored);
  }

  /**
   * A pending revision is a private revision, even when the node is public.
   */
  public function testPendingRevisionStaysPrivate(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $draft = $storage->loadRevision($storage->getLatestRevisionId($this->pending->id()));
    self::assertInstanceOf(NodeInterface::class, $draft);
    self::assertFalse($draft->isDefaultRevision());
    $editor = $this->account($this->editor);
    $coauthor = $this->account($this->coauthor);
    self::assertTrue($this->pending->access('view', $editor));
    self::assertTrue($draft->access('view', $editor));
    self::assertTrue($draft->access('view revision', $editor));
    self::assertTrue($this->editorDraft->access('view revision', $editor));
    self::assertFalse($draft->access('view revision', $coauthor));
    self::assertFalse($draft->access('view all revisions', $coauthor));
  }

  /**
   * The latest version check follows permissions and ownership, not the tab.
   */
  public function testLatestVersionCheck(): void {
    $check = $this->container->get('access_check.latest_revision');
    $route = new Route('/node/{node}/latest', [], [], [
      '_content_moderation_entity_type' => 'node',
    ]);
    $match = static fn (NodeInterface $node): RouteMatch => new RouteMatch(
      'entity.node.latest_version',
      $route,
      ['node' => $node],
    );

    $editor = $this->account($this->editor);
    $coauthor = $this->account($this->coauthor);
    $plain = $this->account($this->plainUser);

    self::assertTrue($check->access($route, $match($this->pending), $editor)->isAllowed());
    self::assertFalse($check->access($route, $match($this->pending), $coauthor)->isAllowed());
    self::assertFalse($check->access($route, $match($this->pending), $plain)->isAllowed());
    // The e-mail confirmed owner has "view latest version" and "view own
    // unpublished content": ownership alone opens the pending revision.
    self::assertTrue($check->access($route, $match($this->ownPending), $coauthor)->isAllowed());
    // Without a pending revision the tab does not exist.
    self::assertTrue($check->access($route, $match($this->published), $editor)->isForbidden());
  }

  /**
   * Preview access is create access or update access, never a generic rule.
   */
  public function testPreviewAccess(): void {
    $check = $this->container->get('access_check.node.preview');
    $editor = $this->account($this->editor);
    $coauthor = $this->account($this->coauthor);
    $plain = $this->account($this->plainUser);
    $anonymous = $this->anonymous();

    $new = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'revista',
      'title' => 'Nova publicação',
    ]);
    self::assertTrue($check->access($editor, $new)->isAllowed());
    self::assertFalse($check->access($coauthor, $new)->isAllowed());
    self::assertFalse($check->access($anonymous, $new)->isAllowed());
    self::assertTrue($check->access($editor, $this->editorDraft)->isAllowed());
    self::assertFalse($check->access($plain, $this->plainDraft)->isAllowed());
  }

  /**
   * The real workflow exposes only the states and transitions it configures.
   */
  public function testWorkflowTransitions(): void {
    $workflow = Workflow::load('revista');
    self::assertNotNull($workflow);
    $plugin = $workflow->getTypePlugin();
    $states = array_keys($plugin->getStates());
    sort($states);
    self::assertSame(['archived', 'draft', 'published', 'review'], $states);
    self::assertFalse($plugin->hasTransitionFromStateToState('review', 'archived'));
    self::assertFalse($plugin->hasTransitionFromStateToState('draft', 'archived'));

    $validation = $this->container->get('content_moderation.state_transition_validation');
    $editor = $this->account($this->editor);
    $coauthor = $this->account($this->coauthor);
    $transitions = array_keys($validation->getValidTransitions($this->editorDraft, $editor));
    sort($transitions);
    self::assertSame(['create_new_draft', 'draft_to_review', 'publish'], $transitions);

    $from = $plugin->getState('draft');
    $to = $plugin->getState('review');
    self::assertTrue($validation->isTransitionValid($workflow, $from, $to, $editor, $this->editorDraft));
    self::assertFalse($validation->isTransitionValid($workflow, $from, $to, $coauthor, $this->editorDraft));

    $this->editorDraft->set('moderation_state', 'review');
    $this->editorDraft->save();
    $inReview = array_keys($validation->getValidTransitions($this->editorDraft, $editor));
    sort($inReview);
    self::assertSame(['review_to_draft', 'review_to_published'], $inReview);
  }

  /**
   * Access results vary per account and carry their cacheability.
   */
  public function testAccessIsPerAccountAndCacheable(): void {
    $editor = $this->account($this->editor);
    $coauthor = $this->account($this->coauthor);
    $confirmed = $this->account($this->confirmed);

    $forEditor = $this->editorDraft->access('view', $editor, TRUE);
    $forCoauthor = $this->editorDraft->access('view', $coauthor, TRUE);
    $forConfirmed = $this->editorDraft->access('view', $confirmed, TRUE);
    self::assertTrue($forEditor->isAllowed());
    self::assertTrue($forCoauthor->isAllowed());
    self::assertFalse($forConfirmed->isAllowed());
    self::assertContains('user', $forCoauthor->getCacheContexts());
    self::assertContains('user.permissions', $forCoauthor->getCacheContexts());
    self::assertContains('user.permissions', $forEditor->getCacheContexts());
  }

  /**
   * The co-authors field is editable only through its own permissions.
   */
  public function testCoauthorsFieldAccess(): void {
    $editor = $this->account($this->editor);
    $coauthor = $this->account($this->coauthor);
    $field = $this->editorDraft->get('co_authors');
    $definition = $field->getFieldDefinition();
    self::assertTrue(node_co_authors_entity_field_access('edit', $definition, $editor, $field)->isForbidden());
    self::assertFalse($field->access('edit', $editor));
    self::assertFalse($this->editorDraft->get('co_authors')->access('edit', $coauthor));
    self::assertTrue($this->editorDraft->get('title')->access('edit', $editor));
  }

  /**
   * The real node form carries the no-JS transition select and the groups.
   */
  public function testPublicationFormKeepsCoreAndGroupsSecondaryFields(): void {
    $editor = $this->account($this->editor);
    $this->container->get('current_user')->setAccount($editor);
    $form = $this->container->get('entity.form_builder')
      ->getForm($this->editorDraft, 'default');

    self::assertArrayHasKey('moderation_state', $form);
    $select = $this->firstSelect($form['moderation_state']);
    self::assertNotNull($select);
    self::assertArrayHasKey('review', $select['#options']);
    self::assertArrayHasKey('published', $select['#options']);
    self::assertSame('draft', $select['#default_value']);

    require_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/RevistaFormHooks.php';
    $form_state = new FormState();
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertArrayHasKey('revista_secondary', $form);
    self::assertFalse($form['revista_secondary']['#open']);
    self::assertArrayHasKey('field_credits', $form['revista_secondary']);
    // The workflow widget is the owner's; it stays in the main flow.
    self::assertArrayNotHasKey('moderation_state', $form['revista_secondary']);
    self::assertContains('aculta_magazine/revista-form', $form['#attached']['library']);
  }

  /**
   * Finds the first select element below a form element.
   *
   * @param array $element
   *   Form element to inspect.
   *
   * @return array|null
   *   The select element, or NULL when there is none.
   */
  private function firstSelect(array $element): ?array {
    if (($element['#type'] ?? NULL) === 'select') {
      return $element;
    }
    foreach ($element as $key => $child) {
      if ($key !== '#' && is_array($child)) {
        $found = $this->firstSelect($child);
        if ($found !== NULL) {
          return $found;
        }
      }
    }
    return NULL;
  }

}
