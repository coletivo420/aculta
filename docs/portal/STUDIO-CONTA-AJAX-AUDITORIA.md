# Conta Studio: auditoria AJAX e plano de recuperação

## Estado atual da entrega — ACULTA420 dev.36 / aculta_studio dev.44

Dashboard, Conta, Perfil e Marley estão implementados e publicados em `dev`; o Firefox ESR no Runtime passou 106/106 checks, 21 SKIP condicionais e zero falhas. As nove rotas Conta responderam diretamente; a suíte verificou AJAX, histórico, retry/403/404, timeout/resposta tardia, sessão expirada, Form API e behaviors. Também verificou Dashboard/Perfil em 320/390/768/1280 px, zoom real 200/400%, teclado/foco, claro/escuro e listagem Marley. Dados e Endereço foram submetidos com valores existentes e permaneceram iguais; preferência de cor foi restaurada. Veja o registro de referências, rotas e limites abaixo.

**Limites atuais:** avatar não foi persistido porque o desafio de verificação humana apareceu; senha não foi submetida sem aceite legal. Orca cobriu Marley, não as demais telas. A conta de teste não tem publicação própria, então checks de edição/criação editorial são condicionais `SKIP`. Os testes Unit verificam owners opcionais ausentes, mas não houve desinstalação de módulo no Runtime. As cinco pranchas PNG oficiais foram validadas e comparadas por composição com capturas Runtime; não se declara paridade pixel a pixel. O ZIP não estava disponível.

**Estado do Runtime após a atualização em andamento (2026-11-03):** o commit `5f695dfecb49b4473aebda4f725c681f9c5b92a9` em `dev` atualizou o código para Drupal 11.5.0-beta1 e Group 4 alpha. O checkout ainda tem alterações locais paralelas em `composer.json`/`composer.lock`, preservadas e fora desta entrega. A verificação anterior registrou HTTP 500 e falha de container (`group.membership_loader` ausente); `drush status` voltou a responder e identifica Drupal 11.5.0-beta1 e banco `aculta_runtime`. Durante uma chamada concorrente, `updatedb:status` falhou ao analisar `modules/contrib/storybook/storybook.info.yml`; em uma chamada posterior, voltou a listar 18 updates pendentes. `config:status` continua mostrando diferenças em `core.extension`, configurações do Group/LMS, ações de usuário e `upgrade_status.settings`. Esta tarefa não executou updates nem importação de configuração. Foi observado um processo paralelo invocando `drush cr`, mas sua saída não ficou disponível; portanto não se atribui resultado a esse rebuild. As requisições anônimas de controle anteriores retornaram 302 em `/`, 404 em `/u/pessoa-5` e 403 em `/conta/dados` no host de Conta; isso não homologa as rotas autenticadas. Os 106/106 e a comparação visual continuam sendo evidência do Runtime anterior a esse upgrade; validação integrada atual está pendente.

**Checagens locais durante a atualização:** `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Unit` passou (65 testes, 634 assertions); `php scripts/validate-aculta420-sdc-schemas.php` passou (55 componentes, 764 checks); `php scripts/validate-aculta420-design-foundations.php` passou (2.462 checks); `php scripts/tests/validate-aculta420-design-foundations-test.php` passou (160 resultados esperados); `npm run lint:css` em `web/themes/custom/aculta420` passou. Essas verificações não substituem a validação autenticada no Runtime após concluir os updates.

## Histórico do reteste de espaçamento dev.32–dev.35 (supersedido pelo dev.36)

Após a redução da escala global, Firefox ESR visível confirmou no Studio Dashboard, Perfil e Marley 62/62 checks aplicáveis, com 21 SKIP condicionais, sem overflow em 320–1440 px; Perfil também passou zoom real de 200/400%, e Marley passou claro/escuro. Capturas/JSON: `$SUITE_OUT/studio-spacing-marley-20261103/`. A suite completa da Conta passou 102/102 verificações aplicáveis (21 SKIP, zero falhas): layout 320/390/768/1280 px, nove links com 46 px, navegação AJAX, histórico, foco/ativo, retry após 403/404, timeout/resposta tardia, Form API e sessão expirada. Medição focada: 1440 px em duas colunas (272/944 px), padding dos painéis 16/24 px; 320 px em uma coluna, padding 16 px e sem overflow. Capturas/JSON: `$SUITE_OUT/studio-spacing-account-dev34-20261103/`. O teste de preferência salvou por Form API e o runner restaurou a opção clara original. Cabeçalho institucional/global manteve-se inalterado. A prancha não foi recapturada nesta rodada, então não alegamos paridade pixel a pixel.

