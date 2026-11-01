<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\FiscalIdentityPolicy;
use Drupal\aculta_portal\Checkout\FiscalIdentityStatus;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Identidade fiscal do checkout: Loja e LMS exigem; Doações não.
 */
#[Group('aculta_portal')]
final class FiscalIdentityPolicyTest extends UnitTestCase {

  /**
   * Monta as configurações do checkout a partir de valores simulados.
   *
   * @param array<string, mixed> $values
   *   Valores da configuração `aculta_portal.checkout_settings`.
   */
  private function settings(array $values = []): CheckoutSettings {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    return new CheckoutSettings($factory);
  }

  /**
   * Cria um pedido simulado de um tipo.
   */
  private function order(string $bundle, array $items = []): OrderInterface {
    $order = $this->createMock(OrderInterface::class);
    $order->method('bundle')->willReturn($bundle);
    $order->method('getItems')->willReturn($items);
    return $order;
  }

  /**
   * Cria um item de pedido com variação de curso e preço base.
   */
  private function courseItem(?Price $price, string $variationBundle = 'curso'): OrderItemInterface {
    $variation = $this->createMock(ProductVariationInterface::class);
    $variation->method('bundle')->willReturn($variationBundle);
    $variation->method('getPrice')->willReturn($price);
    $item = $this->createMock(OrderItemInterface::class);
    $item->method('getPurchasedEntity')->willReturn($variation);
    return $item;
  }

  /**
   * Cria um serviço de documento simulado.
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
   * Verifica que Loja e LMS exigem documento e Doações não.
   */
  public function testLojaLmsExigemDocumentoDoacoesDispensadas(): void {
    $policy = new FiscalIdentityPolicy($this->settings());
    $this->assertFalse($policy->requiresDocument($this->order('default')), 'Loja não exige (CPF só no LMS).');
    $this->assertTrue($policy->requiresDocument($this->order('curso')), 'LMS exige.');
    $this->assertFalse($policy->requiresDocument($this->order('doacao')), 'Doação não exige.');
    $this->assertFalse($policy->requiresDocument($this->order('assinatura')), 'Assinatura não exige.');
  }

  /**
   * Verifica que os tipos de pedido configurados são reconhecidos.
   */
  public function testConfiguredTypesAreRecognized(): void {
    $policy = new FiscalIdentityPolicy($this->settings(['shop_order_type' => 'loja', 'lms_order_type' => 'aula']));
    $this->assertSame(['loja', 'aula', 'curso'], $policy->commercialOrderTypes());
    $this->assertTrue($policy->requiresDocument($this->order('aula')));
    $this->assertFalse($policy->requiresDocument($this->order('default')));
  }

  /**
   * Curso gratuito não exige documento, mesmo usando o tipo de pedido LMS.
   */
  public function testFreeLmsCourseDoesNotRequireDocument(): void {
    $order = $this->order('curso', [$this->courseItem(new Price('0.00', 'BRL'))]);
    $this->assertFalse((new FiscalIdentityPolicy($this->settings()))->requiresDocument($order));
  }

  /**
   * Preço base pago continua exigindo documento independente de descontos.
   */
  public function testPaidLmsCourseRequiresDocument(): void {
    $policy = new FiscalIdentityPolicy($this->settings());
    $freeOrder = $this->order('curso', [$this->courseItem(new Price('0.00', 'BRL'))]);
    $paidOrder = $this->order('curso', [$this->courseItem(new Price('125.00', 'BRL'))]);
    $this->assertFalse($policy->requiresDocument($freeOrder));
    $this->assertTrue($policy->requiresDocument($paidOrder));
  }

  /**
   * Composição ausente ou ambígua permanece sujeita à exigência fiscal.
   */
  public function testUnknownLmsOrderCompositionRequiresDocument(): void {
    $policy = new FiscalIdentityPolicy($this->settings());
    $this->assertTrue($policy->requiresDocument($this->order('curso')));
    $this->assertTrue($policy->requiresDocument($this->order('curso', [$this->courseItem(NULL)])));
    $this->assertTrue($policy->requiresDocument($this->order('curso', [$this->courseItem(new Price('0.00', 'BRL'), 'produto')])));
  }

  /**
   * Verifica que, sem serviço, a situação é indisponível.
   */
  public function testWithoutDocumentServiceFailsClosed(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), NULL))->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Unavailable, $check->status);
    $this->assertFalse($check->isSatisfied());
  }

  /**
   * Verifica que um serviço indisponível não consulta o valor salvo.
   */
  public function testUnavailableDoesNotQueryValue(): void {
    $document = $this->document(FALSE);
    $document->expects($this->never())->method('savedDocument');
    $check = (new FiscalIdentityPolicy($this->settings(), $document))->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Unavailable, $check->status);
  }

  /**
   * Verifica que um valor ilegível resulta em indisponível (falha fechada).
   */
  public function testUnreadableValueFailsClosed(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), $this->document(TRUE, NULL, new \RuntimeException('ilegível'))))
      ->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Unavailable, $check->status);
    $this->assertFalse($check->isSatisfied());
  }

  /**
   * Verifica que, sem documento cadastrado, a situação é ausente.
   */
  public function testNoDocumentSaved(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), $this->document(TRUE, NULL)))
      ->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Missing, $check->status);
    $this->assertNull($check->masked);
  }

  /**
   * Verifica que um documento válido expõe apenas a máscara.
   */
  public function testValidDocumentExposesMaskOnly(): void {
    $check = (new FiscalIdentityPolicy($this->settings(), $this->document(TRUE, '52998*********')))
      ->check($this->createMock(UserInterface::class));
    $this->assertSame(FiscalIdentityStatus::Valid, $check->status);
    $this->assertTrue($check->isSatisfied());
    $this->assertSame('52998*********', $check->masked);
  }

  /**
   * Verifica que o primeiro cadastro é gravado uma única vez.
   */
  public function testFirstFillSavesOnce(): void {
    $doc = $this->createMock(AccountDocumentInterface::class);
    $doc->method('canSave')->willReturn(TRUE);
    $doc->expects($this->once())->method('save');
    $this->assertTrue((new FiscalIdentityPolicy($this->settings(), $doc))->fill($this->createMock(UserInterface::class), '52998224725'));
  }

  /**
   * Verifica que uma gravação recusada não expõe detalhe.
   */
  public function testRefusedSaveLeaksNoDetail(): void {
    $doc = $this->createMock(AccountDocumentInterface::class);
    $doc->method('canSave')->willReturn(TRUE);
    $doc->method('save')->willThrowException(new \LogicException('Document already registered.'));
    $this->assertFalse((new FiscalIdentityPolicy($this->settings(), $doc))->fill($this->createMock(UserInterface::class), '52998224725'));
  }

  /**
   * Verifica que, sem cifra disponível, o cadastro não é preenchido.
   */
  public function testWithoutCipherDoesNotFill(): void {
    $doc = $this->createMock(AccountDocumentInterface::class);
    $doc->method('canSave')->willReturn(FALSE);
    $doc->expects($this->never())->method('save');
    $policy = new FiscalIdentityPolicy($this->settings(), $doc);
    $this->assertFalse($policy->canFill());
    $this->assertFalse($policy->fill($this->createMock(UserInterface::class), '52998224725'));
  }

}
