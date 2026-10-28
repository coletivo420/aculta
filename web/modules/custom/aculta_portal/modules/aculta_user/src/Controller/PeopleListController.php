<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Read-only, data-minimized administration list of account holders. */
final class PeopleListController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /** Lists only display name, nickname, and account status. */
  public function listing(): array {
    $userStorage = $this->entityTypeManager->getStorage('user');
    $profileStorage = $this->entityTypeManager->getStorage('profile');
    $userIds = $userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->sort('uid', 'DESC')
      ->pager(50)
      ->execute();

    $rows = [];
    foreach ($userStorage->loadMultiple($userIds) as $account) {
      if (!$account instanceof UserInterface) {
        continue;
      }
      $profile = PerfilDoPapel::principal($profileStorage, $account);
      $displayName = $profile !== NULL && $profile->hasField('field_first_name')
        ? trim((string) $profile->get('field_first_name')->value)
        : '';
      $nickname = $profile !== NULL && $profile->hasField('field_nickname')
        ? trim((string) $profile->get('field_nickname')->value)
        : '';

      // Never fall back to the account name: some legacy accounts use an
      // e-mail address as their Drupal username.
      $rows[] = ['data' => [
        ['#plain_text' => $displayName !== '' ? $displayName : (string) $this->t('Não informado')],
        ['#plain_text' => $nickname !== '' ? $nickname : (string) $this->t('Não informado')],
        ['#plain_text' => $account->isActive() ? (string) $this->t('Ativa') : (string) $this->t('Bloqueada')],
      ]];
    }

    return [
      '#type' => 'container',
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['user_list'],
        'max-age' => 0,
      ],
      'people' => [
        '#type' => 'table',
        '#header' => [$this->t('Nome de exibição'), $this->t('Apelido'), $this->t('Situação da conta')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma pessoa encontrada.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }

}
