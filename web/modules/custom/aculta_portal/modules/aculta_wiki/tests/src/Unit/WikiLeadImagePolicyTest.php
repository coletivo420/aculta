<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_file_uploader\Form\UploadSettingsForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Garante que a imagem de destaque usa a política central de upload.
 */
#[Group('aculta_wiki')]
final class WikiLeadImagePolicyTest extends UnitTestCase {

  /**
   * O painel de upload por integração oferece a aba do consumidor "wiki".
   */
  public function testSettingsFormOffersTheWikiTab(): void {
    $constant = (new \ReflectionClass(UploadSettingsForm::class))->getReflectionConstant('CONSUMERS');

    self::assertIsArray($constant->getValue());
    self::assertArrayHasKey('wiki', $constant->getValue());
  }


}
