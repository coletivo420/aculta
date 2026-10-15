<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Form\ConversationRelationshipForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
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
    return [
      '#type' => 'html_tag',
      '#tag' => 'details',
      '#attributes' => ['class' => ['aculta-messenger-options']],
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'summary',
        '#attributes' => ['class' => ['aculta-messenger-options__summary'], 'aria-label' => (string) $this->translation->translate('Opções')],
        '#value' => Markup::create('<i class="bi bi-list" aria-hidden="true"></i><span class="aculta-messenger-options__label">' . Html::escape((string) $this->translation->translate('Opções')) . '</span>'),
      ],
      'panel' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-messenger-options__panel']],
        'chat' => [
          '#type' => 'link',
          '#title' => $this->translation->translate('Iniciar conversa'),
          '#url' => Url::fromUri('base:' . ltrim($composeUrl, '/')),
          '#attributes' => [
            'class' => ['aculta-messenger-options__item'],
            'data-aculta-open-conversation' => $composeUrl,
          ],
        ],
        'relationship' => $this->forms->buildForm(ConversationRelationshipForm::class, $state),
      ],
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
  }

}
