<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Controller\MessagesController;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_messenger.aviso_service'),
      $container->get('aculta_messenger.unread_thread_counter'),
      $container->get('current_user'),
      $container->get('class_resolver')->getInstanceFromDefinition(MessagesController::class),
    );
  }

  /** Renderiza a aba pedida. */
  public function view(string $aba = 'avisos'): array {
    if (!isset(self::ABAS[$aba])) {
      $aba = 'avisos';
    }
    $uid = (int) $this->usuarioAtual->id();
    $naoLidosAvisos = $this->avisos->naoLidosTotal($uid);
    $naoLidasConversas = $this->conversas->count();

    $conteudo = $aba === 'mensagens'
      ? $this->conteudoMensagens($naoLidasConversas)
      : $this->conteudoAvisos($uid);

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
        'list' => $conteudo,
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
  private function conteudoAvisos(int $uid): array {
    // Dentro da própria aba a lista já é completa: sem o link Ver todos.
    return $this->mensagens->alertsBuild(FALSE)['build'];
  }

  /**
   * Aba Mensagens: a mesma lista de conversas da gaveta da barra (MessagesController::conversasBuild), com o mesmo
   * comportamento. As conversas continuam no sistema de mensagens.
   */
  private function conteudoMensagens(int $naoLidas): array {
    return $this->mensagens->conversasBuild();
  }


}
