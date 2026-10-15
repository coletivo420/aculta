<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Keeps the native Private Message reply form available in thread views. */
final class PrivateMessageReplyHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $currentRouteMatch,
  ) {}

  /** Adds the native composer when a site's thread display omitted its field. */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof PrivateMessageThreadInterface
      || $view_mode !== 'full'
      || !$this->currentUser->isAuthenticated()
      || !$this->currentUser->hasPermission('use private messaging system')
      || !$this->currentUser->hasPermission('access user profiles')
      || !$entity->isMember($this->currentUser->id())) {
      return;
    }

    $access = $entity->access('view', $this->currentUser, TRUE);
    if (!$access->isAllowed()) {
      return;
    }

    $isDrawerConversation = $this->currentRouteMatch->getRouteName() === 'aculta_messenger.drawer_conversation';
    $conversationsUrl = $isDrawerConversation
      ? Url::fromRoute('aculta_messenger.drawer_conversations')
      : $this->domainPurposeManager->routeUrl('account', 'aculta_messenger.conversations');
    if ($conversationsUrl !== NULL) {
      $build['aculta_messenger_back_to_conversations'] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('← Todas as conversas'),
        '#url' => $conversationsUrl,
        '#weight' => -100,
        '#attributes' => [
          'class' => ['aculta-messenger-back-link'],
          'data-aculta-drawer-back' => $isDrawerConversation ? '' : NULL,
        ],
      ];
    }
    $build['#cache']['contexts'][] = 'user';
    $build['#cache']['max-age'] = 0;

    if (isset($build['private_message_form'])) {
      return;
    }

    $message = $this->entityTypeManager->getStorage('private_message')->create();
    $formObject = $this->entityTypeManager
      ->getFormObject('private_message', 'add')
      ->setEntity($message);
    $replyForm = $this->formBuilder->getForm($formObject, $entity);
    $replyForm['#attributes']['id'] = 'aculta-messenger-reply-form';
    $replyForm['#attributes']['class'][] = 'aculta-messenger-reply-form';

    // The native conversation history is rendered after the entity fields.
    // Keep the composer after that history in the account page and drawer.
    $replyForm['#weight'] = 10000;
    $build['aculta_messenger_reply_form'] = $replyForm;
  }

}
