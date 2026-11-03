# Conta Studio: auditoria AJAX e plano de recuperação

Data: 2026-11-03. Estado: diagnóstico estático e plano; implementação e homologação pendentes.
Referência de integração documental: `60c4deb98c7776ffaaeabc75d3fcbb0da7146d69` em dev. Arquivos consultados na dev nesta sessão; revalidar no HEAD antes de implementar.
Escopo: entrada account, seções privadas /conta/* e comparação com /conta-legada e suas seções. Não altera grants, entidades ou rotas.

## Evidências e limites

As capturas fornecidas pelo responsável mostram /conta/dados com navegação, descrição e formulário distribuídos horizontalmente; /dados apresenta menu lateral, abas e formulário com largura útil. Capturas demonstram apresentação, não execução de AJAX. Não houve acesso autenticado ao Runtime nesta sessão.

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

Conclusão estática: falta integração entre a navegação contextual da Conta Studio e os contratos AJAX analisados. O layout tem um problema adicional de composição/mapeamento. Não declarar causa única de todas as falhas de Runtime nem homologar rotas com base em HTTP 200.

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

Limites desta entrega: documentação apenas, sem alterações funcionais, testes browser, deploy ou sincronização de Runtime. Gates anteriores permanecem históricos com seus escopos; não comprovam AJAX atual das seções /conta/*.
