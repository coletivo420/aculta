# Origem de código derivado do Opigno (aculta_lms)

Registro exigido pela decisão D-25 e pelo plano de independência do Opigno. Cada arquivo derivado tem uma entrada aqui.

## Fonte

- Pacote: `opigno_scorm` 3.2.7, licença GPL-2.0-or-later (`LICENSE.txt` do pacote).
- Licença do aculta_lms: GPL-2.0-or-later. Compatível com a fonte.

## Entradas

### js/scorm/aculta-scorm-api-12.js

- Fonte de conceito: `js/lib/api-1.2.js` do opigno_scorm 3.2.7.
- Tipo: reescrita. Nenhum bloco de código foi copiado. Os textos dos códigos de erro e a lista de caminhos CMI são os da especificação SCORM 1.2, que coincidem com os usados pelo Opigno.
- Mantido do conceito: códigos de retorno, registro de caminhos com somente leitura e somente escrita, eventos de inicialização, pré e pós-commit, e commit com o identificador do SCO.
- Alterado de propósito:
  - sem `eval` (o Opigno monta código por string para criar arrays);
  - sem `console.log` de depuração;
  - sem referência à API 2004 dentro da 1.2 (havia `OpignoScorm2004API` no código 1.2);
  - `LMSInitialize`, `LMSSetValue` e `LMSCommit` devolvem `"false"` em erro, como manda a especificação (o original devolvia `"true"` para evitar alertas);
  - índice de coleção com lacuna é recusado;
  - o SCO vem das opções da API ou do iframe do player, não de busca fixa;
  - commit não grava nada: dispara `commit12` para o player.
- Dependência: jQuery e Drupal, somente para localizar o iframe do player. Declarada em `scorm.api12`.
- Testado: 32/32 em Node com stubs de `window`, `jQuery` e `Drupal` (script de teste fora do repositório).

## Não portado nesta entrada

- `js/lib/api-2004.js`: fase seguinte, com entrada própria aqui.
- `js/lib/player.js` e `js/opigno_scorm.player.js`: player ainda não portado.
- `src/XML2Array.php`: substituído por `DOMDocument` no `ScormManifestParser`.
- `templates/*.twig`: não portados; o tema ACULTA420 define a apresentação.
- `opigno_scorm_ios_13.js`: não portado.

## Pendente

- Responsável: confirmar esta entrada antes do RC (D-25 exige revisão de licença e proveniência).
