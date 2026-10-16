# Handoff — ACULTA Image (aculta_image)

Data da passagem: 2026-10-16. Versão: 0.1.0-dev.15. Leia este documento antes de qualquer alteração no submódulo.

## 1. Resumo em cinco linhas

1. O `aculta_image` é o sistema único de imagens do site (upload, derivados, render, compressão, auditoria, quarentena, ingestão). A regra está em `AGENTS.md`.
2. Fases M0 a M9 e og:image estão prontas no Homelab e no código. A conversão de imagens do texto para 1200 px está pronta no código.
3. **M10 (modelos de imagem no texto) está parcial.** Runtime atualizado; conversão e persistência do modelo confirmadas; formulário e filtro da legenda confirmados; CSS dos quatro modelos medido a 1366/500 px em claro/escuro numa fixture local. Falta a revisão integrada numa página pública, 390 px e acessibilidade.
4. Próximos passos: cobertura integrada da legenda/CSS numa página pública; tentar 390 px útil; fazer revisão de acessibilidade e leitor de tela.
5. A importação do Growroom está bloqueada pela F3 do Fórum (lista de autores). Não é trabalho deste submódulo.

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
| `user.role.authenticated.yml` | permissão `use text format usuario_aculta` | `fdc4c0ad` |
| `field.field.comment.comment_forum.comment_body.yml` | `allowed_formats` = só `usuario_aculta` | `fdc4c0ad` |

Foi executado `config:status`, importação parcial só dos alvos revisados e `cr`. O filtro, a biblioteca CKEditor, o plugin e
o schema do módulo foram carregados pelo rebuild. Depois da atualização dos itens de toolbar, os três editores foram
importados de novo via diretório temporário com apenas esses YAMLs. `config:status` ainda aponta `user.role.authenticated`
por drift existente de permissões não relacionadas; não importar a role inteira, pois isso reverteria permissões ativas.

Firefox headless confirmou em `full_html` que “Imagem: Padrão” serializa `data-aculta-modelo="padrao"` no `<img>`. Em
`usuario_aculta` e `revista_editorial`, “Imagem: Pequena” também funcionou pelo menu de overflow a 488 px. Uma verificação
de reabertura salvou um artigo temporário não publicado com o modelo `larga`; o atributo persistiu e o nó foi removido.
O Firefox headless limitou a viewport útil a 488 px mesmo quando a janela foi solicitada com 402 px; 390 px ainda não foi coberto.

**Revisão de 2026-10-16 (verificado):** filtros e editores de imagem no Runtime batem com o repositório (`config:status`
sem diferença nesses itens). `user.role.authenticated` tem `use text format usuario_aculta`. O `config:status` total mostra 252
itens com diferença, quase todos fora desta frente. Três estilos (`aculta_avatar`, `aculta_card_1x`, `aculta_card_2x`) também
aparecem com diferença, mas efeitos e rótulo são iguais aos do repositório; a diferença está em outro metadado, ainda não
investigado.

Upload real: PNG sintético de 1600×800 foi enviado em `revista_editorial`, convertido a 1200×600 e removido em seguida.
O arquivo gerenciado foi excluído e a ausência confirmada. O upload está coberto para este caminho.

Nenhum `updb` é necessário: o submódulo não mudou o schema desde `aculta_image_update_10003`.

## 5. Riscos conhecidos

- **Resposta antiga em `basic_html`:** ao editar uma resposta do Fórum que já usa `basic_html`, o formulário passa a oferecer só `usuario_aculta`. Testar no Runtime antes de liberar.
- **Upload acima de 1200 px:** o fluxo em `revista_editorial` foi exercitado com PNG 1600×800 e resultou em 1200×600. Falta avaliar outros formatos de imagem se necessário.
- **Seletor de modelo:** dropdown único importado nos três editores. Firefox confirmou as quatro opções do `full_html`, seleção de Larga e `data-aculta-modelo="larga"` serializado. `usuario_aculta` recebe Pequena e Padrão pela configuração dinâmica.
- **Barra multilinha:** o marcador `-` na toolbar desliga o overflow automático do CKEditor. `revista_editorial` já o usava; foi aplicado a `full_html` e `usuario_aculta`. Firefox mediu três linhas e nenhum botão de overflow a 500 px em `full_html`.
- **Estilos dos modelos:** em `revista-post.css`, medidos em fixture local com o CSS e tokens do tema: 1366 px (320/580/900/960 px) e 500 px (484 px, sem breakout); modo claro/escuro troca fundo e cor da legenda. Ainda falta ver na página pública.
- **Texto alternativo:** o Drupal core mostra aviso quando falta `alt`, permite abrir o formulário por teclado e remove o aviso após salvar. Fluxo testado no Firefox; conteúdo temporário removido. Imagem decorativa pode ter `alt` vazio quando marcada como decorativa.
- **Legenda:** o formulário contextual grava e reabre `data-aculta-legenda`; Firefox no Runtime confirmou abertura por Enter, foco no campo e Escape fechando sem alterar dados. Nó temporário foi removido. Falta confirmar `<figcaption>` numa página pública.
- **Tópicos do Fórum:** o corpo do tópico (`node.forum`) continua aceitando `full_html` e `basic_html`. Só as respostas foram restringidas.
- **Formato do básico sem botão:** conteúdo de quem usa só o básico não ganha modelo até o formato próprio.
- **Downloads do Wayback (2026-10-16, 15:42):** assets concluído (9.314 arquivos). HTML em andamento como root, com processo ativo: 6.480 arquivos; fila de tópicos e galerias (perfis fora até o mapeamento). Seções excluídas da importação: Avisos (2 e 52), Multimídia Cannábica (48), Cannabis Medicinal (76, a confirmar) e Notícias (24). **Não interromper nem apagar** sem consulta ao responsável. O script está fora do repositório (`scratchpad` da sessão); ver `IMPORTACAO-GROWROOM.md`, seção de estado.
- **Binários de produção:** ImageMagick e Ghostscript não foram confirmados na Hostinger. A miniatura de PDF e a redução de largura dependem disso em produção.

## 6. Próximos passos (ordem recomendada)

1. **R2 navegador:** Firefox; cobrir claro e escuro e tentar viewport útil de 390 px. A persistência após salvar/reabrir já foi confirmada. Registrar a cobertura em `docs/portal/TESTES-NAVEGADOR.md` (AGENTS).
2. **R5 acessibilidade:** Escape/foco da legenda e aviso/edição de `alt` foram verificados no Firefox. Falta revisão com leitor de tela e confirmação de `<figcaption>` numa página pública.
3. **Decisões do responsável** (ver `PLANO.md`, seção 6): formato do básico (R6); tópicos do Fórum; prevenção de duplicata; confirmação dos binários na Hostinger (RC).

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
