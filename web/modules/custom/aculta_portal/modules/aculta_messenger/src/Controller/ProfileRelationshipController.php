<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_messenger\Form\ConversationRelationshipForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An action from the "Opções" box of a public profile: processed here, then the member goes back to the profile.
 */
final class ProfileRelationshipController extends ControllerBase {

  public function __construct(
    private readonly FormBuilderInterface $forms,
    private readonly MessengerInterface $messenger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('form_builder'), $container->get('messenger'));
  }

  public function post(UserInterface $user, Request $request): RedirectResponse {
    // Back to the profile only: a path of /u/<apelido>, never another address.
    $return = (string) $request->request->get('return', '');
    if (preg_match('~^/u/[^/?#]+$~', $return) !== 1) {
      $return = '/';
    }
    $postUrl = Url::fromRoute('aculta_messenger.profile_relationship', ['user' => $user->id()])->toString();
    // The action runs with the posted input, as the drawer's does; its result message goes to the member on the profile.
    $formState = new FormState();
    $formState->setRequestMethod('POST');
    $formState->setUserInput($request->request->all());
    $formState->set('profile', TRUE);
    $this->forms->buildForm(ConversationRelationshipForm::class, $formState, (int) $user->id(), $postUrl);
    $message = $formState->get('message');
    if ($message !== NULL) {
      $this->messenger->addStatus((string) $message);
    }
    return new RedirectResponse($return, Response::HTTP_SEE_OTHER);
  }

}
