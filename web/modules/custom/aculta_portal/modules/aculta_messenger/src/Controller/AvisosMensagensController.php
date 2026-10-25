<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Aviso\PersonalNoticePageState;
use Drupal\aculta_messenger\Form\FriendRequestsForm;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Central "Avisos/Mensagens" dentro de Minha conta (ACULTA Messenger, F5.2).
 *
 * Mostra a central unificada de avisos e a área de conversas.
 *
 * As conversas permanecem no sistema compartilhado de mensagens. Esta página
 * não cria conversas por conteúdo.
 */
final class AvisosMensagensController extends ControllerBase {

  /**
   * IDs das abas disponíveis.
   */
  public const ABAS = ['avisos', 'mensagens'];

  public function __construct(
    private readonly UnreadThreadCounter $conversas,
    private readonly AccountInterface $usuarioAtual,
    private readonly MessagesController $mensagens,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly RequestStack $requestStack,
    private readonly NoticeListFilter $noticeListFilter,
  ) {}

  /**
   * Creates the controller from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_messenger.unread_thread_counter'),
      $container->get('current_user'),
      $container->get('class_resolver')->getInstanceFromDefinition(MessagesController::class),
      $container->get('aculta_user.friendship_manager'),
      $container->get('request_stack'),
      $container->get('aculta_messenger.notice_list_filter'),
    );
  }

  /**
   * Renders the requested account tab.
   */
  public function view(string $aba = 'avisos'): array {
    if (!in_array($aba, self::ABAS, TRUE)) {
      $aba = 'avisos';
    }
    $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
    $filters = $this->noticeListFilter->normalize($query);
    $filterQuery = array_filter($filters, static fn(string $value): bool => $value !== '');
    $forumOffset = PersonalNoticePageState::normalizeOffset($query['forum_offset'] ?? 0);
    $noticeOffset = PersonalNoticePageState::normalizeOffset($query['notice_offset'] ?? 0);
    $rawOriginOffsets = $query['notice_offsets'] ?? [];
    $originOffsets = [];
    if (is_array($rawOriginOffsets)) {
      foreach (['comunicado', 'forum', 'alerta', 'amizade', 'aprendizagem', 'wiki', 'loja', 'doacao'] as $origin) {
        $rawOffset = $rawOriginOffsets[$origin] ?? NULL;
        $offset = PersonalNoticePageState::normalizeOffset($rawOffset);
        if ($offset > 0) {
          $originOffsets[$origin] = $offset;
        }
      }
    }
    $pagination = [
      'forum_offset' => $forumOffset,
      'notice_offset' => $noticeOffset,
      'notice_offsets' => $originOffsets,
    ];
    $naoLidasConversas = $this->conversas->count();
    if ($aba === 'mensagens') {
      $conteudo = $this->conteudoMensagens();
      $naoLidosAvisos = $this->mensagens->unreadNoticeCount();
    }
    else {
      $noticeResult = $this->conteudoAvisos($filters, $pagination);
      $conteudo = $noticeResult['build'];
      $naoLidosAvisos = $noticeResult['contador'];
    }

    return [
      '#cache' => [
        'contexts' => ['user'],
        'max-age' => 0,
        'tags' => ['aculta_aviso_list'],
      ],
      'description' => [
        '#plain_text' => $this->t('Respostas e atualizações dos tópicos que você segue, e suas conversas.'),
      ],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'account-data-nav',
          'aria-label' => $this->t('Seções de avisos e mensagens'),
          'data-portal-account-data-nav' => 'true',
        ],
        'links' => $this->tabLinks($aba, $naoLidosAvisos, $naoLidasConversas),
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'account-data-content',
          'data-portal-account-data-content' => 'true',
          'aria-busy' => 'false',
        ],
        'heading' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'h3',
          '#value' => $aba === 'avisos' ? $this->t('Avisos') : $this->t('Mensagens'),
          '#element_attributes' => new Attribute(['tabindex' => '-1']),
        ],
        'filters' => $aba === 'avisos' ? $this->noticeFiltersBuild($filters) : [],
        // A gaveta troca esta lista por detalhes no mesmo lugar, sem navegar.
        // O comportamento é implementado por messenger-drawers.js.
        'list' => [
          '#type' => 'container',
          '#attributes' => [
            'data-aculta-drawer-url' => Url::fromRoute(
              'aculta_messenger.avisos',
              ['aba' => $aba],
              ['query' => $aba === 'avisos' ? $filterQuery : []],
            )->toString(),
            'data-aculta-account-list' => '',
          ],
          'conteudo' => [
            '#type' => 'container',
            '#attributes' => ['data-aculta-drawer-content' => ''],
            'itens' => $conteudo,
          ],
        ],
      ],
    ];
  }

  /**
   * Builds tab links with unread counts and marks the active tab.
   */
  private function tabLinks(string $atual, int $avisos, int $conversas): array {
    $links = [];
    foreach (self::ABAS as $id) {
      $texto = (string) match ($id) {
        'avisos' => $this->t('Avisos'),
        'mensagens' => $this->t('Mensagens'),
      };
      $contador = $id === 'avisos' ? $avisos : $conversas;
      if ($contador > 0) {
        $texto .= ' (' . $contador . ')';
      }
      $link = PortalLinkElement::build(
        $texto,
        Url::fromRoute('aculta_messenger.avisos', ['aba' => $id]),
        [
          'data-portal-account-data-link' => 'true',
          'data-portal-ui' => 'account-data-link',
        ],
      );
      if ($id === $atual) {
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }
    return $links;
  }

  /**
   * Builds the notice tab with the sources available in the drawer.
   *
   * Links open a detail in place; dismissal follows the drawer policy.
   */
  private function conteudoAvisos(array $filters, array $pagination): array {
    // Dentro da própria aba a lista já é completa: sem o link Ver todos.
    return $this->mensagens->alertsBuild($filters, TRUE, FALSE, $pagination);
  }

  /**
   * Builds accessible source and read-state links for the notice list.
   */
  private function noticeFiltersBuild(array $filters): array {
    $origins = [
      '' => $this->t('Todas as origens'),
      'comunicado' => $this->t('Comunicados'),
      'forum' => $this->t('Fórum'),
      'alerta' => $this->t('Alertas do site'),
      'amizade' => $this->t('Amizades'),
      'aprendizagem' => $this->t('Aprendizagem'),
      'wiki' => $this->t('Wiki'),
      'loja' => $this->t('Loja'),
      'doacao' => $this->t('Doações'),
      'conta' => $this->t('Conta'),
    ];
    $states = [
      '' => $this->t('Todos os estados'),
      'unread' => $this->t('Não lidos'),
      'read' => $this->t('Lidos'),
    ];
    return [
      '#type' => 'container',
      '#attributes' => [
        'data-aculta-notice-filters' => '',
        'aria-label' => $this->t('Filtros de avisos'),
      ],
      'origins' => [
        '#type' => 'container',
        '#attributes' => [
          'role' => 'group',
          'aria-label' => $this->t('Filtrar por origem'),
        ],
        'links' => $this->filterLinksBuild($origins, 'origem', $filters),
      ],
      'read_state' => [
        '#type' => 'container',
        '#attributes' => [
          'role' => 'group',
          'aria-label' => $this->t('Filtrar por leitura'),
        ],
        'links' => $this->filterLinksBuild($states, 'leitura', $filters),
      ],
    ];
  }

  /**
   * Builds one set of filter links while preserving the other filter.
   */
  private function filterLinksBuild(array $options, string $parameter, array $filters): array {
    $links = [];
    foreach ($options as $value => $label) {
      $query = array_filter($filters, static fn(string $filter): bool => $filter !== '');
      if ($value === '') {
        unset($query[$parameter]);
      }
      else {
        $query[$parameter] = $value;
      }
      $url = Url::fromRoute(
        'aculta_messenger.avisos',
        ['aba' => 'avisos'],
        ['query' => $query],
      );
      $link = PortalLinkElement::build(
        (string) $label,
        $url,
        ['data-portal-ui' => 'account-data-link'],
      );
      if (($filters[$parameter] ?? '') === $value) {
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }
    return ['#theme' => 'item_list', '#items' => $links];
  }

  /**
   * Builds the messages tab content.
   *
   * Incoming friendship requests appear above the conversation list. Both
   * remain in their existing subsystems.
   */
  private function conteudoMensagens(): array {
    $incomingRequests = $this->friendshipManager->getIncomingRequests();
    $pedidos = $incomingRequests === []
      ? []
      : ['pedidos_amizade' => $this->formBuilder()->getForm(FriendRequestsForm::class, $incomingRequests)];
    return $pedidos + $this->mensagens->conversasBuild();
  }

}
