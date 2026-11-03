# ACULTA Studio — plano documental visual e de navegação

## Estado do código e última validação integrada dev.36 — 2026-11-03

Este status descreve a última validação integrada comprovada, anterior à atualização do Runtime em andamento. O Dashboard, a Conta privada, o Perfil público e Marley estão implementados no HEAD validado `d30a9529f7`, com owners, rotas e permissões preservados. O tema estava em ACULTA420 `0.7.0-dev.36`; o módulo Studio no Runtime estava em `0.1.0-dev.44`.

- **Dashboard:** título canônico, cartão de identidade cacheável, link público via owner, edição de Perfil condicionada à rota/acesso e cinco destinos reais autorizados. Não há métricas nem conteúdo inventado.
- **Conta:** as nove rotas `/conta/*` respondem diretamente; shell próprio em caixas, formulário em painel amplo, no-JS e AJAX com URL/histórico/ativo/título/breadcrumb/foco/retry/timeout e Drupal behaviors. Form API continua nos owners existentes.
- **Perfil:** edição privada separada do perfil público; sem endereço/documentos no editor; preview e formulários usam os owners atuais.
- **Marley:** “Criar” junto ao título de “Meu trabalho”, listagem mantida e moderação somente para a permissão do owner. Sem publicação própria na fixture atual, os testes de GET de edição/criação ficam `SKIP`; a listagem vazia, responsividade e autorização A/B estão cobertas.
- **Tema:** tokens compactos pesquisados em Carbon, GOV.UK e USWDS; dev.36 reduz Dashboard a intervalos de 12 px. Cores do cabeçalho multidomínio permanecem ACULTA420.
- **Runtime na validação dev.36:** snapshot pré-import de 270.104.476 bytes, SHA-256 `f1461615bbec2363ab70ee762cac15b5d50115c2c80537fef5272c6500261a0f`; importou-se somente a visibilidade do bloco Próximos eventos (`main` + `<front>`). Cache, updates e config status estavam limpos. HTTP anônimo: landing principal 200 com `.aculta-events-upcoming`; Perfil `/u/pessoa-5` 200 sem o bloco. Depois, a atualização para Drupal 11.5.0-beta1/Group 4 alpha passou pelo commit `5f695df`. `drush status` responde; uma consulta de `updatedb:status` falhou transitoriamente ao analisar `modules/contrib/storybook/storybook.info.yml`, e a seguinte listou 18 updates pendentes. Persistem diferenças de configuração. Esta tarefa não executou updates/import; foi observado um processo paralelo invocando `drush cr` sem saída disponível. A nova validação integrada está pendente; detalhes e respostas HTTP atuais estão na auditoria AJAX.
- **Validação visual:** Firefox ESR integrado passou 106/106 checks, 21 SKIP condicionais, zero falhas; Dashboard 320/390/768/1280 px; Conta AJAX/Form API/mobile; Perfil zoom 200/400%; Marley. Capturas em `$SUITE_OUT/studio-spacing-runtime-dev36-20261103/`. A prancha principal válida (1536×1024) foi cotejada por composição; não há alegação de igualdade pixel a pixel.
- **SDCs:** reuso de `aculta420:card` (`studio_fidelity`), `profile-editor` e `profile-identity`; Conta combina `<details>`, lista Drupal e wrappers de Form API. Nenhum SDC novo.
- **DEFERRED:** gravação do avatar interrompida pelo desafio humano de verificação; senha não enviada porque o aceite legal obrigatório não foi registrado. Leitor de tela coberto em Marley, não nas outras telas. Fixtures sem publicação própria não exercitam editar/criar; Runtime não desinstalou módulos opcionais. Veja a auditoria para limites e evidências.

## Histórico da implementação inicial de AJAX da Conta — dev.31

### Estado revisado naquele reteste — supersedido pelo estado dev.36 acima

