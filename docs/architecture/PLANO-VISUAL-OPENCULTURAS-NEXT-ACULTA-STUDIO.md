## RETIFICAÇÃO VIGENTE - Green Stash integrado e ACULTA420 integralmente mobile-first (2026-10-29)

**Esta seção substitui TODAS as afirmações históricas conflitantes neste documento.** Green Stash **não é uma experiência independente do Aculta Studio**. É a **área funcional de gestão comercial integrada ao Aculta Studio**, sob responsabilidade operacional de `aculta_commerce`. Um `GreenStashExperienceProvider` ou ID `green_stash` no código é um **adaptador de registro/entrada do Studio**, e não autorização para criar shell, produto, plataforma, login, navegação global ou identidade independentes. Conservar os contratos comerciais e o registry existentes enquanto se audita sua semântica; não renomear IDs/rotas em uma mudança visual. As expressões anteriores "experiência independente" ficam expressamente revogadas.

**Exigência global:** TODO o tema `web/themes/custom/aculta420/` deve adotar **mobile-first** como regra arquitetural, em **todos os purposes**, SDCs, layouts, CSS, templates, navegação, formulários, media, modais e bibliotecas, e não apenas no Studio ou Green Stash. OpenCulturas Next é referência de fidelidade visual na área Studio; suas decisões de design devem ser adaptadas à fundação mobile-first global do ACULTA420 sem copiar breakpoints ou regras desktop-first inadvertidamente.

### Resultados de reinspeção estática

- Tarball `openculturas-3.1.5.tar.gz` reaberto: o Next está em `openculturas/profile/themes/opcult/`, com Sass, CSS compilado, templates e Montserrat local; **não** confundir `openculturas_base` com Next.
- ACULTA420 atual: `aculta420.info.yml` utiliza base theme `bootstrap`, bibliotecas globais e SDCs; `css/components/header.css` usa media queries `min-width:1100px` e `max-width:1099.98px`; `navigation.css` usa CSS do navbar e variáveis Bootstrap. Isso demonstra necessidade de **auditoria de cascata e breakpoints**, não comprova que todo o tema já seja desktop-first nem que a conversão seja uma substituição mecânica de `max-width`.
- Fonte funcional do Green Stash: `aculta_commerce/docs/GREEN-STASH-OPERATIONS-CONTRACT.md` exige Form API/Commerce, ACL por loja/operação e nenhum atalho para admin Commerce. `GREEN-STASH-UX-MOBILE-FIRST.md` já exige UI móvel própria, mas sua descrição como "experiência independente" está **superada pela decisão do responsável**. Distinguir provider/entrypoint técnico de posição na arquitetura do produto.

### Microfases normativas revisadas

| Fase | Responsabilidade / entrega | Gate |
| --- | --- | --- |
| **OCS-F0 + THEME-MF0** | Reescanear todos os providers/abas do Studio **e todo o tema ACULTA420**, com inventário de `@media`, containers, unidades, overflow, ordem DOM, bibliotecas, telas, estado de JS/no-JS e SDCs; revisar `opcult` real | matriz de inventário com criticidade, owners e referências de arquivos, incluindo Green Stash integrado e todos os purposes |
| **THEME-MF1** | Definir fundação mobile-first global: estilos-base a 320px, aprimoramentos progressivos por `min-width` ou container queries quando apropriado, sem reordenamento visual que contradiga DOM/foco | sem overflow ou regressão do Institution Bar/Domain Header |
| **THEME-MF2** | Migrar por grupos todos os estilos/SDCs/layouts/componentes do `aculta420` para estratégia mobile-first, respeitando Bootstrap 5, UI Patterns e tokens. Não fazer substituições cegas de media queries. | regressão proporcional por propósito, componentes, formulários, tabelas, modais, menus, media e estados |
| **THEME-MF3** | Gate global de tema: mobile 320/360/390/599/600/768/1024/1280/1440 px, zoom 200%/400%, teclado, leitor de tela quando disponível, no-JS, RTL quando suportado, claro/escuro, reduced motion, browser visível, Unit/Kernel/Functional aplicáveis | **não declarar ACULTA420 mobile-first completo sem evidências transversais** |
| **OCS-F1-F3** | Fidelidade `opcult` escopada ao Studio sobre a fundação mobile-first; SDCs reutilizados, navegação contextual sem segundo shell global | tokens, schemas, stories e consumers validados |
| **OCS-F4** | Hub Studio, com Green Stash integrado segundo autorização e registry existentes | sem cards ou ações fictícios |
| **OCS-F5** | Marley | owner editorial preservado |
| **OCS-F6** | GanjaLab | ACL LMS/matrículas preservadas |
| **OCS-F7** | WE Sativa | autoria e moderação preservadas |
| **OCS-F8** | **Área Green Stash integrada ao Studio**, com UX operacional de comércio compatível com o Studio, navegação contextual mobile-first e permissões Commerce | sem shell ou plataforma comercial paralelos; telas somente para funcionalidades existentes |
| **OCS-F9** | Conta e Perfil distintos | S-R4/S-R5, `/conta-legada` |
| **OCS-F10** | Todas as áreas restantes encontradas nos registros e consumidores reais | paridade por área |
| **OCS-F11** | Gate Fidelity de TODO o Studio, **dependente de THEME-MF3** | sem regressões em outros purposes; só após aprovação iniciar OCS-A1-A4 |
| **OCS-A1-A4** | Identidade própria Aculta Studio reaproveitando a mesma base SDC; Green Stash segue integrado, fundação mobile-first global permanece | comparação Fidelity/Identity + regressão de todo tema |

