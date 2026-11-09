<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\aculta_magazine\Form\StudioContentPopupLayout;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compositor de criação de conteúdo no popup do Aculta Studio (Marley).
 *
 * Na rota de fragmento (aculta_magazine.studio_add_popup), o formulário de revista envia por
 * HTMX para o próprio endpoint e troca o corpo do diálogo. Sucesso leva a página à publicação
 * criada (HX-Redirect). Pré-visualizar sai; Cancelar fecha o popup.
 */
final class StudioCreatePopupFormHooks {

  /**
   * Rota de fragmento do popup de criação de conteúdo.
   */
  public const POPUP_ROUTE = 'aculta_magazine.studio_add_popup';

  /**
   * ID do corpo do diálogo no Marley (alvo do HTMX).
   */
  public const POPUP_BODY_ID = 'aculta-studio-create-body';

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Altera o formulário de revista quando ele é exibido no popup.
   */
  #[Hook('form_node_revista_form_alter', order: Order::Last)]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if ($this->routeMatch->getRouteName() !== self::POPUP_ROUTE) {
      return;
    }
    if (!$formState->getFormObject() instanceof EntityFormInterface) {
      return;
    }

    $form['#attributes']['data-aculta-content-composer'] = '';
    $form['#attributes']['data-hx-select'] = '[data-discussion-content-composer]';
    $htmx = new Htmx();
    $htmx->post(Url::fromRoute(self::POPUP_ROUTE))
      ->target('#' . self::POPUP_BODY_ID)
      ->swap('innerHTML')
      ->onlyMainContent()
      ->applyTo($form);

    // Layout do popup (ver StudioContentPopupLayout), também usado pela story do SDC.
    StudioContentPopupLayout::apply($form);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#submit'][] = [self::class, 'redirectPopupToNode'];
    }
  }

  /**
   * Após salvar no popup, leva o navegador à publicação criada.
   */
  public static function redirectPopupToNode(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $entity = $formObject->getEntity();
    if (!$entity instanceof ContentEntityInterface || $entity->isNew()) {
      return;
    }
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    if (\Drupal::request()->headers->has('HX-Request')) {
      $formState->setResponse(new Response('', 200, ['HX-Redirect' => $url]));
      return;
    }
    $formState->setRedirectUrl(Url::fromUri($url));
  }

}
