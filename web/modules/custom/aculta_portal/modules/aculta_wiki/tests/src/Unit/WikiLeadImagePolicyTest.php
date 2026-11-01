<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_portal\Form\UserUploadSettingsForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Garante que a imagem de destaque usa a política central de upload.
 */
#[Group('aculta_wiki')]
final class WikiLeadImagePolicyTest extends UnitTestCase {

  /**
   * O consumidor "wiki" está exportado e herda a política global.
   */
  public function testExportedConfigCarriesTheWikiConsumer(): void {
    $config = $this->configFile('config/sync/aculta_portal.user_upload.yml');

    self::assertArrayHasKey('wiki', $config['consumers']);
    self::assertTrue($config['consumers']['wiki']['inherit_global']);
    self::assertSame($config['allowed_extensions'], $config['consumers']['wiki']['allowed_extensions']);
    self::assertSame($config['max_size_mb'], $config['consumers']['wiki']['max_size_mb']);
  }

  /**
   * O schema aceita as três chaves que o consumidor grava.
   */
  public function testSchemaDescribesTheWikiConsumer(): void {
    $schema = $this->configFile('web/modules/custom/aculta_portal/config/schema/aculta_portal.schema.yml');
    $mapping = $schema['aculta_portal.user_upload']['mapping']['consumers']['mapping']['wiki']['mapping'];

    self::assertSame('boolean', $mapping['inherit_global']['type']);
    self::assertSame('string', $mapping['allowed_extensions']['type']);
    self::assertSame('integer', $mapping['max_size_mb']['type']);
  }

  /**
   * O formulário administrativo edita a aba do consumidor "wiki".
   */
  public function testSettingsFormOffersTheWikiTab(): void {
    $constant = (new \ReflectionClass(UserUploadSettingsForm::class))->getReflectionConstant('CONSUMERS');

    self::assertIsArray($constant->getValue());
    self::assertContains('wiki', $constant->getValue());
  }

  /**
   * Reads one YAML file from the repository root.
   *
   * @return array<string, mixed>
   *   The decoded file.
   */
  private function configFile(string $path): array {
    $root = dirname(__DIR__, 3);
    $repositoryRoot = dirname($root, 6);

    return Yaml::parseFile($repositoryRoot . '/' . $path);
  }

}
