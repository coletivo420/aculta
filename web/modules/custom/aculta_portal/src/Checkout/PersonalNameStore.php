<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Nome e sobrenome da pessoa no checkout, gravados no perfil principal da conta (Meu Perfil).
 *
 * Quem tem conta vê os valores do perfil já preenchidos. Quem não tem conta usa o pedido, e o nome é copiado para a
 * conta criada na compra (ver PersonalNameCompletionSubscriber).
 */
final class PersonalNameStore {

  /**
   * Tamanho máximo de cada parte do nome (campo field_first_name e field_last_name).
   */
  public const MAX_LENGTH = 80;

  /**
   * Chave do pedido (order data) com o nome informado por quem não tem conta.
   */
  public const ORDER_DATA_KEY = 'aculta_personal_name';

  /**
   * Cria o armazenamento.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Nome e sobrenome do perfil principal da conta; vazios quando não há perfil ou valor.
   *
   * @return array{first: string, last: string}
   *   Partes do nome.
   */
  public function forAccount(AccountInterface $account): array {
    $storage = $this->entityTypeManager->getStorage('profile');
    $perfil = $account->isAuthenticated() ? PerfilDoPapel::principal($storage, $account) : NULL;
    return [
      'first' => $perfil !== NULL ? (string) $perfil->get('field_first_name')->value : '',
      'last' => $perfil !== NULL ? (string) $perfil->get('field_last_name')->value : '',
    ];
  }

  /**
   * Grava nome e sobrenome no perfil principal da conta, criando o perfil se ele não existir.
   */
  public function saveForAccount(AccountInterface $account, string $first, string $last): void {
    $storage = $this->entityTypeManager->getStorage('profile');
    $perfil = PerfilDoPapel::obter($storage, $account);
    $perfil->set('field_first_name', $first);
    $perfil->set('field_last_name', $last);
    $perfil->save();
  }

}
