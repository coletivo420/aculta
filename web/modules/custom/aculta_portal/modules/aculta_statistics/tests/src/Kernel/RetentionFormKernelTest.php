<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Form\RetentionSettingsForm;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Redução de prazo exige confirmação quando há linhas a apagar.
 */
#[CoversClass(RetentionSettingsForm::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class RetentionFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_statistics'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('aculta_statistics', [DailyAggregateStorage::TABLE]);
    $this->installConfig(['aculta_statistics']);
  }

  /**
   * Reduzir de 90 para 30 dias com linhas antigas e sem confirmação bloqueia o salvamento.
   */
  public function testReductionWithRowsRequiresConfirmation(): void {
    $this->insertOldRow(60);

    $errors = $this->validate($this->valuesWithWikiDays(30, FALSE));

    $this->assertArrayHasKey('confirm_reduction', $errors);
  }

  /**
   * Com confirmação marcada, a mesma redução passa na validação.
   */
  public function testReductionWithConfirmationPasses(): void {
    $this->insertOldRow(60);

    $errors = $this->validate($this->valuesWithWikiDays(30, TRUE));

    $this->assertSame([], $errors);
  }

  /**
   * Aumentar prazo não apaga nada e não exige confirmação.
   */
  public function testIncreaseDoesNotRequireConfirmation(): void {
    $this->insertOldRow(60);

    $errors = $this->validate($this->valuesWithWikiDays(180, FALSE));

    $this->assertSame([], $errors);
  }

  /**
   * O formulário mostra um campo por métrica do catálogo, com o prazo atual.
   */
  public function testFormOffersEveryCatalogMetric(): void {
    $catalog = $this->container->get('aculta_statistics.catalog')->all();

    $form = RetentionSettingsForm::create($this->container)->buildForm([], new FormState());
    $fields = array_filter(array_keys($form['retention']), static fn (string $key): bool => $key[0] !== '#');

    $this->assertTrue($form['retention']['#tree']);
    $this->assertSame(array_keys($catalog), array_values($fields));
    $this->assertCount(count($catalog), $fields);
    foreach (array_keys($catalog) as $id) {
      $this->assertSame('select', $form['retention'][$id]['#type']);
      $this->assertSame([30, 90, 180, 365, 0], array_keys($form['retention'][$id]['#options']));
      $this->assertSame(90, $form['retention'][$id]['#default_value']);
    }
    $this->assertArrayHasKey('confirm_reduction', $form);
  }

  /**
   * Salvar grava o prazo de cada métrica, com "indefinido" como zero.
   */
  public function testSubmitPersistsPrazoPerMetric(): void {
    $catalog = array_keys($this->container->get('aculta_statistics.catalog')->all());
    $values = $this->valuesWithWikiDays(30, TRUE);
    $values['retention']['forum.topics.created'] = 0;

    $form_object = RetentionSettingsForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues($values);
    $form = $form_object->buildForm([], $form_state);
    $form_object->submitForm($form, $form_state);

    $saved = $this->config('aculta_statistics.settings')->get('retention');
    $this->assertCount(count($catalog), $saved);
    $this->assertSame(
      ['metric' => 'wiki.entries.read', 'days' => 30],
      array_values(array_filter($saved, static fn (array $row): bool => $row['metric'] === 'wiki.entries.read'))[0],
    );
    $this->assertSame(
      ['metric' => 'forum.topics.created', 'days' => 0],
      array_values(array_filter($saved, static fn (array $row): bool => $row['metric'] === 'forum.topics.created'))[0],
    );
  }

  /**
   * Valida o formulário com os valores informados e devolve os erros por campo.
   *
   * @param array<string, mixed> $values
   *   Valores enviados pelo formulário.
   *
   * @return array<string, mixed>
   *   Erros por nome de campo.
   */
  private function validate(array $values): array {
    $form_object = RetentionSettingsForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues($values);
    $form = $form_object->buildForm([], $form_state);
    $form_object->validateForm($form, $form_state);

    return $form_state->getErrors();
  }

  /**
   * Valores completos do formulário, com a Wiki no prazo informado.
   *
   * @return array<string, mixed>
   *   Valores para validação.
   */
  private function valuesWithWikiDays(int $days, bool $confirm): array {
    $retention = [];
    foreach (array_keys($this->container->get('aculta_statistics.catalog')->all()) as $id) {
      $retention[$id] = $id === 'wiki.entries.read' ? $days : 90;
    }
    return ['retention' => $retention, 'confirm_reduction' => $confirm];
  }

  /**
   * Insere uma linha da Wiki com a idade informada, em dias.
   */
  private function insertOldRow(int $ageDays): void {
    $storage = $this->container->get('aculta_statistics.storage');
    $this->container->get('database')->insert(DailyAggregateStorage::TABLE)
      ->fields(['metric' => 'wiki.entries.read', 'dimension' => '1', 'day' => $storage->currentDay() - $ageDays, 'total' => 1, 'updated' => 0])
      ->execute();
  }

}