**Sequenciamento:** começar por F0+MF0 e corrigir bloqueadores críticos globais antes da homologação visual. Permite trabalho paralelo em stories/tokens quando sem conflito; proíbe declarar F11 se MF3 não passou. Frentes A/B e microcommits continuam valendo. Contratos de interface/rotas de áreas opcionais não são inventados. Quando alteração global afetar outro dono funcional, registrar `Pede-A`. Não exigir uma nova identidade visual OpenCulturas em todo o portal: o **requisito global é mobile-first**, enquanto **a fidelidade visual à marca Next é restrita ao Studio**.
## Registro histórico de correção — Green Stash e cobertura (2026-10-29; superado)

**Este registro é mantido para rastreabilidade e não é instrução vigente.** A expressão histórica “experiência independente” está revogada pela retificação vigente no início deste documento: Green Stash é a área comercial integrada ao Aculta Studio. `GreenStashExperienceProvider`, o ID `green_stash` e sua rota são mecanismos técnicos de integração, não justificativa para shell, identidade, plataforma, autenticação, checkout ou navegação global independentes. A numeração vigente é OCS-F0 a OCS-F11 conforme a retificação vigente e a sequência executável abaixo.

Código verificado historicamente: `aculta_commerce/src/Experience/GreenStashExperienceProvider.php`, ID `green_stash`, route `aculta_commerce.green_stash`, purpose `account`, ACL `GreenStashAccessPolicy`. A presença do provider **não** significa que todas as operações de loja estejam implementadas. Conferir estado das rotas e consumers no HEAD antes de marcar implementação.

**Fontes contratuais indispensáveis:** `web/modules/custom/aculta_portal/modules/aculta_commerce/docs/GREEN-STASH-UX-MOBILE-FIRST.md`, `GREEN-STASH-OPERATIONS-CONTRACT.md` e `GREEN-STASH-0.1.0-D.md`. Green Stash usa Commerce, Commerce Stock, Commerce Shipping/Melhor Envio e Statistics nos respectivos estágios; não duplica entities nem usa telas administrativas nativas para operadores. Preservar carrinho/checkout multidomínio, ACL por loja/entidade/operação, formulários próprios em Form API, PT-BR, navegação própria mobile-first dentro do shell do Studio, sem bottom-nav global adicional.

### Sequência executável definitiva

