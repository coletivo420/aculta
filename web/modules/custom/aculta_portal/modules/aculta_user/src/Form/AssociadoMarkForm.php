<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_user\Associado\AssociadoMarker;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Marca ou desmarca uma conta como associado (marcador puro, F-D19). Não concede nenhum acesso no site.
 */
final class AssociadoMarkForm extends ConfirmFormBase {

  public function __construct(
    private readonly AssociadoMarker $marker,
    private readonly AccountInterface $currentUser,
    private readonly LoggerChannelInterface $logger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.associado_marker'),
      $container->get('current_user'),
      $container->get('logger.factory')->get('aculta_user'),
    );
  }

  public function getFormId(): string {
    return 'aculta_user_associado_marcar';
  }

  public function getQuestion(): string {
    return (string) $this->t('Alterar a marca de associado desta conta?');
  }

  public function getDescription(): string {
    return (string) $this->t('A marca de associado não dá acesso a nenhum conteúdo do site. Serve a relatórios e páginas da associação.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_user.associados');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    $form_state->set('uid', (int) $user?->id());
    $marcada = $this->marker->isAssociado((int) $user?->id());
    $form['estado'] = ['#markup' => '<p>' . ($marcada
      ? $this->t('Estado atual: marcada como associado. Confirmar remove a marca.')
      : $this->t('Estado atual: sem marca de associado. Confirmar adiciona a marca.')) . '</p>'];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->get('uid');
    if ($this->marker->isAssociado($uid)) {
      $this->marker->unmark($uid);
      $this->logger->notice('Marca de associado removida da conta @conta por @admin.', ['@conta' => $uid, '@admin' => $this->currentUser->id()]);
      $this->messenger()->addStatus($this->t('Marca de associado removida.'));
    }
    else {
      $this->marker->mark($uid, (int) $this->currentUser->id());
      $this->logger->notice('Marca de associado adicionada à conta @conta por @admin.', ['@conta' => $uid, '@admin' => $this->currentUser->id()]);
      $this->messenger()->addStatus($this->t('Conta marcada como associado.'));
    }
    $form_state->setRedirect('aculta_user.associados');
  }

}
