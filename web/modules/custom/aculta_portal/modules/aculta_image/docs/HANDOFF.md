# Handoff — ACULTA Image (aculta_image)

Data da passagem: 2026-10-16. Versão: 0.1.0-dev.20. Leia este documento antes de qualquer alteração no submódulo.

## 1. Resumo em cinco linhas

1. O `aculta_image` é o sistema único de imagens do site (upload, derivados, render, compressão, auditoria, quarentena, ingestão). A regra está em `AGENTS.md`.
2. Fases M0 a M9 e og:image estão prontas no Homelab e no código. A conversão de imagens do texto para 1200 px está pronta no código.
3. **M10 (modelos de imagem no texto) está parcial.** Runtime atualizado; conversão e persistência do modelo confirmadas; formulário, persistência e reabertura da legenda confirmados; CSS dos quatro modelos medido em página pública anônima a 1366, 500 e 390 px, em modo claro e escuro. Firefox/Orca/AT-SPI confirmaram foco e leitura da legenda e do `alt`, além da semântica de imagem/legenda.
4. Formatos Core migrados para os formatos ACULTA; a permissão direta do seletor de `usuario_aculta` pertence a `email_confirmed`. A herança atual também a concede efetivamente a `estudante`, `associado`, `lms_teacher` e `content_editor`; `gerente_loja` não herda essa permissão.
5. A galeria 11 teve as mídias duplicadas mescladas com o `alt` do menor ID; arquivos redundantes aguardam o prazo da quarentena até 2026-11-15. A importação do Growroom segue bloqueada pela F3 do Fórum.
6. O R7 adicionou `capa_curso` com política `aculta_image`; as Views usam o cartão com finalidade `destaque`. A configuração foi aplicada ao Runtime e os catálogos respondem 200. Firefox confirmou a capa usando uma imagem existente atribuída temporariamente ao curso 1: o cartão gerou e carregou `aculta_destaque_16x9` em 1280×720; `capa_curso` foi limpo logo depois. `config:status` não aponta diferença nos seis arquivos de configuração deste R7.

## Implementação dev.19 — limpeza e painel

- Hooks de substituição/exclusão enfileiram arquivos de campos imagem/arquivo e referências usadas em texto rico. O cron e a ação administrativa processam até 100 itens, removendo somente imagens sem `file_usage` nem `entity_usage`.
- Temporários de imagem sem referências vencem conforme `system.file:temporary_maximum_age` (seis horas no Runtime previamente inspecionado). Conversão inline ocorre sobre o arquivo gerenciado, sem cópia temporária de conversão.
- A remoção invalida derivados do Image Style e apaga metadados de auditoria/origem/quarentena do FID removido.
- Painel em `/painel-administrativo/imagens`, abas de relatório, limpeza e quarentena. A permissão existente `administer aculta images` protege todas as rotas; nenhuma configuração de contas ou papéis foi alterada.
- Validação: PHP lint, parse YAML, `validate-aculta-image.php` e `validate-aculta-portal-drupal11.php` passaram. O primeiro `drush status` usou URI padrão; depois o status explícito confirmou `https://aculta.toca.net.br` e o banco `aculta_runtime`. Cache reconstruído e as quatro rotas administrativas confirmadas pelo Drush. Nenhuma configuração de contas ou papéis foi alterada.

## Hierarquia atual e o seletor de texto