- **OCS-F0:** auditar TODOS os providers/rotas/consumers do Studio: hub, Marley, GanjaLab, WE Sativa, **Green Stash**, Conta, Perfil e quaisquer outros existentes, opcionais ou planejados. Matriz por experiência, owner, ACL, estados, progresso e paridade `opcult`.
- **OCS-F1:** tokens de fidelidade OpenCulturas Next escopados ao Studio.
- **OCS-F2:** shell interno e navegação contextual orientados ao registry/ACL, mantendo Institution Bar e Domain Header.
- **OCS-F3:** SDCs transversais, variantes, stories e consumers reais.
- **OCS-F4:** hub dinâmico, com Green Stash somente para usuários autorizados.
- **OCS-F5:** Marley e edição editorial.
- **OCS-F6:** GanjaLab e jornada LMS.
- **OCS-F7:** WE Sativa e autoria Wiki.
- **OCS-F8:** **Green Stash integrado ao Studio** e operações de loja realmente disponíveis. Aplicar os mesmos padrões visuais/SDC e navegação contextual do Studio às funcionalidades comerciais existentes. Auditar produtos/pedidos/estoque/envios/promoções/clientes/relatórios/configurações contra seus marcos GS: **não renderizar operações planejadas como prontas**, não incluir links administrativos de edição, não contornar grants nem duplicar checkout.
- **OCS-F9:** Conta privada e Perfil público distintos; S-R4/S-R5 e `/conta-legada`.
- **OCS-F10:** demais experiências do registry e opções ausentes, com estados honestos.
- **OCS-F11:** gate de fidelidade transversal, incluindo testes de Green Stash autorizados/não autorizados, ACL loja/produtos/pedidos, mobile 320 px, sem JS, cache por usuário e isolamento de purpose.

**OCS-F11 substitui OCS-F10 como gate obrigatório antes de OCS-A1.** As fases OCS-A1-A4 abrangem expressamente **Green Stash**, além de todas as experiências já listadas. Qualquer menção histórica de F7 ou F10 como gate final está revogada. Nova experiência descoberta não deve depender de o usuário nomeá-la: inventário dinâmico obrigatório em F0.

### Handoff comercial

A frente visual não deve alterar `GreenStashAccessPolicy`, `GreenStashExperienceProvider`, controllers, forms, navigation service, routing, entities, Commerce, Stock, Shipping ou configurações. Qualquer lacuna de operação vira `Pede-A`, com arquivo, contrato e teste, sem botão falso. Referência para o executor: o contrato de operações tem precedência sobre propostas visuais genéricas.

## Retificação vinculante de escopo transversal - 2026-10-29

**Esta seção substitui a numeração e o escopo restrito das fases F4-F7 na seção 4, as referências a F7 como gate final e qualquer interpretação de que Marley é a única experiência integrada.** O objetivo é reproduzir fielmente o OpenCulturas Next na **plataforma inteira Aculta Studio** antes da identidade própria. As seções anteriores continuam como histórico técnico, mas a matriz abaixo é a sequência executável vigente.

### Catálogo dinâmico obrigatório

F0 deve inventariar, pelo código em HEAD e não por uma lista fechada, todo registro de `StudioTab`, providers/registry, rotas, presenters, permissões, states e consumers do Studio. Cobertura inicial conhecida: **hub Studio, Marley, GanjaLab, WE Sativa, Conta privada e Perfil público**. Adicionar as demais experiências que forem descobertas, inclusive as opcionais; classificar cada uma como presente/condicional/planejada e não criar interface falsa para a última categoria.

A navegação deve **derivar da disponibilidade/autorização do registry existente**, nunca de menu hardcoded com Marley/GanjaLab/WE Sativa. Uma experiência ausente não aparece; entrada permitida e link canônico são calculados pelo provider do módulo dono. Não transferir para o Studio a propriedade dos cursos, matrículas, Wiki, revisão, publicação, loja, notificações ou dados privados. GanjaLab segue o ACL LMS, WE Sativa segue o ACL Wiki e Marley segue contratos editoriais. **Conta, Perfil público e organização nunca são sinônimos.**

### Etapa 1 - OpenCulturas Fidelity: microfases vigentes

