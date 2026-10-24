<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms_messenger\Mail\LmsTransactionalNoticeMailType;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the type contract for LMS transactional email templates.
 */
#[CoversClass(LmsTransactionalNoticeMailType::class)]
#[Group('aculta_lms_messenger')]
final class LmsTransactionalNoticeMailTypeTest extends UnitTestCase {

  /**
   * Declares only the scalar variables needed by the template.
   */
  public function testDeclaresOnlyApprovedScalarTemplateVariables(): void {
    $translator = $this->createMock(TranslationInterface::class);
    $translator->method('translate')->willReturnCallback(static fn(string $string): string => $string);
    $type = new LmsTransactionalNoticeMailType($translator);

    self::assertSame('lms_transactional_notice', $type->id());
    self::assertSame(['summary', 'url'], array_keys($type->variables()));
    self::assertStringContainsString('{{ summary }}', $type->defaultBody());
    self::assertStringContainsString('{{ url }}', $type->defaultBody());
  }

}
