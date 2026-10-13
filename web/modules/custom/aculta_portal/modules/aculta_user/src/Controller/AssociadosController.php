<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatório de associados (plano aculta_user, seção G2).
 *
 * Papel `associado` só é concedido pelo administrador. Na tela, o RG aparece mascarado; a exportação CSV, com
 * o RG completo, é restrita à permissão `export aculta associados` (somente administrador).
 */
final class AssociadosController extends ControllerBase {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManagerService) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /** Tabela na tela, com RG mascarado. */
  public function report(): array {
    $rows = array_map(fn (array $r): array => [$r['apelido'], $r['email'], $this->mask($r['rg']), $r['orgao'], $r['profissao'], $r['nascimento']], $this->rows());
    return [
      '#type' => 'table',
      '#header' => [$this->t('Apelido'), $this->t('E-mail'), $this->t('RG'), $this->t('Órgão emissor'), $this->t('Profissão'), $this->t('Nascimento')],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum associado cadastrado.'),
      '#cache' => ['tags' => ['user_list', 'profile_list'], 'contexts' => ['user.permissions']],
    ];
  }

  /** CSV completo, somente para quem tem a permissão de exportação. */
  public function export(): Response {
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['apelido', 'email', 'rg', 'orgao_emissor', 'profissao', 'data_nascimento']);
    foreach ($this->rows() as $r) {
      fputcsv($handle, [$r['apelido'], $r['email'], $r['rg'], $r['orgao'], $r['profissao'], $r['nascimento']]);
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
    $uids = $this->entityTypeManagerService->getStorage('user')->getQuery()
      ->accessCheck(FALSE)->condition('roles', 'associado')->sort('uid')->execute();
    $rows = [];
    foreach ($this->entityTypeManagerService->getStorage('user')->loadMultiple($uids) as $account) {
      $profile = $this->entityTypeManagerService->getStorage('profile')->loadByUser($account, 'participante');
      $get = static fn (string $field): string => $profile && $profile->hasField($field) ? (string) $profile->get($field)->value : '';
      $rows[] = [
        'apelido' => $get('field_nickname'),
        'email' => (string) $account->getEmail(),
        'rg' => $get('field_rg'),
        'orgao' => $get('field_orgao_emissor'),
        'profissao' => $get('field_profissao'),
        'nascimento' => $get('field_data_nascimento'),
      ];
    }
    return $rows;
  }

  private function mask(string $rg): string {
    return $rg === '' ? '' : str_repeat('•', max(0, strlen($rg) - 2)) . substr($rg, -2);
  }

}
