<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Displays and handles the signed-in member's incoming friendship requests. */
final class FriendRequestsForm extends FormBase {

  public function __construct(
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly EntityTypeManagerInterface $entityManager,
    private readonly AccountProxyInterface $currentAccount,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.friendship_manager'), $container->get('entity_type.manager'), $container->get('current_user'));
  }

  public function getFormId(): string {
    return 'aculta_messenger_friend_requests';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $requests = $this->friendshipManager->getIncomingRequests();
    $form['#cache']['contexts'][] = 'user';
    $form['intro'] = ['#plain_text' => $this->t('Pedidos de amizade')];
    if ($requests === []) {
      $form['empty'] = ['#plain_text' => $this->t('Você não tem pedidos de amizade pendentes.')];
      return $form;
    }

    $requesterIds = array_map(static fn (AcultaFriendshipInterface $request): int => $request->getRequesterId(), $requests);
    $requesters = $this->entityManager->getStorage('user')->loadMultiple($requesterIds);
    foreach ($requests as $request) {
      $requester = $requesters[$request->getRequesterId()] ?? NULL;
      if (!$requester instanceof UserInterface || !$requester->isActive()) {
        continue;
      }
      $key = 'member_' . $requester->id();
      $form[$key] = [
        '#type' => 'container',
        'name' => ['#plain_text' => $requester->getDisplayName()],
        'actions' => [
          '#type' => 'actions',
          'accept' => ['#type' => 'submit', '#value' => $this->t('Aceitar'), '#name' => 'accept_' . $requester->id()],
          'decline' => ['#type' => 'submit', '#value' => $this->t('Recusar'), '#name' => 'decline_' . $requester->id()],
        ],
      ];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if (!preg_match('/^(accept|decline)_([1-9][0-9]*)$/', $name, $matches)) {
      return;
    }
    $requesterId = (int) $matches[2];
    $requester = $this->entityManager->getStorage('user')->load($requesterId);
    if (!$requester instanceof UserInterface || !$requester->isActive()) {
      $this->messenger()->addError($this->t('Este pedido não está mais disponível.'));
      $form_state->setRebuild(TRUE);
      return;
    }
    $relationship = $this->friendshipManager->getRelationshipWith($requesterId);
    try {
      if (!$relationship instanceof AcultaFriendshipInterface || $relationship->getRecipientId() !== (int) $this->currentAccount->id()) {
        throw new \DomainException('This request is no longer available to the current member.');
      }
      if ($matches[1] === 'accept') {
        $this->friendshipManager->accept($relationship);
      }
      else {
        $this->friendshipManager->decline($relationship);
      }
      $this->messenger()->addStatus($matches[1] === 'accept' ? $this->t('Amizade confirmada.') : $this->t('Pedido recusado.'));
    }
    catch (\DomainException|\RuntimeException $exception) {
      $this->messenger()->addError($this->t('Não foi possível atualizar o pedido. Atualize a página e tente novamente.'));
    }
    $form_state->setRebuild(TRUE);
  }

}