## Referências do repositório — 2026-11-03

As pranchas oficiais disponíveis no HEAD remoto estão em
[`studio-visual/referencias/`](studio-visual/referencias/README.md), com cinco
PNG válidos no momento desta revisão. A principal, `image(20261103-123227).png`,
tem 1536×1024 e representa as quatro estruturas; o cotejo conceitual foi feito
contra ela e as outras pranchas presentes. O arquivo desktop/mobile foi
removido pelo commit remoto `4599e115ed`; não foi reintroduzido. Nenhuma prancha
é fonte de dados ou conteúdo aprovado. O Dashboard foi atualizado para mostrar
uma única entrada privada de Conta, o link de edição do Perfil condicionado a
`Url::access()` na rota do mesmo purpose, o link público apenas quando o owner o
resolve, o avatar autorizado e os cards de experiências já disponíveis. Cabeçalho
global e suas cores permaneceram intactos.

O SDC reutilizado é `aculta420:card`, variante `studio_fidelity`, por
`PortalUiHooks`; nenhum SDC novo foi criado. O token `account-entry-links`
mantém estrutura neutra e é mapeado pelo tema ACULTA420.

## Correção visual pós-cotejo — 2026-11-03, ACULTA420 dev.31

O cotejo visual real da captura `05-conta-dados.png` mostrou uma falha que a auditoria anterior havia classificado incorretamente como resolvida: `studio-account-contextual-nav` ainda herdava `nav-tabs`/`studio__subtabs` do espaço editorial Marley. Por isso a Conta exibia itens em linha/colunas curtas e o menu/formulário não tinham as caixas separadas da prancha. A correção remove esse mapeamento compartilhado e dá à lista um estilo vertical próprio; `<details>` nativo permanece como controle móvel, e menu/conteúdo recebem superfície, borda, raio e sombra pelos tokens semânticos ACULTA420. O formulário/validação continuam nos owners Form API. Nenhuma cor do cabeçalho global foi alterada.

Commit `963ab7c6b10a375819e9bb0651bbb1a8e43540da` foi publicado por fast-forward em `origin/dev` e sincronizado no Runtime. Após `drush cr`, o tema informa `0.7.0-dev.31`, `updatedb:status` não tem updates e `config:status` está limpo. Firefox ESR visível passou 97/97 verificações aplicáveis, 22 SKIP, 0 falhas, incluindo checks novos de caixa/lista vertical, nove rotas diretas HTTP 200, AJAX, histórico, formulários inválido/válido, teclado e larguras 320–1440px. Capturas atuais: `$SUITE_OUT/studio-dashboard-final-20261103/account-panels-dev31-final/`; no-JS 14/14 em `$SUITE_OUT/studio-dashboard-final-20261103/final-nojs-retry/`. A inspeção pareada confirma a hierarquia em caixas na Conta e nos demais blocos; barra institucional preservada, conforme orientação do responsável.

Theme Unit 50/358, Design Foundations 2441, SDC schema 764, Portal Drupal 11+ 1202, status index 53 e Stylelint passaram. O mapa Portal UI continua com 14 falhas preexistentes de tokens/atributos em LMS/Eventos/uploader; nenhuma corresponde ao token da navegação da Conta. `validate-module-theme-boundary.php` continua reportando apenas `button`, `button--small` e `superficies-dns` em `aculta_paths`. Dados e Endereço foram submetidos validamente uma vez cada com os valores já exibidos na conta sintética documentada; Firefox mostrou mensagem de sucesso, zero erros e mesmos valores antes/depois. Hashes de `field_first_name`, `field_last_name`, `field_whatsapp` e `field_address` coincidiram com snapshot externo em modo 0600; não houve alteração de conteúdo. O upload sintético foi aceito pelo Form API e abriu o corte, mas um desafio de verificação humana bloqueou a etapa final; a foto persistida não mudou. A senha não foi submetida devido ao diálogo de aceite obrigatório de Termos. Ausência de owner opcional é coberta por testes Unit, embora `aculta_id_cpf` esteja instalado no Runtime. Leitor de tela foi validado em Marley, com cobertura nas demais telas ainda pendente.

