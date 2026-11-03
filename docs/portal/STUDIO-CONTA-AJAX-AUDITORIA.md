# Conta Studio: auditoria AJAX e plano de recuperação

## Reteste ampliado — 2026-11-03

No Firefox ESR visível do Runtime, as nove rotas diretas `/conta/*` responderam HTTP 200; navegação AJAX comum com `replaceWith`, título/breadcrumb/foco, behaviors, back/forward, alternância mobile e cliques concorrentes passaram. No-JS passou 4/4 (Dados, Endereço e voltar). O Dashboard e o Perfil passaram em 320–1280px; Conta/Marley passaram em 320–1440px e Perfil foi verificado com zoom real 200/400% sem overflow.

A suíte ampliada de Conta terminou 92/92 verificações aplicáveis PASS e 22 SKIP condicionais. Cobriu respostas HTTP 403/404 com feedback e retry, timeout, resposta tardia descartada, sessão expirada, navegação concorrente, rebuild de Form API inválido/válido e os demais fluxos listados no harness. Os SKIP dependem de publicação própria/aba de moderação ausente para esta conta. Senha, uploads/anexos e escritas pessoais/endereço não foram submetidos. Acesso anônimo/roles foi verificado em rodada anterior, mas não repetido neste reteste. A imagem de referência não estava disponível como arquivo local para comparação pareada; não declarar homologação visual integral. Artefatos em `$SUITE_OUT/studio-audit-20261103/member-final10/` e `$SUITE_OUT/studio-audit-20261103/nojs-final/`.

O ajuste de `Drupal.Ajax(null, null, …)` evita que o construtor Core instale um segundo listener de clique; o listener delegado único fica em `document` e consulta a raiz atual, que o `replaceWith` substitui. Isso preserva os cliques elegíveis após troca de seção e rebuild de Form API sem listeners globais duplicados. Código alterado em `account-navigation.js`; versão do módulo `0.1.0-dev.41`.

