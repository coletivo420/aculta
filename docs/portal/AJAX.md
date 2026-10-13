# Política AJAX e interatividade

A experiência do ACULTA Portal deve permanecer moderna e assíncrona quando isso
melhorar usabilidade, mas o Portal não deve manter um segundo framework de AJAX.

## Ordem de preferência

1. **Views AJAX** para listagens, paginação e filtros;
2. **Form API `#ajax`** para formulários Drupal;
3. **Drupal AJAX API** (`use-ajax`, `Drupal.ajax`, `AjaxResponse` e commands);
4. **HTMX do Drupal Core** quando reduzir claramente código e permanecer
   compatível com a arquitetura da tela;
5. JavaScript custom apenas para comportamento específico que as APIs acima não
   resolvem adequadamente.

Drupal 11.3 introduziu integração HTMX nativa e o projeto está em Drupal 11.4,
portanto HTMX pode ser avaliado sem adicionar biblioteca paralela.

## Progressive enhancement

Toda área essencial deve continuar funcional com navegação/form submit normal
quando JavaScript falhar.

AJAX melhora a experiência; não pode ser o único caminho para:

- salvar dados pessoais;
- alterar endereço;
- alterar senha/e-mail;
- criar tópico/resposta;
- acessar curso;
- executar ações administrativas críticas.

## Dívida atual: account-navigation.js

O arquivo atual implementa manualmente:

- `fetch()` de HTML;
- `DOMParser`;
- seleção de fragmento;
- merge de `drupalSettings`;
- detach/attach de behaviors;
- `innerHTML`;
- History API;
- fallback full-page.

Isso funciona, mas é infraestrutura genérica demais para permanecer como
baseline de longo prazo.

Classificação:

**REFATORAR, NÃO REMOVER ABRUPTAMENTE.**

A versão dedicada a AJAX deve substituir cada fluxo progressivamente por APIs
Core, preservando:

- URL real;
- history/back;
- focus management;
- aria-live;
- loading state;
- cache metadata;
- behaviors;
- fallback full-page.

## Minha Conta

A fronteira detalhada entre SDC e AJAX está em
[GUARDRAILS.md](GUARDRAILS.md).

Decisão:

- converter markup repetido para SDC **sem remover a experiência assíncrona**;
- manter navegação parcial entre seções;
- manter Básicos/Endereço com atualização parcial;
- substituir gradualmente o transporte custom por APIs Core;
- manter formulários essenciais como Form API com fallback normal;
- não interceptar OAuth/checkout/confirmations com fetch próprio.

### Sanfona do celular (0.2.0-dev.27 a dev.29)

No celular, cada item da sanfona da Minha conta carrega o conteúdo completo da seção ao ser aberto, pela mesma requisição (`requestDocument`). Uma seção aberta por vez. Detalhes em `ACCOUNT-MENU.md`.

## CEP

`cep-address.js` tem regra diferente de `account-navigation.js`.

A adaptação do CEP é uma customização específica da central de dados e pode
permanecer quando acrescenta comportamento necessário à experiência ACULTA.

Ela deve continuar usando o endpoint/cache do `cep_autocomplete`, sem criar
consulta ViaCEP paralela server-side.

Qualquer futura redução do arquivo precisa provar paridade de:

- CEP no Profile customer;
- labels;
- bairro;
- UF/cidade/logradouro;
- acessibilidade;
- stale responses;
- focus;
- forms reinjetados por AJAX.

## Wiki e Fórum

A busca interativa do Wiki420 reaproveita `aculta_portal/search-preview` (Drupal
Behavior + `core/once`), com o endpoint escopado `/wiki/busca/previa`. O JavaScript
custom já existente atende a prévia global e agora também a Wiki; não há um
segundo framework de autocomplete. O formulário GET segue como fallback e o
endpoint de prévia responde `no-store` após aplicar publicação, Domain e acesso
ao nó.

Preferir Views AJAX para:

- listagens;
- filtros;
- "meus tópicos";
- "minhas respostas";
- "minhas contribuições Wiki";
- busca depois da adoção do Search API.

Criação/edição de conteúdo deve continuar usando Form API nativa.

## Acessibilidade

Toda atualização parcial deve:

- fornecer feedback perceptível;
- manter foco lógico;
- não remover headings/landmarks;
- evitar mudanças inesperadas de contexto;
- preservar mensagens de erro do Form API;
- funcionar por teclado.

## Regra de revisão

Nenhum JavaScript custom novo para infraestrutura genérica deve entrar sem
explicar por que Views/Form AJAX/Drupal.ajax/HTMX não atendem.