### Comparação pareada e diferenças restantes

As capturas atuais foram inspecionadas em 1280px para Dashboard, Conta, Perfil e Marley; houve também captura de Conta em 320px, Perfil em 320px e Marley em 390px. A hierarquia proposta foi reproduzida com identidade e cards reais no Dashboard; Conta tem caixas lado a lado em desktop e empilhadas no celular; Perfil usa identidade à esquerda e edição à direita, empilhadas no celular; Marley mantém suas abas owner, botão “Criar” junto ao título e lista do owner. Preferências foi submetida pela suíte e restaurada. Dados e Endereço foram submetidos validamente com os valores existentes; a comparação antes/depois confirmou valores e hashes iguais. A lista editorial desta conta de teste estava vazia.

Diferenças intencionais: o shell e a barra multidomínio reais permanecem os do ACULTA420, sem copiar as cores/nominação fictícias da prancha; “Seções da conta” é o rótulo acessível do `<details>`; campos, descrições, avatar e estados seguem os providers/Form API reais. A prancha não é aceita como paridade pixel a pixel. Dados/Endereço foram enviados com os valores existentes e comparados sem mudança persistida. A persistência final de avatar está DEFERRED pelo desafio humano, e senha pelo aceite de Termos. Orca/Firefox ESR validou os anúncios de Marley (5/5); não cobre leitor de tela das quatro telas. A listagem disponível para moderação estava vazia, embora acesso positivo/negativo tenha sido testado por role.

## Histórico do reteste ampliado pré-dev.36 — 2026-11-03

No Firefox ESR visível do Runtime, as nove rotas diretas `/conta/*` responderam HTTP 200; navegação AJAX comum com `replaceWith`, título/breadcrumb/foco, behaviors, back/forward, alternância mobile e cliques concorrentes passaram. No-JS passou 4/4 (Dados, Endereço e voltar). O Dashboard e o Perfil passaram em 320–1280px; Conta/Marley passaram em 320–1440px e Perfil foi verificado com zoom real 200/400% sem overflow.

A suíte ampliada pós-correção do Dashboard terminou 94/94 verificações aplicáveis PASS, 21 SKIP e zero falhas. Cobriu respostas HTTP 403/404 com feedback e retry, timeout, resposta tardia descartada, sessão expirada, navegação concorrente, rebuild de Form API inválido/válido, Dashboard, Perfil e demais fluxos listados no harness. As nove rotas `/conta/*` responderam HTTP 200; no-JS 4/4. O novo teste verificou ações do Perfil legíveis na coluna lateral (390 px de largura, links 159–174 px). SKIPs dependem de conteúdo/links próprios de outra conta editorial. Senha, uploads/anexos e escritas pessoais/endereço não foram submetidos. Acesso anônimo/roles foi verificado em rodada anterior, mas não repetido neste reteste. Capturas e JSON em `$SUITE_OUT/studio-dashboard-final-20261103/post-push/`.

Na inspeção pareada por composição com a prancha `image(20261103-123227).png`, Dashboard, Conta, Perfil e Marley têm caixas distintas e responsivas; o cabeçalho institucional e a barra multidomínio permanecem fora dos blocos e sem mudança de cor. Marley mantém abas e lista do owner, e agora “Criar” fica à direita de “Meu trabalho” no mesmo cabeçalho. Firefox ESR Runtime confirmou a composição em 1280px e a ação/tabela sem overflow da página em 320 e 390px. O teste teve 97/97 verificações aplicáveis PASS, 21 SKIP. `drush cr` precedeu a verificação; `extension.list.theme` inicialmente reteve dev.29 em memória, e após `reset()` passou a informar dev.30 em execução nova. Módulo no Runtime: `aculta_studio` dev.44. Artefatos em `$SUITE_OUT/studio-dashboard-final-20261103/marley-header-dev30-retest/`. Escrita pessoal/endereço, senha e uploads permanecem não submetidos.

O ajuste de `Drupal.Ajax(null, null, …)` evita que o construtor Core instale um segundo listener de clique; o listener delegado único fica em `document` e consulta a raiz atual, que o `replaceWith` substitui. Isso preserva os cliques elegíveis após troca de seção e rebuild de Form API sem listeners globais duplicados. Código alterado em `account-navigation.js`; versão do módulo `0.1.0-dev.41`.

