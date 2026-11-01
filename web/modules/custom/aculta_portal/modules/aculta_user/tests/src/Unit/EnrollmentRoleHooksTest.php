<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Hook\EnrollmentRoleHooks;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Concessão da categoria estudante na matrícula em curso (2026-10-17).
 *
 * A concessão é a criação do perfil "estudante" da conta, e não a alteração
 * de papel ou de campo da própria conta.
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
   * Conta com o id informado; anônima quando o id é nulo.
   */
  private function conta(bool $anonima, int $id = 7): UserInterface {
    $conta = $this->createMock(UserInterface::class);
    $conta->method('isAnonymous')->willReturn($anonima);
    $conta->method('id')->willReturn($id);
    return $conta;
  }

  /**
   * Gestor de entidades cujo storage de perfil já contém os ids informados.
   *
   * O callback de criação marca $criou e registra os valores usados.
   */
  private function entityTypeManager(array $perfisExistentes, bool &$criou, ?array &$valoresCriados = NULL): EntityTypeManagerInterface {
    $consulta = $this->createMock(QueryInterface::class);
    $consulta->method('accessCheck')->willReturnSelf();
    $consulta->method('condition')->willReturnSelf();
    $consulta->method('range')->willReturnSelf();
    $consulta->method('execute')->willReturn($perfisExistentes);

    $perfil = $this->createMock(EntityInterface::class);
    $perfil->method('save')->willReturn(1);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($consulta);
    $storage->method('create')->willReturnCallback(static function (array $valores) use ($perfil, &$criou, &$valoresCriados) {
      $criou = TRUE;
      $valoresCriados = $valores;
      return $perfil;
    });

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    return $entityTypeManager;
  }

  /**
   * Matrícula em curso concede estudante criando o perfil da conta.
   */
  public function testMatriculaEmCursoConcedeEstudante(): void {
    $criou = FALSE;
    $valores = NULL;
    $conta = $this->conta(FALSE);
    (new EnrollmentRoleHooks($this->entityTypeManager([], $criou, $valores)))
      ->groupRelationshipInsert($this->relacao('group_membership', 'lms_course', $conta));
    $this->assertTrue($criou);
    $this->assertSame(['type' => 'estudante', 'uid' => 7], $valores);
  }

  /**
   * Aluno sem papel de professor ou administração concede estudante.
   */
  public function testMatriculaDeAlunoConcedeEstudante(): void {
    $criou = FALSE;
    $conta = $this->conta(FALSE);
    (new EnrollmentRoleHooks($this->entityTypeManager([], $criou)))
      ->groupRelationshipInsert($this->relacao('group_membership', 'lms_course', $conta, []));
    $this->assertTrue($criou);
  }

  /**
   * Papel de professor, criador ou administração não concede estudante.
   */
  #[DataProvider('papeisNaoAlunoCasos')]
  public function testMatriculaComPapelNaoAlunoNaoConcede(string $papel): void {
    $criou = FALSE;
    $conta = $this->conta(FALSE);
    (new EnrollmentRoleHooks($this->entityTypeManager([], $criou)))
      ->groupRelationshipInsert($this->relacao('group_membership', 'lms_course', $conta, [$papel]));
    $this->assertFalse($criou);
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
   * Outros plugins, outros tipos de grupo e visitantes não são alterados.
   */
  #[DataProvider('semConcessaoCasos')]
  public function testSemConcessao(string $plugin, string $tipoGrupo, bool $anonima): void {
    $criou = FALSE;
    $conta = $this->conta($anonima, $anonima ? 0 : 7);
    (new EnrollmentRoleHooks($this->entityTypeManager([], $criou)))
      ->groupRelationshipInsert($this->relacao($plugin, $tipoGrupo, $conta));
    $this->assertFalse($criou);
  }

  /**
   * Casos sem concessão: [plugin, tipo de grupo, anônima].
   */
  public static function semConcessaoCasos(): array {
    return [
      'outro plugin de relação' => ['group_node', 'lms_course', FALSE],
      'grupo que não é curso nem turma' => ['group_membership', 'lms_other', FALSE],
      'visitante' => ['group_membership', 'lms_course', TRUE],
    ];
  }

  /**
   * Conta que já possui a categoria não recebe um segundo perfil.
   */
  public function testContaQueJaTemCategoriaNaoCriaOutroPerfil(): void {
    $criou = FALSE;
    $conta = $this->conta(FALSE);
    (new EnrollmentRoleHooks($this->entityTypeManager(['profile:1'], $criou)))
      ->groupRelationshipInsert($this->relacao('group_membership', 'lms_course', $conta));
    $this->assertFalse($criou);
  }

}
