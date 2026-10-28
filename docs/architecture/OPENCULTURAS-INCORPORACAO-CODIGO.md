# OpenCulturas → ACULTA: o que incorporar no código (funções e hooks)

Rodada de revisão de código, focada no que pode entrar no ACULTA. O OpenCulturas é mais maduro na
organização de hooks e de configuração; o ACULTA tem mais código e mais testes próprios. Este documento
registra o que vale copiar **como padrão** (reescrito, com teste), o que corrigir antes de reusar e o que não levar.

Nada deste documento foi implementado. Cada item vira tarefa no roadmap do módulo de destino.

Referência: `/home/piradopirata/projetos/aculta-openculturas/` (somente leitura; GPL-2.0-or-later, ver
`web/modules/custom/aculta_portal/modules/aculta_events/docs/ORIGEM-OPENCULTURAS.md`).

## 1. Números verificados

| Medida | ACULTA (`web/modules/custom`) | OpenCulturas (`profile/modules/custom`) | Leitura |
| --- | --- | --- | --- |
| Hooks em atributo `#[Hook]` | 343 | 62 | ACULTA já migrou para OOP; OC idem |
| Funções procedurais de hook em `.module`/`.install` | 116 | 15 | ACULTA ainda tem resíduo procedural em `aculta_commerce.module` (3 funções), `aculta_donation.module` (1) e `aculta_forum.module` (1) |
| Hook implementado nos dois estilos ao mesmo tempo | 0 | 0 | Sem duplicidade |
| `\Drupal::` estático dentro de `src/` (sem testes) | 5 ocorrências em 3 arquivos | — | Baixo; 2 delas estão na entidade `Shipment` |
| Funções acima de 100 linhas | 70 de 5.640 (1,2%) | 13 de 407 (3,2%) | Proporção do ACULTA é menor, mas os casos são maiores |
| Maiores funções confirmadas | `PdfViewerBuilder::buildFile` (357 linhas, 70–426); `AvisosResumo::enviarParaComLock` (313 linhas, 223–535) | `OSMIDDefaultWidget::rebuildFormSubmit` (206) | Candidatas a dividir |
| TODO/FIXME | 20 | 0 | 16 estão em `aculta_lms_h5p` |
| Métodos-stub sem implementação | 4 em `aculta_H5PDrupal.php` (1557–1581) | — | Retornam `null` sem erro e sem chamadas no ACULTA |
| `catch` genérico | 155 | 17 | **Não conclusivo**: a heurística não reconhece métodos próprios de log (`logFailure`). Revisão manual antes de afirmar |

Medição com `grep` e leitura de código. O tamanho de funções foi conferido manualmente nas duas maiores.

## 2. Incorporar (padrões reescritos, com teste)

### C-01 — Destinatários de notificação como entidade de configuração

- **OpenCulturas:** `EcaNotificationRecipient` (entidade de configuração), `EcaNotificationRecipientListBuilder`,
  `EcaNotificationRecipientForm`. Cada destinatário é um registro editável, com lista administrativa própria.
- **ACULTA hoje:** `aculta_mailer` guarda `alerts_recipients` e `summary_recipients` como **texto livre** em
  `MailAlertSettingsForm`.
- **Recomendação:** para o aviso de denúncia (I-04), usar entidade com lista, validação de e-mail e edição
  por finalidade (denúncia, moderação, resumo). Não copiar a dependência do ECA: a entidade é independente.
- **Destino:** `aculta_ugc` (U2), consumindo `aculta_mailer` para o envio.
- **Cuidado:** o OC carrega idioma do destinatário pelo ECA. No ACULTA, idioma é fixo em português.

### C-02 — Reivindicação recusada pelo dono do conteúdo