- PR [#286](https://github.com/coletivo420/aculta/pull/286) está aberta como draft, no HEAD `15071a10`. A seção 9 de `docs/portal/PAPEIS-PARTICIPACAO.md` encerra a Fase 2 e define a cadeia unilateral; as decisões de hierarquia estão fechadas na PR.
- A configuração do Runtime `role_inheritance.settings` foi consultada em modo somente leitura. `extendRoles()` confirmou: `estudante`, `associado`, `lms_teacher` e `content_editor` herdam `email_confirmed`; `gerente_loja` herda apenas `authenticated`.
- Não alterei contas, atribuições de papéis nem permissões. A configuração direta de `use text format usuario_aculta` continua em `email_confirmed`; consulte a versão efetiva acima ao documentar o seletor.

## 2. Git e working tree

- Branch: `chore/messenger-pendencias`, publicado em `origin` (o remoto mudou de nome para `coletivo420/aculta`; o `origin` local ainda aponta para `coletivo420/aculta-site` e o push funciona).
- **Observação de processo:** o responsável decidiu que não haverá um branch por tarefa. A tarefa de imagem continua no branch atual e deve ser registrada em um commit atômico próprio, sem incluir alterações de outras frentes. Não criar ou trocar de branch para esta entrega.
- Commits desta frente (do mais antigo ao mais novo, a partir de `b5260eef`):

| Commit | Conteúdo |
| --- | --- |
| `b5260eef` | plano de modelos de imagem (revisão) |
| `babb78d5` | og:image 1200×630, limite de 1200 px no texto, compressor próprio (0.1.0-dev.8) |
| `1e6d5126` | estilos `aculta_corpo_*`, catálogo `MODELOS`, filtro `aculta_imagem_modelo`, formatos com o filtro |
| `4a4f4b0c` | plugin JS do seletor de modelo (sem build), biblioteca e declaração |
| `19b779eb` | botão de modelo nos três editores (depois corrigido) |
| `6ba90058` | botão removido do básico e do HTML completo (só Revista) |
| `42a0d5eb` | botão de modelo reativado no HTML completo |
| `9bfca6cb` | módulo 0.1.0-dev.9: seletor configurável por editor, conversão para 1200 px, crédito removido, docs |
| `fdc4c0ad` | configuração: formato `usuario_aculta`, permissão no autenticado, comentários do Fórum, limites do core desligados |
| (este commit) | documentação: README, PLANO, MODELOS, ESTILOS, IMPORTAÇÃO, este handoff |
| `0941388b` | política de IA: um commit por tarefa no branch atual; sem branch por tarefa |
| `e52c402d` | correção dos botões de modelo no overflow (dev.10): um botão por modelo |
| `b66646ef` | migração `basic_html`/`full_html` para formatos ACULTA, seletor para `email_confirmed`, Fórum restrito |
| `16016400` | mesclagem de duplicatas de mídia e correção da lista de candidatas em quarentena (dev.16) |
| `a2380003` | R7: campo `capa_curso` do LMS com política `aculta_image`, uso nos cartões e documentação (dev.17) |
| (este commit) | limite de upload de 10 MB no widget de `capa_curso` e documentação do Runtime (dev.18) |

- **Não commitar** o que é de outras sessões. No momento da passagem havia 25 entradas em `git status`. Todas as do submódulo `aculta_image` são desta frente. As demais são de outra frente e **não** devem entrar nos commits de imagem:
  - `config/sync/group.role.lms_course-member.yml`, `group.role.lms_course-teacher.yml`, `user.role.lms_teacher.yml`;
  - tudo em `aculta_pdf_viewer` e `aculta_pdf_viewer_group` (inclusive `config/install/`, `src/Hook/`, `tests/`);
  - `config/sync/*course_document*`, `config/sync/group.relationship_type.lms_course-af8ec2205541224f9d8c4.yml`, `media.type.course_document.yml`.
- Para commitar sem arrastar esses arquivos: `git add <caminhos explícitos>`. Nunca `git add -A`.

## 3. Verificação rápida (Homelab, sem alterar nada)

```sh
cd /home/piradopirata/projetos/aculta-site
php scripts/validate-aculta-image.php                       # esperado: PASS
php -l web/modules/custom/aculta_portal/modules/aculta_image/src/Plugin/CKEditor5Plugin/ImageModel.php
node --check web/modules/custom/aculta_portal/modules/aculta_image/js/ckeditor5/aculta-image-model.js
php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_image/tests/src/Unit
# esperado: OK (14 tests). O aviso "browser_output is not a writable directory" é esperado e não afeta o resultado.
```

Ambiente: checkout de teste em `/home/piradopirata/projetos/aculta-site`, branch `chore/messenger-pendencias`, host `toca`. Runtime `https://aculta.toca.net.br`, Drupal 11.4.10 / Drush 13.8.0. `config:status` foi consultado antes da importação. As mudanças de imagem foram aplicadas parcialmente e o cache reconstruído; não foi feita importação total.

## 4. Estado do Runtime

As oito configurações de imagem listadas abaixo já foram importadas em operações parciais e revisadas por diff. Os três
editores foram reimportados com o dropdown único `acultaImageModel`.

| Arquivo em `config/sync/` | Mudança aplicada | Commit |
| --- | --- | --- |
| `filter.format.basic_html.yml` | sem `data-aculta-credito` | `fdc4c0ad` |
| `filter.format.usuario_aculta.yml` | formato novo (cópia do básico) | `fdc4c0ad` |
| `editor.editor.usuario_aculta.yml` | editor novo: botão de imagem e modelo, `modelos: [pequena, padrao]`, upload sem limite de dimensão | `fdc4c0ad` |
| `editor.editor.basic_html.yml` | `max_dimensions` nulo | `fdc4c0ad` |
| `editor.editor.full_html.yml` | botão de modelo e `max_dimensions` nulo | `42a0d5eb`, `fdc4c0ad` |
| `editor.editor.revista_editorial.yml` | botão de modelo e `max_dimensions` nulo | `19b779eb`, `fdc4c0ad` |
| `field.field.comment.comment_forum.comment_body.yml` | `allowed_formats` = só `usuario_aculta` | `fdc4c0ad` |

Foi executado `config:status`, importação parcial só dos alvos revisados e `cr`. O filtro, a biblioteca CKEditor, o plugin e
o schema do módulo foram carregados pelo rebuild. Depois da atualização dos itens de toolbar, os três editores foram
importados de novo via diretório temporário com apenas esses YAMLs. `config:status` ainda aponta `user.role.authenticated`
por drift existente de permissões não relacionadas; não importar a role inteira, pois isso reverteria permissões ativas.

Firefox headless confirmou em `full_html` que “Imagem: Padrão” serializa `data-aculta-modelo="padrao"` no `<img>`. Em
`usuario_aculta` e `revista_editorial`, “Imagem: Pequena” também funcionou pelo menu de overflow a 488 px. Uma verificação
de reabertura salvou um artigo temporário não publicado com o modelo `larga`; o atributo persistiu e o nó foi removido.
O Firefox headless limitou a viewport útil a 488 px mesmo quando a janela foi solicitada com 402 px. Na revisão integrada de 2026-10-16, WebDriver BiDi aplicou viewport CSS real de 390 px.

**Revisão de 2026-10-16 (verificado):** filtros e editores de imagem no Runtime batem com o repositório (`config:status`
sem diferença nesses itens). `user.role.authenticated` tem `use text format usuario_aculta`. O `config:status` total mostra 252
itens com diferença, quase todos fora desta frente. Três estilos (`aculta_avatar`, `aculta_card_1x`, `aculta_card_2x`) também
aparecem com diferença, mas efeitos e rótulo são iguais aos do repositório; a diferença está em outro metadado, ainda não
investigado.

Upload real: PNG sintético de 1600×800 foi enviado em `revista_editorial`, convertido a 1200×600 e removido em seguida.
O arquivo gerenciado foi excluído e a ausência confirmada. O upload está coberto para este caminho.

O schema do submódulo permanece em `aculta_image_update_10003`. O `updb` aplicado nesta passagem executou `aculta_portal_update_11009` para migrar os formatos de texto do site.

**R7 (2026-10-16):** foi preparado o campo nativo `capa_curso`, com política `aculta_image`, no Config Sync e o estilo de finalidade `destaque` nas Views `courses` e `courses_catalog`. A importação parcial falhou na validação por uma dependência preexistente em `field.field.lms_activity.no_answer.field_content` para `filter.format.0` (ausente). Para não alterar essa configuração fora da tarefa, o campo, displays e Views foram aplicados ao Runtime com Entity API e o cache foi reconstruído. `config:status` não aponta diferença nos seis arquivos do R7. O display já incluía `search_api_excerpt` oculto no Runtime; esse estado foi preservado no Config Sync. Os catálogos `/courses` e `/cursos/catalogo` respondem 200. No Firefox, a imagem temporária do curso 1 foi renderizada pelo cartão como JPEG 1280×720 e removida após a conferência; o campo voltou a vazio. O cartão também imprime todo o `field_description`, inclusive títulos e listas longas; decidir em tarefa de produto se o catálogo deve mostrar texto completo, resumo ou nenhum texto. A dependência inválida segue bloqueando futuras importações parciais e requer revisão separada.

## 5. Riscos conhecidos

- **Valores de formato antigos:** textos e revisões foram migrados antes da exclusão dos formatos Core; respostas antigas do Fórum usam `usuario_aculta` após a migração.
- **Mesclagem de mídias duplicadas (2026-10-16):** a galeria 11 mantém uma ocorrência por arquivo, com o `alt` do menor ID (`126` e `128`). Mídias `127`, `159` e `129` foram removidas. Arquivos redundantes `223`, `255` e `225` estão na quarentena `aculta_image`, vencendo em 2026-11-15; excluí-los somente pelo fluxo normal da quarentena após confirmar que continuam sem uso. `gruposDeDuplicatas()` voltou vazio.
- **Upload acima de 1200 px:** o fluxo em `revista_editorial` foi exercitado com PNG 1600×800 e resultou em 1200×600. Falta avaliar outros formatos de imagem se necessário.
- **Seletor de modelo:** dropdown único importado nos três editores. Firefox confirmou as quatro opções do `full_html`, seleção de Larga e `data-aculta-modelo="larga"` serializado. `usuario_aculta` recebe Pequena e Padrão pela configuração dinâmica.
- **Barra multilinha:** o marcador `-` na toolbar desliga o overflow automático do CKEditor. `revista_editorial` já o usava; foi aplicado a `full_html` e `usuario_aculta`. Firefox mediu três linhas e nenhum botão de overflow a 500 px em `full_html`.
- **Estilos dos modelos:** em `revista-post.css`, medidos na página pública anônima: a 1366 px, Pequena/Padrão/Larga/Destaque ocupam 320/580/900/960 px; a 500 px, os quatro refluem para 456 px; a 390 px, os quatro medem 346 px, sem overflow horizontal. Firefox carregou as imagens e a legenda aparece no fluxo. Modo claro e escuro integrados foram conferidos, conforme a revisão de 390 px registrada abaixo.
- **Texto alternativo:** o Drupal core mostra aviso quando falta `alt`, permite abrir o formulário por teclado e remove o aviso após salvar. Fluxo testado no Firefox; conteúdo temporário removido. Imagem decorativa pode ter `alt` vazio quando marcada como decorativa.
- **Legenda:** o formulário contextual grava e reabre `data-aculta-legenda`; Firefox no Runtime confirmou abertura por Enter, foco no campo e Escape fechando sem alterar dados. O HTML anônimo de um artigo temporário confirmou `<figcaption>`; o nó foi removido.
- **Renderização pública integrada (2026-10-16):** no host correto `revista420.aculta.toca.net.br`, artigos temporários com estado editorial `published`, domínio `coletivo420_aculta_org` e a imagem existente `exame-toxicologico-cnh-e-maconha.jpg` responderam HTTP 200 anonimamente. O HTML continha `<figure>` e `<figcaption>`. Com Firefox/geckodriver sem sessão autenticada, os quatro modelos foram renderizados e suas imagens carregaram após rolagem. Em 1366 px mediram 320/580/900/960 px; em 500 px, cada figura mediu 456 px sem overflow horizontal. Os nós temporários foram removidos. O 500 antigo veio de um nó de teste sem `moderation_state` e não reproduzia um artigo editorial válido.
- **Persistência da legenda (2026-10-16):** em artigo temporário válido no Runtime, o formulário do CKEditor aceitou “Legenda salva no Firefox visível”; após salvar, `data-aculta-legenda` apareceu no corpo salvo no banco e o formulário de edição reaberto recuperou o mesmo texto. O formulário estava válido e o nó temporário foi removido (IDs 370 e 371 confirmados no cleanup).
- **Revisão com Orca/AT-SPI (2026-10-16):** Firefox 153.4 na sessão LXQt/X11. O Orca anunciou o campo como “Legenda da imagem”, entry e o valor salvo. Ao focar a imagem da fixture DOM na página pública, anunciou “Legenda acessível de teste” e “Imagem de teste com texto alternativo image”. A árvore AT-SPI expôs a figura como painel, imagem com nome acessível derivado do `alt` e o filho de legenda. A fixture foi removida sem salvar conteúdo.
- **Revisão a 390 px e tema escuro (2026-10-16):** em página pública anônima de artigo existente, Firefox 153.4 ESR via WebDriver BiDi manteve `innerWidth=390`. Quatro figuras demonstrativas adicionadas apenas ao DOM usaram as classes reais Pequena/Padrão/Larga/Destaque; todas mediram 346 px, exibiram `figcaption` e o documento mediu 378 px, sem rolagem horizontal. As quatro imagens carregaram (largura natural 960 px). No tema escuro integrado, definido temporariamente em `aculta_portal.appearance:default_mode` e depois restaurado para `light`, o HTML público veio com `data-bs-theme="dark"`; corpo `rgb(23, 21, 19)`, texto `rgb(244, 239, 232)`, legenda `rgb(183, 174, 165)`. As figuras permaneceram em 346 px, sem overflow. As figuras de verificação existiram só no DOM da sessão; nenhum conteúdo foi salvo.
- **Fórum:** tópicos e respostas usam somente `usuario_aculta`. O formato está disponível ao papel `email_confirmed`; `content_editor` elegível tem também esse papel conforme a hierarquia documentada.
- **Formatos textuais (2026-10-16):** `basic_html` foi migrado para `usuario_aculta` e `full_html` para `revista_editorial`, incluindo revisões. Campos, views, Agreement e permissões foram atualizados; os formatos e editores Core foram removidos do Runtime e do Config Sync. O update `aculta_portal_update_11009` exige que os dois formatos de destino existam antes de rodar.
- **Downloads do Wayback (2026-10-16, 15:42):** assets concluído (9.314 arquivos). A coleta HTML não tinha processo ativo na última verificação; os números anteriores não foram remedidos. A fila de tópicos e galerias segue dependente do mapeamento de perfis. Seções excluídas da importação: Avisos (2 e 52), Multimídia Cannábica (48), Cannabis Medicinal (76, a confirmar) e Notícias (24). **Não interromper nem apagar** sem consulta ao responsável. O script está fora do repositório (`scratchpad` da sessão); ver `IMPORTACAO-GROWROOM.md`, seção de estado.
- **Binários de produção:** ImageMagick e Ghostscript não foram confirmados na Hostinger. A miniatura de PDF e a redução de largura dependem disso em produção.

## 6. Próximos passos (ordem recomendada)

1. Adicionar uma capa a um curso quando a edição de cursos estiver disponível pela frente de contas/permissões; conferir a imagem no cartão via Firefox.
2. Revisar a dependência inválida `filter.format.0` antes de futuras importações parciais do Config Sync.
3. Continuar R8 (consumidores restantes e estilos legados) mantendo a frente de contas e permissões aberta, sem alterar contas nesta tarefa.
4. A confirmação dos binários na Hostinger segue pendente (RC). A mesclagem da galeria foi concluída; arquivos redundantes continuam na quarentena até 2026-11-15.

## 7. Regras que valem nesta frente (resumo; a regra completa está no AGENTS)

- Toda imagem passa pelo `aculta_image`. Não usar `image_style` direto, `ImageFactory` ou `->delete()` em arquivo de imagem fora da quarentena. O gate `validate-aculta-image.php` tem tolerância zero.
- Código de comportamento no Aculta Portal; design e CSS no ACULTA420 (com autorização explícita).
- Não usar `\Drupal::` novo em `src/`. Injeção de dependência.
- Commit antes de encerrar; mensagem em português; trailer `Co-Authored-By` do agente.
- Sem merge, tag ou deploy sem pedido explícito.
- Não alterar `estados/`, nem `settings.local.php`, nem secrets.

## 8. Mapa dos documentos

| Documento | Para quê |
| --- | --- |
| [README](../README.md) | serviços, rotas, dependências, gates e testes |
| [PLANO](PLANO.md) | estado por fase, decisões, roadmap, perguntas abertas, Hostinger |
| [MODELOS-DE-IMAGEM](MODELOS-DE-IMAGEM.md) | modelos no texto: pesquisa, tamanhos, decisões e pendências do M10 |
| [ESTILOS](ESTILOS.md) | catálogo de estilos, pesquisa e remoções |
| [IMPORTACAO-GROWROOM](IMPORTACAO-GROWROOM.md) | alterações, caminhos dos dados recuperados e bloqueio da F3 |
| [CHANGELOG](../CHANGELOG.md) | histórico de versões |

Fora deste submódulo, consultar antes de mexer em áreas vizinhas:

- Fórum: `web/modules/custom/aculta_portal/modules/aculta_forum/docs/RECONSTRUCAO.md` (F3 e F6 dependem da lista de autores).
- Portal e gates gerais: `docs/portal/README.md`, `docs/portal/GUARDRAILS.md`, `docs/operations/DEBT-REGISTER.md`.
- Política de imagem em `AGENTS.md` (seção "Imagens e arquivos de imagem").

Não foram reescritos nesta passagem: o roadmap global do Portal (`docs/portal/`), o registro de dívida e a documentação dos módulos Fórum, LMS e Messenger. O `AGENTS.md` diz que o roadmap do Portal é definido fora da execução de código; a reescrita dele depende de decisão do responsável.
