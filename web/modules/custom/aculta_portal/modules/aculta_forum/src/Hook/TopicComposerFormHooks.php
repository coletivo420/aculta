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
   */
  #[Hook('form_node_aculta_forum_topic_form_alter', order: Order::Last)]
  public function nodeFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if (isset($form['title'])) {
      $form['title']['#weight'] = -10;
      $form['title']['widget'][0]['value']['#title'] = $this->translation->translate('Assunto');
    }
    if (isset($form['body'])) {
      $form['body']['#weight'] = 0;
      $form['body']['widget'][0]['#title'] = $this->translation->translate('Comentário');
    }

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
    $form['#attributes']['data-aculta-topic-composer'] = '';
    $form['#attributes']['data-hx-select'] = '[data-aculta-topic-composer]';
    $htmx = new Htmx();
    $htmx->post(Url::fromRoute(self::POPUP_ROUTE, [], ['absolute' => FALSE]))
      ->target('#' . self::POPUP_BODY_ID)
      ->swap('innerHTML')
      ->onlyMainContent()
      ->applyTo($form);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#submit'][] = self::class . ':redirectPopupToTopic';
    }
  }

  /**
   * Após salvar o tópico no popup, leva o navegador ao tópico criado.
   *
   * Envio HTMX: HX-Redirect para o tópico. Envio comum: redirecionamento normal.
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

}