| Fase | Entrega | Gate |
| --- | --- | --- |
| **OCS-F0** | Inventário completo de registros, rotas, módulos opcionais, telas e estados; fonte exata `opcult`, licenças, screenshots e matriz de paridade por experiência | registrar todos os providers existentes e os planejados sem supor rotas; não alterar runtime |
| **OCS-F1** | Tokens visuais Next escopados e referência tipográfica/espacial | não vazar estilos para outros purposes |
| **OCS-F2** | Navegação contextual e layout interno guiados pelo registry/ACL | Institution Bar e Domain Header intactos; sem segunda navegação global |
| **OCS-F3** | Variantes e consumidores SDC compartilhados; cards, grids, tabs, campos, vazios, estados e ações | reutilizar SDCs existentes, story e schema no mesmo commit |
| **OCS-F4** | Hub Studio, cards de experiências disponíveis, onboarding e estados reais | matriz de acesso entre contas e testes de módulo opcional ausente |
| **OCS-F5** | Marley: listagens/editor/formulários e estados de publicação | sem mudar ownership editorial |
| **OCS-F6** | GanjaLab: cursos, turmas, biblioteca, painel, ações de aprendizagem que já existirem | matrícula/grants LMS, limite entre portal público e área do Studio, nenhuma ação simulada |
| **OCS-F7** | WE Sativa: autoria Wiki, verbetes, revisão e acompanhamento realmente disponíveis | ACL de autoria versus moderação, sem confundir GanjaLab e Wiki |
| **OCS-F8** | Green Stash como área comercial integrada ao Studio, com UX dos recursos realmente disponíveis | SDCs e navegação contextual compartilhados; sem shell/produtos paralelos, sem fluxo Commerce administrativo para operadores |
| **OCS-F9** | Conta privada e Perfil público como experiências separadas | S-R4/S-R5, `/conta-legada`, foto e privacidade A/B, sem confundir entidades |
| **OCS-F10** | Outras experiências encontradas em F0, com fallback e ausência de módulo | matriz de paridade fechada para cada provider real; nenhuma aba inventada |
| **OCS-F11** | Gate transversal de fidelidade, screenshots e testes completos, dependente de THEME-MF3 | aprovação antes de começar OCS-A1; nenhum PASS sem execução real |

**OCS-F11 é o gate de entrada da Etapa 2.** As fases OCS-A1 a OCS-A4 continuam iguais em propósito, mas sua aplicação e regressão devem abranger **todas as experiências identificadas no F0**, inclusive Green Stash integrado, e todos os purposes afetados pela fundação mobile-first.

### Matriz de aceite por experiência

Para cada experiência inventariada exigir: provider e módulos donos, rota canônica, condição de disponibilidade, ACL para usuário anônimo/autenticado/editor/matriculado/autor/revisor quando aplicável, library CSS carregada, SDC/consumer, screenshots desktop/mobile/light/dark, teclado/no-JS, cache, estados vazios e limites de acesso cruzado. Para uma experiência indisponível no ambiente, marcar `DEFERRED` com motivo e evidência, não `PASS`. Um provider planejado sem código executável fica `PLANEJADO` e não bloqueia visual de funcionalidades existentes, mas não será simulado.

### Protocolo de atualização documental

Atualizar o catálogo e as stories do tema, matriz `visual-parity-matrix.md`, este plano, roadmap Studio e roadmap transversal por microfase. Se a integração exigir controller, access checker, router ou services, abrir handoff `Pede-A` com arquivo/contrato/teste, sem invadir a Frente A. Commits estritamente nos paths da Frente B e push fast-forward na PR #299. Se os gates de S-R4/S-R5 ou de F10 não forem homologados, documentar bloqueio e **não** avançar para a etapa Identity.

# Plano de implementação visual - OpenCulturas Next → ACULTA Studio

**Decisão do responsável, 2026-10-29:** duas etapas consecutivas. **Etapa 1: fidelidade visual mensurável ao OpenCulturas Next. Etapa 2: identidade própria ACULTA Studio sobre os mesmos contratos SDC.** Documento normativo de planejamento; nenhuma fase é considerada implementada por sua inclusão.

## 1. Fontes e retificações

- Pacote fornecido: `openculturas-3.1.5.tar.gz` (inventário e limites em [auditoria 3.1.5](AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md)).
- **Tema moderno Next: `openculturas/profile/themes/opcult/`**; `openculturas_base/` é base antiga e `opcult_starterkit/` é referência de customização. Não inverter as funções. Inspecionar primeiro `opcult/templates`, `opcult/sass`, `opcult/css`, `opcult/js`, bibliotecas e fontes. Não assumir o que uma imagem externa demonstra.
- Priorizar `opcult/templates/views/user-dashboard-views.scss`, templates de página/navegação, menus, Views e teasers, estilos de layout, cabeçalho, layout-regions, forms, buttons, tabs, exposed forms e hero. **Verificar o caminho e conteúdo reais de cada arquivo antes do porte**: um arquivo citado conceitualmente não comprova presença ou uso em Runtime.
- ACULTA: `web/themes/custom/aculta420/docs/component-catalog.md`, `visual-cross-product-roadmap.md`; `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`, `S-R5-MATRIZ-COMPARACAO.md`; [decisões vigentes](DECISOES-RESPONSAVEL-2026-10-28.md).
- Etapa de fidelidade não significa ativar `opcult`, introduzir `classy`, copiar YAML de instalações, inserir dependência no Composer ou alterar permissões. Licenças dos fontes, SVGs, ícones, fontes e CSS precisam ser verificadas individualmente; registrar derivação e atribuição. Não presumir autorização de copiar ativos de terceiros.

