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
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
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
   * Último pedido criado por `pane()`.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface|null
   */
  private $lastOrder;

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
   * @param bool $available
   *   Se o serviço reporta a cifra disponível.
   */
  private function document(array $saved, bool $canSave = TRUE, bool $available = TRUE): AccountDocumentInterface {
    $doc = $this->createMock(AccountDocumentInterface::class);
    $doc->method('isAvailable')->willReturn($available);
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
   * @param \Drupal\aculta_portal\Checkout\CheckoutSettings|null $settings
   *   Configuração de checkout alternativa; sem ela, a real (config ausente).
   */
  private function pane(string $orderType, AccountDocumentInterface $doc, ?CheckoutSettings $settings = NULL): FiscalIdentityPane {
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
    $this->lastOrder = $order;
    $flow = CheckoutFlow::create([
      'id' => 'teste_' . $orderType,
      'label' => 'Teste',
      'plugin' => 'multistep_default',
      'configuration' => [],
    ])->getPlugin();
    $flow->setOrder($order);
    $policy = new FiscalIdentityPolicy(
      $settings ?? new CheckoutSettings($this->container->get('config.factory')),
      $doc,
    );
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
   * Monta o formulário do pane e devolve os elementos gerados.
   *
   * @param \Drupal\aculta_portal\Plugin\Commerce\CheckoutPane\FiscalIdentityPane $pane
   *   Pane a montar.
   *
   * @return array<string, mixed>
   *   Elementos do formulário do pane.
   */
  private function build(FiscalIdentityPane $pane): array {
    $state = new FormState();
    $form = [];
    return $pane->buildPaneForm([], $state, $form);
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

  /**
   * Verifica que o link para Outros Dados devolve ao checkout deste pedido.
   */
  public function testMissingWithCipherOffersOtherDataLink(): void {
    $pane = $this->pane('default', $this->document([NULL]));
    $form = $this->build($pane);
    $this->assertArrayHasKey('document', $form);
    $this->assertArrayHasKey('other_data', $form);
    $this->assertSame('link', $form['other_data']['#type']);
    $url = $form['other_data']['#url'];
    // `internal:` é normalizado para `base:` pelo core: caminho relativo à
    // raiz do site, sem depender do provedor de rotas de aculta_portal.
    $this->assertFalse($url->isRouted());
    $this->assertSame('base:dados/outros-dados', $url->getUri());
    $query = $url->getOption('query');
    $this->assertSame(
      '/checkout/' . $this->lastOrder->id(),
      $query['destination'],
    );
    $this->assertSame(
      '/dados/outros-dados?destination=/checkout/' . $this->lastOrder->id(),
      $url->toString(),
    );
  }

  /**
   * Verifica que, sem cifra, não há link: falha fechada mostra só o aviso.
   */
  public function testMissingWithoutCipherHasNoLink(): void {
    $pane = $this->pane('default', $this->document([NULL], FALSE));
    $form = $this->build($pane);
    $this->assertArrayNotHasKey('document', $form);
    $this->assertArrayNotHasKey('other_data', $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertSame([], $form['status']['#markup']->getArguments());
  }

  /**
   * Verifica que o documento válido aparece mascarado e sem campo editável.
   */
  public function testValidDocumentIsMaskedAndReadOnly(): void {
    $doc = $this->document([self::MASCARA]);
    $doc->expects($this->never())->method('save');
    $pane = $this->pane('default', $doc);
    $form = $this->build($pane);
    $this->assertArrayNotHasKey('document', $form);
    $this->assertArrayNotHasKey('other_data', $form);
    $text = (string) $form['status']['#markup'];
    $this->assertStringContainsString(self::MASCARA, $text);
    $this->assertStringContainsString('Não pode ser alterado', $text);
  }

  /**
   * Verifica que o checkout não grava quando o documento já está cadastrado.
   */
  public function testCheckoutDoesNotSaveExistingDocument(): void {
    $doc = $this->document([self::MASCARA, self::MASCARA]);
    $doc->expects($this->never())->method('save');
    $state = $this->validate(
      $this->pane('curso', $doc),
      ['document' => '52998224725'],
    );
    $this->assertFalse($state->hasAnyErrors());
  }

  /**
   * Verifica que cifra indisponível falha fechada, sem gravar.
   */
  public function testUnavailableFailsClosedAndDoesNotSave(): void {
    $doc = $this->document([NULL], TRUE, FALSE);
    $doc->expects($this->never())->method('save');
    $pane = $this->pane('default', $doc);
    $form = $this->build($pane);
    $this->assertArrayNotHasKey('document', $form);
    $this->assertArrayHasKey('status', $form);
    $state = $this->validate($pane, ['document' => '52998224725']);
    $this->assertTrue($state->hasAnyErrors());
  }

  /**
   * Verifica que o tipo de pedido do LMS vem da configuração.
   */
  public function testLmsTypeComesFromConfiguration(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['lms_order_type', NULL, 'matricula'],
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with(CheckoutSettings::CONFIG)
      ->willReturn($config);
    $settings = new CheckoutSettings($factory);
    $this->assertSame(['matricula', 'curso'], $settings->lmsOrderTypes());

    $doc = $this->document([self::MASCARA, self::MASCARA, self::MASCARA]);
    $this->assertTrue($this->pane('matricula', $doc, $settings)->isVisible());
    $this->assertTrue($this->pane('curso', $doc, $settings)->isVisible());
    $this->assertFalse($this->pane('doacao', $doc, $settings)->isVisible());
  }

}
