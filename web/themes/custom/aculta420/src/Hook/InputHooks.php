<?php

declare(strict_types=1);

namespace Drupal\aculta420\Hook;

use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Apresentação dos elementos `input`: ícones de botão do Webform e tokens do Portal.
 *
 * O Core aceita uma única implementação de cada hook por extensão
 * (`ModuleHandler::invoke()` lança exceção com duas), por isso `preprocess_input`
 * vive aqui e delega a `PortalUiHooks` a tradução de tokens.
 *
 * Ícones: o webform_bootstrap 3.x (deprecado) trocava os ícones do Webform por
 * glifos do Bootstrap 3. Aqui o botão continua `input[type=image]`, porque o Form
 * API identifica o clique pelas coordenadas da imagem; só o `src` muda, para o SVG
 * oficial do Bootstrap Icons em `assets/icons/bootstrap-icons/icons/`.
 *
 * @see docs/portal/WEBFORM-BOOTSTRAP-AVALIACAO.md
 */
final class InputHooks {

  /**
   * Ícones do Webform substituídos, com o SVG correspondente do tema.
   */
  private const WEBFORM_ICONS = [
    'webform/images/icons/plus.svg' => 'bootstrap-icons/icons/plus-circle.svg',
    'webform/images/icons/minus.svg' => 'bootstrap-icons/icons/dash-circle.svg',
  ];

  /**
   * Classes aplicadas ao botão de ícone do Webform.
   */
  private const WEBFORM_ICON_CLASSES = ['btn', 'btn-sm', 'btn-outline-primary', 'webform-icon-button'];

  public function __construct(
    private readonly PortalUiHooks $portalUi,
    #[Autowire(service: 'extension.list.theme')]
    private readonly ExtensionList $themes,
  ) {}

  /**
   * Implementa hook_preprocess_input().
   */
  #[Hook('preprocess_input')]
  public function preprocess(array &$variables): void {
    $this->applyWebformImageButton($variables);
    $this->portalUi->preprocess($variables);
  }

  /**
   * Troca o ícone de adicionar e remover de `image_button` do Webform.
   *
   * Antes da conversão para Attribute, `attributes` ainda pode ser array.
   */
  private function applyWebformImageButton(array &$variables): void {
    $attributes = $variables['attributes'] ?? NULL;
    if (($variables['element']['#type'] ?? NULL) !== 'image_button' || !(is_array($attributes) || $attributes instanceof Attribute)) {
      return;
    }
    $src = (string) ($attributes['src'] ?? '');
    foreach (self::WEBFORM_ICONS as $webformPath => $themePath) {
      if (!str_contains($src, '/' . $webformPath)) {
        continue;
      }
      $icon = Url::fromUri('base:' . $this->themes->getPath('aculta420') . '/assets/icons/' . $themePath)->toString();
      if (is_array($attributes)) {
        $variables['attributes']['src'] = $icon;
        $variables['attributes']['class'] = [...(array) ($attributes['class'] ?? []), ...self::WEBFORM_ICON_CLASSES];
      }
      else {
        $attributes['src'] = $icon;
        $attributes->addClass(...self::WEBFORM_ICON_CLASSES);
      }
      return;
    }
  }

}