Data: 2026-11-03. Estado: navegação da Conta e composição Studio verificadas no Runtime de desenvolvimento; matriz de formulários com escrita e comparação integral da prancha permanecem parciais.

## Validações adicionais — 2026-11-03

- Firefox ESR com Orca e áudio real: `tests/browser/aculta-studio/orca-speech.mjs` passou 5/5 no fluxo Marley de `teste.comum`. Foram anunciadas as três abas disponíveis para esta conta (`Revista`, `Moderação do Fórum`, `Galerias`) e o título `Meu trabalho`; reteste final e artefatos ficam fora do repositório em `$SUITE_OUT/studio-dashboard-final-20261103/orca-final-retest/`. Este resultado cobre Marley e não substitui teste de leitor de tela nas outras telas.
- O estado atual de `teste.comum` concede `moderar denuncias do forum` pelo papel `content_editor`; `MarleyPresenter` inclui a aba de moderação. Uma conta sintética recém-criada, sem esse grant, não recebe a aba. Isso corrige as observações antigas sobre a efetiva autorização da conta comum; grants não foram alterados.
- Avatar: `tests/browser/aculta-studio/avatar-write.mjs` passou 11/11 checks e deixou 1 item `DEFERRED`: Firefox ESR autenticado em conta sintética exclusiva confirmou upload no `managed_file` e abertura do corte; um desafio de verificação humana apareceu antes da gravação. O harness não envia o formulário nesse estado. A foto persistida e os papéis ficaram iguais, nenhum aceite foi gravado; três arquivos sintéticos estão temporários na área de upload do owner e expiram pela limpeza normal. Evidências externas em `$SUITE_OUT/studio-dashboard-final-20261103/avatar-write-final-verified/`.
- Senha: a submissão não foi executada. A conta sintética descartável ainda encontra o diálogo obrigatório de Termos; não houve aceite em nome da pessoa nem mudança de senha. A fixture `teste.comum` também proíbe alteração de senha sem decisão do responsável. O arquivo externo com a credencial aleatória da conta sintética está sob `$SUITE_OUT`, modo 0600; nenhum identificador ou segredo foi registrado aqui.
- Comparação pareada real realizada com `image(20261103-123227).png` (1536×1024) e capturas Firefox do Runtime para as quatro telas. O cotejo confirma composição em caixas e ordem geral, sem declaração de paridade pixel a pixel. Cinco PNGs rastreados passaram `file`/`identify`; o ZIP não estava disponível, portanto `unzip -t` não foi executado.
- Orca/Firefox ESR: `tests/browser/aculta-studio/orca-speech.mjs` passou 5/5 checks em Marley, incluindo as três abas permitidas para `teste.comum`, heading e áudio; captura em `$SUITE_OUT/studio-dashboard-final-20261103/orca-final-retest/`. Cobertura de screen reader no Dashboard, Conta e Perfil permanece pendente.
- Reteste local do código atual: Studio Unit 65/634 e Theme Unit 50/358 PASS; Portal Drupal 11+ 1202, public slugs 410, admin MAIN 172, ACULTA420 Foundation 354, shell contract 13, Design Foundations 2441, SDC schema 764, Single Acceptance, User Photo e status index 53 PASS. O PHPUnit avisou que o diretório padrão de saída HTML não é gravável, mas ambos os comandos Unit concluíram com exit 0 e assertions PASS.
- Runtime após os testes: Drupal 11.4.10 / `aculta_runtime`, sem updates e sem diferenças de configuração. `aculta_id_cpf`, Commerce, Social Auth, `aculta_user` e uploader estão habilitados; a ausência de `aculta_support` não elimina o owner Portal de Apoio. Testes Unit cobrem owner CPF/CNPJ opcional ausente e consumers de Perfil opcionais.
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
- Focais locais: Studio Unit 72 testes / 659 assertions PASS; browser Firefox ESR visível no Runtime: Conta 92/92 verificações aplicáveis PASS e 22 SKIP; no-JS 4/4 PASS; gates `validate-aculta-portal-drupal12.php` 1202 checks PASS, slugs 410 PASS, admin routes 172 PASS e status index 53 PASS; PHP lint, PHPCS focal e `node --check` PASS. Senha/upload/anexos, escritas pessoais, módulos opcionais ausentes, conteúdo aguardando moderação, leitor de tela e cotejo integral da prancha permanecem DEFERRED.
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

