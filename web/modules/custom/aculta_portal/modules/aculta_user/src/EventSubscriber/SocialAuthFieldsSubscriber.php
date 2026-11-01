<?php

declare(strict_types=1);

namespace Drupal\aculta_user\EventSubscriber;

use Drupal\aculta_user\Account\AccountNames;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\Event\UserFieldsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Conta criada por OAuth recebe o nome de usuário a partir da parte local do e-mail recebido, sem o domínio.
 */
final class SocialAuthFieldsSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function getSubscribedEvents(): array {
    return [SocialAuthEvents::USER_FIELDS => 'onUserFields'];
  }

  public function onUserFields(UserFieldsEvent $event): void {
    $fields = $event->getUserFields();
    $email = (string) ($fields['mail'] ?? '');
    if ($email === '' || !str_contains($email, '@')) {
      return;
    }
    $fields['name'] = $this->uniqueName(AccountNames::localPart($email));
    $event->setUserFields($fields);
  }

  /** Acrescenta sufixo numérico quando o nome já existe. */
  private function uniqueName(string $base): string {
    $storage = $this->entityTypeManager->getStorage('user');
    $candidate = $base;
    $i = 2;
    while ($storage->getQuery()->accessCheck(FALSE)->condition('name', $candidate)->range(0, 1)->count()->execute() > 0) {
      $candidate = $base . '-' . $i++;
    }
    return $candidate;
  }

}
