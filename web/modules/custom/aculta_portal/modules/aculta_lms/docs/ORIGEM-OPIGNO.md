# Origem de código derivado do Opigno (aculta_lms)

Registro exigido pela decisão D-25 e pelo plano de independência do Opigno. Cada arquivo derivado tem uma entrada aqui.

> **Atualização (aculta_lms 0.1.0-dev.112, D-66):** os trechos SCORM abaixo foram removidos do módulo por decisão do responsável (substituir SCORM por xAPI 2.0). Este registro fica como histórico de proveniência do que foi derivado e depois retirado.

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

### js/scorm/aculta-scorm-api-2004.js

- Fonte de conceito: `js/lib/api-2004.js` do opigno_scorm 3.2.7.
- Tipo: reescrita. Nenhum bloco de código copiado. Códigos de erro e nomes de elementos CMI são os da especificação SCORM 2004 RTE.
- Mantido do conceito: métodos Initialize, Terminate, GetValue, SetValue, Commit, GetLastError, GetErrorString; registro de caminhos com somente leitura e somente escrita; eventos; commit com o identificador do SCO.
- Alterado de propósito:
  - sem `eval`, sem `console.log`, sem testes internos do Opigno (`cmi.__value__`, `cmi.__unimplemented__` e similares);
  - Terminate, SetValue e Commit devolvem `"false"` em erro, como a especificação manda;
  - estado de término é checado antes do de inicialização (123, 133, 143, 113), como manda a especificação;
  - suspend_data não é mais redirecionado por iframe; cada SCO tem sua própria instância da API;
  - adl.nav.request é guardado e entregue ao player pelo evento `navigation` no Terminate. O Opigno trocava o iframe pelo DOM; essa troca agora é do player;
  - objeto exportado como `window.API_1484_11`, nome exigido pelo conteúdo 2004.
- Dependência: jQuery e Drupal, somente para localizar o iframe do player. Declarada em `scorm.api2004`.
- Testado: 34/34 em Node com stubs (script de teste fora do repositório).

### js/scorm/aculta-scorm-player.js

- Fonte de conceito: `js/lib/player.js` e `js/opigno_scorm.player.js` do opigno_scorm 3.2.7 (ordem dos SCOs, navegação pedida pelo conteúdo, envio do progresso).
- Tipo: escrita própria. Nenhum bloco de código copiado.
- Sem DOM, sem jQuery: só ordem, destino de navegação e envio do commit. A árvore e o iframe ficam para a camada de apresentação.
- Navegação aceita apenas `continue`, `previous` e `{target=ID}choice` com alvo existente. Outros valores não navegam.
- Envio do commit é POST same-origin com JSON; falha de rede não quebra o conteúdo.
- Testado: 21/21 em Node, com as APIs 1.2 e 2004 reais carregadas em contextos separados.

## Não portado nesta entrada

- `js/lib/player.js` e `js/opigno_scorm.player.js`: player ainda não portado.
- `src/XML2Array.php`: substituído por `DOMDocument` no `ScormManifestParser`.
- `templates/*.twig`: não portados; o tema ACULTA420 define a apresentação.
- `opigno_scorm_ios_13.js`: não portado.

## Pendente

- Responsável: confirmar estas entradas antes do RC (D-25 exige revisão de licença e proveniência).
