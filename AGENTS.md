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
- **Endereço centralizado em `aculta_portal_cep` e país exclusivo Brasil (BR):**
  - Ao implementar ou alterar qualquer formulário de endereço no ACULTA, use o serviço, widget e contrato centralizados em `aculta_portal_cep` (e `AccountAddressForm`). Não crie campos, endpoints, clientes de CEP ou fluxos de endereço paralelos.
  - O país é sempre o Brasil (`BR`). Não exiba opção para selecionar país em nenhum formulário ou local do projeto; configure o endereço para usar exclusivamente `BR` e confirme esse valor também no servidor ao validar e salvar. JavaScript ou campo oculto, por si só, não substitui a validação do servidor.
  - A busca de CEP deve continuar usando a biblioteca `aculta_portal_cep/cep-address`. Ela pode preencher os dados postais brasileiros, mas deve preservar número, S/N e complemento conforme o contrato central vigente.
  - Antes de concluir, procure outros formulários e widgets de endereço afetados. Teste criação e edição, comportamento do CEP e rejeição ou normalização de um `country_code` diferente de `BR`. Preserve dados já armazenados e atualize a documentação do owner quando o formulário ou seu contrato mudar. Referências: `docs/decisions/ADR-034-pais-unico-brasil-endereco-central.md` e `docs/portal/ACCOUNT-ADDRESS.md`.
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
- **Mobile-first é norma global do tema ACULTA420 para todos os agentes que alterem UI**, em todos os purposes e consumers; não é restrito ao Studio. Antes de mudar CSS, Twig, SDC, JS de apresentação ou layout, consulte `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md` e `web/themes/custom/aculta420/AGENTS.md`. A fidelidade OpenCulturas Next é restrita ao Studio; preserve Institution Bar e Domain Header. Não oculte a marca da Institution Bar por presumir que Domain Header existe em todos os purposes; em telas estreitas, quebre a barra em linhas quando necessário.
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


## Endereços e Locais (regra para agentes)

- Antes de criar ou alterar qualquer formulário de endereço, consulte `docs/decisions/ADR-033-endereco-busca-e-locais.md`, `docs/portal/ACCOUNT-ADDRESS.md` e `docs/portal/GUARDRAILS.md`. A integração CEP central é padrão para todo formulário de endereço completo do Portal, inclusive módulos ACULTA novos.
- Não adicione seletor de país a formulários brasileiros. Preserve Conta e checkout: a busca existente por CEP não deve mudar.
- Busca por logradouro/endereço é uma função planejada e restrita às interfaces de Events e Maps. Não a exponha em Conta, checkout nem em formulário global. Não invente provedor, geocodificação ou endpoint.
- Para Activity, use Local de `aculta_events`; não reintroduza campos de endereço avulsos. Seletor e cadastro devem seguir o plano de modal Drupal/AJAX, o contrato de autorização e a revisão de privacidade.
- Ao encontrar formulário completo sem o adaptador, registre como lacuna de integração. Não altere dados/configuração Runtime nem promova versão ao apenas documentar.


## Regra para popup de Local e integração entre módulos

Agentes devem interpretar “modal” como popup sobreposto criado pelo sistema de diálogo do Drupal, com AJAX. Não trocar por formulário inline, off-canvas ou rota de tela inteira sem decisão explícita. Preservar o estado do formulário pai, retornar foco, suportar teclado/Escape, anunciar erros/estados e atualizar a referência do Local após confirmação.

Para Locais, `aculta_events` é o owner da entidade e do ciclo editorial; `aculta_maps` consome os mesmos Locais autorizados para mapas e geocodificação. Não criar catálogo ou formulário paralelo em Maps. Consulte ADR-033 e o plano LOC antes de alterar os módulos.


## Centralização administrativa de Eventos, Datas e Locais

- A interface administrativa de Eventos é o ponto único de entrada para criar e gerir eventos, datas e locais. Datas (aculta_event_date) são componentes de um Evento, não uma tarefa editorial ou conteúdo administrativo independente.
- A criação inicial do Evento deve permitir configurar suas datas e associar um Local a cada data. Na edição, datas, horários e Locais devem ser geridos dentro do contexto do Evento, sem ação global “Adicionar data de evento” ou formulário apresentado como conteúdo separado.
- Um endpoint ou formulário técnico de Data só pode permanecer se estiver integrado ao fluxo do Evento, receber e validar a referência do Evento no servidor e não for exposto como operação independente. Acesso direto sem contexto autorizado deve ser negado. Não criar alias ou redirect temporário para rotas aposentadas.
- A seleção/cadastro de Local continua em popup Drupal com AJAX, aberto a partir do contexto de edição da Data do Evento. aculta_events é o owner; aculta_maps consome os mesmos Locais. Não duplicar formulários ou catálogos.
- Opções administrativas de criação de Eventos e Locais devem ser configuráveis no painel de Eventos, com padrão content_editor e autorização aplicada também no servidor.
- Rotas canônicas de Data (sempre com o Evento no caminho): `aculta_events.event_date_add` (`/painel-administrativo/eventos/{aculta_event}/datas/adicionar`), `event_date_edit` e `event_date_delete`. A antiga rota global `aculta_events.date_add` foi aposentada e responde 404, sem alias. Não reintroduza formulário/menu de Data sem Evento de origem.
- **Fronteira de domínio:** criar/editar/publicar Eventos, Datas e Locais existe somente no purpose `eventos`. As rotas editoriais de `aculta_events` declaram `_aculta_domain_purpose: eventos` + `_aculta_domain_purpose_authoritative: true`; o `DomainRouteSubscriber` deve preservar esse opt-in e o `DomainPurposeRequestSubscriber` nega a rota em outros domínios (inclusive POST direto). A integração `aculta_events_openstreetmap`, que cria Locais, também pertence a `eventos`. Não crie alias/redirect para escrita cruzada nem um segundo fluxo de eventos. Outros domínios só podem exibir o bloco somente leitura `aculta_events_upcoming`, sem controles editoriais, com cache/canonical corretos e sem expor eventos privados do LMS.
