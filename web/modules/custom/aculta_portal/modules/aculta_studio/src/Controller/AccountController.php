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
    $build['#attached']['library'][] = 'aculta420_v2/account-carousel';
    $build['suites']['#items'] = $this->experienceCards($this->experiences->buildEntries(
      $this->account,
      $this->domains,
      ['conta', 'perfil', 'salvos', 'mercado_pago_plans'],
    ));
    $cache = CacheableMetadata::createFromRenderArray($build);
    foreach ($this->experiences->all() as $candidate) {
      if (!in_array($candidate->id(), ['conta', 'perfil', 'salvos', 'mercado_pago_plans'], TRUE)) {
        $cache->addCacheableDependency($candidate->access($this->account));
      }
    }
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Visual metadata for the account experience cards, keyed by provider id.
   */
  private const CARD_META = [
    'marley' => ['icon' => 'book-open', 'description' => 'Criação e moderação da Revista, moderação do Fórum e Galerias.'],
    'ganjalab' => ['icon' => 'flask', 'description' => 'Aprendizagem: painel e interface do estudante. Regras acadêmicas ficam no LMS.'],
    'we_sativa' => ['icon' => 'plant', 'description' => 'Área integrada de contribuição à Wiki.'],
    'culturas' => ['icon' => 'users-three', 'description' => 'Eventos e organizações culturais da ACULTA.'],
  ];

  /**
   * Faixa de largura total para experiências integradas (Green Stash).
   */
  private const STRIP_META = [
    'green_stash' => ['icon' => 'archive', 'description' => 'Área integrada de gestão comercial da Loja. A operação fica com o Commerce.'],
  ];

  /**
   * Turns the account experience links into cards with icon, text and action.
   *
   * @param array<int, array<string, mixed>> $items
   *   Link render arrays from StudioExperienceManager::buildEntries().
   *
   * @return array<int, array<string, mixed>>
   *   Card and strip render arrays, in the same order.
   */
  private function experienceCards(array $items): array {
    $cards = [];
    foreach ($items as $item) {
      $id = (string) ($item['#attributes']['data-experience'] ?? '');
      $cache = $item['#cache'] ?? [];
      if (isset(self::STRIP_META[$id])) {
        $meta = self::STRIP_META[$id];
        $cards[] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['aculta-xp-strip'], 'data-experience' => $id],
          '#cache' => $cache,
          'icon' => $this->iconComponent($meta['icon']),
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['aculta-xp-strip__body']],
            'title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $item['#title'], '#attributes' => ['class' => ['aculta-xp-strip__title']]],
            'tag' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => 'Acesso integrado', '#attributes' => ['class' => ['aculta-xp-strip__tag']]],
            'text' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $meta['description'], '#attributes' => ['class' => ['aculta-xp-strip__text']]],
          ],
        ];
        continue;
      }
      $meta = self::CARD_META[$id] ?? ['icon' => 'user', 'description' => ''];
      $cards[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-xp-card'], 'data-experience' => $id],
        '#cache' => $cache,
        'icon' => $this->iconComponent($meta['icon']),
        'title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $item['#title'], '#attributes' => ['class' => ['aculta-xp-card__title']]],
        'text' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $meta['description'], '#attributes' => ['class' => ['aculta-xp-card__text']]],
        'action' => [
          '#type' => 'link',
          '#title' => 'Acessar',
          '#url' => $item['#url'],
          '#attributes' => ['class' => ['aculta-xp-card__action']],
        ],
      ];
    }
    return $cards;
  }

  /**
   * Phosphor duotone icon from the ACULTA420 V2 theme.
   *
   * @return array<string, mixed>
   *   Component render array.
   */
  private function iconComponent(string $name): array {
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:icon',
      '#props' => ['name' => $name, 'variant' => 'duotone', 'size' => 'xl'],
    ];
  }

}
