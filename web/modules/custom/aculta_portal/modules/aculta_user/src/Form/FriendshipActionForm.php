<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Presents the current member's allowed action for a profile relationship. */
final class FriendshipActionForm extends FormBase {

  public function __construct(
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly AccountProxyInterface $currentAccount,
    private readonly EntityTypeManagerInterface $entityManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.friendship_manager'), $container->get('current_user'), $container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'aculta_user_friendship_action';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $target = NULL, ?AcultaFriendshipInterface $relationship = NULL): array {
    if ($target === NULL || !$target->isActive() || !$this->currentAccount->isAuthenticated() || (int) $target->id() === (int) $this->currentAccount->id()) {
      return [];
    }
    $form_state->set('target_uid', (int) $target->id());
    $form['#cache']['contexts'][] = 'user';
    $form['actions'] = ['#type' => 'actions'];
    $status = $relationship?->getStatus();
    $requester = $relationship?->getRequesterId();
    $uid = (int) $this->currentAccount->id();

    if ($status === FriendshipStatus::Accepted->value) {
      $form['actions']['remove'] = ['#type' => 'submit', '#value' => $this->t('Remover amizade'), '#name' => 'remove'];
    }
    elseif ($status === FriendshipStatus::Pending->value && $requester === $uid) {
      $form['status'] = ['#plain_text' => $this->t('Pedido de amizade enviado.')];
      $form['actions']['cancel'] = ['#type' => 'submit', '#value' => $this->t('Cancelar pedido'), '#name' => 'cancel'];
    }
    elseif ($status === FriendshipStatus::Pending->value) {
      $form['status'] = ['#plain_text' => $this->t('Esta pessoa enviou um pedido de amizade.')];
    }
    else {
      $form['actions']['request'] = ['#type' => 'submit', '#value' => $this->t('Adicionar amizade'), '#name' => 'request'];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->get('target_uid');
    $target = $this->entityManager->getStorage('user')->load($uid);
    if (!$target instanceof UserInterface || !$target->isActive() || $uid === (int) $this->currentAccount->id()) {
      $this->messenger()->addError($this->t('Não foi possível atualizar esse relacionamento.'));
      return;
    }
    $relationship = $this->friendshipManager->getRelationshipWith($uid);
    try {
      switch ($form_state->getTriggeringElement()['#name'] ?? '') {
        case 'request':
          $this->friendshipManager->request($target);
          break;
        case 'cancel':
          if ($relationship === NULL) {
            throw new \DomainException('This relationship no longer exists.');
          }
          $this->friendshipManager->cancel($relationship);
          break;
        case 'remove':
          if ($relationship === NULL) {
            throw new \DomainException('This relationship no longer exists.');
          }
          $this->friendshipManager->remove($relationship);
          break;
        default:
          throw new \DomainException('Unknown relationship action.');
      }
      $this->messenger()->addStatus($this->t('Relacionamento atualizado.'));
    }
    catch (\DomainException|\RuntimeException|\InvalidArgumentException $exception) {
      $this->messenger()->addError($this->t('Não foi possível concluir a ação. Atualize a página e tente novamente.'));
    }
    $form_state->setRebuild(TRUE);
  }

}
