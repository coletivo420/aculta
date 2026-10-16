# Handoff — ACULTA Image (aculta_image)

Data da passagem: 2026-10-17. Versão: 0.1.0-dev.9. Leia este documento antes de qualquer alteração no submódulo.

## 1. Resumo em cinco linhas

1. O `aculta_image` é o sistema único de imagens do site (upload, derivados, render, compressão, auditoria, quarentena, ingestão). A regra está em `AGENTS.md`.
2. Fases M0 a M9 e og:image estão prontas no Homelab e no código. A conversão de imagens do texto para 1200 px está pronta no código.
3. **M10 (modelos de imagem no texto) está parcial.** O código e a configuração estão no repositório, mas **nada foi importado no Runtime e o seletor nunca foi testado no navegador**.
4. O próximo passo é o **R1 (Runtime)**: inspecionar, importar a configuração listada na seção 4 e reconstruir o cache. Só depois o teste no navegador (R2).
5. A importação do Growroom está bloqueada pela F3 do Fórum (lista de autores). Não é trabalho deste submódulo.

## 2. Git e working tree

- Branch: `chore/messenger-pendencias`, publicado em `origin` (o remoto mudou de nome para `coletivo420/aculta`; o `origin` local ainda aponta para `coletivo420/aculta-site` e o push funciona).
- **Observação de processo:** o trabalho de imagem está num branch com nome de outra frente. A política do projeto pede um branch por tarefa. Antes do merge, criar um branch próprio (ex.: `feat/aculta-image-modelos`) a partir do commit `fdc4c0ad`, ou abrir PR com a base correta. Não fazer isso sem decisão do responsável.
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

Ambiente: a regra do `AGENTS.md` pede confirmar `hostname`, `pwd`, branch e `php vendor/drush/drush/drush.php status` antes de qualquer operação de ambiente. Nesta passagem **não** foi feita nenhuma operação no Runtime.

## 4. Estado do Runtime: o que falta importar

Tudo o que é de configuração abaixo está **no repositório e não no Runtime**. Antes de importar, rodar `config:status` e
confirmar a diferença. Não usar importação total sem inspeção.

| Arquivo em `config/sync/` | Mudança | Commit |
| --- | --- | --- |
| `filter.format.basic_html.yml` | sem `data-aculta-credito` | `fdc4c0ad` |
| `filter.format.usuario_aculta.yml` | formato novo (cópia do básico) | `fdc4c0ad` |
| `editor.editor.usuario_aculta.yml` | editor novo: botão de imagem e modelo, `modelos: [pequena, padrao]`, upload sem limite de dimensão | `fdc4c0ad` |
| `editor.editor.basic_html.yml` | `max_dimensions` nulo | `fdc4c0ad` |
| `editor.editor.full_html.yml` | botão de modelo e `max_dimensions` nulo | `42a0d5eb`, `fdc4c0ad` |
| `editor.editor.revista_editorial.yml` | botão de modelo e `max_dimensions` nulo | `19b779eb`, `fdc4c0ad` |
| `user.role.authenticated.yml` | permissão `use text format usuario_aculta` | `fdc4c0ad` |
| `field.field.comment.comment_forum.comment_body.yml` | `allowed_formats` = só `usuario_aculta` | `fdc4c0ad` |

Código que o Runtime precisa carregar: o plugin de CKEditor (`aculta_image.ckeditor5.yml`, biblioteca `ckeditor5.image_model`, classe `ImageModel`), o filtro, o hook de upload e o compressor. Depois da importação: `php vendor/drush/drush/drush.php cr`.

Comandos de referência (não executados): `config:status`, `config:import --partial` (somente depois de revisar o diff; ver AGENTS sobre import persistente), `cr`.

Nenhum `updb` é necessário: o submódulo não mudou o schema desde `aculta_image_update_10003`.

## 5. Riscos conhecidos

- **Resposta antiga em `basic_html`:** ao editar uma resposta do Fórum que já usa `basic_html`, o formulário passa a oferecer só `usuario_aculta`. Testar no Runtime antes de liberar.
- **Upload acima de 1200 px:** a conversão é do `aculta_image` (hook `file_insert` em `public://inline-images/`). Não foi exercitada com upload real: o diretório `inline-images` pertence ao usuário `aculta`, e o terminal não grava nele. Testar com um arquivo de teste pela interface.
- **Seletor de modelo:** o JavaScript nunca rodou no navegador. O atributo gravado no elemento de imagem do modelo de dados (`imageBlock`/`imageInline`) é exportado no `<img>`. Se o `img` não receber o atributo no downcast, ajustar o conversor (`js/ckeditor5/aculta-image-model.js`, função de downcast).
- **Legenda:** o seletor não tem campo de legenda. A legenda do `figcaption` só aparece quando o atributo `data-aculta-legenda` existir no HTML.
- **Tópicos do Fórum:** o corpo do tópico (`node.forum`) continua aceitando `full_html` e `basic_html`. Só as respostas foram restringidas.
- **Formato do básico sem botão:** conteúdo de quem usa só o básico não ganha modelo até o formato próprio.
- **Downloads do Wayback:** em `/home/aculta/private/aculta/forum/restauro/` havia um job de download (`wayback_fetch.py assets`, depois `html`) iniciado como root numa sessão anterior. Nesta passagem não havia processo ativo, mas o estado final não foi verificado. **Não interromper nem apagar** sem consulta ao responsável. Os scripts estão fora do repositório.
- **Binários de produção:** ImageMagick e Ghostscript não foram confirmados na Hostinger. A miniatura de PDF e a redução de largura dependem disso em produção.

## 6. Próximos passos (ordem recomendada)

1. **R1 Runtime:** `hostname`, `pwd`, `status` do Drush; `config:status`; importar a lista da seção 4 depois de revisar o diff; `cr`; verificar os formatos e editores em `/admin/config/content/formats`.
2. **Teste de upload:** enviar uma imagem acima de 1200 px num editor (`revista_editorial`) e confirmar que o arquivo resultante tem 1200 px de largura.
3. **R2 Teste do seletor (navegador):** Firefox com perfil dedicado; login de teste via `drush uli`; criar a publicação de teste, inserir uma imagem, escolher o modelo, salvar e reabrir. Testar claro e escuro, 390 px e 1280 px. Registrar em `docs/portal/TESTES-NAVEGADOR.md` (AGENTS).
4. **R3 Legenda no editor**, depois **R4 CSS dos modelos** (exige autorização para alterar `web/themes/custom/aculta420/**`) e **R5 acessibilidade**.
5. **Decisões do responsável** (ver `PLANO.md`, seção 6): formato do básico (R6); tópicos do Fórum; prevenção de duplicata; confirmação dos binários na Hostinger (RC).

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
