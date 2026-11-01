<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Policy\MailPolicyResolver;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Política de envio: hierarquia global → tipo, validação dos ajustes, suspensão, remetente e prioridade.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailPolicyKernelTest extends KernelTestBase {

  /**
   * Módulos do teste.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_mailer', 'aculta_mailer_test'];

  /**
   * Correlation ID fixo usado nas mensagens de teste.
   */
  private const CORRELATION = '0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_mail_log');
  }

  /**
   * O ajuste do tipo prevalece sobre o global; valor inválido é ignorado.
   */
  public function testTypeOverridesGlobalAndInvalidValuesAreIgnored(): void {
    $this->policy([
      'global' => ['priority' => 3, 'reply_to' => 'contato@example.invalid'],
      'types' => ['aculta_mailer_test_message' => ['transport' => 'smtp2go', 'priority' => 9]],
    ]);
    $policy = $this->container->get(MailPolicyResolver::class)->resolve('aculta_mailer_test_message');

    $this->assertSame('smtp2go', $policy->transport);
    $this->assertSame('contato@example.invalid', $policy->replyTo);
    $this->assertSame(3, $policy->priority, 'prioridade 9 é inválida e não substitui a global.');
    $this->assertFalse($policy->skip);
  }

  /**
   * Ajustes recusam valores fora do contrato.
   */
  public function testAdjustersValidate(): void {
    $manager = $this->container->get('plugin.manager.aculta_mailer_adjuster');
    $this->assertFalse($manager->createInstance('transport')->validate('hostinger'));
    $this->assertTrue($manager->createInstance('transport')->validate('smtp2go'));
    $this->assertFalse($manager->createInstance('reply_to')->validate('nao-e-email'));
    $this->assertFalse($manager->createInstance('priority')->validate(0));
    $this->assertTrue($manager->createInstance('skip')->validate(TRUE));
  }

  /**
   * Suspensão: não envia, não cria retry e registra o envio como cancelado.
   */
  public function testSkipCancelsWithoutSending(): void {
    $this->policy(['types' => ['aculta_mailer_test_message' => ['skip' => TRUE]]]);
    $result = $this->container->get('aculta_mailer.mailer')->deliver($this->message());

    $this->assertTrue($result->cancelled);
    $this->assertFalse($result->sent);
    $this->assertEmpty($this->container->get('state')->get('system.test_mail_collector', []));
    $logs = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')->loadByProperties(['correlation_id' => self::CORRELATION]);
    $this->assertSame('cancelled', reset($logs)->get('status')->value);
  }

  /**
   * Remetente de resposta e prioridade chegam ao envio.
   */
  public function testReplyToAndPriorityReachTheMessage(): void {
    $this->policy(['global' => ['reply_to' => 'contato@example.invalid', 'priority' => 2]]);
    $this->assertTrue($this->container->get('aculta_mailer.mailer')->send($this->message()));

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $last = end($mails);
    $this->assertSame('contato@example.invalid', $last['reply-to'] ?? NULL);
    $this->assertSame('2', $last['headers']['X-Priority'] ?? NULL);
  }

  /**
   * Cota diária esgotada: a mensagem segue pelo transporte de contingência, e a cota vem do próprio registro.
   */
  public function testExhaustedQuotaUsesFallbackTransport(): void {
    $this->config('aculta_mailer.policy')->set('quota', ['brevo' => 1])->save();
    $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')->create([
      'correlation_id' => 'a0000000-0000-4000-8000-000000000020',
      'type' => 'aculta_mailer_test_message',
      'recipient_hash' => str_repeat('e', 64),
      'status' => 'sent',
      'transport' => 'brevo',
      'created' => time(),
    ])->save();

    $this->assertTrue($this->container->get('aculta_mailer.mailer')->deliver($this->message(), 'brevo')->sent);
    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertSame('smtp2go', end($mails)['headers']['X-Transport'] ?? NULL);
  }

  /**
   * Grava a configuração da política de teste.
   *
   * @param array $values
   *   Valores de `global` e `types`.
   */
  private function policy(array $values): void {
    $config = $this->config('aculta_mailer.policy');
    $config->set('global', $values['global'] ?? []);
    $config->set('types', $values['types'] ?? []);
    $config->save();
  }

  /**
   * Mensagem de teste com contexto escalar.
   */
  private function message(): MailMessage {
    $context = ['name' => 'Maria', 'link' => 'https://example.invalid/x'];
    return new MailMessage('aculta_mailer_test_message', 'destino@example.invalid', 'pt-br', $context, self::CORRELATION);
  }

}
