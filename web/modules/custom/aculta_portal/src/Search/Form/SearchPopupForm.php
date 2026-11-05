<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search\Form;

use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Search\SearchPageController;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Popup da busca (ícone da barra multidomínio). GET para a busca central no MAIN.
 *
 * Em requisição HTMX (ACULTA420 V2), a prévia é carregada pela rota
 * /busca/previa com `hx-get` no próprio campo. Na V1, sem HTMX, o script
 * `aculta_portal/search-preview` mantém o Fetch com `AbortController`. Sem
 * callback AJAX no Form API: a prévia não depende do cache do formulário.
 */
final class SearchPopupForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    protected readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_portal.domain_purpose'));
  }

  public function getFormId(): string {
    return 'aculta_search_popup_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $onMain = $this->domainPurposeManager->getCurrentPurpose() === 'main';
    $form['#method'] = 'get';
    $form['#token'] = FALSE;
    $form['#action'] = $onMain
      ? Url::fromRoute('aculta_portal.search')->toString()
      : ($this->domainPurposeManager->routeUrl('main', 'aculta_portal.search')?->toString() ?? Url::fromRoute('aculta_portal.search')->toString());
    $form['#attributes'] = ['role' => 'search', 'data-portal-ui' => 'search-popup'];
    $form['#cache'] = ['contexts' => ['domain', 'url.path']];
    $previewUrl = Url::fromRoute('aculta_portal.search_preview');
    $form['q'] = [
      '#type' => 'search',
      '#title' => $this->t('Buscar no site'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Buscar no site'),
      '#maxlength' => SearchPageController::MAX_CHARS,
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off',
        'data-portal-ui' => 'search-preview-input',
        'data-preview-url' => $previewUrl->toString(),
        'data-min-chars' => (string) SearchPageController::MIN_CHARS,
      ],
    ];
    if ($this->isHtmxRequest()) {
      // HTMX owns the preview request in V2: debounce, same-request replace
      // and the short-term filter avoid the legacy AbortController. The
      // controller itself returns the minimum-length hint.
      (new Htmx())
        ->get($previewUrl)
        ->trigger('input changed delay:300ms[this.value.trim() !== ""], search')
        ->target('#aculta-search-preview')
        ->swap('innerHTML')
        ->sync('this:replace')
        ->applyTo($form['q']);
    }
    else {
      // V1 keeps the Fetch-based preview script.
      $form['#attached']['library'][] = 'aculta_portal/search-preview';
    }
    $form['preview'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'aculta-search-preview',
        'data-portal-ui' => 'search-preview',
        'role' => 'region',
        'aria-label' => $this->t('Prévia dos resultados da busca'),
        'aria-live' => 'polite',
      ],
      'hint' => ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#element_attributes' => new Attribute(['data-portal-ui' => 'hint']), '#value' => $this->t('Digite pelo menos @n caracteres para ver resultados.', ['@n' => SearchPageController::MIN_CHARS])],
    ];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => $this->t('Buscar')]];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET: o navegador envia direto para a busca central; nada a processar no servidor.
  }

}
