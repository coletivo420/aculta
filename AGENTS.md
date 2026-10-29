# ACULTA — agentes CLI

## REGRA GERAL PARA AGENTES (vale para todos, sem exceção)
1. Um único branch de trabalho, definido pelo responsável. **Pendente de definição**: até lá,
   trabalhe no branch atual do checkout. Todo trabalho é commit nele.
   Não criar branch de frente, PR nova ou branch de publicação, nem trocar de branch sem ordem.
2. O servidor de desenvolvimento (checkout /home/piradopirata/projetos/aculta) acompanha
   SEMPRE o head da PR. Toda publicação é seguida de git checkout --detach no novo head.
   Não esperar janela para isso.
3. Bancos: só os existentes (aculta_runtime, aculta_simpletest). Nunca criar banco.
   Nunca site:install em banco com tabelas. toca não é nosso: não tocar.
4. Gatilho de banco do aculta_runtime em toda troca de head: updatedb:status e
   config:status somente leitura; se houver pendência, snapshot com hash antes, depois
   updatedb e config:import só do config/sync. Nunca pm:uninstall nem DROP sem ordem
   explícita do responsável.
5. Publicação por fast-forward, sem force. Gate vermelho: não publicar.
6. Em dúvida ou ordem fora deste texto: parar e perguntar ao responsável.
Detalhes: docs/operations/AGENTES-PROCEDIMENTO.md.

PROJECT-STATUS-INDEX-GUARD: 2026-10-21

Drupal 11.3+, PHP conforme `docs/architecture/environments.md`; Drupal root `web/`, módulos `web/modules/custom/`, tema `web/themes/custom/aculta420/`.

## Regras sempre ativas
- Atue apenas no escopo e na branch/PR autorizados; preserve trabalho paralelo. Sem nova PR, merge, deploy ou mudanças de produção sem autorização.
- Antes de operar no Runtime, identifique host, diretório, branch, URI e banco com Drush; consulte `docs/architecture/environments.md`. Inspecione impacto/reversão antes de operações persistentes; nunca execute ações destrutivas sem autorização.
- **Ambiente local do servidor de desenvolvimento:** quando `hostname` for `toca` e `drush status` confirmar `aculta_runtime`, a sessão já está no servidor de desenvolvimento. Use o checkout local e o Drush local (URI `http://default` é esperado). Gates somente leitura (`drush scr` de `validate-aculta420-foundation.php`, `validate-aculta420-shell-contract.php` etc.) são permitidos contra `aculta_runtime`. Não é permitido `updatedb`, `config:import` nem escrita persistente sem autorização. Não ler `aculta_runtime` como motivo para bloquear o trabalho: bloqueie só operações de escrita.
- **Autorização de Runtime pelo responsável:** uma ordem direta do responsável para trabalhar, alinhar ou validar com escrita no Runtime é autorização explícita para as escritas necessárias ao escopo solicitado em `aculta_runtime`. Antes delas, confirme host, checkout, URI, nome e contagem de tabelas; faça e registre snapshot íntegro com hash. Isso não autoriza ampliar o escopo, alterar produção, trocar credenciais, nem executar `pm:uninstall`, `DROP DATABASE` ou `DROP TABLE` sem ordem explícita que nomeie essa operação. Em importação de configuração, revise o diff e preserve/migre conteúdo conforme a decisão do responsável; não trate autorização para trabalhar no Runtime como aprovação de qualquer diff global.
- Testes Kernel usam sempre o banco dedicado `SIMPLETEST_DB` (`aculta_simpletest`), nunca `aculta_runtime`.
- **MCP Drupal de leitura:** quando `aculta-drupal` estiver disponível, use-o para status do site, extensões, diferenças config/sync e updates pendentes. O servidor é somente leitura; não expõe conteúdo nem valores de configuração. Se a sessão ainda não carregou suas ferramentas, use o Drush local pelas regras acima e recarregue a sessão depois. Nunca trate este MCP como autorização de escrita.
- Worktrees ficam em `/home/piradopirata/worktrees/` (ext4). Não usar `/mnt/disco1` para worktree ou dependências: é NTFS (`ntfs3`) e causa erros de permissão e de cópia.
- **Servidor de desenvolvimento e docroot:** o docroot `/home/aculta/public_html` é o checkout `/home/piradopirata/projetos/aculta` (o `web/` dele). Os testes visuais X11 (`DISPLAY=:0`) rodam contra esse checkout. Ele deve estar no commit da PR sob teste (`git -C /home/piradopirata/projetos/aculta rev-parse --short HEAD`). Antes de trocar o commit desse checkout, guarde alterações não commitadas (patch em pasta de backup e `git stash`) e crie uma branch de backup do HEAD anterior. Não trocar sem autorização do responsável. Após trocar, rode `drush cr` (o Twig e o CSS compilados só mudam após rebuild de cache).
- **Worktrees de integração** (ex.: `/home/piradopirata/worktrees/integracao-fronteira`) servem para desenvolvimento e revisão. Não são o docroot de teste visual.
- **X11 disponível:** `DISPLAY=:0` com Firefox ESR visível e geckodriver, conforme a seção de testes de navegador abaixo. Use-o para testes visuais no servidor de desenvolvimento; não use Chromium headless.
- Não exponha segredos ou dados pessoais. Respeite acesso, cacheability, DI, APIs Drupal 11+ e código instalado.
- Faça commits focados; execute gates pertinentes e relate somente testes efetivamente realizados. Respeite versionamento/status dos componentes.
- **Bibliotecas novas ou atualizadas (Composer, npm, módulo Drupal contrib, JS/CSS copiado ou CDN): atualize o `README.md` no mesmo commit** — tabela da seção `## Bibliotecas usadas`, com versão lida do manifesto, origem e uso — e o README do módulo/tema que passou a depender dela. Commit que adiciona dependência sem tocar em README está incompleto. Detalhes: `docs/operations/AGENT-DEPENDENCIAS.md`.
- Integração externa (Melhor Envio, Mercado Pago, Google e futuras): abra a plataforma em popup (`aculta_portal/integration-popup`, atributo `data-aculta-popup`) e responda o retorno com `Drupal\aculta_portal\Integration\PopupReturnPage`, que fecha o popup e leva a janela de origem ao destino. O resultado vai para as mensagens do Drupal (`messenger`), exibidas pelo tema no destino. Não devolva texto puro nem tela própria. Rotas de autorização e retorno levam `no_cache`. Webhook de serviço responde só com o código HTTP. **Todas as chamadas de integração (ida, retorno, webhook, páginas de configuração e endpoints JSON) usam `/integracoes/<nome>/<ação>`; é proibido `/admin/...` e `/painel-administrativo/...` (o `rename_admin_paths` reescreve o primeiro).** Detalhes: `docs/architecture/integracoes-externas.md`.

