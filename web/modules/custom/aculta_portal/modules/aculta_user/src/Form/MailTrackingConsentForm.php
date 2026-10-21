<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_user\Consent\ConsentLedger;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Lets a member grant or revoke optional email tracking consent. */
final class MailTrackingConsentForm extends FormBase {

  public function __construct(
    private readonly ConsentLedger $ledger,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.consent_ledger'), $container->get('current_user'));
  }

  public function getFormId(): string {
    return 'aculta_user_mail_tracking_consent';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Aceito o rastreamento de aberturas e cliques nos e-mails enviados pela ACULTA.'),
      '#default_value' => $this->ledger->hasAccepted((int) $this->currentUser->id(), 'aculta_rastreamento'),
      '#description' => $this->t('Opcional. O registro do aceite ou da revogação inclui data e hora, endereço IP resolvido pelo servidor e User-Agent do navegador. A revogação vale para os próximos e-mails.'),
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar preferência')];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->ledger->record((int) $this->currentUser->id(), 'aculta_rastreamento', $form_state->getValue('consent') ? 'accepted' : 'revoked');
    $this->messenger()->addStatus($this->t('Sua preferência de rastreamento de e-mail foi atualizada.'));
  }

}