Data: 2026-11-03. Estado: navegação da Conta e composição Studio verificadas no Runtime de desenvolvimento; matriz de formulários com escrita e comparação integral da prancha permanecem parciais.
Implementação inicial em `8ccbcb0618` (mais alias autowired `bf731e8789` e reconciliação Sync `1e336fe73e`/`b70e56dbec`), baseada nos contratos vigentes. Correção de persistência do listener publicada em `27e8d06263`; Runtime sincronizado no mesmo checkout, `updatedb:status` sem pendências, `config:status` limpo e `drush cr` PASS. Firefox ESR visível autenticado passou na conta comum nesta rodada e nas contas comum/moderadora na rodada A/B anterior; sem alterações de conteúdo editorial.
Escopo: entrada account, seções privadas /conta/* e comparação com /conta-legada e suas seções. Não altera grants, entidades ou rotas.

## Evidências e limites

As capturas fornecidas pelo responsável mostram /conta/dados com navegação, descrição e formulário distribuídos horizontalmente; /dados apresenta menu lateral, abas e formulário com largura útil. A auditoria inicial era estática; o reteste autenticado no Runtime está registrado abaixo.

| Achado | Evidência no repositório | Consequência |
| --- | --- | --- |
| Rotas distintas | aculta_studio.routing.yml declara /conta/dados, /conta/endereco etc.; aculta_portal.routing.yml declara /dados e /dados/endereco | Reutilizar controllers/Form API não equivale a reutilizar URLs. Não criar outro prefixo para tentar resolver AJAX. |
| Shell legado anexa sua biblioteca | src/AccountShellBuilder.php registra seções legadas e anexa aculta_portal/account-navigation ao tema aculta_portal_shell | O builder retorna quando a rota não corresponde às seções. As rotas Studio não estão na lista base; hooks de extensão precisam ser conferidos em Runtime. |
| Conta Studio emite links comuns | StudioAccountSectionController::compose/navigation usa studio-account-section, studio-account-nav-link e studio-account-contextual-nav | Não emite nesse controller o alvo portal-account-body nem o contrato de links do JS editorial. |
| JS Studio é editorial | js/studio-navigation.js busca aculta-studio-root e links data-aculta-studio tab/section/subtab; popstate aceita somente /marley | Carregar apenas essa biblioteca não habilita as seções privadas /conta/*. |
| Contrato legado é diferente | js/account-navigation.js usa data-portal-account-layout e portal-account-body | Copiar a library sem adaptar o markup não ativa a navegação. |
| Metadados AJAX existem | src/Render/AccountAjaxMainContentRenderer.php acrescenta data-portal-chrome em rotas com purpose account | Responder em drupal_ajax não basta: consumidor deve aplicar/remover metadados e atualizar título/breadcrumb. |
| Mapeamento visual inadequado à composição | Tema src/Hook/PortalUiHooks.php traduz studio-account-section para studio + studio__layout; css/components/studio.css torna esse layout flex em linha no desktop | Os filhos de render arrays sem wrappers semânticos adequados podem disputar a mesma linha. Evidência compatível com a captura; confirmar DOM e estilo computado antes da correção. |
| Formulários já reconhecem rotas Studio | PortalHooks::formAlter identifica aculta_studio.account_data e aculta_studio.account_address | Não duplicar formulários/storage para corrigir navegação. Investigar AJAX de navegação e AJAX de formulário separadamente. |

Arquivos acima são relativos a web/modules/custom/aculta_portal, exceto arquivos do tema, relativos a web/themes/custom/aculta420. Controller e JS Studio ficam em modules/aculta_studio.

Conclusão confirmada: as rotas Conta Studio não anexavam uma biblioteca que consumisse seus links; o JS legado e o JS editorial Marley usavam seletores e escopos diferentes. Além disso, o mapeamento de `studio-account-section` para o layout flex editorial comprimia os filhos sem wrappers de sidebar/main. Corrigido com biblioteca dedicada, atributos elegíveis, Grid próprio e listener delegado único em `document`: a raiz substituída por `replaceWith` não leva mais o listener consigo. A matriz Runtime registrada no topo valida a troca, erros, retry, timeout, histórico e Form API.

## Implementação local — 2026-11-03

- `StudioAccountSectionController` mantém os render arrays owners intactos em `content.owner`, adiciona root estável, região `aria-live`, navegação própria e biblioteca `aculta_studio/account-navigation`.
- O cliente intercepta somente links marcados, same-origin e sem modificadores/target/download; usa `Drupal.Ajax` com `replaceWith`, `pushState`/`popstate`, metadados server-side do renderer de Account, foco, estado busy e fallback/retry após erro/timeout. O detalhe nativo mantém abrir/fechar no mobile. O listener único em `document` sobrevive à substituição da raiz; behaviors do Drupal foram verificados após resposta e rebuild de Form API no Runtime.
- O layout tema usa navegação e conteúdo em Grid, coluna em viewport estreito e menu/conteúdo em colunas a partir de 768px; classes visuais vêm do ACULTA420.
- Dashboard usa identidade `AuthorIdentityResolver`, link autorizado para Perfil e providers registrados; cada card exige disponibilidade e AccessResult permitido e resolve destino com `DomainPurposeManager`. Green Stash permanece provider integrado. Culturas usa `EventPermissionPolicy::createEventAccessResult` além do gate Portal.
- Marley removeu “Meu trabalho” redundante, exibe “Criar” junto ao título, e a aba de moderação continua condicionada ao owner Fórum/UGC; layout/listagem responsivos e gate de autorização foram verificados nas rodadas registradas.
- Focais locais: Studio Unit 72 testes / 659 assertions PASS; browser Firefox ESR visível no Runtime: Conta 92/92 verificações aplicáveis PASS e 22 SKIP; no-JS 4/4 PASS; gates `validate-aculta-portal-drupal11.php` 1202 checks PASS, slugs 410 PASS, admin routes 172 PASS e status index 53 PASS; PHP lint, PHPCS focal e `node --check` PASS. Senha/upload/anexos, escritas pessoais, módulos opcionais ausentes, conteúdo aguardando moderação, leitor de tela e cotejo integral da prancha permanecem DEFERRED.
- `validate-module-theme-boundary.php` falha por literais preexistentes em `aculta_paths`; `validate-module-theme-map.php` falha porque `aculta_paths` não consta no mapa; `validate-portal-ui-docs.php` também aponta tokens/atributos preexistentes e agora documenta os atributos desta implementação. Não declarar aceite visual integrado.

## Preservação do legado

Preservar barra multidomínio, identidade visual por tokens, navegação contextual reconhecível, itens autorizados da conta, conteúdo carregado na primeira abertura mobile, foco, mensagens e fallback. Manter /conta-legada disponível.

Inventariar Visão geral, Meu Apoio, Cursos, Atividade, Associado, Meus pedidos, Meus Dados, Conexões, Segurança, Configurações e Avisos/Mensagens, incluindo os itens inseridos por hooks. Registrar para cada um owner, disponibilidade, acesso e destino atual. Cursos/atividade/mensagens podem ser atalhos para owners; não migrar sua lógica para a Conta. Meu Perfil conserva apelido, bio, redes e identidade pública; cadastro/endereço/documentos continuam privados.

O legado é referência de experiência, não implementação livre de defeitos. Na leitura do JS:
- setupAccordion remove o conteúdo desktop, mas popstate retorna quando portal-account-body não existe: histórico mobile precisa de cobertura específica.
- navigate marca aria-busy e não passa callback próprio de erro para requestInto: revisar limpeza do estado em falha.
- timeout mobile não cancela requisição; revisar respostas tardias e repetição de tentativa.
- múltiplas requisições podem concluir fora de ordem; assegurar que a intenção mais recente prevaleça.
- reabrir painel carregado retorna antes de refreshChrome; conferir coerência entre painel, URL e título.
- comentários sobre exclusão de ações de formulário não substituem validação dos seletores efetivos de links.

Esses pontos são riscos identificados por leitura, sem reprodução nesta sessão.

## Plano executável e dependências

| Fase | Trabalho | Saída obrigatória |
| --- | --- | --- |
| C0 - baseline | Registrar commit servido, modules/libraries, DOM, seletores, console, rede, resposta AJAX, purpose e estilos computados nas rotas nova e legada | Evidências desktop/mobile; distinguir código remoto de código instalado |
| C1 - composição | Definir um único shell de conta Studio e região substituível; agrupar navegação, introdução e formulário em wrappers com papéis explícitos; ajustar mapeamento do tema | Formulário com largura útil, barra global única, HTML sem IDs duplicados |
| C2 - navegação | Implementar comportamento próprio da conta, aproveitando Drupal.Ajax e padrões do legado; declarar biblioteca e contrato de fragmento; optar explicitamente por substituição do conteúdo ou da raiz | Mesma estrutura para entrada direta e AJAX; sem shell aninhado; rotas atuais preservadas |
| C3 - estado | URL, query, título, breadcrumb, ativo, foco, aria-busy/live, back/forward desktop/mobile, falha/retry, concorrência e detach/attach behaviors | Transições previsíveis, sem conteúdo obsoleto ou eventos duplicados |
| C4 - formulários | Validar salvar, erro, CEP, Número/S/N, documentos, e-mail pendente, senha inicial OAuth, conexões, pedidos e preferências com owners | Fluxos reais preservados; links internos remanescentes auditados, sem forçar AJAX em ações |
| C5 - visual/aceite | Aplicar direção visual P1 após estabilizar C1-C4; conferir tokens, modo escuro, teclado, zoom, mobile e legada | Capturas comparáveis e matriz de aceite; atualizar roadmap com evidências |

C0-C4 são pré-requisitos para declarar P1.2 funcionalmente concluída. Não aplicar o JS de Marley indiscriminadamente a todo link da Conta. Interceptar apenas links de navegação declarados, da mesma origem e dentro do escopo autorizado; preservar modificadores, download, target, fragmentos, logout, links externos e ações de formulário. Domínios resolvidos pelos serviços existentes, sem hosts fixos.

## Matriz de aceite

- Abrir cada seção diretamente, pelo menu, após refresh e por voltar/avançar; confirmar URL/query e conteúdo correspondente.
- Mobile: abrir, fechar, reabrir e alternar painéis, navegar pelo histórico e redimensionar viewport sem perder alvo ou duplicar IDs.
- Rede lenta, erro, timeout, sessão expirada, 403/404 e cliques rápidos: feedback e recuperação; não mostrar dados de resposta antiga.
- Form API após múltiplas trocas: validação no campo correto, mensagens atuais, bibliotecas/behaviors funcionais, valores preservados em rebuild.
- Anônimo, usuário autorizado, outro usuário e módulos opcionais ausentes; acesso no servidor e cache privado preservados.
- Conta/Perfil separados; documentos/endereço nunca expostos no perfil público.
- Barra multidomínio única; navegação interna clara; formulário não espremido em coluna residual; desktop/mobile e claro/escuro.
- Nenhum aceite automático por screenshot gerada, HTTP 200 ou gate histórico de outra rota/commit.

## Integração documental

- [Plano geral](ACULTA-STUDIO-VISUAL-PLANNING.md)
- [P1 pessoal/editorial](STUDIO-VISUAL-P1-PESSOAL-EDITORIAL.md)
- [Roadmap Studio](../../web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md)

## Primeiro reteste autenticado no Runtime — 2026-11-03 (baseline anterior à matriz ampliada)

A suíte `tests/browser/aculta-studio/run-visual.sh` foi executada com Firefox ESR visível (`DISPLAY=:0`) usando as contas sintéticas existentes `teste.comum` e `aculta_visual_admin`; credenciais ULI ficaram em memória e nenhum formulário editorial foi submetido. A conta comum passou **82/82 verificações** com 21 SKIP (sem publicações próprias para os casos de edição/criação; navegação Marley de seção não se aplica quando a página apresenta abas) e zero falhas. A conta moderadora passou **99/99**, com 5 SKIP ligados à navegação em abas, zero falhas; comprovou a aba Comentários e moderação e a listagem vazia em `/marley/revista/publicacoes/comentarios`. A conta comum não recebe a aba e a mesma rota retorna 404; moderadora recebe 200.

A suíte cobriu Dashboard com cinco cartões dos providers autorizados, link `/perfil`, Conta/Perfil separados, perfil público e ausência de rótulos de endereço/documentos no editor. Dashboard e Perfil refluíram sem overflow em 320/390/768/1280px. Conta refluíu em 320/390/768/1280px com alvos de 44px; Marley foi verificado em 320–1440px, teclado, claro/escuro e contraste. Navegação Conta via Drupal.Ajax validou troca sem reload, replaceWith, URL/ativo, título/breadcrumb/foco, behaviors, voltar, mobile abrir/fechar, falha/retry, timeout/fallback, resposta tardia e cliques concorrentes. Form API owner de Endereço rejeitou campos vazios sem persistir nem perder token/build ID; Preferências salvou via AJAX mantendo o valor atual, restaurado pelo wrapper.

Probes HTTP: anônimo recebe 302 na raiz e 403 em todas as rotas privadas Perfil/Conta/Marley; as rotas diretas listadas acima respondem 200 autenticadas. `/marley/revista/publicacoes/comentarios`: 404 para comum, 200 para moderadora. Os relatórios e PNGs ficam fora do repositório em `$SUITE_OUT/studio-account-member-final-4-20261103/` e `$SUITE_OUT/studio-account-moderator-final-4-20261103/` (Dashboard, Conta, Perfil, listagem/edição/criação Revista, galeria e mobile). O editor proprietário foi aberto por GET sem submit; verificação visual passou com a identidade ativa Inter/Oswald.

Nessa primeira rodada ficaram DEFERRED 403/404 e zoom; ambos foram cobertos pela matriz ampliada no topo. Continuam DEFERRED upload/avatar/formulário de senha e anexos; submissão válida de dados pessoais/endereço; módulos opcionais ausentes no Runtime; moderação com conteúdo aguardando; leitor de tela e revisão visual comparativa com a prancha. A expiração de sessão foi testada removendo o cookie temporário do Firefox; nenhuma escrita em dados pessoais, endereço, documentos, senha, conteúdo editorial ou workflow foi executada. Capturas reais existem, mas não representam homologação integral de todas as linhas da matriz. Gates anteriores mantêm seus escopos próprios.
