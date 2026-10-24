<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Controller\MessagesController;
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
 * Duas abas no mesmo padrão de Atividade e Meus Dados: Avisos (avisos por pessoa, só da própria conta) e Mensagens
 * (conversas, que continuam no sistema de mensagens; aqui só aparece a contagem e o link para abri-las). Os dois
 * sistemas não se misturam: esta página não grava nada e não cria conversa.
 */
final class AvisosMensagensController extends ControllerBase {

  /** Abas: id => rótulo. */
  public const ABAS = ['avisos' => 'Avisos', 'mensagens' => 'Mensagens'];

  public function __construct(
    private readonly AvisoService $avisos,
    private readonly UnreadThreadCounter $conversas,
    private readonly AccountInterface $usuarioAtual,
    private readonly MessagesController $mensagens,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly RequestStack $requestStack,
    private readonly NoticeListFilter $noticeListFilter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_messenger.aviso_service'),
      $container->get('aculta_messenger.unread_thread_counter'),
      $container->get('current_user'),
      $container->get('class_resolver')->getInstanceFromDefinition(MessagesController::class),
      $container->get('aculta_user.friendship_manager'),
      $container->get('request_stack'),
      $container->get('aculta_messenger.notice_list_filter'),
    );
  }

  /** Renderiza a aba pedida. */
  public function view(string $aba = 'avisos'): array {
    if (!isset(self::ABAS[$aba])) {
      $aba = 'avisos';
    }
    $uid = (int) $this->usuarioAtual->id();
    $filters = $this->noticeListFilter->normalize($this->requestStack->getCurrentRequest()?->query->all() ?? []);
    $filterQuery = array_filter($filters, static fn(string $value): bool => $value !== '');
    $naoLidosAvisos = $this->avisos->naoLidosTotal($uid);
    $naoLidasConversas = $this->conversas->count();

    $conteudo = $aba === 'mensagens'
      ? $this->conteudoMensagens($naoLidasConversas)
      : $this->conteudoAvisos($filters);

    return [
      '#cache' => ['contexts' => ['user'], 'max-age' => 0, 'tags' => ['aculta_aviso_list']],
      'description' => ['#plain_text' => $this->t('Respostas e atualizações dos tópicos que você segue, e suas conversas.')],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-data-nav', 'aria-label' => $this->t('Seções de avisos e mensagens'), 'data-portal-account-data-nav' => 'true'],
        'links' => $this->tabLinks($aba, $naoLidosAvisos, $naoLidasConversas),
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-data-content', 'data-portal-account-data-content' => 'true', 'aria-busy' => 'false'],
        'heading' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => $this->t(self::ABAS[$aba]), '#element_attributes' => new Attribute(['tabindex' => '-1'])],
        'filters' => $aba === 'avisos' ? $this->noticeFiltersBuild($filters) : [],
        // Mesmo comportamento da gaveta da barra: a área abaixo do cabeçalho é trocada pelo script da gaveta
        // (messenger-drawers.js), que busca o aviso ou a conversa e mostra no lugar, sem navegar para outra página.
        'list' => [
          '#type' => 'container',
          '#attributes' => [
            'data-aculta-drawer-url' => Url::fromRoute('aculta_messenger.avisos', ['aba' => $aba], ['query' => $aba === 'avisos' ? $filterQuery : []])->toString(),
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

  /** Links das abas, com contador não lido no rótulo, e a aba atual marcada. */
  private function tabLinks(string $atual, int $avisos, int $conversas): array {
    $links = [];
    foreach (self::ABAS as $id => $rotulo) {
      $texto = (string) $this->t($rotulo);
      $contador = $id === 'avisos' ? $avisos : $conversas;
      if ($contador > 0) {
        $texto .= ' (' . $contador . ')';
      }
      $link = PortalLinkElement::build($texto, Url::fromRoute('aculta_messenger.avisos', ['aba' => $id]), ['data-portal-account-data-link' => 'true', 'data-portal-ui' => 'account-data-link']);
      if ($id === $atual) {
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }
    return $links;
  }

  /**
   * Aba Avisos: a mesma lista da gaveta da barra (comunicados, avisos da pessoa e alertas), com o mesmo
   * comportamento: os links abrem o detalhe e a dispensa segue a regra da gaveta.
   */
  private function conteudoAvisos(array $filters): array {
    // Dentro da própria aba a lista já é completa: sem o link Ver todos.
    return $this->mensagens->alertsBuild($filters)['build'];
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
      '#attributes' => ['data-aculta-notice-filters' => '', 'aria-label' => $this->t('Filtros de avisos')],
      'origins' => [
        '#type' => 'container',
        '#attributes' => ['role' => 'group', 'aria-label' => $this->t('Filtrar por origem')],
        'links' => $this->filterLinksBuild($origins, 'origem', $filters),
      ],
      'read_state' => [
        '#type' => 'container',
        '#attributes' => ['role' => 'group', 'aria-label' => $this->t('Filtrar por leitura')],
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
      $link = PortalLinkElement::build((string) $label, $url, ['data-portal-ui' => 'account-data-link']);
      if (($filters[$parameter] ?? '') === $value) {
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }
    return ['#theme' => 'item_list', '#items' => $links];
  }

  /**
   * Aba Mensagens: pedidos de amizade recebidos (aceitar e recusar, no topo) e a mesma lista de conversas da gaveta da
   * barra (MessagesController::conversasBuild). As conversas continuam no sistema de mensagens.
   */
  private function conteudoMensagens(int $naoLidas): array {
    $pedidos = $this->friendshipManager->getIncomingRequests() === []
      ? []
      : ['pedidos_amizade' => $this->formBuilder()->getForm(FriendRequestsForm::class)];
    return $pedidos + $this->mensagens->conversasBuild();
  }


}
