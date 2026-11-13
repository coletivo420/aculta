# ACULTA420 V2 — instruções para agentes

Herde o `AGENTS.md` da raiz do repositório. Estas regras são específicas do tema V2 e complementam a raiz; em caso de conflito, aplicar a precedencia do AGENTS.md raiz e as excecoes tecnicas aprovadas do tema.

## 1. Antes de editar

1. Consulte somente os trechos relevantes de [README.md](README.md), [docs/architecture.md](docs/architecture.md) e das ADRs vigentes. Confira o codigo atual antes de decidir.
2. Confira HEAD, `git status`, remoto e alteracoes de outros agentes. Worktree isolada e permitida. Entrega por fast-forward em `dev` sem force push ou merge em `main`.
3. Confirme o estado no código antes de afirmar status. O roadmap pode estar atrás do código.
4. O pedido do responsavel autoriza alteracoes tecnicas no tema V2 dentro do escopo, sem nova confirmacao. Nao altere o legado V1 sem tarefa especifica.

## 2. Regras de implementação

**Contrato vigente (ADR-054 + ADR-056):** cinco regiões finais (`institution`, `header`, `content`, `sidebar_first`, `footer`), um shell, composição por Layout Builder/UI Patterns nos displays compatíveis. A UI Patterns Library é o catálogo único de SDCs/stories; `aculta_ui_library` mantém apenas redirecionamento legado para esse catálogo, sem listar ou pré-visualizar blocos. A ADR-056 substitui a estratégia de shell paralelo e determina corte orientado por consumidores comprovados; não remover adaptadores necessários ao Drupal nem converter templates mecanicamente. Componentes novos têm schema SDC e story da UI Patterns Library. A função administrativa `Layout de container` está descontinuada. Plano histórico: `../../../../docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md`; decisão vigente: `../../../../docs/decisions/ADR-056-ui-patterns-2-recomendacoes-e-corte-documentacao.md`.

### Contrato obrigatório para componentes e páginas

- Antes de criar markup de interface, procure um SDC compatível em `components/` e consulte `docs/components.md`. Reutilize ou componha SDCs quando o contrato servir; não duplique componente para cada domínio, View, display ou origem de dados.
- Ao criar um componente visual, crie-o como SDC em `components/<nome>/`: manifest `<nome>.component.yml` com schema estrito (`additionalProperties: false`), Twig `<nome>.twig`, CSS/JS locais somente quando necessários e pelo menos uma story `<nome>.<variante>.story.yml` (use `default` quando houver um estado padrão real). Story faz parte da entrega do SDC, desde o primeiro commit, e deve usar conteúdo fictício, acessível e representativo.
- Leia [a skill SDC](../../../../.agents/skills/sdc/SKILL.md) para autoria/contrato do componente. Leia também [a skill UI Patterns](../../../../.agents/skills/ui-patterns/SKILL.md) quando for expor ou compor o SDC em UI Patterns, Blocks, Layout Builder, Views, formatter ou outra integração editorial.
- Use props para valores simples e tipados; slots para conteúdo renderizável e composição. Não passe entidades, usuários nem render arrays por props. Preserve `attributes`, metadados de cache, acesso e tradução. Não esconda SDC interno ou editorial da UI Patterns Library.
- A UI Patterns Library descobre SDCs e stories pelos arquivos do tema: não crie registro, catálogo, story PHP ou preview paralelo. Verifique que o SDC e sua story aparecem no catálogo oficial; se a descoberta não puder ser validada no Runtime, marque-a `DEFERRED`. Rode `php scripts/validate-aculta420-sdc-library-contract.php` para verificar manifest/Twig/story e os guardrails contra catálogo próprio.
- Para composição editorial, use o plugin UI Patterns correspondente que esteja instalado e habilitado: Blocks, Layouts, Views ou Field Formatters. Confirme versões em Composer/lock e módulos em `config/sync/core.extension.yml`; não invente Sources nem habilite integrações por inferência.
- Páginas e experiências novas devem ser compostas por SDCs via UI Suite sempre que houver integração adequada. Crie um SDC de página apenas quando houver responsabilidade semântica própria não coberta pelos componentes existentes. Consulta, regras de negócio e autorização ficam no módulo owner; o tema recebe dados preparados e apresenta.
- `aculta_ui_library` é somente uma rota legada que redireciona à UI Patterns Library. Não adicionar catálogo de blocos, listagem de componentes nem prévias nesse módulo ou no tema.


