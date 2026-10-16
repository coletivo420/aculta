<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Aviso\AvisoService;

/**
 * Contador individual nas linhas de tópico do fórum (ACULTA Fórum, F5.2).
 *
 * O módulo de fórum monta a lista de tópicos em uma tabela pronta, sem ponto de alteração por linha. Depois do
 * pré-processamento do Core, este hook percorre as linhas, acha cada tópico pelo link do título e acrescenta o número
 * de itens não lidos da própria pessoa. A marcação leva data-portal-ui; a aparência fica no tema.
 */
final class ForumTopicCounterHooks {

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly AvisoService $avisos,
  ) {}

  #[Hook('preprocess_forums')]
  public function preprocessForums(array &$variables): void {
    if ($this->currentUser->isAnonymous() || !isset($variables['topics']['#rows'], $variables['topics_original'])) {
      return;
    }
    $uid = (int) $this->currentUser->id();
    // Mapa do link do título para o tópico, a partir dos objetos originais.
    $porLink = [];
    foreach ($variables['topics_original'] as $topico) {
      $porLink[(string) ($topico->title_link ?? '')] = (int) $topico->id();
    }
    foreach ($variables['topics']['#rows'] as $indice => $linha) {
      $titulo = $linha['data'][1] ?? NULL;
      if (!is_array($titulo) || !isset($titulo['#title_link'])) {
        continue;
      }
      $nid = $porLink[(string) $titulo['#title_link']] ?? NULL;
      if ($nid === NULL) {
        continue;
      }
      $contador = $this->avisos->contadorDoTopico($uid, $nid);
      if ($contador <= 0) {
        continue;
      }
      // Link original preservado; o contador é um elemento com data-portal-ui, que o tema traduz em aparência.
      $variables['topics']['#rows'][$indice]['data'][1]['#title_link'] = [
        'link' => ['#markup' => $titulo['#title_link']],
        'contador' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#value' => t('@n não lidos', ['@n' => $contador]),
          '#element_attributes' => new Attribute(['data-portal-ui' => 'forum-topic-unread']),
        ],
      ];
    }
    CacheableMetadata::createFromRenderArray($variables['topics'])->addCacheContexts(['user'])->setCacheMaxAge(0)->applyTo($variables['topics']);
  }

}
