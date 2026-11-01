<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Template;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\aculta_mailer\Entity\MailLayout;
use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\filter\FilterFormatInterface;

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
    $html = $template?->isHtmlBody() ?? FALSE;
    $body = self::fill($body, $context, $html);
    $layout = $template !== NULL ? $this->layoutFor($template) : NULL;
    if ($layout !== NULL) {
      $body = $this->wrap($layout, $body, $html);
    }
    if ($html) {
      // O corpo HTML vem do editor e não passou pelo filtro no envio: filtra com o formato restrito.
      $body = $this->sanitizeHtml($body);
    }
    return [
      'subject' => self::fill($subject, $context),
      'body' => $body,
      'html' => $html,
      'layout' => $layout !== NULL,
    ];
  }

  /**
   * Prévia com os valores de exemplo do tipo, para o editor.
   *
   * @return array
   *   Chaves `subject` e `body`, já com exemplos.
   */
  public function preview(MailTypeInterface $type, string $subject, string $body, bool $html = FALSE): array {
    $samples = [];
    foreach ($type->variables() as $name => $variable) {
      $samples[$name] = $variable['sample'];
    }
    return [
      'subject' => self::fill($subject, $samples),
      // Prévia em HTML passa pelo mesmo filtro do envio, para mostrar o resultado real.
      'body' => $html ? $this->sanitizeHtml(self::fill($body, $samples, TRUE)) : self::fill($body, $samples),
    ];
  }

  /**
   * Substitui os placeholders declarados por valores escalares do contexto.
   *
   * @param string $text
   *   Texto com placeholders.
   * @param array $context
   *   Valores disponíveis.
   * @param bool $escape
   *   TRUE quando o texto é HTML: cada valor é escapado, para que dados do
   *   usuário nunca entrem como marcação. O conteúdo fixo do modelo não muda.
   */
  public static function fill(string $text, array $context, bool $escape = FALSE): string {
    return (string) preg_replace_callback(self::PLACEHOLDER, static function (array $match) use ($context, $escape): string {
      $value = $context[$match[1]] ?? NULL;
      if (!is_scalar($value)) {
        return '';
      }
      $value = (string) $value;
      return $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
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
  private function wrap(MailLayout $layout, string $content, bool $html = FALSE): string {
    $brand = $this->entityTypeManager->getStorage(MailBrand::ENTITY_TYPE)->load($layout->getBrand());
    $values = [
      'brand_name' => $brand instanceof MailBrand ? $brand->getName() : '',
      'brand_footer' => $brand instanceof MailBrand ? $brand->getFooter() : '',
    ];
    $header = $layout->getHeader();
    $footer = $layout->getFooter();
    if ($html) {
      // Cabeçalho, rodapé e marca são texto simples: com corpo HTML viram HTML seguro (escape e quebras).
      $values = array_map([self::class, 'plainToHtml'], $values);
      $header = self::plainToHtml($header);
      $footer = self::plainToHtml($footer);
    }
    $text = $header . "\n\n{{ content }}\n\n" . $footer;
    return self::fill($text, $values + ['content' => $content]);
  }

  /**
   * Texto simples para HTML: escapa a marcação e troca quebras de linha por <br>.
   */
  private static function plainToHtml(string $text): string {
    return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), FALSE);
  }

  /**
   * Passa o HTML pelos filtros habilitados do formato restrito do modelo.
   *
   * Sem o formato, falha fechada: o texto é escapado inteiro, sem marcação.
   */
  private function sanitizeHtml(string $html): string {
    $format = $this->entityTypeManager->getStorage('filter_format')->load(MailTemplate::BODY_FORMAT_HTML);
    if (!$format instanceof FilterFormatInterface) {
      return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    // Script e estilo saem com o conteúdo; o filtro só tira a tag e manteria o texto.
    $text = (string) preg_replace('#<(script|style)\\b[^>]*>.*?</\\1\\s*>#is', '', $html);
    foreach ($format->filters() as $filter) {
      if ($filter->status) {
        $text = $filter->process($text, '')->getProcessedText();
      }
    }
    return $text;
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