A inspeção das capturas Runtime revelou que a primeira correção ainda deixava a navegação Conta com classes de abas do Marley. ACULTA420 dev.31 removeu essa herança: menu vertical em painel próprio e formulário em superfície independente, com tokens semânticos e `<details>` acessível no mobile. Firefox Runtime passou 97/97 verificações aplicáveis (22 SKIP), incluindo 320–1440px, caixas computadas, acesso/ativo, Form API inválido/válido e histórico AJAX. Capturas reais em `$SUITE_OUT/studio-dashboard-final-20261103/account-panels-dev31-final/`; a captura principal da prancha, 1536×1024, foi inspecionada junto aos resultados. A barra multidomínio e cores do cabeçalho permaneceram sem alteração, como solicitado.

SDCs: Dashboard reutiliza `aculta420:card` (`studio_fidelity`); Perfil reutiliza `profile-editor` e `profile-identity`; Conta usa `details`, lista Drupal e wrappers do owner, pois nenhum SDC é adequado para controlar navegação contextual e compor Form APIs de owners distintos; Marley mantém as estruturas de navegação/listagem do owner e o token de cabeçalho existente. Nenhum SDC novo foi criado. Dados e Endereço passaram submissão válida com valores existentes e verificação de igualdade dos campos antes/depois. Avatar foi enviado com arquivo sintético e abriu o corte, mas a etapa de salvamento parou no desafio de verificação humana; senha não foi enviada por exigir aceite de Termos. Orca validou Marley (5/5); as outras três telas ainda precisam da cobertura de leitor de tela. Testes Unit cobrem owners opcionais ausentes; `aculta_id_cpf` está ativo no Runtime.

### Complemento de validação — 2026-11-03

Orca no Firefox ESR passou 5/5 verificações em Marley, cobrindo as três abas efetivamente disponíveis para `teste.comum`, o heading da seção e saída de áudio não silenciosa. A conta tem autorização real de moderação pelo papel `content_editor`; uma conta sintética sem esse grant não vê a aba. O teste de avatar passou 11/11 verificações e deixou 1 `DEFERRED`: PNG sintético aceito, formulário de corte aberto e desafio humano presente antes da gravação; não foi enviado, a foto persistida não mudou e os arquivos permanecem temporários para expiração normal. Senha não submetida: diálogo legal obrigatório permaneceu sem aceite e não foi contornado. Comparação pareada foi feita com a prancha principal válida e capturas reais; sem alegação de igualdade pixel a pixel. ZIP ausente; cinco PNGs rastreados válidos por `file`/`identify`.

### Histórico dos retestes anteriores ao dev.31

Firefox ESR no Runtime: pós-dev.29 94/94 e pós-dev.30 97/97 verificações aplicáveis PASS, 21 SKIP em ambas, sem falhas. As cinco PNGs válidas em [`studio-visual/referencias/`](studio-visual/referencias/README.md) foram conferidas e a prancha de quatro estruturas `image(20261103-123227).png` serviu para cotejo visual das caixas; capturas reais por tela estão em `$SUITE_OUT/studio-dashboard-final-20261103/post-push/` e `$SUITE_OUT/studio-dashboard-final-20261103/marley-header-dev30-retest/`. O cabeçalho global manteve sua apresentação e cores. Em Marley “Criar” está junto a “Meu trabalho”; a tabela continua contida em viewport mobile. O arquivo desktop/mobile está ausente no HEAD remoto (remoção `4599e115ed`), não foi restaurado. Naqueles retestes, escrita pessoal/endereço, senha/upload/anexo e módulos opcionais ausentes permaneciam DEFERRED. Ver a [auditoria](STUDIO-CONTA-AJAX-AUDITORIA.md) para limites e evidências.

A presença de `studio-navigation.js` não cobria `/conta/*`. A biblioteca dedicada da Conta usa links elegíveis, histórico/foco/estado, recuperação e Grid próprio; o listener delegado único permanece ativo após `replaceWith`. O Dashboard mostra uma única entrada privada para `/conta/dados`, edição do Perfil condicionada a rota/acesso e perfil público somente quando resolvido pelo owner; providers autorizados continuam como cards. Reutiliza SDC `aculta420:card` na variante `studio_fidelity`, tokens ACULTA420, sem alterar o cabeçalho global. Marley apresenta criação junto ao título e moderação condicionada. Capturas Runtime integradas pós-dev.36 estão em `$SUITE_OUT/studio-spacing-runtime-dev36-20261103/`; evidência e limites estão na [auditoria](STUDIO-CONTA-AJAX-AUDITORIA.md).


