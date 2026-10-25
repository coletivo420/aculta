<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Form;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Form\AccountOtherDataForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Retorno de Outros Dados: só caminho interno de checkout volta ao pedido.
 *
 * O link do pane de identidade fiscal traz `destination=/checkout/{pedido}`.
 * O formulário grava o documento e, no retorno, aceita esse caminho e recusa
 * qualquer outro destino.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class AccountOtherDataFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Envia o formulário com o destino informado e devolve o estado resultante.
   *
   * @param string|null $destination
   *   Valor do parâmetro `destination` na consulta; NULL para ausente.
   */
  private function submit(?string $destination): FormState {
    $document = $this->createMock(AccountDocumentInterface::class);
    $document->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($this->createMock(UserInterface::class));
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('user')->willReturn($storage);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);

    $request = new Request();
    if ($destination !== NULL) {
      $request->query->set('destination', $destination);
    }
    $requests = new RequestStack();
    $requests->push($request);

    $form = new AccountOtherDataForm(
      $document,
      $account,
      $entities,
      $this->createMock(MessengerInterface::class),
      $requests,
    );
    $state = new FormState();
    $state->set('canonical', '52998224725');
    $form_array = [];
    $form->submitForm($form_array, $state);
    return $state;
  }

  /**
   * Verifica que o destino de checkout do pedido é honrado.
   */
  public function testCheckoutDestinationReturnsToOrder(): void {
    $redirect = $this->submit('/checkout/5')->getRedirect();
    $this->assertInstanceOf(Url::class, $redirect);
    // Sem o Commerce habilitado no teste, o caminho não é roteado: o retorno
    // usa a raiz do site. Em produção o PathValidator resolve a rota.
    $this->assertFalse($redirect->isRouted());
    $this->assertSame('base:checkout/5', $redirect->getUri());
  }

  /**
   * Verifica que destinos fora do checkout voltam para Outros Dados.
   */
  #[DataProvider('destinosRecusados')]
  public function testRejectedDestinationStaysInOtherData(?string $destination): void {
    $redirect = $this->submit($destination)->getRedirect();
    $this->assertInstanceOf(Url::class, $redirect);
    $this->assertTrue($redirect->isRouted());
    $this->assertSame('aculta_portal.my_data_other', $redirect->getRouteName());
  }

  /**
   * Destinos que não podem ser usados como retorno.
   */
  public static function destinosRecusados(): array {
    return [
      'URL externa' => ['https://exemplo.org/checkout/5'],
      'relativa de protocolo' => ['//exemplo.org/checkout/5'],
      'fora do checkout' => ['/admin/config'],
      'sem destino' => [NULL],
      'vazio' => [''],
    ];
  }

}