## Política global de URLs durante o desenvolvimento

- **Não crie nem mantenha aliases ou redirects temporários de compatibilidade**
  para rotas renomeadas. O projeto ainda está em desenvolvimento: atualize
  todos os consumidores para os IDs/paths canônicos e elimine os caminhos
  anteriores do roteamento. A URL aposentada deve retornar 404, não 301/302.
- Aplique esta regra a **todos os módulos ACULTA**, inclusive Studio, Marley,
  LMS, Fórum, User, Portal e submódulos. Atualize também testes e mapa de rotas.
  Gate: `php scripts/validate-no-temporary-route-aliases.php`.
- **Não confundir aliases transitórios com redirects funcionais:** login,
  logout, OAuth, checkout, confirmação, troca legítima de purpose, links
  curtos e QR codes preservam seus fluxos de segurança. Nenhum redirect
  funcional pode ser removido sem auditoria dos consumidores e callbacks.
- Rotas técnicas de front de Domain permanecem **internas** e bloqueadas para
  acesso direto; não as reative como aliases. Preserve segurança e dados
  mesmo que uma URL histórica seja descartada.

## Testes de navegador
- Use **Firefox ESR visível na sessão X11 local** (`DISPLAY=:0`), controlado pelo geckodriver via WebDriver. Referência: Firefox ESR 153.4 e geckodriver 0.37.1.
- **Não use Chromium headless nem Firefox headless** para validar páginas. O motivo e o histórico estão em `docs/operations/NAVEGADOR-TESTES.md`.
- Confirme `DISPLAY=:0` antes de abrir o navegador. Use perfil temporário em `$SUITE_OUT` (fora do repositório), criado para a sessão e removido ao final; nunca o perfil pessoal. Não use `/tmp`: é tmpfs, com cerca de 3,9 GB, e costuma estar cheio.
- O agente pode fechar Firefox ESR aberto sem uso. Antes, confirme que nenhum teste está em andamento e que nenhuma sessão WebDriver usa esse Firefox; se alguma estiver em uso, não feche.
- **Testes visuais são reutilizáveis:** todo script de teste visual (navegador, screenshot, medição de renderização) que um agente criar deve ser salvo no repositório em `tests/browser/<frente>/`, com `README.md` na pasta dizendo o que cobre, o ambiente (servidor de desenvolvimento ou local), as variáveis e as limitações. Nunca deixe o script só em `/tmp` ou em diretório pessoal: ele se perde e a sessão seguinte refaz o trabalho. Artefatos (PNG, HTML, logs) ficam fora do repositório, em `$SUITE_OUT`. Consulte `tests/browser/README.md` antes de escrever um script novo e reaproveite `wdlib.py`. Se o teste troca estado global (tema, configuração), o comando de execução documentado deve restaurar com `trap`.