**Estado:** planejamento para agentes; não é especificação de implementação já aprovada nem registro de homologação.  
**Branch de referência:** `dev`.  
**Objetivo:** orientar revisões visuais, documentação, roadmaps e futuras implementações das interfaces Studio, usando as estruturas existentes e a identidade real do tema ACULTA420.

## 1. Como usar este documento

Este arquivo é o ponto de entrada para trabalho documental relacionado à experiência visual do Studio. Ele consolida referências e aponta conflitos que precisam ser resolvidos. Não substitui decisões arquiteturais, owners de funcionalidades, ACL, contratos de domínio ou roadmaps de módulos.

Antes de editar documentação ou código, o agente deve:

1. Confirmar o HEAD atual de `dev` e ler os documentos normativos listados abaixo.
2. Distinguir código presente, código testado, comportamento homologado em Runtime e proposta visual. São estados diferentes.
3. Fazer inventário de rotas e providers no código. Não deduzir rota a partir de imagens, documentação histórica ou nomes de menus.
4. Atualizar o documento dono da decisão e os roadmaps afetados na mesma unidade documental, mantendo links entre eles.
5. Não abrir nova PR se o trabalho estiver sendo executado em uma PR existente; não misturar alterações de outras frentes.

### Precedência documental

Em conflito, verificar a data e implementação no HEAD e resolver explicitamente com esta ordem de referência:

1. Decisões vigentes em `docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md` para domínio, purpose e caminhos canônicos.
2. `docs/portal/ACULTA-STUDIO-IDENTITY.md` para identidade, nomes e destinos.
3. `docs/portal/ACULTA-STUDIO-EXPERIENCE-BOUNDARIES.md` para escopo das experiências e separação de responsabilidades.
4. `docs/portal/STUDIO-VISUAL-CONCEPT.md` para princípios visuais e padrões gerais, depois de corrigir trechos históricos que apresentem rotas obsoletas como atuais.
5. Roadmap do owner funcional e do Studio para fases, estado e gates de entrega.
6. Pranchas visuais deste documento como referências conceituais. Elas não definem dados, rótulos finais, permissões ou rotas.

Não resolver divergência apagando histórico sem registro. Marcar material obsoleto como histórico, apontar para a decisão vigente e evitar duplicar contratos.

## 2. Inventário verificado na branch de referência

O Studio está em `web/modules/custom/aculta_portal/modules/aculta_studio`. É uma camada compartilhada de composição e registry. Módulos funcionais continuam donos de conteúdo, formulários, permissões, workflows, inscrições, estados e persistência.

As experiências e caminhos atuais descritos nos documentos de identidade/ADR são:

| Experiência | Purpose e caminho canônico | Escopo visual a representar |
|---|---|---|
| Studio Dashboard / entrada da Conta | purpose `account`, raiz do domínio; conferir controller/provider no HEAD | Entradas autorizadas às experiências e à Conta; não inventar métricas |
| Marley | `account:/marley` | Revista, moderação do Fórum e Galerias; áreas e ações dependem dos providers/permissões |
| Ganjalab Estudante | `courses:/ganjalab` | Catálogo elegível, jornada, sala e progresso oficial disponíveis pelo LMS |
| Ganjalab Educador | `courses:/ganjalab/educador` | Autoria e gestão educacional; Course Builder somente conforme APIs persistentes existentes |
| WE Sativa | `wiki:/we-sativa` | Contribuições Wiki; Wiki é dona de conteúdo e autorização |
| Culturas | `eventos:/culturas` | Eventos, Locais e Organizações conforme integração presente; homologação/grants a confirmar |
| Green Stash | `shop:/green-stash` | Área integrada, não Suite independente; a navegação atualmente disponibiliza Visão geral, Produtos e Envios |

