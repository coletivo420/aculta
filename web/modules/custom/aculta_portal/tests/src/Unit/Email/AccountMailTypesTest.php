<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Email;

use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_portal\Email\AccountMailRouter;
use Drupal\aculta_portal\Email\AccountPasswordResetMailType;
use Drupal\aculta_portal\Email\AccountRegisterAdminMailType;
use Drupal\aculta_portal\Email\AccountRegisterMailType;
use Drupal\aculta_portal\Email\EmailConfirmationRequestMailType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Textos de conta aprovados em 2026-10-20: marcadores declarados, sem sobra, e chaves do Core mapeadas.
 */
#[Group('aculta_portal')]
final class AccountMailTypesTest extends UnitTestCase {

  /**
   * Cada tipo só usa variáveis que declara, e o assunto e o corpo não ficam vazios.
   */
  public function testPlaceholdersAreDeclaredVariables(): void {
    $translation = $this->getStringTranslationStub();
    $types = [
      new AccountPasswordResetMailType($translation),
      new AccountRegisterMailType($translation),
      new AccountRegisterAdminMailType($translation),
      new EmailConfirmationRequestMailType($translation),
    ];
    foreach ($types as $type) {
      $declared = array_keys($type->variables());
      $used = [];
      preg_match_all(MailTemplateRenderer::PLACEHOLDER, $type->defaultSubject() . "\n" . $type->defaultBody(), $m);
      $used = array_unique($m[1]);
      $this->assertNotSame('', trim($type->defaultSubject()), $type->id());
      $this->assertNotSame('', trim($type->defaultBody()), $type->id());
      $this->assertSame([], array_values(array_diff($used, $declared)), $type->id() . ': marcador sem variável declarada.');
    }
  }

  /**
   * As três chaves do Core apontam para tipos com a mesma ID registrada.
   */
  public function testCoreKeysMapToRegisteredTypes(): void {
    $ids = [
      AccountPasswordResetMailType::ID,
      AccountRegisterMailType::ID,
      AccountRegisterAdminMailType::ID,
    ];
    $this->assertSame($ids, array_values(AccountMailRouter::TYPES));
    $this->assertSame(['password_reset', 'register_no_approval_required', 'register_admin_created'], array_keys(AccountMailRouter::TYPES));
  }

}
