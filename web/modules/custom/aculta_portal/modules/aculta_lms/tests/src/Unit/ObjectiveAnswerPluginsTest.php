<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Assessment\ObjectiveAnswerScorer;
use Drupal\aculta_lms\Learning\CourseActivity;
use Drupal\aculta_lms\Plugin\ActivityAnswer\FillBlanksAnswerPlugin;
use Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginResolver;
use Drupal\aculta_lms\Plugin\ActivityAnswer\SelectAnswerPlugin;
use Drupal\aculta_lms\Plugin\ActivityAnswer\TrueFalseAnswerPlugin;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the ACULTA objective answer plugins without Drupal Kernel. */
#[CoversClass(FillBlanksAnswerPlugin::class)]
#[CoversClass(ObjectiveAnswerPluginResolver::class)]
#[CoversClass(SelectAnswerPlugin::class)]
#[CoversClass(TrueFalseAnswerPlugin::class)]
final class ObjectiveAnswerPluginsTest extends UnitTestCase {

  /**
   * Avaliador de respostas objetivas usado nos testes.
   *
   * @var \Drupal\aculta_lms\Assessment\ObjectiveAnswerScorer
   */
  private ObjectiveAnswerScorer $scorer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->scorer = new ObjectiveAnswerScorer();
  }

  /**
   * Verifica a pontuação do plugin verdadeiro/falso em cada caso.
   */
  public function testTrueFalsePluginScoresCorrectWrongAndEmptyAnswers(): void {
    $plugin = new TrueFalseAnswerPlugin($this->scorer);
    $activity = $this->activity('true_false');
    $this->assertSame(100.0, $plugin->score($activity, TRUE, TRUE));
    $this->assertSame(0.0, $plugin->score($activity, FALSE, TRUE));
    $this->assertSame(0.0, $plugin->score($activity, NULL, TRUE));
  }

  /**
   * Verifica que o plugin select exige a seleção exata e trata ausência.
   */
  public function testSelectPluginRequiresTheExactSelectionAndHandlesEmptyAnswer(): void {
    $plugin = new SelectAnswerPlugin($this->scorer);
    $activity = $this->activity('select');
    $this->assertSame(100.0, $plugin->score($activity, ['second', 'first'], ['first', 'second']));
    $this->assertSame(0.0, $plugin->score($activity, ['first'], ['first', 'second']));
    $this->assertSame(0.0, $plugin->score($activity, [], ['first', 'second']));
    $this->assertSame(0.0, $plugin->score($activity, ['first'], []));
  }

  /**
   * Verifica a normalização de respostas do plugin de lacunas.
   */
  public function testFillBlanksPluginNormalizesCorrectWrongAndEmptyAnswers(): void {
    $plugin = new FillBlanksAnswerPlugin($this->scorer);
    $activity = $this->activity('fill_blanks');
    $correct = [0 => ['São Paulo', 'SP'], 1 => ['Brasil']];
    $this->assertSame(100.0, $plugin->score($activity, [0 => '  SÃO   PAULO ', 1 => 'brasil'], $correct));
    $this->assertSame(50.0, $plugin->score($activity, [0 => 'sp', 1 => 'Rio'], $correct));
    $this->assertSame(0.0, $plugin->score($activity, [], $correct));
  }

  /**
   * Verifica que o resolvedor escolhe o plugin pelo tipo estável.
   */
  public function testResolverSelectsPluginFromStableActivityType(): void {
    $resolver = new ObjectiveAnswerPluginResolver(
      new TrueFalseAnswerPlugin($this->scorer),
      new SelectAnswerPlugin($this->scorer),
      new FillBlanksAnswerPlugin($this->scorer),
    );
    $activity = $this->activity('select');
    $this->assertInstanceOf(SelectAnswerPlugin::class, $resolver->forActivity($activity));
    $this->assertSame(100.0, $resolver->score($activity, ['yes'], ['yes']));
  }

  /**
   * Verifica que o plugin rejeita atividade de outro tipo estável.
   */
  public function testPluginRejectsActivityWithAnotherStableType(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new TrueFalseAnswerPlugin($this->scorer))->score($this->activity('select'), TRUE, TRUE);
  }

  /**
   * Cria uma atividade de curso para o tipo informado.
   *
   * @param string $type
   *   Tipo estável da atividade.
   *
   * @return \Drupal\aculta_lms\Learning\CourseActivity
   *   Atividade de curso instanciada.
   */
  private function activity(string $type): CourseActivity {
    return new CourseActivity(10, 20, $type, TRUE);
  }

}
