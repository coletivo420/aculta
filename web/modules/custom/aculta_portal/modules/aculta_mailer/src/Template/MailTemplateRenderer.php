<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Template;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\aculta_mailer\Entity\MailLayout;
use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_mailer\Api\PublicUrlInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Monta assunto e corpo: o modelo salvo quando existe e está ativo; senão, o texto padrão do tipo.
 *
 * Substitui somente `{{ variavel }}` declarada pelo tipo. Variável ausente vira vazio, e nunca se executa código.
 */
final class MailTemplateRenderer {

  /**
   * Placeholder aceito em modelos: `{{ nome_da_variavel }}`.
   */
  public const PLACEHOLDER = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrls,
    private readonly ?PublicUrlInterface $publicUrls = NULL,
  ) {}

  /**
   * Assunto e corpo finais para o contexto da mensagem.
   *
   * @param \Drupal\aculta_mailer\Type\MailTypeInterface $type
   *   Tipo da mensagem.
   * @param array $context
   *   Valores da mensagem.
   *
   * @return array
   *   Chaves `subject` e `body`.
   */
  public function render(MailTypeInterface $type, array $context): array {
    $template = $this->activeTemplate($type->id());
    $subject = $template?->getSubject() ?? $type->defaultSubject();
    $body = $template?->getBody() ?? $type->defaultBody();
    $body = self::fill($body, $context);
    $layout = $template !== NULL ? $this->layoutFor($template) : NULL;
    if ($layout !== NULL) {
      $body = $this->wrap($layout, $body);
    }
    return [
      'subject' => self::fill($subject, $context),
      'body' => $body,
    ];
  }

  /**
   * Prévia com os valores de exemplo do tipo, para o editor.
   *
   * @return array
   *   Chaves `subject` e `body`, já com exemplos.
   */
  public function preview(MailTypeInterface $type, string $subject, string $body): array {
    $samples = [];
    foreach ($type->variables() as $name => $variable) {
      $samples[$name] = $variable['sample'];
    }
    return [
      'subject' => self::fill($subject, $samples),
      'body' => self::fill($body, $samples),
    ];
  }

  /**
   * Substitui os placeholders declarados por valores escalares do contexto.
   *
   * @param string $text
   *   Texto com placeholders.
   * @param array $context
   *   Valores disponíveis.
   */
  public static function fill(string $text, array $context): string {
    return (string) preg_replace_callback(self::PLACEHOLDER, static function (array $match) use ($context): string {
      $value = $context[$match[1]] ?? NULL;
      return is_scalar($value) ? (string) $value : '';
    }, $text);
  }

  /**
   * Nomes de placeholders do texto que o tipo não declara.
   *
   * @param string $text
   *   Texto a verificar.
   * @param string[] $allowed
   *   Variáveis declaradas pelo tipo.
   *
   * @return string[]
   *   Nomes desconhecidos, sem repetição.
   */
  public static function unknownPlaceholders(string $text, array $allowed): array {
    preg_match_all(self::PLACEHOLDER, $text, $matches);
    return array_values(array_unique(array_diff($matches[1], $allowed)));
  }

  /**
   * Envolve o corpo já preenchido no cabeçalho e no rodapé do layout, com os campos da marca.
   *
   * O corpo entra como valor: não é reprocessado pelos placeholders.
   */
  private function wrap(MailLayout $layout, string $content): string {
    $brand = $this->entityTypeManager->getStorage(MailBrand::ENTITY_TYPE)->load($layout->getBrand());
    $values = [
      'brand_name' => $brand instanceof MailBrand ? $brand->getName() : '',
      'brand_footer' => $brand instanceof MailBrand ? $brand->getFooter() : '',
    ];
    $text = $layout->getHeader() . "\n\n{{ content }}\n\n" . $layout->getFooter();
    return self::fill($text, $values + ['content' => $content]);
  }

  /**
   * URL absoluta do logo da marca do layout ativo do tipo, ou vazio quando não há logo.
   */
  public function logoUrl(MailTypeInterface $type): string {
    $template = $this->activeTemplate($type->id());
    $layout = $template !== NULL ? $this->layoutFor($template) : NULL;
    $brand = $layout !== NULL ? $this->entityTypeManager->getStorage(MailBrand::ENTITY_TYPE)->load($layout->getBrand()) : NULL;
    if (!$brand instanceof MailBrand || $brand->getLogoFid() === 0) {
      return '';
    }
    $file = $this->entityTypeManager->getStorage('file')->load($brand->getLogoFid());
    if ($file === NULL) {
      return '';
    }
    // Host do ambiente pelo aculta_portal; sem ele, a URL gerada pelo Core (pode ser http://default na linha de comando).
    $absolute = $this->publicUrls?->absolute($this->fileUrls->generateString($file->getFileUri())) ?? '';
    return $absolute !== '' ? $absolute : $this->fileUrls->generateAbsoluteString($file->getFileUri());
  }

  /**
   * Layout ativo do modelo (existente), ou NULL.
   */
  private function layoutFor(MailTemplate $template): ?MailLayout {
    if ($template->getLayout() === '') {
      return NULL;
    }
    $layout = $this->entityTypeManager->getStorage(MailLayout::ENTITY_TYPE)->load($template->getLayout());
    return $layout instanceof MailLayout ? $layout : NULL;
  }

  /**
   * Modelo ativo para o tipo, ou NULL.
   */
  private function activeTemplate(string $id): ?MailTemplate {
    $template = $this->entityTypeManager->getStorage(MailTemplate::ENTITY_TYPE)->load($id);
    return $template instanceof MailTemplate && $template->status() ? $template : NULL;
  }

}
