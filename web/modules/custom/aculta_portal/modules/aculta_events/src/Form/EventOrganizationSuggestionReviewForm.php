<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\EventOrganizationSuggestion;
use Drupal\aculta_events\Organization\OrganizationSuggestionService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reviews and decides a proposal to change an unclaimed organization.
 */
final class EventOrganizationSuggestionReviewForm extends FormBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_events.organization_suggestion')]
    private readonly OrganizationSuggestionService $suggestionService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_organization_suggestion_review_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EventOrganizationSuggestion $aculta_event_org_suggestion = NULL): array {
    if ($aculta_event_org_suggestion === NULL) {
      throw new NotFoundHttpException();
    }
    $suggestion = $aculta_event_org_suggestion;
    if ($suggestion->get('field_status')->value !== 'pending') {
      throw new NotFoundHttpException();
    }
    $form['suggestion_id'] = ['#type' => 'value', '#value' => (int) $suggestion->id()];
    $organization = $this->entityTypeManager->getStorage('aculta_event_organization')
      ->load($suggestion->get('field_organization')->target_id);
    $changes = json_decode((string) $suggestion->get('field_changes')->value, TRUE) ?: [];

    $rows = [];
    foreach ($changes as $field => $proposed) {
      if (!in_array($field, ['title', 'body', 'imagem', 'links', 'localidade', 'uf', 'ocultar_localidade'], TRUE)) {
        continue;
      }
      $current = $organization !== NULL
        ? ($field === 'title' ? $organization->label() : $organization->get($field)->value)
        : $this->t('Organização removida');
      $rows[] = [
        'field' => $this->fieldLabel($field),
        'current' => ['data' => ['#plain_text' => $this->displayValue($current)]],
        'proposed' => ['data' => ['#plain_text' => $this->displayValue($proposed)]],
      ];
    }

    $form['changes'] = [
      '#type' => 'table',
      '#header' => [$this->t('Campo'), $this->t('Atual'), $this->t('Proposto')],
      '#rows' => $rows,
    ];
    $form['review_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Nota da revisão'),
      '#description' => $this->t('A nota é registrada na decisão administrativa.'),
      '#rows' => 3,
      '#maxlength' => 4000,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Aprovar alterações'),
      '#submit' => ['::submitApprove'],
    ];
    $form['actions']['reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rejeitar proposta'),
      '#submit' => ['::submitReject'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Approves only if the organization is still unclaimed and unchanged.
   */
  public function submitApprove(array &$form, FormStateInterface $form_state): void {
    $suggestion = $this->loadSuggestion($form_state);
    $approved = $this->suggestionService->approve($suggestion, $this->currentUser(), (string) $form_state->getValue('review_note'));
    if ($approved) {
      $this->messenger()->addStatus($this->t('As alterações foram aprovadas e aplicadas ao perfil público.'));
    }
    else {
      $this->messenger()->addWarning($this->t('A proposta foi marcada como desatualizada porque a organização mudou ou recebeu titular. Nenhuma alteração foi aplicada.'));
    }
    $form_state->setRedirect('aculta_events.admin_organization_suggestions');
  }

  /**
   * Rejects the proposal without modifying the organization.
   */
  public function submitReject(array &$form, FormStateInterface $form_state): void {
    $this->suggestionService->reject(
      $this->loadSuggestion($form_state),
      $this->currentUser(),
      (string) $form_state->getValue('review_note'),
    );
    $this->messenger()->addStatus($this->t('A proposta foi rejeitada. O perfil público não foi alterado.'));
    $form_state->setRedirect('aculta_events.admin_organization_suggestions');
  }

  /**
   * Loads the suggestion currently under review.
   */
  private function loadSuggestion(FormStateInterface $form_state): EventOrganizationSuggestion {
    $suggestion = $this->entityTypeManager
      ->getStorage('aculta_event_org_suggestion')
      ->load((int) $form_state->getValue('suggestion_id'));
    if (!$suggestion instanceof EventOrganizationSuggestion) {
      throw new \InvalidArgumentException('A sugestão não foi encontrada.');
    }
    return $suggestion;
  }

  /**
   * Returns a translated label for an allowlisted field.
   */
  private function fieldLabel(string $field): string {
    return match ($field) {
      'title' => (string) $this->t('Nome'),
      'body' => (string) $this->t('Descrição'),
      'imagem' => (string) $this->t('Imagem ou logotipo'),
      'links' => (string) $this->t('Links oficiais'),
      'localidade' => (string) $this->t('Cidade'),
      'uf' => (string) $this->t('UF'),
      'ocultar_localidade' => (string) $this->t('Ocultar localidade'),
      default => $field,
    };
  }

  /**
   * Formats scalar values safely for the comparison table.
   */
  private function displayValue(mixed $value): string {
    if (is_bool($value) || $value === 0 || $value === 1) {
      return $value ? (string) $this->t('Sim') : (string) $this->t('Não');
    }
    return is_scalar($value) ? (string) $value : '';
  }

}