Conta privada, Perfil público e Conta legada são estruturas diferentes. A Conta legada permanece em `/conta-legada`; conferir no HEAD os caminhos atuais de Conta e Perfil Studio. O perfil público canônico e as operações privadas não devem ser combinados na mesma tela.

O menu multidomínio é parte do shell global. Links entre purposes devem usar o mecanismo de domínio do projeto; telas de uma Suite não devem fingir que todos os destinos pertencem ao mesmo host.

## 3. Cores e tipografia encontradas no tema

Fonte canônica: `web/themes/custom/aculta420/css/tokens.css`; composição de identidade Studio: `web/themes/custom/aculta420/css/components/studio-identity.css`.

| Papel | Token / valor claro |
|---|---|
| Fundo de página | `--aculta-surface-page: #f2f7f0` |
| Cartão/superfície elevada | `--aculta-surface-raised: #ffffff` |
| Superfície creme e faixa institucional | `--aculta-surface-muted: #fbf4e8` |
| Verde institucional | `--aculta-green: #689427` |
| Verde escuro, texto principal | `--aculta-green-dark: #0c3c29` |
| Amarelo, ação e seleção | `--aculta-yellow: #f2ca36` |
| Vermelho de marca/alerta | `--aculta-red: #d4452d` |
| Corpo | Inter |
| Títulos | Oswald |

O tema também define modo escuro por tokens semânticos. Componentes devem consumir tokens; não fixar hexadecimais nem criar uma paleta paralela. A identidade institucional do Studio existe em `studio-identity.css`, mas sua ativação é controlada por estado `aculta420.studio_identity`. O agente deve verificar a configuração do ambiente antes de afirmar que essa identidade está ativa em Runtime.

Há tokens de fidelidade ao OpenCulturas, incluindo magenta, dentro de `tokens.css`. Eles coexistem com a camada de identidade ACULTA e não provam qual modo está ativo. Roadmaps devem registrar a decisão de migração visual e gates antes de declarar a fidelidade incorporada ou a identidade própria concluída.

### Direção visual para as próximas propostas

- Manter o shell multidomínio e suas duas faixas conforme a implementação real do tema.
- Aplicar fundos claros, cartões brancos, superfícies creme, texto verde escuro e amarelo para CTA/seleção; usar verde médio em detalhes e vermelho somente em seus papéis semânticos.
- Manter cantos e sombras discretos, densidade adequada ao trabalho e tipografia institucional.
- Mobile-first: uma coluna, alvos generosos, listas convertidas em cartões quando tabela não couber, navegação acessível sem duplicar menus.
- A cor não deve ser o único sinal de estado. Usar rótulo, ícone/semântica e foco visível.
- Preservar distinção funcional entre Estudante, Educador, Conta privada e Perfil público.

## 4. Inventário de componentes e evidências

A branch contém componentes SDC e CSS do tema relevantes, incluindo padrões de cartão, context header/tabs, perfil, conteúdo editorial, cursos e identidade Studio. O agente deve procurar no repositório antes de propor componentes novos. Candidatos listados em `STUDIO-VISUAL-CONCEPT.md` são nomes propostos, não implementação automaticamente disponível.

Contratos observados na camada Studio incluem:

- Navegação AJAX em `modules/aculta_studio/js/studio-navigation.js`; conferir comportamento atual, fallback sem JS, histórico, foco e anúncio acessível.
- Providers/registry de experiências em `src/Experience`.
- Controllers para Conta, Perfil, Marley, Ganjalab e WE Sativa.
- Green Stash navega por provider próprio no módulo Commerce.
- Culturas possui owner `aculta_events`; conferir suas rotas, templates e estado no HEAD.
- Tokens semânticos e componentes de apresentação são responsabilidade do tema. Controllers devem entregar dados e contratos, sem impor CSS/HTML de tema.

Para qualquer proposta, registrar: arquivo/rota consultado, usuário/role de teste, purpose/domínio, viewport, estado da interface e se a captura veio de Runtime. Uma imagem gerada é referência de design, não evidência de funcionamento.

## 5. Pranchas conceituais existentes

