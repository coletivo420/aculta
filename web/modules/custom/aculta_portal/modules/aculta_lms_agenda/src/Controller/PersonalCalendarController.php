<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Controller;

use Drupal\aculta_calendar\Ics\IcsEventBuilder;
use Drupal\aculta_lms_agenda\AgendaEntriesProvider;
use Drupal\aculta_lms_agenda\Calendar\PersonalCalendarSeries;
use Drupal\aculta_lms_agenda\Calendar\PersonalCalendarTokens;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * .ics pessoal: a agenda das turmas em que o aluno está matriculado, por link com token revogável.
 *
 * Gerar e revogar exigem login e token CSRF. O link de exportação leva só o token. Respostas são
 * privadas e sem indexação.
 */
final class PersonalCalendarController extends ControllerBase {

  /**
   * Janela da exportação: 30 dias antes e 365 depois de agora.
   */
  private const ANTES_DIAS = 30;
  private const DEPOIS_DIAS = 365;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly PersonalCalendarTokens $tokens,
    private readonly AgendaEntriesProvider $agenda,
    private readonly AccountInterface $usuario,
    private readonly TimeInterface $tempo,
    private readonly EntityTypeManagerInterface $entidades,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_lms_agenda.tokens'),
      $container->get('aculta_lms_agenda.entries'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Página do usuário: gerar ou revogar o link.
   */
  public function pagina(): array {
    $gerar = $this->urlComCsrf('aculta_lms_agenda.gerar');
    $revogar = $this->urlComCsrf('aculta_lms_agenda.revogar');
    return [
      '#markup' => '<p>' . $this->t('O link do calendário mostra os prazos e eventos das turmas em que você está matriculado. Quem tem o link consegue ver essas datas, por isso não o compartilhe.') . '</p>'
        . '<p><a href="' . Html::escape($gerar) . '">' . $this->t('Gerar link (substitui o anterior)') . '</a></p>'
        . '<p><a href="' . Html::escape($revogar) . '">' . $this->t('Revogar link') . '</a></p>',
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Gera um token novo e mostra o link uma única vez.
   */
  public function gerar(): Response {
    $token = $this->tokens->gerar((int) $this->usuario->id());
    $url = $this->url('aculta_lms_agenda.exportar', ['token' => $token], ['absolute' => TRUE]);
    $html = '<p>' . $this->t('Link gerado. Copie agora: ele não será mostrado de novo.') . '</p>'
      . '<p><a href="' . Html::escape($url) . '">' . Html::escape($url) . '</a></p>';
    return $this->privado($html);
  }

  /**
   * Revoga o token do usuário.
   */
  public function revogar(): Response {
    $existia = $this->tokens->revogar((int) $this->usuario->id());
    return $this->privado('<p>' . ($existia ? $this->t('Link revogado.') : $this->t('Não havia link ativo.')) . '</p>');
  }

  /**
   * Exporta a agenda do dono do token.
   */
  public function exportar(string $token): Response {
    $uid = $this->tokens->usuarioDoToken($token);
    if ($uid === NULL) {
      return new Response('Link inválido ou revogado.', 404, $this->cabecalhosPrivados('text/plain; charset=UTF-8'));
    }
    $dono = $this->entidades->getStorage('user')->load($uid);
    if ($dono === NULL) {
      return new Response('Link inválido ou revogado.', 404, $this->cabecalhosPrivados('text/plain; charset=UTF-8'));
    }
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    $entradas = $this->agenda->forAccount($dono, $agora->modify('-' . self::ANTES_DIAS . ' days'), $agora->modify('+' . self::DEPOIS_DIAS . ' days'));
    $conteudo = (new IcsEventBuilder('aculta-lms'))->buildMany(PersonalCalendarSeries::paraIcs($entradas, $agora));
    return new Response($conteudo, 200, $this->cabecalhosPrivados('text/calendar; charset=UTF-8') + [
      'Content-Disposition' => 'attachment; filename="minha-agenda-aculta.ics"',
    ]);
  }

  /**
   * URL com token CSRF para uma rota que exige _csrf_token.
   */
  private function urlComCsrf(string $rota): string {
    $caminho = ltrim((string) $this->url($rota), '/');
    $token = \Drupal::csrfToken()->get($caminho);
    return (string) $this->url($rota, [], ['query' => ['token' => $token]]);
  }

  /**
   * Resposta HTML privada, sem indexação e sem cache compartilhado.
   */
  private function privado(string $corpo): Response {
    $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="robots" content="noindex, nofollow"><title>'
      . Html::escape((string) $this->t('Calendário pessoal')) . '</title></head><body><main>' . $corpo . '</main></body></html>';
    return new Response($html, 200, $this->cabecalhosPrivados('text/html; charset=UTF-8'));
  }

  /**
   * Cabeçalhos de resposta privada.
   *
   * @return array<string, string>
   */
  private function cabecalhosPrivados(string $tipo): array {
    return [
      'Content-Type' => $tipo,
      'Cache-Control' => 'private, no-store, max-age=0',
      'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ];
  }

}
