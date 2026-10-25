<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Email\EmailConfirmationLinkRegenerator;
use Drupal\aculta_portal\Email\EmailConfirmationRequestMailType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * O Portal gera o pedido novo só para a confirmação de e-mail.
 */
#[Group('aculta_portal')]
final class EmailConfirmationLinkRegeneratorTest extends UnitTestCase {

  /**
   * Só o tipo de confirmação de e-mail é gerado pelo Portal.
   */
  public function testSupportsOnlyConfirmationRequest(): void {
    $regenerator = (new \ReflectionClass(EmailConfirmationLinkRegenerator::class))->newInstanceWithoutConstructor();
    $this->assertTrue($regenerator->supports(EmailConfirmationRequestMailType::ID));
    $this->assertFalse($regenerator->supports('registration_existing_mail'));
    $this->assertFalse($regenerator->supports('aculta_admin_alert'));
  }

}