As pranchas abaixo foram geradas nesta exploração e são referências conceituais. Textos, retratos, e-mails, cursos, eventos, itens e datas são ilustrativos; revisar antes de transpor para documentação normativa ou produto. A segunda prancha contém problemas gerativos: cabeçalho repetido incorretamente em Culturas/Green Stash e menção a “ACULTA Drupal Studio”; não copiar esses elementos. Corrigir em nova geração se forem usadas para apresentação.

- [Dashboard com barra multidomínio - exploração visual](https://chatgpt.com/library?file_id=libfile_7735ab10eae88191b49c9ba339cca5b0&account_id=personal)
- [Dashboard revisado com identidade e experiências](https://chatgpt.com/library?file_id=libfile_b257710add448191b534ceb982a8f913&account_id=personal)
- [Quatro estruturas pessoais/editoriais - proposta visual](https://chatgpt.com/library?file_id=libfile_60aecd549e64819190d243cb41ea9b87&account_id=personal)
- [Aprendizagem, colaboração e gestão - proposta visual](https://chatgpt.com/library?file_id=libfile_4d9994a20b848191b1b76e814ace7991&account_id=personal)

**Regra de uso:** não tratar as pranchas como aprovadas para implementação. Antes de um agente de implementação usá-las, produzir capturas anotadas de referência com rótulos/dados confirmados e destinos reais; corrigir os erros gerativos e vincular cada tela ao seu owner e rota.

## 6. Plano documental por microfases

As fases abaixo planejam documentação e preparação visual. Elas não renumeram nem substituem as fases funcionais existentes (incluindo OCS-F0–F11, THEME-MF0–MF3, OCS-A1–A4, Course Builder CB-UX e roadmaps de Eventos/Wiki/Commerce).

### D0 - Reconciliação da fonte de verdade

- Auditar `STUDIO-VISUAL-CONCEPT.md`, `ACULTA-STUDIO-IDENTITY.md`, ADR-038, mapa de rotas, contrato de fronteiras, README/changelog do módulo.
- Resolver as ocorrências que descrevem `/studio/*` como rotas vigentes; preservar histórico claramente marcado.
- Conferir propósitos, hosts configurados, caminhos de Wiki (documentos apresentam variações como `/we-sativa` e `/wesativa` em trechos históricos) e Green Stash. Usar ADR e código atual, não a versão mais recente por data sem inspeção.
- Para cada divergência, corrigir fonte dona, atualizar referências e incluir nota de retificação quando decisão já aprovada.
- Entregável: índice de documentos normativos/históricos e tabela de divergências resolvidas.

### D1 - Inventário de telas reais

- Percorrer rotas e providers do HEAD e mapear telas existentes por experiência, separando implementado, parcial, planejado e não homologado.
- Relacionar tela → rota/purpose → controller/provider → módulo owner → access gate → componentes do tema → roadmap.
- Incluir estados vazio, carregando, erro, sem permissão e conteúdo realista autorizado.
- Entregável: matriz de telas com evidências/links de código e lacunas.

### D2 - Contrato visual e tokens

- Atualizar o conceito visual para refletir a paleta e tipografia do tema, modo claro/escuro e shell multidomínio.
- Inventariar SDCs, libraries, padrões, classes e atributos semânticos que já existem; separar candidatos de componentes implementados.
- Decidir documentalmente convivência/finalização da fidelidade OpenCulturas e identidade ACULTA, alinhada ao estado de ativação de Runtime.
- Entregável: contrato visual sem tokens duplicados e com exemplos por componente.

### D3 - Especificações por experiência

Produzir ou atualizar documentos focados, ligados ao contrato comum:

- Studio Dashboard/entrada da Conta: somente links autorizados e dados disponíveis; sem contadores inventados; Conta e Perfil separados.
- Marley: Revista, Galerias, moderação; remover navegação redundante de “Meu trabalho”; botão Criar sob o título; Rascunhos/Publicados/Arquivados/Comentários e moderação nas abas conforme decisão de UX existente.
- Ganjalab: duas especificações distintas, Estudante e Educador; jornada e sala versus autoria/gestão; nunca misturar as permissões e dados.
- WE Sativa: workspace Wiki, contribuições, autoria/ownership coletivo e estados de proposta.
- Culturas: Eventos, Locais e Organizações, cadastro/seleção interativa por AJAX, papéis e gates; indicar o que segue parcial.
- Green Stash: módulo integrado, acessível por permissão; mostrar apenas áreas presentes e acessíveis no código.
- Minha Conta/Meu Perfil: formulários privados separados de perfil público; Conta legada documentada para compatibilidade/comparação.

Entregável: especificação por estrutura com screenshot conceitual, inventário de dados/ações reais, comportamento mobile e estados.

### D4 - Alinhamento dos roadmaps

- Acrescentar links de dependência visual nos roadmaps existentes; não duplicar backlog técnico.
- Identificar bloqueios de cada fase: contratos de dados, ACL/owner, host/purpose, componentes de tema, Runtime, responsividade e acessibilidade.
- Atualizar status com evidência: código presente, teste automatizado aprovado, Runtime validado, revisão visual ou pendente.
- Não marcar fases visuais concluídas com base em pranchas geradas.
- Entregável: mapa consolidado de fases por owner, com links de ida e volta entre documento visual e roadmap funcional.

### D5 - Pacote para agentes de implementação

Antes de iniciar alterações visuais, entregar para cada microfase:

- arquivos/rotas incluídos e explicitamente excluídos;
- comportamento observado e imagem Runtime atual;
- imagem de destino revisada e anotada;
- tokens/componentes a reutilizar;
- dados e ações autorizados pelo owner;
- breakpoints e estados de interface;
- critérios de aceitação visuais, acessibilidade, AJAX/no-JS e permissões;
- comandos de validação já aprovados no projeto;
- arquivos de roadmap/documentação que precisam ser atualizados junto ao código.

Entregável: issue/prompt técnico rastreável ao contrato, sem itens vagos como “deixar bonito”.

## 7. Gates mínimos antes de implementar

1. Rotas/hosts confirmados no código e no DomainPurposeManager.
2. Dados e ações confirmados em provider/owner, sem mocks apresentados como reais.
3. Permissões por experiência e estados anônimo/autorizado/negado verificados.
4. Tokens e SDCs existentes inventariados; nenhum hex paralelo sem justificativa aprovada.
5. Mobile e desktop desenhados com a barra multidomínio real.
6. AJAX mantém URL, back/forward, título, foco e fallback; formulário Drupal continua válido.
7. Testes visuais no Runtime com capturas reais e comparação a viewport fixada.
8. Acessibilidade, modo escuro, teclado, foco e mensagens de erro cobertos pelo gate do tema.
9. Documentação e roadmap atualizados com o resultado e limitações observadas.

## 8. Regra de conclusão

Uma microfase só pode ser declarada concluída quando:

- a implementação estiver ligada às rotas/providers e owners corretos;
- a autorização for verificada no servidor e respeitar purpose/domínio;
- desktop e mobile tiverem sido conferidos em Runtime;
- estados reais, acessibilidade, AJAX e fallback forem avaliados conforme o escopo;
- documentos e roadmaps refletirem o HEAD validado;
- evidências e limitações forem registradas.

Imagem gerada, CSS compilado ou teste unitário isolado não constituem homologação visual integrada.

## 9. Documentos relacionados

- [Conceito visual geral](STUDIO-VISUAL-CONCEPT.md)
- [Identidade e rotas](ACULTA-STUDIO-IDENTITY.md)
- [Fronteiras entre experiências](ACULTA-STUDIO-EXPERIENCE-BOUNDARIES.md)
- [Editor e fluxos editoriais](STUDIO-EDITOR-FLOWS.md)
- [Course Builder visual](STUDIO-LMS-COURSE-BUILDER-VISUAL.md)
- [ADR-038: suites por domínio](../decisions/ADR-038-aculta-studio-suites-por-dominio.md)
- [Tokens do tema](../../web/themes/custom/aculta420/css/tokens.css)
- [Identidade Studio do tema](../../web/themes/custom/aculta420/css/components/studio-identity.css)
- [Módulo compartilhado Studio](../../web/modules/custom/aculta_portal/modules/aculta_studio/README.md)
