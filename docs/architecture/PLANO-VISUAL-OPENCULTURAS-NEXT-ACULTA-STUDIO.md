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

Somente após F7 aprovada. Não duplicar controllers, rotas, caches, formulários ou SDCs.

| Fase | Entrega | Aceite |
| --- | --- | --- |
| **A1** | retokenização institucional (verde-escuro, verde, amarelo, vermelho conforme decisões, superfície claro/escuro) | contraste e sem perda de hierarquia |
| **A2** | acabamento próprio de cards, ícones, campos, alertas, navegação e tipografia | mesmos contratos/consumers, stories equivalentes |
| **A3** | identidade visual do Studio no dashboard, editor, Conta e Perfil | benchmark lado a lado Fidelity × Identity, sem mudança funcional |
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

**Estado inicial:** plano aprovado, implementação **não iniciada por este documento**, evidência de Runtime pendente.