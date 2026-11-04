# ADR-044 — Ampliação do uso de CTools: AJAX responder, exportables e form wizard

- **Status:** **revisado em 2026-11-04**, a pedido do responsável, que pediu para recomeçar a análise de CTools, jQuery e AJAX. As decisões vigentes estão na seção 2 (revisão). As decisões anteriores ficam registradas como histórico. Nenhum código foi implementado a partir delas.
- **Data:** 2026-11-04.
- **Relaciona-se com:** `web/modules/custom/aculta_portal/docs/CTOOLS.md` (uso atual e limites), `web/modules/custom/aculta_portal/docs/AGENT-POLICIES.md` (seção "CTools como implementação do Portal"), `web/themes/custom/aculta420_v2/docs/roadmap.md` (Fase 2 e pendências de RC), AGENTS §3–§5 (dependências e storage paralelo), AGENTS §13 (segredos), ADR-042 (remoção de dependências contrib do LMS).

## 1. Contexto

- `drupal/ctools ^4.1` já está em `composer.json`, e `aculta_portal` declara `ctools:ctools` e `ctools:ctools_views`. O CTools 4.1.0 declara compatibilidade com Drupal 9.5, 10 e 11. A página da release não menciona Drupal 12, nem mudanças de AJAX ou de jQuery.
- Uso atual: `ctools_views` para quantidade e pager por colocação em blocos de Views (atividades, categorias e tópicos do Fórum, catálogo de loja), e a API de condições/plugins na integração H5P do LMS.
- O tema `aculta420_v2` é independente do Bootstrap e não carrega jQuery por padrão. A correção de 2026-11-04 mantém `core/drupal.dialog.ajax` nas páginas que o Portal pede (seção 5).
- O tema deve ser **compatível com Drupal 12** (`12.0 ready`), mas o desenvolvimento segue no **Drupal 11.5 instalado no servidor de desenvolvimento**. A migração para 12 fica para depois.

## 2. Decisões vigentes (revisão de 2026-11-04)

1. **CTools: ampliar com novos usos**, com decisão caso a caso. Cada novo uso precisa de justificativa escrita: qual necessidade, por que o Core ou a Entity API não resolvem e qual o impacto em jQuery e em cache.
2. **jQuery: aceito caso a caso.** Pode ser usado no tema V2 e no LMS quando a alternativa sem jQuery for mais complexa. Cada caso é registrado neste ADR, com a página afetada e o motivo.
3. **Exportables: adiados.** Nenhum objeto de conteúdo de aplicação foi definido. Só serão implementados quando houver caso concreto que a Entity API não atenda.
4. **Form wizard: sem formulário definido.** O primeiro uso no Aculta LMS depende de o responsável indicar o formulário. Não há implementação.
5. **AJAX responder: possível, com jQuery registrado caso a caso** (ver seção 3.1). Não é mais proibido pela decisão anterior.
6. O uso funcional continua no **`aculta_portal`**; o tema apenas apresenta.
7. O tema deve ser "12.0 ready" enquanto o desenvolvimento roda no Drupal 11.5.
8. Commit e push dependem de autorização explícita. Para este ADR, o responsável autorizou commit e push em `dev`.

## 2.1 Decisões anteriores (histórico, 2026-11-04, substituídas)

1. Ampliar o uso do CTools para: **AJAX responder**, **exportables** e **form wizard**. Modal dialog não é uso próprio do CTools.
2. **Evitar jQuery.** O responsável pediu pesquisa sobre SDC, CTools e AJAX antes de decidir o AJAX responder (ver seção 3.1).
3. **Exportables** serão usados para **conteúdo de aplicação**. Configuração estrutural continua na Config API e em `config/sync`.
4. **Form wizard** será usado para um formulário multietapa do **Aculta LMS**, que não é Commerce, Webform nem Core.
5. O uso funcional fica no **`aculta_portal`**. O tema `aculta420_v2` apenas apresenta, conforme "código no Portal, design no ACULTA420".
6. Este ADR é escrito antes da implementação. A edição local acontece sem commit; commit e push dependem de autorização explícita.
7. O tema deve ser "12.0 ready" enquanto o desenvolvimento roda no Drupal 11.5.
8. **AJAX do LMS e da V2: caminho B** — Fetch próprio com resposta aplicada em `<dialog>` nativo, sem jQuery. O CTools AJAX responder fica descartado enquanto o `core/drupal.ajax` depender de jQuery. Os fluxos de wizard do LMS usam essa abordagem ou páginas completas, conforme a seção 3.3.