- **OpenCulturas:** `OpenculturasCustomFlagHooks::flagActionAccess()` bloqueia o flag `claim_ownership` quando o
  campo `field_allow_claiming` do nó está marcado como "não reivindico" (o texto do campo: "Someone else should
  be responsible for this content, I do not claim it").
- **Falha do OC a não copiar:** se o campo estiver **vazio**, a regra devolve `neutral` (aceita). Um conteúdo sem
  o campo preenchido fica reivindicável por padrão.
- **Recomendação ACULTA:** campo com padrão **fechado** (não reivindicável até o dono liberar) e teste de
  padrão. Vale para locais, eventos e perfis de organização (I-05).
- **Destino:** `aculta_user` (I-05, plano em `ACULTA-USER-PLAN.md` 9A) e `aculta_events` (locais e eventos).

### C-03 — "Em nome de" (`field_behalf`)

- **OpenCulturas:** `OpenculturasCustomFlagHooks::flaggingPrepareForm()` preenche o campo `field_behalf` com o
  perfil principal do usuário (`field_main_profile`) ao criar uma recomendação. Assim a recomendação é feita
  **em nome da organização**, não da pessoa.
- **Recomendação ACULTA:** para o papel de organizador (I-06), a sinalização e a recomendação podem indicar o
  perfil da organização. Só quando o usuário tem vínculo verificado com esse perfil.
- **Destino:** `aculta_user` (I-06) e `aculta_ugc` (sinalização em nome de organização).
- **Cuidado:** sem verificação de vínculo, qualquer pessoa pode falar em nome de uma organização. Exigir
  vínculo aprovado.

### C-04 — Regra de mídia por licença e dono (com correção)

- **OpenCulturas:** `OpenculturasCustomViewsHooks::viewsPreRender()` (media_library) mantém só a mídia do próprio
  usuário, mídia que ele pode editar, ou mídia com licença Creative Commons (`field_licenses`).
- **Defeito confirmado:** a regra **remove linhas depois da consulta** (`$view->result = $result`) e não ajusta
  `total_rows` nem o pager. Resultado: contagem errada e páginas com menos itens do que o número indica.
- **Recomendação ACULTA:** aplicar o critério na consulta (filtro de acesso da View ou entidade), nunca em
  `pre_render`. Mesma regra de licença de I-13.
- **Destino:** `aculta_image` e `aculta_gallery` (I-13); `USER-UPLOADS.md`.

### C-05 — Cache por usuário em "Meus salvos" (rebaixado: risco futuro, não defeito atual)

- **OpenCulturas:** `flaggingInsert()` invalida a tag `entity_view_user_<uid>_full` ao salvar `bookmark_node`
  ou `bookmark_term`. `nodePresave()` invalida a tag do dono ao salvar um nó.
- **Verificado no ACULTA (2026-10-28):** `salvar_topico` está configurado (`config/sync/flag.flag.salvar_topico.yml`),
  mas **nenhum código PHP o lê hoje**. Só `seguir_topico` é lido, pelo `aculta_messenger`. Logo, a lista
  "Meus salvos" (I-07) ainda não existe e nenhum cache atual depende de salvar.
- **Botão de salvar:** é renderizado pelo módulo Flag, que já trata cache por usuário e tags de lista. Não há
  motivo para invalidação manual nesse caso.
- **Risco real na lista futura:** uma View de "Meus salvos" com cache do tipo `tag` e **sem contexto `user`**
  serviria a lista de uma pessoa a outra (vazamento). Esse é o ponto a testar, não a invalidação.
- **Recomendação:** ao criar "Meus salvos", exigir contexto `user` no cache da View e um teste Kernel com duas
  contas: A salva, B não vê; A desfaz, a lista de A atualiza. A invalidação por tag customizada só entra se o
  teste mostrar atraso.
- **Destino:** `aculta_studio` (I-07).

### C-06 — Acesso a campo por valor de outro campo

- **OpenCulturas:** `entityFieldAccess()` controla a visualização de `field_forfree` a partir do próprio nó.
- **Padrão útil**, com regra a corrigir: o OC oculta o campo quando o evento **não** é gratuito, o que tende a
  esconder o preço no lugar errado. No ACULTA, a regra deve ser escrita pelo responsável e testada.
- **Destino:** `aculta_events` (campo de gratuidade, `MODELO-DE-DADOS.md`).

### C-07 — Breadcrumb a partir do caminho de taxonomia

- **OpenCulturas:** `TermPathBasedBreadcrumbBuilder` (52 linhas) monta a trilha de termos pelo caminho.
- **ACULTA:** já existe `AcultaBreadcrumbBuilder`. Comparar antes de criar qualquer coisa nova.
- **Destino:** páginas de categoria da Revista e da Wiki (I-25). Se o `AcultaBreadcrumbBuilder` já cobre,
  não criar.

### C-08 — Hooks procedurais para OOP

- **OpenCulturas:** só 15 funções procedurais, e o `openculturas_custom.module` tem 4 (uma delas é o handler de
  submit do formulário de evento).
- **ACULTA:** `aculta_commerce.module` (3 funções), `aculta_donation.module` (1) e `aculta_forum.module` (1).
- **Recomendação:** migrar para `#[Hook]` em classe de serviço, com teste de regressão. Mecânico e de baixo risco.
- **Destino:** `aculta_commerce`, `aculta_donation` e `aculta_forum`, em frente de manutenção, sem mudança de comportamento.

### C-09 — Quebra de funções longas

- **Candidatas confirmadas:** `PdfViewerBuilder::buildFile` (357 linhas) e `AvisosResumo::enviarParaComLock` (313).
- **Recomendação:** extrair etapas com nomes (validação, montagem, persistência), com os testes existentes
  como rede de segurança. Não usar o OC como modelo: `SettingsForm::buildForm` do OC também é longo.
- **Destino:** `aculta_document_viewer` e `aculta_mailer`.

### C-10 — Qualidade: ferramentas que o OC roda

- **OpenCulturas:** scripts Composer `php:lint`, `php:cs`, `php:cs-fix`, `php:phpstan`, `php:rector` (dry-run e
  correção) e scripts npm `lint:js`, `lint:css`, `lint:scss`, `prettier`. Tudo em um container padrão (DDEV).
- **ACULTA:** PHPCS e PHPUnit são usados; `stylelint`, `eslint` e Rector não aparecem em `composer.json`.
- **Destino:** `docs/operations/` e I-19 (`aculta420`). Rector em modo dry-run, para medir antes de aplicar.

## 3. Corrigir antes de reusar (defeitos do OpenCulturas)

| Local | Defeito | Correção no ACULTA |
| --- | --- | --- |
| `OpenculturasCustomViewsHooks::viewsPreRender` | Remove linhas após a consulta; pager e contagem errados | Filtro na consulta (C-04) |
| `OpenculturasCustomFlagHooks::flagActionAccess` | Padrão aberto quando o campo está vazio | Padrão fechado (C-02) |
| `OpenculturasCustomDateEventHooks::nodePrepareForm` | Carrega evento e perfil sem checar acesso (S1 da auditoria) | Verificação de acesso e teste de negação |
| `OpenculturasCustomDateEventHooks` | Strings em inglês no PHP ("Visit the location's profile", "See all about") | `t()` em português |
| `OpenculturasCustomIntegrationHooks` | Usa `update_helper_checklist` sem declarar | Dependências declaradas |
| `OpenculturasCustomLocationPrecisionHooks` | Nome sugere privacidade; é área desenhada (T9 da auditoria) | Não portar |

## 4. Não levar

| Item | Motivo |
| --- | --- |
| `OpenculturasCustomConfigDevelSubscriber` (449 linhas) | Acoplado ao `config_devel`; o ACULTA usa `config/sync` completo |
| `OpenculturasCustomDefaultContentImportSubscriber` | Importa conteúdo de exemplo; o pilot de eventos (F8) usa dados aprovados |
| `OpenculturasCustomCookiesLegalPagesSubscriber` | Cookies e páginas legais: o ACULTA tem aceite único |
| Qualquer código que dependa do ECA | ACULTA escreve regra em código (decisão de 2026-10-28) |

## 5. Ordem de incorporação

| Ordem | Item | Frente | Pré-requisito |
| --- | --- | --- | --- |
| 1 | C-08 (hooks para OOP) | Manutenção | Nenhum |
| 2 | C-05 (cache por usuário em flag) | Studio | Confirmar em teste |
| 3 | C-02 (reivindicação com padrão fechado) | `aculta_user` | ADR de organização |
| 4 | C-01 (destinatários como entidade) | `aculta_ugc` | U0 |
| 5 | C-04 (filtro de mídia na consulta) | `aculta_image` | I-13 |
| 6 | C-03 (em nome de organização) | `aculta_user` | C-02 e vínculo verificado |
| 7 | C-09 (quebra de funções longas) | Documento e mailer | Testes existentes |
| 8 | C-10 (ferramentas de qualidade) | Ops | Decisão de ferramenta |

## 6. Links

- Comparativo geral: `docs/architecture/OPENCULTURAS-COMPARATIVO.md`
- Auditoria de cópia: `web/modules/custom/aculta_portal/modules/aculta_events/docs/AUDITORIA-OPENCULTURAS.md`
- Plano de UGC: `web/modules/custom/aculta_portal/modules/aculta_ugc/docs/PLANO-IMPLANTACAO.md`
- Papéis e reivindicação: `docs/portal/ACULTA-USER-PLAN.md` (9A)
