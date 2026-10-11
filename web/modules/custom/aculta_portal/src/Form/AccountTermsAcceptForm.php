<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Account\AccountTermsPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Aceite dos termos: usado no popup (modal) e na rota /termos/aceitar, que é o retorno sem JavaScript.
 *
 * O aceite é gravado no mesmo registro do cadastro (AccountTermsPolicy). Depois de aceitar, o usuário volta à
 * página de onde veio (destino validado como caminho local).
 */
final class AccountTermsAcceptForm extends FormBase {

  public function __construct(
    private readonly AccountTermsPolicy $policy,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.account_terms_policy'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_account_terms_accept';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'portal-terms-modal__form';
    $form['#action'] = Url::fromRoute('aculta_portal.account_terms.accept', [], [
      'query' => ['destination' => $this->destination()],
    ])->toString();

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Para continuar usando a conta, leia e aceite os documentos abaixo.') . '</p>',
    ];
    $form['documents'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['portal-terms-modal__documents']],
      'terms' => $this->documentLink('/termos-de-uso', $this->t('Termos de Uso')),
      'privacy' => $this->documentLink('/politica-de-privacidade', $this->t('Política de Privacidade')),
    ];
    $form['accept'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Li e aceito os Termos de Uso e a Política de Privacidade.'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Aceitar e continuar'),
      '#button_type' => 'primary',
    ];

    // Quando aparece como popup, o <dialog> envolve o formulário na hook page_bottom (AccountTermsHooks).
    $form['#cache'] = ['contexts' => ['user'], 'max-age' => 0];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->policy->record($this->currentUser());
    $destination = $this->destination();
    $form_state->setRedirectUrl(Url::fromUserInput($destination));
  }

  /** Destino de retorno: caminho local da própria requisição (nunca outro host). */
  private function destination(): string {
    $request = $this->getRequest();
    $candidate = (string) ($request->query->get('destination') ?? '');
    if ($candidate === '') {
      $candidate = $request->getRequestUri();
    }
    $path = parse_url($candidate, PHP_URL_PATH) ?: '/';
    if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($candidate, '\\')
      || $path === '/termos/aceitar') {
      return '/';
    }
    return $candidate;
  }

  /** Link para o documento no host principal, em nova aba (o popup continua aberto). */
  private function documentLink(string $path, $label): array {
    $url = $this->domainPurposeManager->pathUrl('main', $path) ?? Url::fromUserInput($path);
    return [
      '#type' => 'link',
      '#title' => $label,
      '#url' => $url,
      '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
    ];
  }

}
