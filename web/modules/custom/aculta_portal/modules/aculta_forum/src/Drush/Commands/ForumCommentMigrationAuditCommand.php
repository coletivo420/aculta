<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Drush\Commands;

use Drupal\aculta_forum\Migration\ForumCommentMigrationAudit;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Audits Forum Comments before migration to revisionable posts.
 */
#[AsCommand(name: 'aculta-forum:audit-comment-migration', description: 'Inspeciona Comments do Fórum para preparar a migração a posts revisionáveis; somente leitura.')]
final class ForumCommentMigrationAuditCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'aculta_forum.comment_migration_audit')]
    private readonly ForumCommentMigrationAudit $audit,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $report = $this->audit->inspect();
    }
    catch (\Throwable $exception) {
      $output->writeln('<error>Auditoria interrompida: ' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }

    $labels = [
      'source_comments' => 'Comments na consulta de origem',
      'eligible_comments' => 'Comments em tópicos do purpose forum',
      'invalid_topic' => 'Hosts ausentes ou fora do bundle do Fórum',
      'wrong_purpose' => 'Tópicos em outro purpose ou sem domínio',
      'published_comments' => 'Comments publicados',
      'unpublished_comments' => 'Comments não publicados',
      'rejected_comments' => 'Comments marcados como rejeitados',
      'comments_with_legacy_author' => 'Comments com referência de autoria histórica',
      'comments_with_publication_timestamp' => 'Comments com timestamp de primeira publicação',
      'comments_with_translations' => 'Comments com traduções',
      'media_embeds' => 'Embeds de Media',
      'gallery_embeds' => 'Embeds de Gallery',
      'flaggings' => 'Denúncias/flags associadas aos Comments elegíveis',
      'topics' => 'Tópicos distintos elegíveis',
      'parent_outside_scope' => 'Pais existentes fora do escopo',
      'parent_missing' => 'Pais ausentes',
      'parent_cross_topic' => 'Relações pai/filho entre tópicos',
      'self_parent' => 'Relações de post consigo mesmo',
      'existing_revisionable_posts' => 'Posts revisionáveis existentes',
    ];
    foreach ($labels as $key => $label) {
      $output->writeln(sprintf('%s: %d', $label, (int) $report[$key]));
    }
    $output->writeln('Formatos de texto:');
    foreach ($report['body_formats'] as $format => $count) {
      $output->writeln(sprintf('  %s: %d', $format === '' ? '(vazio)' : $format, $count));
    }
    $output->writeln('Idiomas:');
    foreach ($report['languages'] as $language => $count) {
      $output->writeln(sprintf('  %s: %d', $language, $count));
    }
    $output->writeln('Flags por configuração:');
    foreach ($report['flaggings_by_flag'] as $flag => $count) {
      $output->writeln(sprintf('  %s: %d', $flag, $count));
    }
    $output->writeln('<comment>Dry run somente leitura: nenhum conteúdo ou configuração foi alterado.</comment>');
    return Command::SUCCESS;
  }

}
