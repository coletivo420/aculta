<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_forum\Form\TopicPopupLayout;
use Drupal\aculta_forum\Poll\ForumPollManager;
use Drupal\aculta_forum\Poll\PollDefinition;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Formulário de criação de tópico (Compositor de Criação de Tópico), no popup do fórum.
 *
 * O tópico é o único compositor com Assunto. O Assunto fica antes do Comentário. Na rota
 * do popup (aculta_forum.topic_composer), o envio é por HTMX: erro de validação devolve o
 * formulário no popup; sucesso redireciona o navegador para o tópico criado.
 */
final class TopicComposerFormHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'aculta_forum.poll_manager')]
    private readonly ForumPollManager $pollManager,
    #[Autowire(service: 'messenger')]
    private readonly MessengerInterface $messenger,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
  ) {}

  /**
   * Nome da rota de fragmento usada pelo popup de criação de tópico.
   */
  public const POPUP_ROUTE = 'aculta_forum.topic_composer';

  /**
   * ID do corpo do popup de criação de tópico (alvo do HTMX).
   */
  public const POPUP_BODY_ID = 'aculta-topic-dialog-body';

  /**
   * Alters the aculta_forum_topic node form used by the topic composer.
   *
   * @param array<mixed> $form
   */
  #[Hook('form_node_aculta_forum_topic_form_alter', order: Order::Last)]
  public function nodeFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if (isset($form['title']) && is_array($form['title'])) {
      $form['title']['#weight'] = -10;
      if (isset($form['title']['widget']) && is_array($form['title']['widget'])) {
        $titleWidget = &$form['title']['widget'];
        if (isset($titleWidget[0]) && is_array($titleWidget[0])) {
          $titleFirst = &$titleWidget[0];
          if (isset($titleFirst['value']) && is_array($titleFirst['value'])) {
            $titleFirst['value']['#title'] = $this->translation->translate('Assunto');
          }
        }
      }
    }
    if (isset($form['body']) && is_array($form['body'])) {
      $form['body']['#weight'] = 0;
      if (isset($form['body']['widget']) && is_array($form['body']['widget'])) {
        $bodyWidget = &$form['body']['widget'];
        if (isset($bodyWidget[0]) && is_array($bodyWidget[0])) {
          $bodyWidget[0]['#title'] = $this->translation->translate('Comentário');
        }
      }
    }

    $this->addPollComposer($form, $formState);

    // Popup: o formulário envia por HTMX para o próprio endpoint e troca o corpo do diálogo.
    if ($this->routeMatch->getRouteName() !== self::POPUP_ROUTE) {
      return;
    }
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }

    // Layout do popup (ver TopicPopupLayout), também usado pela story do SDC.
    TopicPopupLayout::apply($form);
    // A resposta traz a página inteira: o HTMX recorta só o formulário do tópico para o popup.
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['data-aculta-topic-composer'] = '';
    $form['#attributes']['data-hx-select'] = '[data-aculta-topic-composer]';
    $htmx = new Htmx();
    $htmx->post(Url::fromRoute(self::POPUP_ROUTE, [], ['absolute' => FALSE]))
      ->target('#' . self::POPUP_BODY_ID)
      ->swap('innerHTML')
      ->onlyMainContent()
      ->applyTo($form);
    if (isset($form['actions']) && is_array($form['actions'])) {
      $actions = &$form['actions'];
      if (isset($actions['submit']) && is_array($actions['submit'])) {
        if (!isset($actions['submit']['#submit']) || !is_array($actions['submit']['#submit'])) {
          $actions['submit']['#submit'] = [];
        }
        $actions['submit']['#submit'][] = self::class . ':redirectPopupToTopic';
      }
    }
  }

  /**
   * Após salvar o tópico no popup, leva o navegador ao tópico criado.
   *
   * Envio HTMX: HX-Redirect para o tópico. Envio comum: redirecionamento normal.
   *
   * @param array<mixed> $form
   */
  public function redirectPopupToTopic(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $entity = $formObject->getEntity();
    if (!$entity instanceof ContentEntityInterface || $entity->isNew()) {
      return;
    }
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    if ($this->requestStack->getCurrentRequest()?->headers->has('HX-Request')) {
      $formState->setResponse(new Response('', 200, ['HX-Redirect' => $url]));
      return;
    }
    $formState->setRedirectUrl(Url::fromUri($url));
  }

  /**
   * Adiciona "Adicionar enquete" ao compositor de tópico novo.
   *
   * A enquete é criada uma única vez, depois que o tópico é salvo. Em edição, o
   * bloco não aparece. A validação reutiliza PollDefinition, a mesma regra do domínio.
   *
   * @param array<mixed> $form
   *   Render array do formulário, alterado por referência.
   * @param FormStateInterface $formState
   *   Estado atual do formulário.
   */
  private function addPollComposer(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface || !$formObject->getEntity()->isNew()) {
      return;
    }

    $form['poll_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->translation->translate('Adicionar enquete'),
      '#default_value' => FALSE,
      '#weight' => 5,
    ];
    $enabled = [':input[name="poll_enabled"]' => ['checked' => TRUE]];
    $multiple = [':input[name="poll_type"]' => ['value' => 'multiple']];
    $form['poll'] = [
      '#type' => 'container',
      '#weight' => 6,
      '#states' => ['visible' => $enabled],
      'poll_question' => [
        '#type' => 'textfield',
        '#title' => $this->translation->translate('Pergunta da enquete'),
        '#maxlength' => 255,
      ],
      'poll_options' => [
        '#type' => 'textarea',
        '#title' => $this->translation->translate('Opções (uma por linha, de 2 a 20)'),
        '#rows' => 5,
      ],
      'poll_type' => [
        '#type' => 'select',
        '#title' => $this->translation->translate('Tipo de votação'),
        '#options' => [
          'single' => $this->translation->translate('Escolha única'),
          'multiple' => $this->translation->translate('Múltipla escolha'),
        ],
        '#default_value' => 'single',
      ],
      'poll_min_choices' => [
        '#type' => 'number',
        '#title' => $this->translation->translate('Mínimo de escolhas'),
        '#default_value' => 1,
        '#min' => 1,
        '#states' => ['visible' => $multiple],
      ],
      'poll_max_choices' => [
        '#type' => 'number',
        '#title' => $this->translation->translate('Máximo de escolhas'),
        '#default_value' => 1,
        '#min' => 1,
        '#states' => ['visible' => $multiple],
      ],
      'poll_results_visibility' => [
        '#type' => 'select',
        '#title' => $this->translation->translate('Mostrar resultados'),
        '#options' => [
          'after_vote' => $this->translation->translate('Depois de votar'),
          'always' => $this->translation->translate('Sempre'),
          'after_close' => $this->translation->translate('Depois de encerrar'),
        ],
        '#default_value' => 'after_vote',
      ],
      'poll_close_at' => [
        '#type' => 'datetime',
        '#title' => $this->translation->translate('Encerrar automaticamente em (opcional)'),
        '#description' => $this->translation->translate('Deixe em branco para encerrar só manualmente.'),
      ],
    ];

    if (!isset($form['#validate']) || !is_array($form['#validate'])) {
      $form['#validate'] = [];
    }
    $form['#validate'][] = self::class . ':validatePoll';
    if (isset($form['actions']) && is_array($form['actions'])) {
      $actions = &$form['actions'];
      if (isset($actions['submit']) && is_array($actions['submit'])) {
        if (!isset($actions['submit']['#submit']) || !is_array($actions['submit']['#submit'])) {
          $actions['submit']['#submit'] = [];
        }
        $actions['submit']['#submit'][] = self::class . ':createPoll';
      }
    }
  }

  /**
   * Valida a enquete opcional com as mesmas regras do domínio.
   *
   * @param array<mixed> $form
   *   Render array do formulário.
   * @param FormStateInterface $formState
   *   Estado atual do formulário.
   */
  public function validatePoll(array &$form, FormStateInterface $formState): void {
    if (!$formState->getValue('poll_enabled')) {
      return;
    }
    try {
      $this->buildPollDefinition($formState);
    }
    catch (\InvalidArgumentException) {
      $formState->setErrorByName('poll_options', $this->translation->translate('Revise a pergunta, as opções (de 2 a 20) e os limites de escolha da enquete.'));
    }
    $closeAt = $this->closeAtTimestamp($formState);
    if ($closeAt !== NULL && $closeAt <= $this->time->getRequestTime()) {
      $formState->setErrorByName('poll_close_at', $this->translation->translate('A data de encerramento da enquete precisa estar no futuro.'));
    }
  }

  /**
   * Cria a enquete do tópico recém-salvo.
   *
   * @param array<mixed> $form
   *   Render array do formulário.
   * @param FormStateInterface $formState
   *   Estado atual do formulário.
   */
  public function createPoll(array &$form, FormStateInterface $formState): void {
    if (!$formState->getValue('poll_enabled')) {
      return;
    }
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $entity = $formObject->getEntity();
    if (!$entity instanceof NodeInterface || $entity->isNew()) {
      return;
    }
    try {
      $this->pollManager->createPoll($entity, $this->buildPollDefinition($formState));
    }
    catch (\Throwable) {
      $this->messenger->addError($this->translation->translate('O tópico foi salvo, mas a enquete não foi criada. Tente novamente ou encaminhe a operação à equipe técnica.'));
    }
  }

  /**
   * Monta a definição da enquete a partir dos valores do formulário.
   *
   * @throws \InvalidArgumentException
   *   Quando as opções ou os limites não atendem ao domínio.
   */
  private function buildPollDefinition(FormStateInterface $formState): PollDefinition {
    $isMultiple = $formState->getValue('poll_type') === 'multiple';
    $options = preg_split('/\R/u', $this->stringValue($formState, 'poll_options')) ?: [];

    return new PollDefinition(
      title: trim($this->stringValue($formState, 'poll_question')),
      options: $options,
      pollType: $isMultiple ? 'multiple' : 'single',
      minChoices: $isMultiple ? $this->intValue($formState, 'poll_min_choices') : 1,
      maxChoices: $isMultiple ? $this->intValue($formState, 'poll_max_choices') : 1,
      resultsVisibility: $this->stringValue($formState, 'poll_results_visibility'),
      closeAt: $this->closeAtTimestamp($formState),
    );
  }

  /**
   * Lê a data de encerramento opcional da enquete como timestamp, ou NULL se vazia.
   */
  private function closeAtTimestamp(FormStateInterface $formState): ?int {
    $value = $formState->getValue('poll_close_at');
    return $value instanceof DrupalDateTime ? $value->getTimestamp() : NULL;
  }

  /**
   * Lê um valor de texto do formulário; valores ausentes ou não textuais viram vazio.
   */
  private function stringValue(FormStateInterface $formState, string $key): string {
    $value = $formState->getValue($key);
    return is_string($value) ? $value : '';
  }

  /**
   * Lê um valor numérico do formulário; valores ausentes ou não numéricos viram zero.
   */
  private function intValue(FormStateInterface $formState, string $key): int {
    $value = $formState->getValue($key);
    return is_numeric($value) ? (int) $value : 0;
  }

}
