# ACULTA Studio — revisão parcial S-R4 / gate S-R5 (2026-10-25)

**PR única:** [#297](https://github.com/coletivo420/aculta/pull/297). Branch: `docs/aculta-forum-0.1.0-dev.1`.
**Estado:** S-R4 **não homologada**, S-R5 **não iniciada para aceite**. Nenhum RC, deploy ou merge.
Documento complementar ao [handoff](HANDOFF-S-R4-S-R5-2026-10-25.md), que permanece normativo.

## Base e limite de execução

- HEAD remoto na inspeção inicial: `c0bd0113a58d26ee9b9a0e570cb53f72937a8fa3`; o HEAD de referência do pedido (`bb4dc7...`) estava ultrapassado.
- Arquivos inspecionados diretamente na branch pelo conector GitHub: `AGENTS.md`; instruções locais Studio/ACULTA420; ambientes; contrato Conta/Perfil; handoff; controladores e serviços do Studio/User; routes; quatro manifests/Twig/CSS SDC; Unit e Kernel; infraestrutura `tests/browser/aculta-studio/`.
- **Ambiente de execução desta sessão:** contêiner separado, sem checkout ACULTA e sem ligação SSH ao Homelab. `pwd` devolveu `/`; PHP CLI disponível: `8.4.24`; Node CLI disponível. `composer`, `phpunit`, `phpcs` e `firefox-esr` não foram encontrados. `DISPLAY=:0` estava definido, mas sem navegador ESR. `git ls-remote https://github.com/coletivo420/aculta.git` falhou por DNS (`Could not resolve host: github.com`). O acesso ao repositório ocorreu somente pelo conector, sem runtime Drupal.
- Nenhum `git status` do Homelab, `Drush status` ou identificação do banco/usuário local pôde ser executado. Nada foi modificado no Runtime.

## Evidências e resultados reais

| Gate ou revisão | Estado | Evidência/limite |
| --- | --- | --- |
| Branch e PR #297 abertas, HEAD remoto novo | **PASS (GitHub)** | `get_pr_info` confirmou branch e PR aberta; commits focados na mesma branch |
| Inspeção de rotas e limites módulo/tema | **PASS (leitura estática)** | Rota `aculta_studio.profile` aponta a `/studio/perfil`, purpose ACCOUNT; editor compõe quatro `#type: component`; propriedade pública e formulários continuam no User |
| Separação de identidade/conta no código | **PASS (leitura estática limitada)** | Controller carrega exclusivamente UID autenticado; `AuthorIdentityResolver::resolve(..., 'profile_full')` não tem fallback de nome cadastral; ausência resolve rótulo neutro |
| Contratos Twig/CSS/schema dos SDCs | **PASS (arquivos existentes)** | Quatro manifests, Twig e CSS presentes com slots e props declarados; **não prova** descoberta Core, schema runtime ou HTML renderizado |
| `node --check` em cópia local da nova suíte `profile-s-r4.mjs` | **PASS (sintaxe local)** | Exit 0; não substitui execução pelo Firefox ESR, tampouco testa Drupal |
| PHP `-l`, PHPCS e PHPUnit Unit/Kernel/Functional | **SKIP (bloqueio ambiente)** | Checkout/dependências/comandos do repositório indisponíveis; não declarar PASS |
| Foundation, módulo-tema, Drupal 11, status, aliases e `aculta_image` | **SKIP (bloqueio ambiente)** | Scripts exigem checkout completo; **nenhum gate executado** |
| SDC discovery, slots renderizados, tema Olivero, cache tags/contextos | **SKIP (bloqueio Runtime)** | Leitura estática não equivale a `plugin.manager.sdc` nem a render Core |
| Duas contas, perfil público/privado, fotos, AJAX e formulários/no-JS | **SKIP (bloqueio Runtime/fixtures)** | Dados sintéticos e sessões HTTP/Drush do servidor de teste ausentes |
| Firefox ESR visível/X11, claro/escuro, responsivo, zoom, teclado, WCAG | **SKIP (Firefox ESR indisponível)** | Suíte preparada, sem capturas de Runtime nem execução visual |
| S-R5 comparação Conta Studio/legado e Perfil MAIN | **SKIP (gate S-R4 bloqueante)** | Planejamento abaixo; não iniciar homologação antes de S-R4 |

**FAIL funcional:** nenhum reproduzido; isso **não significa** que o código esteja livre de falhas.
**SKIP não pode ser convertido em PASS**. Sem evidência de Runtime, manter SDC `experimental` e versões atuais Studio `0.1.0-dev.22` / ACULTA420 `0.7.0-dev.2`.

## Instrumentação adicionada

- `tests/browser/aculta-studio/profile-s-r4.mjs`: suíte Firefox ESR visível (duas sessões independentes, teste sem JS, quatro SDCs, links de edição, sentinelas diferentes por conta, reflow 320/375/390/768/1024/1440, capturas claro/escuro). O script **não grava estado persistente** e bloqueia produção.
- `tests/browser/aculta-studio/README.md`: comandos, pré-condições, variáveis, leitura de PASS/FAIL/SKIP, proteções de privacidade e limites. Artefatos fora do Git em `$SUITE_OUT`.
- A instrumentação é **parcial**: não substitui teste manual de foto/crop/modal, escrita Form API, CSRF, links públicos no MAIN, preferência persistida, inspeção de contraste/zoom nem o gate S-R5. Não adicionar PASS à matriz por apenas existir script.

## Próxima execução no checkout Homelab

1. Confirmar `hostname`, `pwd -P`, `git status --short`, `git branch --show-current`, `git rev-parse HEAD`, `gh pr view 297`, `php -v`, `composer --version`; identificar com `php vendor/drush/drush/drush.php status` o ambiente/URI/banco **antes** de qualquer alteração de dados.
2. Rodar PHP lint e PHPCS nos arquivos Studio/tema aplicáveis; PHPUnit Unit, Kernel e Functional do Studio e ACULTA420, com contagens, exit codes e log saneado. Corrigir erros reais com commits de escopo estreito.
3. Executar gates: `validate-aculta420-foundation.php`, `validate-module-theme-boundary.php`, `validate-aculta-portal-drupal11.php`, `validate-project-status-index.php`, `validate-no-temporary-route-aliases.php`, `validate-aculta-image.php` e `git diff --check`. Registrar falhas preexistentes sem ocultação.
4. Inspecionar `plugin.manager.sdc`, render Core, escopo ACL/Domain ACCOUNT, tags/contextos e isolamento cache com duas contas sintéticas, inclusive apelido ausente, foto privada/publicada e visualização de próprio avatar privado só pelo titular. Conferir o HTML sob ACULTA420 e Olivero.
5. Rodar Firefox ESR `DISPLAY=:0` via geckodriver; a nova suíte `profile-s-r4.mjs` complementa `visual.mjs`. Registrar screenshots e resultados em `SUITE_OUT`, sem subir dados das contas ao Git. Exercitar formulários User e modal de imagem também manualmente com JS e sem JS; validar teclado, foco, zoom 200–400%, contraste, overflow e estilos responsivos.
6. Só declarar S-R4 homologada quando Unit/Kernel/Functional, ACL/cache e browser/Runtime cumprirem o contrato; só então executar S-R5 comparativa, mantendo permanentemente `/conta-legada`.

## Matriz preparatória S-R5 (não executada)

| Domínio / áreas | Studio | Referência obrigatória | Comparação necessária |
| --- | --- | --- | --- |
| ACCOUNT: cadastro e dados | `/studio/conta/dados` | `/conta-legada`, `/dados` | Nome/WhatsApp privados sem campo apelido no formulário Studio |
| ACCOUNT: endereço | `/studio/conta/endereco` | Formulário legado | CEP, número, S/N, edição, checkout, erro, retorno |
| ACCOUNT: documentos | `/studio/conta/documentos` | `/dados/outros-dados` | Serviço opcional CPF/CNPJ, máscara, ausência 404, não alterar dado gravado |
| ACCOUNT: segurança | `/studio/conta/seguranca`, `/senha`, `/conexoes` | Interfaces legadas | E-mail, senha, OAuth, CSRF, acesso próprio, cancelamento |
| ACCOUNT: comércio e apoio | `/studio/conta/pedidos`, `/apoio` | Pedidos e apoio legados | UID, detalhes de pedidos, assinatura, ações e autorização |
| ACCOUNT: preferências | `/studio/conta/preferencias` | Preferências legadas | Aparência gravada via owner, persistência e invalidação |
| ACCOUNT / MAIN: perfil | `/studio/perfil` e editores User | Perfil público `/u/{apelido}` em MAIN | Apelido público/ausente, foto/avatar, bio, redes, privacidade e dados ocultos por policy |

**Atenção:** a lacuna de unicidade transacional concorrente de CPF/CNPJ permanece no owner `aculta_id_cpf`, fora do escopo de correção automática desta revisão. Não copiar dados de Conta para Perfil, não criar aliases, não remover legado. Ao obter evidências de aceite, atualizar README/roadmap/status, changelogs, índice gerado e versões conforme `docs/operations/RELEASE-LIFECYCLE.md`, sem antecipar RC.