## Procedimento de trabalho dos agentes
Vale para todos os agentes. Detalhes: docs/operations/AGENTES-PROCEDIMENTO.md.
- Um único branch de trabalho, definido pelo responsável (pendente; ver regra 1 acima). Todo trabalho é commit nele.
- O servidor de desenvolvimento é o checkout /home/piradopirata/projetos/aculta, sempre no
  head da PR, em HEAD destacado.
- Bancos: só os existentes. Nunca criar banco. Nunca site:install em banco com tabelas.
- updatedb e config:import no aculta_runtime seguem o gatilho do procedimento (por
  head), com snapshot antes. pm:uninstall e DROP exigem ordem explícita.
- Publicação por fast-forward, sem force. Gate vermelho: não publicar.

## Consulte apenas quando aplicável
- **Índice de regras por escopo:** `docs/operations/AGENT-POLICY-INDEX.md`. Consulte somente o documento local pertinente. `docs/operations/AGENT-GUARDRAILS-FULL.md` é arquivo histórico para auditoria, não contexto padrão.
- **Aceite e consentimento:** existe um único formulário de aceite e o ledger registra somente os termos. Não crie formulários/checkboxes ou tipos adicionais sem decisão do responsável. Consulte `docs/portal/ACEITE-UNICO.md` e rode `php scripts/validate-single-acceptance.php` quando alterar fluxos de dados pessoais ou consentimento.
- Portal: `docs/portal/GUARDRAILS.md`, `docs/portal/DRUPAL-11-STANDARDS.md` e documentação local.
- Rotas/domínios: `docs/architecture/ROUTES-SITEMAP.md` e contratos vinculados.
- Status/releases: `docs/operations/RELEASE-LIFECYCLE.md`; índice gerado, nunca editado manualmente (`php scripts/validate-project-status-index.php`).
- UI: documentação do tema; demais componentes: README/contratos próprios.

## Economia de contexto
Modelo fixo **por sessão** escolhido no CLI, não neste arquivo. Busque trechos relevantes, evite dumps/logs integrais e repetições; não abra documentação não relacionada. Preserve prefixos estáveis quando possível, compacte contexto obsoleto e meça uso/cache real. Detalhes: `docs/operations/AGENT-CACHE.md`.

## Ferramentas e escopo
- Para saídas extensas, use `python3 scripts/agent-output.py --lines 60 -- COMANDO` (consulte `docs/operations/AGENT-OUTPUT.md`); preserve e verifique o código de saída. Logs locais podem conter dados sensíveis.
- Instruções locais em `web/modules/custom/aculta_portal/AGENTS.md` e submódulos prevalecem no próprio escopo; não carregue regras de componentes alheios.
- Exclua `vendor/`, `web/core/`, `web/modules/contrib/`, `node_modules/`, caches e artefatos gerados das buscas **por padrão**, mas inclua o código instalado de Core/contrib quando necessário para verificar APIs e compatibilidade.

## Worktrees de agentes
- Todo worktree de agente é criado em **`/home/piradopirata/worktrees/<frente>-<sufixo>`** (ext4). Não use `/mnt/disco1` nem `/tmp`.
- Motivo: `/mnt/disco1` é NTFS (`ntfs3`, `fmask=0022`) — `cp -a` não preserva permissões nem bit de execução e emite "Operação sem suporte" em todos os arquivos; `/tmp` é tmpfs (≈3,9 GB, quase sempre cheio). Permissão errada já invalidou execuções antes.
- Copie `vendor/`, `web/core/` e `web/modules/contrib/` como **cópias físicas** (`cp -a`), nunca symlink para outro checkout. Confira `du -sh` e dono `piradopirata` antes de rodar scripts; `php vendor/bin/drush` dispensa o bit de execução.
- Um worktree por frente/branch. Consulte `git worktree list` antes de criar; não reutilize, limpe nem remova o worktree ou a base de teste de outro agente.
- Ao terminar, remova apenas o próprio worktree (`git worktree remove <path>`) e publique a branch remota antes de descartá-lo.

## DeepSeek: bloqueio de pico
- Se o provedor da sessão for DeepSeek, **não execute chamadas diretas à API**: use o proxy local de `scripts/deepseek-offpeak.py` conforme `docs/operations/DEEPSEEK-OFFPEAK.md`. No pico, o trabalho segue com o Claude padrão (lançador `claude-deepseek`, que escolhe o provedor pelo horário); não chame a API DeepSeek diretamente e não contorne o HTTP 429. A instrução não substitui o bloqueio de rede nem a configuração do endpoint no CLI.
