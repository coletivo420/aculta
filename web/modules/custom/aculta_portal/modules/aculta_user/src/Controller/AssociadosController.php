<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\aculta_user\Associado\AssociadoMarker;
use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\user\UserInterface;

/**
 * Relatório de associados (situação, data de adesão e pedidos pendentes).
 *
 * Associado é situação definida pelo administrador (decisão de 2026-10-21). Na tela, o RG aparece mascarado; a exportação
 * CSV, com o RG completo, é restrita à permissão `export aculta associados` (somente administrador).
 */
final class AssociadosController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AssociadoMarker $marker,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_user.associado_marker'),
      $container->get('date.formatter'),
    );
  }

  /** Tela: pedidos pendentes (com link para decidir) e situações, com RG mascarado. */
  public function report(): array {
    $pendentes = [];
    foreach ($this->marker->pendentes() as $uid) {
      $conta = $this->entityTypeManagerService->getStorage('user')->load($uid);
      if ($conta instanceof UserInterface) {
        $pendentes[] = [
          $conta->getDisplayName(),
          (string) $conta->getEmail(),
          [
            'data' => ['#type' => 'link', '#title' => $this->t('Decidir'), '#url' => Url::fromRoute('aculta_user.associado_situacao', ['user' => $uid])],
          ],
        ];
      }
    }
    $rows = array_map(fn (array $r): array => [
      $r['apelido'], $r['email'], $r['situacao'], $r['adesao'], $this->mask($r['rg']), $r['orgao'], $r['profissao'], $r['nascimento'],
      ['data' => ['#type' => 'link', '#title' => $this->t('Alterar'), '#url' => Url::fromRoute('aculta_user.associado_situacao', ['user' => $r['uid']])]],
    ], $this->rows());
    return [
      'pendentes' => [
        '#type' => 'table',
        '#caption' => $this->t('Pedidos de adesão em análise'),
        '#header' => [$this->t('Pessoa'), $this->t('E-mail'), $this->t('Ações')],
        '#rows' => $pendentes,
        '#empty' => $this->t('Nenhum pedido em análise.'),
      ],
      'situacoes' => [
        '#type' => 'table',
        '#caption' => $this->t('Associados e situações'),
        '#header' => [$this->t('Apelido'), $this->t('E-mail'), $this->t('Situação'), $this->t('Data de adesão'), $this->t('RG'), $this->t('Órgão emissor'), $this->t('Profissão'), $this->t('Nascimento'), $this->t('Ações')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum associado cadastrado.'),
      ],
      '#cache' => ['tags' => ['user_list', 'profile_list', 'aculta_associado_list'], 'contexts' => ['user.permissions']],
    ];
  }

  /** CSV completo, somente para quem tem a permissão de exportação. */
  public function export(): Response {
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['apelido', 'email', 'situacao', 'data_adesao', 'rg', 'orgao_emissor', 'profissao', 'data_nascimento']);
    foreach ($this->rows() as $r) {
      fputcsv($handle, [$r['apelido'], $r['email'], $r['situacao'], $r['adesao_iso'], $r['rg'], $r['orgao'], $r['profissao'], $r['nascimento']]);
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return new Response($csv, 200, [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="associados.csv"',
      'Cache-Control' => 'no-store',
    ]);
  }

  /** @return array<int, array<string, string>> */
  private function rows(): array {
    $rows = [];
    foreach ($this->marker->listadas() as $linha) {
      $account = $this->entityTypeManagerService->getStorage('user')->load($linha['uid']);
      if (!$account instanceof UserInterface) {
        continue;
      }
      $profile = PerfilDoPapel::principal($this->entityTypeManagerService->getStorage('profile'), $account);
      $get = static fn (string $field): string => $profile && $profile->hasField($field) ? (string) $profile->get($field)->value : '';
      $adesao = $linha['data_adesao'];
      $rows[] = [
        'uid' => (string) $linha['uid'],
        'apelido' => $get('field_nickname'),
        'email' => (string) $account->getEmail(),
        'situacao' => $this->rotulo($linha['situacao']),
        'adesao' => $adesao === NULL ? '' : $this->dateFormatter->format($adesao, 'custom', 'd/m/Y'),
        'adesao_iso' => $adesao === NULL ? '' : $this->dateFormatter->format($adesao, 'custom', 'Y-m-d'),
        'rg' => $get('field_rg'),
        'orgao' => $get('field_orgao_emissor'),
        'profissao' => $get('field_profissao'),
        'nascimento' => $get('field_data_nascimento'),
      ];
    }
    return $rows;
  }

  private function rotulo(string $situacao): string {
    return match ($situacao) {
      AssociadoMarker::ATIVO => (string) $this->t('Ativo'),
      AssociadoMarker::SUSPENSO => (string) $this->t('Suspenso'),
      AssociadoMarker::DESLIGADO => (string) $this->t('Desligado'),
      AssociadoMarker::CONVIDADO => (string) $this->t('Convidado (aguardando aceite)'),
      default => $situacao,
    };
  }

  private function mask(string $rg): string {
    return $rg === '' ? '' : str_repeat('•', max(0, strlen($rg) - 2)) . substr($rg, -2);
  }

}
