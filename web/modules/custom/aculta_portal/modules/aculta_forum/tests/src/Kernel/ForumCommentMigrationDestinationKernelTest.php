<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Plugin\migrate\destination\ForumCommentPost;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the migration destination rejects invalid Comment rows before saving.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCommentMigrationDestinationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'file',
    'image',
    'media',
    'migrate',
    'aculta_forum_post_test',
  ];

  /**
   * Destination under test.
   */
  private ForumCommentPost $destination;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'file', 'image', 'media']);

    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->destination = new ForumCommentPost(
      [],
      'aculta_forum_comment_post',
      [],
      $this->createMock(MigrationInterface::class),
      $entity_type_manager->getStorage('aculta_forum_post'),
      ['aculta_forum_post'],
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('account_switcher'),
      $this->container->get('entity_type.bundle.info'),
      $entity_type_manager,
    );
  }

  /**
   * A row without account or valid historical author fails closed.
   */
  public function testRowWithoutAuthorIsRejected(): void {
    $this->assertRejected($this->row(['uid' => 0, 'legacy_author_id' => 0]), 'autoria histórica');
  }

  /**
   * The source must include its default translation.
   */
  public function testMissingDefaultTranslationIsRejected(): void {
    $this->assertRejected($this->row(['langcode' => 'en', 'translations' => []]), 'tradução padrão');
  }

  /**
   * A rejected row may not also be published.
   */
  public function testRejectedAndPublishedIsRejected(): void {
    $this->assertRejected($this->row(['rejected' => 1]), 'publicação com rejeição');
  }

  /**
   * Empty bodies and unsupported formats are rejected.
   */
  public function testBodyContractIsEnforced(): void {
    $this->assertRejected($this->row(['translations' => $this->translations(['body_value' => ''])]), 'corpo vazio');
    $this->assertRejected($this->row(['translations' => $this->translations(['body_format' => 'basic_html'])]), 'formato de texto incompatível');
  }

  /**
   * Embeds must have a UUID and resolve to a destination entity.
   */
  public function testEmbedsMustResolve(): void {
    $embed = '<drupal-media data-entity-type="media" data-entity-uuid=""></drupal-media>';
    $this->assertRejected($this->row(['translations' => $this->translations(['body_value' => $embed])]), 'embed media sem UUID');

    $missing = '<drupal-media data-entity-type="media" data-entity-uuid="00000000-0000-0000-0000-000000000000"></drupal-media>';
    $this->assertRejected($this->row(['translations' => $this->translations(['body_value' => $missing])]), 'embed(s) media sem entidade');
  }

  /**
   * Asserts one invalid row throws with the expected message fragment.
   */
  private function assertRejected(Row $row, string $fragment): void {
    try {
      $this->destination->import($row);
      self::fail('A linha inválida foi aceita pela migration.');
    }
    catch (MigrateException $exception) {
      self::assertStringContainsString($fragment, $exception->getMessage());
    }
  }

  /**
   * Builds a source row with a valid base contract.
   *
   * @param array<string, mixed> $overrides
   *   Source values that replace the defaults.
   */
  private function row(array $overrides = []): Row {
    $source = $overrides + [
      'cid' => 10,
      'uid' => 7,
      'legacy_author_id' => 0,
      'langcode' => 'en',
      'rejected' => 0,
      'translations' => $this->translations(),
    ];
    return new Row($source, ['cid' => 10], TRUE);
  }

  /**
   * Builds one valid source translation.
   *
   * @param array<string, mixed> $overrides
   *   Translation values that replace the defaults.
   */
  private function translations(array $overrides = []): array {
    return [
      'en' => $overrides + [
        'subject' => 'Assunto',
        'body_value' => 'Corpo da resposta',
        'body_format' => 'forum',
        'published' => 1,
      ],
    ];
  }

}
