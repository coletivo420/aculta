<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Form\MailPolicyForm;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tela de política (F5): valida e grava o padrão global, a sobrescrita por tipo e o limite por transporte.
 */
#[CoversClass(MailPolicyForm::class)]
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailPolicyFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'aculta_mailer', 'aculta_mailer_test'];

  /**
   * Recusa e-mail de resposta inválido e grava os níveis válidos.
   */
  public function testValidatesAndSavesPolicy(): void {
    $form = MailPolicyForm::create($this->container);
    $typeId = $this->container->get(MailTypeRegistry::class)->ids()[0];

    $bad = new FormState();
    $built = $form->buildForm([], $bad);
    $bad->setValue(['global', 'reply_to'], 'nao-e-email');
    $form->validateForm($built, $bad);
    $this->assertArrayHasKey('reply_to', $bad->getErrors());

    $good = new FormState();
    $built = $form->buildForm([], $good);
    $good->setValue(['global', 'transport'], 'brevo');
    $good->setValue(['global', 'reply_to'], 'resposta@example.invalid');
    $good->setValue(['global', 'priority'], '2');
    $good->setValue(['global', 'skip'], FALSE);
    $good->setValue(['types', $typeId, 'skip'], TRUE);
    $good->setValue(['rate_limit', 'smtp', 'day'], '50');
    $form->validateForm($built, $good);
    $this->assertSame([], $good->getErrors());
    $form->submitForm($built, $good);

    $config = $this->config('aculta_mailer.policy');
    $this->assertSame(['transport' => 'brevo', 'reply_to' => 'resposta@example.invalid', 'priority' => 2], $config->get('global'));
    $this->assertTrue($config->get('types')[$typeId]['skip']);
    $this->assertSame(['day' => 50], $config->get('rate_limit')['smtp']);
  }

}
