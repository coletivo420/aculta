<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_messenger\Form\ConversationRelationshipForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
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
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('form_builder'));
  }

  public function post(UserInterface $user, Request $request): RedirectResponse {
    // Back to the profile only: a path of /u/<apelido>, never another address.
    $return = (string) $request->request->get('return', '');
    if (preg_match('~^/u/[^/?#]+$~', $return) !== 1) {
      $return = '/';
    }
    $postUrl = Url::fromRoute('aculta_messenger.profile_relationship', ['user' => $user->id()])->toString();
    $this->forms->getForm(ConversationRelationshipForm::class, (int) $user->id(), $postUrl);
    return new RedirectResponse($return, Response::HTTP_SEE_OTHER);
  }

}