## 2. Invariantes arquiteturais

1. **Institution Bar + Domain Header ficam intactos.** A linguagem de cabeçalho e menu OpenCulturas é aplicada ao *interior do Studio*, sem novo cabeçalho global. Não alterar rotas de purpose, autenticação nem domínio.
2. **Conta privada, Perfil público e organização são conceitos distintos**. Conta e Perfil têm donos/consumidores e permissões próprias. Manter `/conta-legada`, rotas existentes, S-R4/S-R5 e operações Form API; nenhum componente Twig consulta usuários, decide ACL ou grava storage.
3. O tema `aculta420` é dono de SDCs, variantes, slots, CSS, tokens, stories e responsividade. O `aculta_studio` apresenta render arrays e ações existentes; novos dados/serviços pertencem a seus módulos donos. Preservar props/slots e contexts/tags/max-age. Alterações de arquivos PHP, routing, services, info, install ou permissions são pedidos à frente A conforme [divisão da PR](PLANO-REFATORACAO-OPENCULTURAS.md).
4. Reuso obrigatório, nesta ordem: **componente atual → nova variante/slot → SDC transversal com consumer real → adaptador Studio restrito**. Verificar `patterns/card`, `context-header`, `context-tabs`, `empty-state`, `content-section`, `content-grid`, `profile-identity`, `profile-editor`, `profile-bio`, `profile-social`. Não criar SDCs hipotéticos em massa.
5. **A fidelidade é uma camada reversível escopada apenas ao Studio**, não troca de tema, não alteração global de Bootstrap/tokens nem `!important` espalhado. Evitar novo pipeline Sass paralelo quando o build atual do tema já atende. Alternância Fidelity/Identity é configurada por implementação visual estática/controlada, sem expor ao usuário uma preferência funcional não aprovada.
6. Não simular features ausentes (salvos, reivindicações, recomendações, organização, publicações) como concluídas. Usar somente links/ações reais; para story estática, placeholders não interativos. Estado visual de erro/sucesso nunca substitui confirmação do módulo.

## 3. Matriz de paridade a produzir em F0

Criar `web/themes/custom/aculta420/docs/studio-visual/upstream-inventory.md` e `visual-parity-matrix.md` com colunas: tela/estado; URL/rota Aculta; HTML/CSS/JS upstream concreto; tela/viewport de referência; SDC existente; alteração; owner; nível de fidelidade; risco; consumer; teste; status comprovado. Inclua painel inicial, navegação desktop/mobile, conteúdos próprios, criação/edição, Perfil, Conta, pedidos/apoio/preferências, estados vazios/erro, diálogos, componentes de média e dashboards.

Referência visual oficial: preferir capturas reais reproduzíveis de uma instalação/preview compatível com 3.1.5. Se indisponível, documentar **limitação de evidência** e fazer correspondência de tokens, geometria e estrutura pelo código sem prometer paridade pixel-perfect. Não comparar capturas de versões diferentes como evidência equivalente.

## 4. Etapa 1: OCS-F - Fidelity

| Fase | Entrega | Aceite obrigatório |
| --- | --- | --- |
| **F0** inventário | mapeamento arquivo a arquivo do `opcult` → Studio/SDCs; baseline screenshots, estados, breakpoint, source provenance; checar build, licenças | sem código de Runtime; cobertura da matriz de telas, sem caminhos ou assets inventados |
| **F1** tokens | tokens escopados de cores, Montserrat (se licença e fontes disponíveis), espaçamento, largura, bordas, sombras, tipografia e estados | zero efeito em outros purposes e no shell; contraste AA; não baixar fonte não licenciada |
| **F2** estrutura/navegação | layout interno, hierarquia, menus, tabs e navegação responsiva, seguindo estrutura do Next | Institution/Domain Header intactos, URLs verdadeiras, acessível sem JS |
| **F3** padrões | variantes dos SDCs existentes para cards, formulários, abas, feedback, teasers e seções; stories | schema, props/slots, tokens, mínimo de um consumer; sem novo SDC se existe equivalente |
| **F4** dashboard | seção de conteúdos próprios, tarefas reais, ações, estados vazios e listas, inspirado no `user-dashboard-views.scss` | sem dados fictícios ou acesso cruzado; cache por UID; não apresentar feature inexistente |
| **F5** Marley/editor | listas, ações editoriais, edição, views, mídia e moderação existentes | ownership dos serviços; sem trocar Form API, media/storage ou workflows |
| **F6** Conta/Perfil | formulários e painéis distintos com acabamento Next, mantendo S-R4/S-R5 | acesso A/B, `/conta-legada`, rotas, perfis e fluxo de foto sem regressão; NÃO declarar S-R4 homologada sem testes |
| **F7** aceitação | snapshots lado a lado, auditoria de diferença visual, catálogo/stories atualizados, issues fechadas | baseline aprovado do Fidelity, todos os gates do §6; gate formal antes de A1 |