## 3. Consequências, evidências e pendências

### 3.1 AJAX responder — evidências (revisão: possível caso a caso, com jQuery registrado)

Evidências encontradas na pesquisa:

- O AJAX responder do CTools é uma camada sobre o pipeline AJAX do Drupal. Não consegui ler o código de `js/ajax-responder.js` pela documentação pública, então a dependência de jQuery dele ainda precisa ser confirmada no código instalado.
- O AJAX do core (`core/drupal.ajax`, `misc/ajax.js`) é a base desse pipeline. A meta de remoção do jQuery do core (issue #3052002) está ativa, com o item "Drupal.ajax" marcado como "TBD". Uma issue de fevereiro de 2024 ainda lista a dependência de jQuery Form em `misc/ajax.js` como bloqueador. A página não traz status atual.
- O jQuery UI do dialog do core também não tem substituto documentado na mesma meta.

Consequência: **na versão 11.5, o AJAX responder não é compatível com a restrição "evitar jQuery" sem verificação adicional no código do core instalado**. Não recomendo adotá-lo nos fluxos da V2.

Drupal 12 (pesquisa de 2026-11-04):
- Drupal 12.0.0 e 11.5.0 estão agendados para a semana de 7/12/2026, com release candidates na semana de 9/11/2026 (fonte secundária, post de 31/10/2026, não confirmada em drupal.org).
- Não encontrei plano publicado para remover jQuery de `Drupal.ajax` ou do dialog no Drupal 12. A lista de itens críticos do 12 também não cita isso. Portanto, **presume-se que o jQuery continue no AJAX do core no 12, até verificação**.
- Core HTMX: a partir do Drupal 11.2 (dependência) e 11.3 (completo), o core tem integração nativa com HTMX, uma biblioteca JavaScript sem dependências, usada via o objeto `htmx` da Form API e respostas `onlyMainContent`/`_htmx_route`. Os relatos dizem que o HTMX substitui soluções "caseiras" (Form State API, AJAX API, BigPipe), mas não confirmam se `#ajax`/`Drupal.ajax` é removido ou deprecado. **Ainda não é claro se o HTMX é a alternativa sem jQuery para o AJAX do core no 11.5 e no 12.**

Alternativas para AJAX no LMS e no tema:
- **A — Form wizard sem AJAX.** O wizard do CTools pode funcionar em modo de páginas completas (requer caminhos de retorno e cancelamento, segundo a documentação antiga). Isso evita AJAX e jQuery no fluxo do LMS. Precisa de confirmação na API 4.x.
- **B — Fetch próprio + `<dialog>` nativo**, como a barra da V2 já faz no login. Mantém "sem jQuery", mas reimplementa parte do protocolo.
- **C — HTMX do core (11.3+)**, sem jQuery. Precisa de verificação no código instalado e de confirmação de que ele sobrevive ao Drupal 12.
- **D — AJAX do core com jQuery** só onde o Portal já depende dele. Contradiz a decisão 2.

**Pendente:** escolher entre A, B, C ou D. Como o AJAX responder depende do `core/drupal.ajax`, a opção "CTools AJAX responder" fica descartada enquanto o jQuery estiver no core.

### 3.2 Exportables — escopo aceito, justificativa pendente

- Exportables do CTools são objetos de aplicação fora da Config API. Pela decisão 3, servem para **conteúdo de aplicação** que não cabe em config.
- **Conflito com AGENTS §5.1 e com "não criar storage paralelo quando a capacidade já existir".** Antes de cada exportable, precisa estar documentado por que uma entidade ou uma configuração do Core não serve.
- Nenhum segredo pode ficar em exportable (AGENTS §6 e §13; Drupal Key).
- A forma dos exportables no CTools 4.x (se ainda existe como API pública) precisa ser verificada no código instalado. A documentação de exportables que encontrei é da versão 7 do CTools.

**Pendente:** listar o primeiro objeto de conteúdo de aplicação que será exportable e por que a Entity API não serve para ele.

### 3.3 Form wizard — uso aceito para o Aculta LMS

- Pela decisão 4, o wizard atende um formulário multietapa do Aculta LMS. Isso respeita `CTOOLS.md`: o formulário não pertence a Commerce, Webform ou Core.
- **Evidência limitada:** a documentação do wizard que encontrei é da versão 7 do CTools (rotas via `hook_menu`, cache de objeto com `ctools_object_cache_*`). Ela não representa a API do CTools 4.x em Drupal 11. Antes de implementar, a API 4.x precisa ser lida no código instalado.
- O wizard guarda dados entre etapas e salva só no final. Se o formulário tiver ações irreversíveis ou pagamento, o cache precisa ser coerente com as regras de cache e de acesso do Portal.
- Não usar o wizard para formulários de Commerce, Webform ou Core (`CTOOLS.md`).

**Pendente:** formulário do Aculta LMS que será o primeiro uso (nome, etapas e onde o estado intermediário fica).

### 3.4 Limites que permanecem

- Não usar Page Manager nem Panels, nem criar páginas paralelas.
- Não habilitar `configure_filters`, `disable_filters`, `hide_fields` ou `sort_fields` globalmente.
- Não usar `ctools_entity_mask` em tipos transacionais (Webform, Commerce).
- Filtros de acesso e domínio continuam fixos nas Views e no Core. O CTools não altera acesso, cache, domínio ou entidade.

### 3.5 Compatibilidade com Drupal 12

- Antes de declarar o tema "12 ready", o CTools precisa ter compatibilidade confirmada com Core 12 nas releases upstream. A release 4.1.0 não menciona Drupal 12.
- Se não houver suporte a 12 na época da migração, há duas saídas: esperar a release compatível ou remover o uso. A decisão fica para o ADR de migração para 12.
- Em desenvolvimento, o tema continua declarando `^11.5 || ^12`, conforme o roadmap (validação em Core 12 é pendência de RC).

## 4. Requisitos de implementação (quando autorizada)

- Ler `CTOOLS.md`, `AGENT-POLICIES.md` e o código do CTools instalado no servidor antes de criar configuração ou código.
- Injeção de dependência nas classes (sem `\Drupal::` novos em `src/`), `#[Hook]` para hooks novos.
- Tema: apenas apresentação, por componentes SDC e tokens. Nada de regra de acesso, domínio ou CTools no Twig/JS do tema.
- Gates do Portal: `php scripts/validate-aculta-portal-drupal11.php`; para o tema, os gates de `aculta420_v2`.
- Testes do Portal também com o tema Olivero (CLAUDE.md).
- Validação no servidor de desenvolvimento (Drupal 11.5), com `RUNTIME STATUS: DEFERRED` até a execução.

## 5. Interação com a estratégia de diálogos da V2

- Esta ADR não decide a estratégia de diálogos. A correção de 2026-11-04 deixou `core/drupal.dialog.ajax` nas páginas em que o Portal o pede.
- Como o responsável pediu evitar jQuery, o caminho preferido para a V2 é `<dialog>` nativo com Fetch, sem `core/drupal.dialog.ajax` no tema. A aplicação disso no Portal depende de a auditoria de diálogos (F3 do plano) ser concluída.

## 6. Não-decisões

- Não define a migração para Drupal 12.
- Não altera `CTOOLS.md` nem `AGENT-POLICIES.md`. Se a decisão for aceita, essas documentações são atualizadas em commit próprio, junto da implementação.
- Não define o uso de UI Styles na V2 (ver plano de implementação do tema).

## 7. Próximos passos

1. Responsável decide o caminho do AJAX (seção 3.1): descartar o AJAX responder na V2 (recomendado) ou aceitar jQuery no LMS.
2. Informar o primeiro objeto exportable (3.2) e o formulário do LMS que usará o wizard (3.3).
3. No servidor de desenvolvimento, ler o código instalado do CTools 4.1 (AJAX responder, exportables, wizard) e confirmar as dependências de jQuery e a API.
4. Aceitar ou ajustar este ADR e atualizar `CTOOLS.md` e `AGENT-POLICIES.md` no mesmo PR da implementação.