- **Nao reintroduzir Bootstrap ou bundler Vite/Node no tema.** Node/npm continuam permitidos para lint, testes e ferramentas locais. Evitar `core/drupal.dialog.ajax` em fluxos novos; excecoes com justificativa e regressao. **jQuery 4 permitido** nos módulos ACULTA e nos SDCs V2, sob demanda, com dependência `core/jquery` somente nas bibliotecas consumidoras. Não instalar outra cópia nem carregar globalmente por padrão. Manter `Drupal.behaviors`, `core/once`, acessibilidade, testes e fallback. **HTML nativo/Form API continuam padrão de formulários; HTMX é preferido para atualização assíncrona de fragmentos** pelo ADR-050. jQuery não libera Drupal AJAX legado automaticamente. Decisão: ADR-054.
- **SDC é o mecanismo de componente.** Schema estrito em toda prop. `with_context = false` em includes de componente. Não passe entidade, usuário ou render array de conteúdo inteiro como prop.
- **Botões são o SDC `aculta420_v2:button`.** Variantes: `primary`, `secondary`, `outline`, `ghost`, `danger`. Tamanho: `size: small` (só espaçamento e fonte; alvo de 44 px). Ícones: `icon_before` e `icon_after`. Só ícone: sem `text`, com `aria_label`. Desativado: `disabled`. `action` vira `primary`; `small` não é variante. Em Twig: `include('aculta420_v2:button', { text: '...', url: '...', variant: 'secondary' }, with_context = false)`. Em PHP: `['#type' => 'component', '#component' => 'aculta420_v2:button', '#props' => [...]]`, com `->toString()` em URLs e `(string)` em textos traduzidos. Não crie marcação nova com `button`, `button--*`, `btn-*` ou `data-portal-ui` de botão. Exceções (envios de formulário como `<input>`/`<button name>`, `#type => button` com JS próprio e links dentro de `#links`) estão em `docs/botoes.md`; não as estenda sem registrar a exceção lá.
- **Tokens semânticos.** Novos componentes usam o token que descreve a função visual (`--color-text-primary`, `--color-surface-raised`). Primitivas de paleta ficam dentro de `src/styles/tokens.css`.
- **Modo de cor só por `data-theme`.** Não crie seletor de dark fora de `tokens.css` e não use `prefers-color-scheme`.
- **Mobile-first.** Base em 320 CSS px, sem rolagem horizontal da página, alvo de toque de 44 px (exceção registrada: controles da barra institucional com 35 px, ADR-047, item 10; ícones da barra com 30 px), zoom de 200% funcional. Antes de mexer em `max-width`, rastreie seletor, cascata, componente, consumidores e estados.
- **Tema de teste:** mantenha o tema ACULTA ativo sem alterar `system.theme` apenas para validar testes (regra global em `AGENTS.md`).
- **Acessibilidade.** HTML semântico, foco visível (`:focus-visible`), estado nunca só por cor, texto oculto (`visually-hidden`) quando um controle só com ícone precisa de nome.
- **JavaScript mínimo.** Só onde há interação. Use `Drupal.behaviors` com `core/once`. O menu e os diálogos devem funcionar sem JavaScript quando possível.
- **HTMX:** preferido para interacoes assincronas de fragmentos (`core/drupal.htmx`, `data-hx-*`), mantendo Form API, HTML nativo e fallback. Drupal AJAX e legado, mas uma integracao existente pode ser preservada ate migracao segura; escolha tecnica documentada e testada. Progresso: ADR-050.
- **Gavetas de mensagens/avisos (exceção registrada em 2026-11-05):** abrem por `<button>` e HTMX; dependem de JavaScript. O fallback sem JavaScript não tem link nativo para a página completa, porque o Portal não fornece `all_href`. Decisão do responsável: manter a exceção documentada, sem converter o gatilho em link nesta fase.
- **Classes mapeadas:** toda classe de `PortalUiHooks::TOKENS` precisa de CSS no tema ou de exceção em `docs/classes-sem-css-excecoes.md`. Nomes Bootstrap (`bi*`, `btn-*`, `breadcrumb*`) são proibidos no mapa. Gate: `scripts/validate-aculta420-mapped-classes.php`.
- **Nada de contexto de entidade no tema.** Não leia `user_picture`, Profile, configuração Social Auth ou regra de domínio. O Portal entrega o dado pronto.
- **Avatar.** Ordem: foto liberada → avatar do acervo → ícone neutro. Nunca letra, inicial ou monograma do nome.

