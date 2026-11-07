<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Studio account entry, independent from the original Portal account shell.
 */
final class AccountController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
    private readonly DomainPurposeManager $domains,
  ) {}

  /**
   * Shows the authorized account experience without rewriting Portal forms.
   *
   * @return array<string, mixed>
   *   The owner's account entrypoint render array.
   */
  public function page(): array {
    $provider = $this->experiences->get('conta');
    if ($provider === NULL || !$provider->available()) {
      throw new NotFoundHttpException();
    }
    if (!$this->experiences->access('conta', 'account', $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $build = $provider->build();
    $build['#experiences'] = $this->experienceCards($this->experiences->buildEntries(
      $this->account,
      $this->domains,
      ['conta', 'perfil', 'salvos', 'mercado_pago_plans'],
    ));
    $cache = CacheableMetadata::createFromRenderArray($build);
    foreach ($build['#experiences'] as $card) {
      $cache->addCacheableDependency($card);
    }
    foreach ($this->experiences->all() as $candidate) {
      if (!in_array($candidate->id(), ['conta', 'perfil', 'salvos', 'mercado_pago_plans'], TRUE)) {
        $cache->addCacheableDependency($candidate->access($this->account));
      }
    }
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Descriptive metadata for the account experience cards, keyed by provider id.
   */
  private const CARD_META = [
    'marley' => [
      'icon' => 'book-open',
      'description' => 'Criação e moderação da Revista, moderação do Fórum e Galerias.',
    ],
    'ganjalab' => ['icon' => 'flask', 'description' => 'Aprendizagem: painel e interface do estudante.'],
    'we_sativa' => ['icon' => 'plant', 'description' => 'Área integrada de contribuição à Wiki.'],
    'culturas' => ['icon' => 'users-three', 'description' => 'Eventos e organizações culturais da ACULTA.'],
  ];

  /**
   * Faixa de largura total para experiências integradas (Green Stash).
   */
  private const STRIP_META = [
    'green_stash' => [
      'icon' => 'archive',
      'description' => 'Área integrada de gestão comercial da Loja. A operação fica com o Commerce.',
    ],
  ];

  /**
   * Turns the account experience links into neutral card data.
   *
   * @param array<int, array<string, mixed>> $items
   *   Link render arrays from StudioExperienceManager::buildEntries().
   *
   * @return array<int, array<string, mixed>>
   *   Card render arrays using the `aculta_studio_account_card` theme hook.
   */
  private function experienceCards(array $items): array {
    $cards = [];
    foreach ($items as $item) {
      $id = (string) ($item['#attributes']['data-experience'] ?? '');
      $cache = $item['#cache'] ?? [];
      $title = $item['#title'] ?? NULL;
      if (isset(self::STRIP_META[$id])) {
        $meta = self::STRIP_META[$id];
        $cards[] = [
          '#theme' => 'aculta_studio_account_card',
          '#id' => $id,
          '#kind' => 'strip',
          '#icon' => $meta['icon'],
          '#title' => $title,
          '#description' => $meta['description'],
          '#tag' => 'Acesso integrado',
          '#cache' => $cache,
        ];
        continue;
      }
      $meta = self::CARD_META[$id] ?? ['icon' => 'user', 'description' => ''];
      $cards[] = [
        '#theme' => 'aculta_studio_account_card',
        '#id' => $id,
        '#kind' => 'card',
        '#icon' => $meta['icon'],
        '#title' => $title,
        '#description' => $meta['description'],
        '#url' => isset($item['#url']) ? $item['#url']->toString() : NULL,
        '#action_label' => 'Acessar',
        '#cache' => $cache,
      ];
    }
    return $cards;
  }

}
