<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\aculta_portal\Form\SimplifiedAccountPasswordForm;
use Drupal\aculta_portal\EventSubscriber\SimplifiedAccountCheckoutSubscriber;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Etapa de criação de senha do cadastro simplificado.
 *
 * Só mostra o formulário para a conta criada pelo cadastro simplificado, logada logo após a confirmação do e-mail.
 * Outros visitantes e contas comuns são levados para a página inicial.
 */
final class SimplifiedAccountPasswordController extends ControllerBase {

  /**
   * Cria o controller.
   */
  public function __construct(
    private readonly AccountProxyInterface $accountProxy,
    private readonly UserDataInterface $userDataStore,
    private readonly FormBuilderInterface $pageFormBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('form_builder'),
    );
  }

  /**
   * Formulário de senha, ou redirecionamento quando não há cadastro simplificado pendente.
   */
  public function page(): array|RedirectResponse {
    $uid = (int) $this->accountProxy->id();
    $pendente = $uid > 0 && (bool) $this->userDataStore->get('aculta_portal', $uid, SimplifiedAccountCheckoutSubscriber::FLAG);
    if (!$pendente) {
      return new RedirectResponse('/');
    }
    return $this->pageFormBuilder->getForm(SimplifiedAccountPasswordForm::class);
  }

}