F0–F3 podem avançar em documentação, CSS e histórias enquanto S-R4/S-R5 aguardam gates. Integração funcional F4–F6 respeita o bloqueio de cada fase do Studio.

## 5. Etapa 2: OCS-A - ACULTA Identity

Somente após **THEME-MF3 e OCS-F11 aprovados**. Não duplicar controllers, rotas, caches, formulários ou SDCs. Aplicar a identidade a todas as áreas do Studio, incluindo Green Stash integrado, sobre os mesmos contratos e componentes da Etapa 1.

| Fase | Entrega | Aceite |
| --- | --- | --- |
| **A1** | retokenização institucional (verde-escuro, verde, amarelo, vermelho conforme decisões, superfície claro/escuro) | contraste e sem perda de hierarquia |
| **A2** | acabamento próprio de cards, ícones, campos, alertas, navegação e tipografia | mesmos contratos/consumers, stories equivalentes |
| **A3** | identidade visual do Studio inteiro, incluindo dashboard, Marley, GanjaLab, WE Sativa, Green Stash, Conta, Perfil e demais áreas inventariadas | benchmark lado a lado Fidelity × Identity, sem mudança funcional |
| **A4** | remover estilos temporários, consolidar biblioteca, licença e documentação | CI, lint, teste visual, gate de regressão sem overrides mortos |

**Separação de implementação:** preferir arquivo de fundação e variantes escopadas Fidelity/Identity dentro do mecanismo existente de libraries/tokens do tema, evitando inventar nomes de bibliotecas ou variáveis antes de auditar o repositório.

## 6. Gates técnicos por microfase

- PHP lint/PHPCS e PHPUnit Unit/Kernel/Functional quando PHP/integração mudar; lint CSS/JS e SDC schema/discovery UI Patterns para mudanças do tema. Não declarar testes executados sem logs.
- Firefox ESR/X11 visível, 320/360/390/768/1024/1280/1440px, zoom 200% e 400%, modo claro/escuro, teclado, foco visível, reduced motion, no-JS e AJAX; WCAG 2.2 AA.
- Contas A/B, anônimo/autenticado, donos/não donos, privacidade, purpose ACCOUNT e perfil público; snapshot da navegação e verificação de shell de Fórum/Wiki/Revista/Loja/LMS sem vazamento CSS.
- Cache contexts/tags/max-age, anexos de bibliotecas, escape Twig, Form API, URLs canônicas, IDs de entidades e AJAX; testes de regressão do legado.
- Cada commit deve registrar: arquivos upstream efetivamente estudados, licenças, screenshots acessíveis ou lacunas, consumers, testes executados/não executados e atualização simultânea de story, catálogo, matriz e roadmap.
- Sem deploy, Drush destructive/config import, mudança de dependência, merge ou criação de nova PR sem autorização explícita. Nenhum `push --force`.

## 7. Ordem, ownership e integração com PR #299

Branch única existente: `refactor/opencultures-codigo-visual`, PR [#299](https://github.com/coletivo420/aculta/pull/299). Um microcommit por fase, `git add` específico e `git pull --rebase` antes de publicar, push fast-forward. Frente visual edita `web/themes/custom/aculta420/**`, templates/CSS/JS/blocos autorizados e documentação de seu assunto; PHP/rotas/serviços ficam com a frente de código e exigem handoff. Não alterar trabalho de outros agentes, nem mergear a PR. Bloqueio de integração deve ser descrito, sem contorno fictício.

**Status deste documento:** especificação normativa; o andamento e as evidências de cada microfase ficam registrados nos roadmaps e matrizes vinculados. A existência do plano não significa que qualquer gate tenha sido aprovado.