## 3. Fronteiras com o Portal

- O Portal entrega variáveis de página pelo contrato `domain_presentation` e pelas variáveis `aculta_*` (ver [docs/architecture.md](docs/architecture.md)).
- Mudança de contrato do Portal (por exemplo, a chave neutra de purpose) é commit ou PR separado, conforme ADR-046.
- Autenticação, Social Auth, CAPTCHA e formulários de conta pertencem ao `aculta_portal`. O tema apresenta.

## 4. Gates e evidência

- Rode só os gates do escopo: o catálogo por escopo está em `docs/operations/GATES.md` (raiz); os gates específicos do tema estão em [docs/gates.md](docs/gates.md). Cole a saída. Não declare PASS sem execução.
- Se um gate não puder rodar no ambiente, registre como `DEFERRED` com o motivo.
- Na homologacao de feature visual, validar claro/escuro e viewports relevantes, incluindo 320 CSS px, desktop, zoom e teclado quando afetados. Fix localizado pede captura focal quando viavel, nao matriz completa por rotina. Sem execucao real, registrar DEFERRED (ver `docs/operations/NAVEGADOR-TESTES.md`).
- Estados autenticados e leitores de tela são evidência separada. Se não foram testados, diga isso.

## 5. Versão e entrega

- Classifique a mudança como PATCH, MINOR ou MAJOR. Mudança de shell ou fundações é MINOR (ADR-046).
- Avance a versao em `aculta420_v2.info.yml` somente numa entrega de release validada, com CHANGELOG e roadmap quando o marco mudar. Fixes intermediarios nao exigem incrementar versao.
- Commits atômicos em português, sem coautoria fictícia. Trailer de CLI é opcional e só identifica a sessão efetiva. Entrega em `origin/dev` por fast-forward após os gates pertinentes; PR e merge só com pedido explícito.
- Entrega de fase com código não testado no servidor de desenvolvimento termina com `RUNTIME STATUS: DEFERRED` no relatório.

## 6. Documentacao por mudanca de contrato

- Novos componentes SDC devem ter schema, story e criterios de acesso/estilo consistentes; documentar props e slots publicos em `docs/components.md`. Fix de CSS sem mudanca de API nao exige reescrever inventario.
- Atualize `docs/ui-styles.md` ao alterar opcao editorial; `docs/architecture.md` ao alterar hooks, servicos, bibliotecas ou contrato do Portal; `docs/gates.md` quando criar/mudar gate.
- CHANGELOG, roadmap, TODO e status so mudam quando release, marco, fase ou pendencia efetiva mudar. Nao registrar cada teste em todos os documentos; resultados ficam no CI/relato.
- Nunca declarar implementado ou homologado sem evidencia. A classe de revisao da entrega segue `docs/operations/AGENT-REVIEW-POLICY.md`, preservando gates do escopo.

## 7. Não faça

- Não crie componente só para substituir uma classe utilitária simples sem contrato reutilizável.
- Não adote outra suíte concorrente (como `drupal/bootstrap_components` ou design systems externos) sem nova decisão arquitetural; a integração da UI Suite oficial (`drupal/ui_patterns`, `drupal/ui_styles`, `drupal/ui_icons`) foi decidida nas ADR-054 e ADR-055 como infraestrutura do tema e de seus SDCs, sem concorrer com a arquitetura visual nem substituir o tema ACULTA420 V2.
- Não grave segredo, token, caminho de Homelab ou hostname de produção no tema.
- Nao apague dados, nao execute migracao/configuracao estrutural sem autorizacao especifica. A sincronizacao segura de codigo/cache no Runtime de desenvolvimento segue o preflight de `aculta-runtime-sync`.
- Não declare o tema "12 ready" sem a validação do ADR-045.
