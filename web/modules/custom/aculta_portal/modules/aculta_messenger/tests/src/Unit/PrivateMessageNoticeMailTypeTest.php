<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Mail\PrivateMessageNoticeMailType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the privacy-minimal private-message Mailer template. */
#[CoversClass(PrivateMessageNoticeMailType::class)]
#[Group('aculta_messenger')]
final class PrivateMessageNoticeMailTypeTest extends UnitTestCase {

  /**
   * Exposes only a generic summary and a link to the member's inbox.
   */
  public function testTemplateExposesOnlyGenericSummaryAndAccountLink(): void {
    $type = new PrivateMessageNoticeMailType($this->getStringTranslationStub());

    self::assertSame('private_message_notice', $type->id());
    self::assertSame(['summary', 'url'], array_keys($type->variables()));
    self::assertStringNotContainsString('conteúdo', mb_strtolower($type->defaultBody()));
    self::assertStringNotContainsString('nome', mb_strtolower($type->defaultBody()));
    self::assertStringContainsString('{{ url }}', $type->defaultBody());
  }

}