## Reteste no-JS ampliado e pranchas versionadas — 2026-11-03

Com ACULTA420 ativo no Runtime, Firefox ESR visível e a conta sintética documentada, o smoke `tests/browser/aculta-studio/nojs.mjs` passou **14/14**: login temporário, shell e formulário de Dados, navegação HTML nativa para Endereço, voltar e abertura direta das nove rotas de Conta com conteúdo e seção ativa. Nenhum formulário foi enviado. Relatório externo em `$SUITE_OUT/studio-audit-20261103/nojs-aculta-final2/nojs-results.json`. O harness agora segue o redirect explícito do ULI para `/conta/dados`, confirma autenticação antes de testar rotas privadas e consulta a semântica do shell sem depender de classes do tema.

Registro histórico: os seis arquivos de `references/studio-visual/` do commit `d732e95c` eram truncados e não foram usados. O conjunto oficial posterior está em `docs/portal/studio-visual/referencias/`: cinco PNGs íntegros, validados por `file`, `identify` e integridade de chunks/CRC/IEND; a prancha principal é `image(20261103-123227).png` (1536×1024). A comparação pareada por composição das quatro telas foi feita com capturas Firefox reais, sem alegação de igualdade pixel a pixel. O estado atual da validação e Runtime está na seção **Reteste integrado dev.36** abaixo.
## Espaçamento compacto e matriz pública por purpose — 2026-11-03

- ACULTA420 passou a usar uma escala semântica compacta (4/8/12/16/20/24/32 px), com intervalos globais de seção de 24–40 px e painéis de Conta/Perfil ajustados aos tokens. O título global usa 20 px; no Dashboard os intervalos entre blocos e cartões usam 12 px e títulos internos não duplicam margens.
- Firefox ESR visível validou a suíte Studio pós-dev.35: 102/102 verificações aplicáveis PASS, 21 SKIP condicionais e zero falhas; Dashboard, Perfil e Marley 62/62 aplicáveis PASS. A matriz read-only de propósitos em 320/1440 px capturou 16 páginas: main, support, magazine e os shells Wiki/Shop/Courses/Eventos sem overflow. Wiki/Shop/Courses/Eventos retornaram acesso negado para anônimo, portanto essa parte valida shell/reflow, não conteúdo autorizado.
- Limite observado na matriz: Links retornou página de erro Drupal sem shell em 320 e 1440 px. Isso não foi atribuído ao espaçamento nem corrigido nesta tarefa. Resultado bruto externo em `$SUITE_OUT/spacing-purpose-matrix-20261103/purpose-matrix.json`.
- Próximos eventos: a configuração exige purpose `main` e rota `<front>`. Após snapshot e importação no Runtime, HTTP anônimo confirmou a caixa presente na landing principal (`200`) e ausente no Perfil público (`200`).

### Reteste integrado dev.36

- Snapshot pré-import: 270.104.476 bytes, SHA-256 `f1461615bbec2363ab70ee762cac15b5d50115c2c80537fef5272c6500261a0f`. Importou-se somente `block.block.aculta420_aculta_events_upcoming`; cache rebuild PASS, sem updates de banco e sem diferenças de configuração após a importação.
- Firefox ESR sem CSS candidato injetado: 106/106 PASS, 21 SKIP condicionais, zero falhas. Dashboard medido em 320/390/768/1280 px com gap 12 px, margens internas zeradas, grade 1/2 colunas e sem overflow. Conta AJAX, histórico, retry, timeout, Form API, foco e comportamento mobile permaneceram aprovados; Perfil manteve reflow e zoom 200/400%; Marley manteve cabeçalho Criar/listagem.
- HTTP anônimo: landing principal `200` com `.aculta-events-upcoming`; `/u/pessoa-5` `200` sem esse bloco. Evidências em `$SUITE_OUT/studio-spacing-runtime-dev36-20261103/` e `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-studio-events-20261103/`.
- A primeira suíte com CSS candidato injetado registrou 126 PASS, 2 FAIL de harness (detecção de Montserrat no CSS injetado e moldura de abas secundárias) e 21 SKIP. A repetição contra CSS publicado, após `drush cr`, passou sem falhas; o resultado final usado é a repetição integrada.
