<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search\Form;

use Drupal\aculta_portal\Search\SearchPageController;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Formulário da página /busca. GET sem token, com o termo da URL no campo (o mesmo campo que o popup usa).
 */
final class SearchPageForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly RequestStack $searchRequestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('request_stack'));
  }

  public function getFormId(): string {
    return 'aculta_search_page_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $term = mb_substr(trim((string) ($this->searchRequestStack->getCurrentRequest()?->query->get('q', '') ?? '')), 0, SearchPageController::MAX_CHARS);
    $form['#method'] = 'get';
    $form['#token'] = FALSE;
    $form['#action'] = Url::fromRoute('aculta_portal.search')->toString();
    $form['#attributes'] = ['role' => 'search'];
    $form['#cache'] = ['contexts' => ['url.query_args:q']];
    $form['q'] = [
      '#type' => 'search',
      '#title' => $this->t('Buscar no site'),
      '#default_value' => $term,
      '#maxlength' => SearchPageController::MAX_CHARS,
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'off'],
      '#autocomplete_route_name' => 'aculta_portal.search_suggestions',
    ];
    $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Buscar')];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirectUrl(Url::fromRoute('aculta_portal.search', [], ['query' => ['q' => trim((string) $form_state->getValue('q'))]]));
  }

}
