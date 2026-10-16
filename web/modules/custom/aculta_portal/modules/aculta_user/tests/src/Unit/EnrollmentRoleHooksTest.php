<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Hook\EnrollmentRoleHooks;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Concessão de estudante na matrícula em curso (decisão de 2026-10-17).
 */
#[Group('aculta_user')]
class EnrollmentRoleHooksTest extends UnitTestCase {

  /**
   * Relação de grupo com plugin, tipo de grupo e conta informados.
   */
  private function relacao(string $plugin, string $tipoGrupo, ?UserInterface $conta, array $papeisDeGrupo = []): GroupRelationshipInterface {
    $campo = $this->createMock(FieldItemListInterface::class);
    $campo->method('getValue')->willReturn(array_map(static fn (string $id) => ['target_id' => $id], $papeisDeGrupo));
    $relacao = $this->createMock(GroupRelationshipInterface::class);
    $relacao->method('getPluginId')->willReturn($plugin);
    $relacao->method('getGroupTypeId')->willReturn($tipoGrupo);
    $relacao->method('getEntity')->willReturn($conta);
    $relacao->method('get')->with('group_roles')->willReturn($campo);
    return $relacao;
  }

  /**
   * Conta com os papéis informados; registra a gravação em $salvou.
   */
  private function conta(bool $anonima, bool $temEstudante, bool &$salvou, bool &$adicionou): UserInterface {
    $conta = $this->createMock(UserInterface::class);
    $conta->method('isAnonymous')->willReturn($anonima);
    $conta->method('hasRole')->with('estudante')->willReturn($temEstudante);
    $conta->method('addRole')->willReturnCallback(static function () use (&$adicionou) {
      $adicionou = TRUE;
      return NULL;
    });
    $conta->method('save')->willReturnCallback(static function () use (&$salvou) {
      $salvou = TRUE;
      return 1;
    });
    return $conta;
  }

  /**
   * Matrícula em curso concede estudante e grava a conta.
   */
  public function testMatriculaEmCursoConcedeEstudante(): void {
    $salvou = FALSE;
    $adicionou = FALSE;
    $conta = $this->conta(FALSE, FALSE, $salvou, $adicionou);
    (new EnrollmentRoleHooks())->groupRelationshipInsert($this->relacao('group_membership', 'lms_course', $conta));
    $this->assertTrue($adicionou);
    $this->assertTrue($salvou);
  }

  /**
   * Matrícula de aluno (sem papel de professor ou administração) concede estudante.
   */
  public function testMatriculaDeAlunoConcedeEstudante(): void {
    $salvou = FALSE;
    $adicionou = FALSE;
    $conta = $this->conta(FALSE, FALSE, $salvou, $adicionou);
    (new EnrollmentRoleHooks())->groupRelationshipInsert($this->relacao('group_membership', 'lms_course', $conta, []));
    $this->assertTrue($adicionou);
  }

  /**
   * Matrícula com papel de professor, criador ou administração não concede estudante.
   */
  #[DataProvider('papeisNaoAlunoCasos')]
  public function testMatriculaComPapelNaoAlunoNaoConcede(string $papel): void {
    $salvou = FALSE;
    $adicionou = FALSE;
    $conta = $this->conta(FALSE, FALSE, $salvou, $adicionou);
    (new EnrollmentRoleHooks())->groupRelationshipInsert($this->relacao('group_membership', 'lms_course', $conta, [$papel]));
    $this->assertFalse($adicionou);
    $this->assertFalse($salvou);
  }

  /**
   * Papéis de grupo que não são de aluno.
   */
  public static function papeisNaoAlunoCasos(): array {
    return [
      'professor' => ['lms_course-teacher'],
      'criador do curso' => ['lms_course-course_creator'],
      'administrador do LMS' => ['lms_course-lms_admin'],
      'membro administrador do LMS' => ['lms_course-lms_admin_member'],
    ];
  }

  /**
   * Outros plugins, outros tipos de grupo, visitantes e contas que já são estudantes não são alterados.
   *
   */
  #[DataProvider('semConcessaoCasos')]
  public function testSemConcessao(string $plugin, string $tipoGrupo, bool $anonima, bool $temEstudante): void {
    $salvou = FALSE;
    $adicionou = FALSE;
    $conta = $this->conta($anonima, $temEstudante, $salvou, $adicionou);
    (new EnrollmentRoleHooks())->groupRelationshipInsert($this->relacao($plugin, $tipoGrupo, $conta));
    $this->assertFalse($adicionou);
    $this->assertFalse($salvou);
  }

  /**
   * Casos sem concessão: [plugin, tipo de grupo, anônima, já é estudante].
   */
  public static function semConcessaoCasos(): array {
    return [
      'outro plugin de relação' => ['group_node', 'lms_course', FALSE, FALSE],
      'grupo que não é curso' => ['group_membership', 'lms_class', FALSE, FALSE],
      'visitante' => ['group_membership', 'lms_course', TRUE, FALSE],
      'já é estudante' => ['group_membership', 'lms_course', FALSE, TRUE],
    ];
  }

}
