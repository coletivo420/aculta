<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Docx;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;

/**
 * Monta o render array do leitor DOCX para um arquivo já autorizado.
 *
 * O builder não decide acesso: quem chama verifica Media, campo e arquivo.
 * Aqui só valida a estrutura do pacote e gera a URL pelo Core. Arquivos fora
 * de `public://` e `private://` nunca recebem viewer.
 */
final class DocxViewerBuilder {

  /**
   * Biblioteca do leitor DOCX (behavior e estilos).
   */
  public const LIBRARY = 'aculta_document_viewer/docx';

  /**
   * MIME type of WordprocessingML documents.
   */
  public const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

  /**
   * Bibliotecas locais carregadas pelo iframe, nessa ordem.
   */
  private const LOCAL_LIBS = [
    'libraries/docx-preview/dist/jszip.min.js',
    'libraries/docx-preview/dist/docx-preview.min.js',
  ];

  /**
   * Constructs the builder.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly FileSystemInterface $fileSystem,
    private readonly DocxPackageValidator $validator,
    private readonly ModuleExtensionList $moduleList,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Builds the viewer for an authorized DOCX, or NULL to use the Core link.
   *
   * @return array<string, mixed>|null
   *   Render array, or NULL when the file must not be rendered.
   */
  public function build(FileInterface $file, string $title): ?array {
    $uri = $file->getFileUri();
    if (!$this->isLocalUri($uri)) {
      return NULL;
    }
    $path = $this->fileSystem->realpath($uri);
    if ($path === FALSE || $path === '') {
      return NULL;
    }
    try {
      $this->validator->validate($path);
    }
    catch (DocxValidationException $exception) {
      $this->logger->notice('DOCX rejeitado pela validação estrutural: @reason (fid @fid).', [
        '@reason' => $exception->getReason(),
        '@fid' => $file->id(),
      ]);
      return NULL;
    }

    $modulePath = $this->moduleList->getPath('aculta_document_viewer');
    $libs = array_map(
      static fn(string $lib): string => Url::fromUri('base:' . $lib)->toString(),
      self::LOCAL_LIBS,
    );
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['aculta-docx-viewer'],
        'data-aculta-docx' => 'true',
        'data-aculta-docx-url' => $this->fileUrlGenerator->generateString($uri),
        'data-aculta-docx-frame-url' => Url::fromUri('base:' . $modulePath . '/docx/docx-frame.html')->toString(),
        'data-aculta-docx-libs' => json_encode($libs, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'data-aculta-docx-title' => $title,
      ],
      '#attached' => ['library' => [self::LIBRARY]],
      'viewer' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['aculta-docx-viewer__frame'], 'role' => 'region', 'aria-label' => $title],
      ],
    ];
  }

  /**
   * Accepts only Drupal-managed local schemes.
   */
  private function isLocalUri(string $uri): bool {
    return str_starts_with($uri, 'public://') || str_starts_with($uri, 'private://');
  }

}
