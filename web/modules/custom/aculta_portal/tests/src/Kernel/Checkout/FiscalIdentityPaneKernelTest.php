<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Checkout;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\FiscalIdentityPolicy;
use Drupal\aculta_portal\Plugin\Commerce\CheckoutPane\FiscalIdentityPane;
use Drupal\commerce_checkout\Entity\CheckoutFlow;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\Core\Form\FormState;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Pane de identidade fiscal com o container do Commerce, sem habilitar aculta_portal.
 *
 * As classes do Portal são instanciadas diretamente. Recebem o CheckoutSettings
 * real (configuração ausente = padrões) e um documento simulado.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class FiscalIdentityPaneKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_checkout'];

  /**
   * Máscara simulada de um documento válido.
   */
  private const MASCARA = '529********';

  /**
   * Conta do comprador.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->user = User::create(['name' => 'comprador', 'mail' => 'comprador@example.test', 'status' => 1]);
    $this->user->save();
  }

  /**
   * Cria um serviço de documento simulado com a sequência de situações informada.
   *
   * @param array<int, string|null> $saved
   *   Valores devolvidos por `savedDocument()`, em ordem de chamada.
   * @param bool $canSave
   *   Se o serviço reporta que pode gravar o primeiro documento.
   */
  private function document(array $saved, bool $canSave = TRUE): AccountDocumentInterface {
    $doc = $this->createMock(AccountDocumentInterface::class);
    $doc->method('isAvailable')->willReturn(TRUE);
    $doc->method('canSave')->willReturn($canSave);
    $doc->method('canonicalize')->willReturnCallback(
      static fn(string $in): ?string => preg_match('/^\d{11}$/', $in) ? $in : NULL,
    );
    $doc->method('savedDocument')->willReturnOnConsecutiveCalls(...$saved);
    return $doc;
  }

  /**
   * Cria o pane para um tipo de pedido, com o serviço de documento informado.
   *
   * @param string $orderType
   *   Tipo do pedido de teste.
   * @param \Drupal\aculta_portal\Account\AccountDocumentInterface $doc
   *   Serviço de documento simulado.
   */
  private function pane(string $orderType, AccountDocumentInterface $doc): FiscalIdentityPane {
    if (!OrderType::load($orderType)) {
      OrderType::create(['id' => $orderType, 'label' => $orderType, 'workflow' => 'order_default'])->save();
    }
    $order = Order::create([
      'type' => $orderType,
      'store_id' => $this->store->id(),
      'uid' => (int) $this->user->id(),
      'state' => 'draft',
    ]);
    $order->save();
    $flow = CheckoutFlow::create([
      'id' => 'teste_' . $orderType,
      'label' => 'Teste',
      'plugin' => 'multistep_default',
      'configuration' => [],
    ])->getPlugin();
    $flow->setOrder($order);
    $policy = new FiscalIdentityPolicy(new CheckoutSettings($this->container->get('config.factory')), $doc);
    $this->container->set('aculta_portal.fiscal_identity_policy', $policy);
    return FiscalIdentityPane::create($this->container, [], 'aculta_fiscal_identity', [
      'id' => 'aculta_fiscal_identity',
      'label' => 'Identidade fiscal',
      'default_step' => 'review',
      'wrapper_element' => 'fieldset',
    ], $flow);
  }

  /**
   * Executa a validação do pane com a entrada informada e devolve o estado do formulário.
   *
   * @param \Drupal\aculta_portal\Plugin\Commerce\CheckoutPane\FiscalIdentityPane $pane
   *   Pane a validar.
   * @param array<string, string> $input
   *   Valores enviados pelo usuário, indexados pelo nome do elemento.
   */
  private function validate(FiscalIdentityPane $pane, array $input = []): FormState {
    $state = new FormState();
    $form = ['#parents' => ['aculta_fiscal_identity']];
    foreach ($input as $key => $value) {
      $state->setValue(['aculta_fiscal_identity', $key], $value);
    }
    $pane->validatePaneForm($form, $state, $form);
    return $state;
  }

  /**
   * Verifica que o pane aparece somente para tipos comerciais.
   */
  public function testVisibleOnlyForCommercialTypes(): void {
    $doc = $this->document([self::MASCARA, self::MASCARA, self::MASCARA]);
    $this->assertTrue($this->pane('default', $doc)->isVisible());
    $this->assertTrue($this->pane('curso', $doc)->isVisible());
    $this->assertFalse($this->pane('doacao', $doc)->isVisible());
  }

  /**
   * Verifica que documento válido passa sem entrada do usuário.
   */
  public function testValidDocumentPassesWithoutInput(): void {
    $this->assertFalse($this->validate($this->pane('curso', $this->document([self::MASCARA])))->hasAnyErrors());
  }

  /**
   * Verifica que, sem documento e com cifra, exige entrada.
   */
  public function testWithoutDocumentAndCipherRequiresInput(): void {
    $this->assertTrue($this->validate($this->pane('default', $this->document([NULL])))->hasAnyErrors());
  }

  /**
   * Verifica que o primeiro cadastro na compra é gravado uma única vez e valida.
   */
  public function testFirstRegistrationSavesOnce(): void {
    $doc = $this->document([NULL, self::MASCARA]);
    $doc->expects($this->once())->method('save');
    $state = $this->validate($this->pane('default', $doc), ['document' => '52998224725']);
    $this->assertFalse($state->hasAnyErrors());
  }

  /**
   * Verifica que entrada inválida não grava.
   */
  public function testInvalidInputDoesNotSave(): void {
    $doc = $this->document([NULL]);
    $doc->expects($this->never())->method('save');
    $this->assertTrue($this->validate($this->pane('default', $doc), ['document' => 'abc'])->hasAnyErrors());
  }

  /**
   * Verifica que, sem cifra, falha fechada.
   */
  public function testWithoutCipherFailsClosed(): void {
    $doc = $this->document([NULL], FALSE);
    $this->assertTrue($this->validate($this->pane('default', $doc), ['document' => '52998224725'])->hasAnyErrors());
  }

}
