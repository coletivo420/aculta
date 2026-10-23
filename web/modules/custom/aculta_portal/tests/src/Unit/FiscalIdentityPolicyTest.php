<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\FiscalIdentityPolicy;
use Drupal\aculta_portal\Checkout\FiscalIdentityStatus;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Política de identidade fiscal do checkout comercial: Loja e LMS exigem, Doações não.
 */
#[Group('aculta_portal')]
final class FiscalIdentityPolicyTest extends UnitTestCase {

  /**
   * @param array<string, mixed> $values
   *   */
  private function settings(array $values = []): CheckoutSettings {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    return new CheckoutSettings($factory);
  }

  /**
   *
   */
  private function order(string $bundle): OrderInterface {
    $order = $this->createMock(OrderInterface::class);
    $order->method('bundle')->willReturn($bundle);
    return $order;
  }

  /**
   *
   */
  private function document(?bool $available = TRUE, ?string $saved = NULL, ?\Throwable $throws = NULL): AccountDocumentInterface {
    $document = $this->createMock(AccountDocumentInterface::class);
    $document->method('isAvailable')->willReturn((bool) $available);
    if ($throws !== NULL) {
      $document->method('savedDocument')->willThrowException($throws);
    }
    else {
      $document->method('savedDocument')->willReturn($saved);
    }
    return $document;
  }

  /**
   *
   */
  public function testLojaLmsExigemDocumentoDoacoesDispensadas(): void {
    $policy = new FiscalIdentityPolicy($this->settings());
    $this->assertTrue($policy->requiresDocument($this->order('default')), 'Loja exige.');
    $this->assertTrue($policy->requiresDocument($this->order('curso')), 'LMS exige.');
    $this->assertFalse($policy->requiresDocument($this->order('doacao')), 'Doação não exige.');
    $this->assertFalse($policy->requiresDocument($this->order('assinatura')), 'Assinatura de doação não exige.');
  }

  /**
   *
   */
  public function testTiposConfiguradosSaoReconhecidos(): void {
    $policy = new FiscalIdentityPolicy($this->settings(['shop_order_type' => 'loja', 'lms_order_type' => 'aula']));
    $this->assertSame(['loja', 'aula', 'curso'], $policy->commercialOrderTypes());
    $this->assertTrue($policy->requiresDocument($this->order('aula')));
    $this->assertFalse($policy->requiresDocument($this->order('default')));
  }

  /**
   *
   */
  public function testSemServicoDeDocumentoFalhaFechada(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), NULL))->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Unavailable, $check->status);
    $this->assertFalse($check->isSatisfied());
  }

  /**
   *
   */
  public function testIndisponivelNaoConsultaValor(): void {
    $document = $this->document(FALSE);
    $document->expects($this->never())->method('savedDocument');
    $check = (new FiscalIdentityPolicy($this->settings(), $document))->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Unavailable, $check->status);
  }

  /**
   *
   */
  public function testValorIlegivelFalhaFechada(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), $this->document(TRUE, NULL, new \RuntimeException('ilegível'))))
      ->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Unavailable, $check->status);
    $this->assertFalse($check->isSatisfied());
  }

  /**
   *
   */
  public function testSemDocumentoCadastrado(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), $this->document(TRUE, NULL)))
      ->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Missing, $check->status);
    $this->assertNull($check->masked);
  }

  /**
   *
   */
  public function testDocumentoValidoExpoeSoMascara(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), $this->document(TRUE, '52998*********')))
      ->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Valid, $check->status);
    $this->assertTrue($check->isSatisfied());
    $this->assertSame('52998*********', $check->masked);
  }

}
