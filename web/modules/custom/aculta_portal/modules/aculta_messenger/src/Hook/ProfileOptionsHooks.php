<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Form\ConversationRelationshipForm;
use Drupal\aculta_messenger\Support\OptionsDisclosure;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The "Opções" box of a public profile (/u/): the same box as in a conversation, with "Iniciar conversa" in place of
 * "Limpar histórico". It replaces the profile's friendship button, because the box holds those actions.
 */
final class ProfileOptionsHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $forms,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
  ) {}

  #[Hook('aculta_user_public_profile_actions_alter')]
  public function alterActions(array &$actions, ProfileInterface $profile): void {
    if (!$this->currentUser->isAuthenticated()
      || !$this->currentUser->hasPermission('use private messaging system')
      || !$this->currentUser->hasPermission('access user profiles')) {
      return;
    }
    $owner = $profile->getOwner();
    if (!$owner instanceof UserInterface || !$owner->isActive() || (int) $owner->id() === (int) $this->currentUser->id()) {
      return;
    }
    unset($actions['friendship']);
    $actions['messenger'] = $this->optionsBox($owner);
  }

  private function optionsBox(UserInterface $target): array {
    $returnPath = (string) $this->requestStack->getCurrentRequest()?->getPathInfo();
    $postUrl = Url::fromRoute('aculta_messenger.profile_relationship', ['user' => $target->id()])->toString();
    $state = new FormState();
    $state->set('target', (int) $target->id());
    $state->set('post_url', $postUrl);
    $state->set('profile', TRUE);
    $state->set('return_path', $returnPath);
    $composeUrl = Url::fromRoute('aculta_messenger.new_conversation_compose', ['user' => $target->id()])->toString();
    $box = OptionsDisclosure::build((string) $this->translation->translate('Opções'), [
      'chat' => PortalLinkElement::build($this->translation->translate('Iniciar conversa'), Url::fromRoute('aculta_messenger.new_conversation_compose', ['user' => $target->id()]), [
        'data-portal-ui' => 'messenger-options-item',
        'data-aculta-open-conversation' => $composeUrl,
      ]),
      'relationship' => $this->forms->buildForm(ConversationRelationshipForm::class, $state),
    ]);
    $box['#cache'] = ['contexts' => ['user'], 'max-age' => 0];
    return $box;
  }

}
