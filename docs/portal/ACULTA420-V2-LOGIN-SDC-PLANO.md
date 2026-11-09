# ACULTA420 V2 - Plano de implantação do modal de login SDC

Estado: PLANEJADO (2026-11-09). Branch alvo: `dev`. **Nenhum SDC foi implementado por este documento.**

## 1. Decisão visual e referências oficiais

Duas variantes aprovadas para comparação, sempre com uma faixa superior **verde, amarelo e vermelho**, na mesma ordem, preferencialmente como três segmentos justapostos, sem decoração excessiva.

| Variante | Claro | Escuro | Utilização |
| --- | --- | --- | --- |
| `neutral` (preferida) | Superfície branca e título verde | Superfície grafite e título claro | Login padrão do site |
| `institutional` | Faixa/cabeçalho verde-limão institucional e corpo branco | Cabeçalho verde profundo e corpo grafite | Variante disponível aos administradores |

Referências aprovadas, para desktop e mobile, nos dois temas:
- [Comparativo neutro](login-sdc-referencias/comparativo-neutral.png)
- [Comparativo institucional](login-sdc-referencias/comparativo-institutional.png)

As referências visuais são **objetivos de aparência**, não contratos de markup, proporções fixas ou autorização para substituir a autenticação Drupal. É preciso ajustar contraste, zoom, reflow e 44 px de alvo de toque. A assinatura tricolor **não** varia conforme `data-purpose`.

## 2. Inventário conferido em origin/dev em 2026-11-09

- `web/themes/custom/aculta420_v2/components/dialog/*`: componente nativo `<dialog>`, variantes `default/fullscreen`, slots `content/actions`, controles por `js/utility-dialogs.js`. **Não duplicar o mecanismo de modal.**
- `components/form-field/*`: rótulo, controle, descrição e erro em slots; `additionalProperties: false`.
- `components/button/*`: botões padrão. Envio de Form API com `name/value` é exceção documentada em `docs/botoes.md`.
- `components/google-signin-button/*`: componente Google existente com variante link a partir de `url`; preservar fluxo OAuth real.
- `src/styles/tokens.css`: tokens `--color-*`, `--button-*`, `--color-auth-*`, `--accent-*-stripe`; `data-theme` controla light/dark.
- `aculta420_v2.libraries.yml`: `component-library-mode` e `ui-library-admin`; `js/ui-library-tabs.js` agrupa os exemplos da biblioteca.
- `aculta420_v2.ui_styles.yml`: estilos UI Styles atuais de botão, acento de cartão, Revista e purpose; nenhum estilo de login ainda.
- `web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php`: herda `UserLoginForm`, valida nome/e-mail/CPF, flood control e conta bloqueada; possui HTMX, destino local validado, OAuth Google condicional, formulário com links e fallback legado AJAX. Preservar essas proteções.
- `config/sync/captcha.captcha_point.aculta_login_popup_form.yml`: tratamento próprio do CAPTCHA. Não remover nem afrouxar.
- `docs/portal/COMPONENT-DESIGN-SYSTEM.md` e ADR-007 contêm legado Bootstrap/V1; para V2 a instrução atual explícita é **SDC nativo, sem Bootstrap, sem jQuery, sem Node/Vite**. Seguir `AGENTS.md` da raiz e do tema e ADR-046.

**Correção arquitetural:** o usuário chamou de "Libraries UI", mas a apresentação efetivamente identificada usa catálogo de SDC/UI Patterns (`ui_patterns_quicklinks`, `ui_patterns_component`), stories YAML e bibliotecas do tema. O agente deve inventariar a rota e os módulos habilitados em runtime antes de afirmar que há o módulo contrib `Libraries UI`; não adicionar dependência presumida.

## 3. Arquitetura de componentes

Organizar em três níveis:

1. **Base modal**: `aculta420_v2:dialog` continua responsável por `showModal`, foco, Escape, fechamento, backdrop, restauração de foco e fallback navegável para a rota completa de login. Se precisar exibir cabeçalho especializado, criar uma variante/slot específico de forma compatível e com testes, ou um wrapper sem duplicar `<dialog>`. Não modificar todos os diálogos.
2. **Composição `aculta420_v2:auth-login` (novo)**: SDC puramente visual para a composição de título/subtítulo, faixas tricolores, regiões, divisão entre Google e login local, rodapé e os estilos de variante. Props estritas e escalares sugeridas: `id`, `title`, `subtitle`, `variant` enum `neutral|institutional`; slots sugeridos: `provider`, `fields`, `submit`, `recovery`, `register`, `messages`. Não duplicar labels nem controles, nem colocar dados de identidade/autenticação nas props. O mecanismo do diálogo pode envolver a composição, conforme decisão após prova de render.
3. **Controller/Form adapter no Portal**: prepara o render array de `LoginPopupForm` respeitando Form API, cache/access, HTMX e validação, e entrega os slots autorizados à apresentação. Não desmembrar o formulário em vários `<form>`; manter token, método, destino, CAPTCHA e erros intactos. O tema não resolve URL, domínio, currentUser, Google, CPF nem qualquer validação.

Preferir a menor mudança compatível com o render Form API; nenhuma tentativa de renderizar controle do formulário como HTML estático. Evitar `#markup` com link Google montado manualmente quando houver um adaptador seguro ao SDC já existente, sem mudar permissões.

### Contrato de render e estados

- `<dialog>` acessível com `aria-labelledby`, identificação única, fallback `/entrar` real e botão fechar de 44px; diálogo de login nunca parte da árvore de diálogo em duplicidade.
- `form` único do Drupal, com submit original; campos usuário/e-mail/CPF e senha, input type `password`, auto-complete apropriado; alternância de visibilidade de senha apenas se segura e sem expor valor em logs.
- Estados: fechado, aberto, foco, carregando, erro de credenciais, erro de validação, conta bloqueada, erro de rede/HTMX, sem Google configurado, CAPTCHA, login concluído, destino original, teclado e sem JavaScript.
- Três faixas finas contínuas no topo: verde, amarelo e vermelho; não representar erro/estado por essas cores.
- Dimensões fluídas, nunca cópia fixa dos pixels dos PNGs. Em 320 px o modal tem margens seguras, sem rolagem horizontal; altura limitada pela viewport e conteúdo rolável dentro, mantendo botões alcançáveis.
- `data-theme` muda tokens, não requer duplicar DOM. Preferir tokens semânticos novos `--color-auth-login-*` apenas quando os existentes não servirem. Primitivos somente em `tokens.css`.
- Neutral preferido: superfície clara/branca, título verde profundo; dark grafite, texto claro, CTA verde-lima com contraste medido. Institutional: cabeçalho verde e texto legível, corpo consistente com neutral. Google mantém fundo branco e logotipo legítimo.

## 4. Biblioteca de componentes e UI Styles

Implementar stories suficientes para cobrir:
- `auth-login.neutral-light.story.yml`, `auth-login.neutral-dark.story.yml`, `auth-login.institutional-light.story.yml`, `auth-login.institutional-dark.story.yml`, além de `auth-login.default.story.yml`, na convenção **real** de stories descoberta no runtime.
- Story de `error`, `loading`, `google-unavailable` e `long-text` somente com fixtures fictícias; **não** incluir credenciais ou formulário que realmente autentique na UI Library.
- A biblioteca renderiza casos mobile e desktop e fornece seletor/abas de variantes. Preferir descoberta automática por `*.component.yml` e `*.story.yml` e usar a biblioteca `ui-library-admin` apenas como camada de layout.
- Identificar a rota real do catálogo, verificar se o tema administra via `ui_patterns` e `ui_styles` e documentar cada vínculo. Não presumir que um registro em `*.libraries.yml` sozinho crie uma entrada no catálogo.
- Se UI Styles puder ser aplicado ao SDC no fluxo concreto, criar estilo administrativo `auth_login_variant` com opções neutra/institucional, documentar versão instalada e comportamento. Caso não haja consumidor compatível, limitar escolha ao prop `variant` e **documentar a impossibilidade** em vez de criar UI Styles inoperante.
- Uma única opção de variante global controlada no servidor pelo administrador, se já existir infraestrutura compatível; padrão neutral. Não introduzir escolha de aparência do login por visitante nem configuração por purpose sem aprovação.

## 5. Microfases executáveis

**F0 - Inventário e baseline:** checar `origin/dev`, worktree limpo/sujo, `AGENTS.md` e escopo real dos módulos UI Patterns, UI Styles, navegação, login popup e testes. Capturar comportamento atual e identificar onde o modal é injetado; verificar rota completa e fallback.

**F1 - Tokens e visual:** mapear tokens do modal em ambos os modos, definir assinatura tricolor e duas variantes; revisar contraste WCAG AA (4,5:1 normal, 3:1 texto grande/controles), foco, mobile/reflow. Criar CSS só no SDC e tokens somente em `tokens.css`. Não editar CSS global de login sem inventário dos consumidores.

**F2 - SDC e stories:** criar `components/auth-login/` com schema, Twig, CSS, story default e variantes; compor os SDCs `dialog`, `form-field`, `button`, `google-signin-button` conforme contrato real. Se necessário ajustar `dialog`, fazer alteração retrocompatível com testes dos outros consumidores.

**F3 - Integração real:** adaptar Portal/LoginPopupForm e gatilhos, usar HTMX para requisições novas, manter Form API e redirect seguro. Inspecionar e tratar cuidadosamente o fallback legado `#ajax` existente; não remover proteção, não trocar ação ou POST de forma incompatível. Não alterar perfis, registro ou reset além dos links.

**F4 - UI Library e UI Styles:** garantir listagem das variantes na biblioteca real; incluir previews com estados sintéticos, grupo `Autenticação` ou convenção já existente; classificar a opção institucional. Documentar se UI Styles de fato se aplica ao renderer. Teste de ausência de dados sensíveis.

**F5 - testes e homologação:** PHP lint, unit/kernel de montagem, Form API e fluxo; `php scripts/validate-aculta420-sdc-schemas.php`, `php scripts/validate-aculta420-design-foundations.php`, `php scripts/validate-aculta420-v2-foundations.php`, `php scripts/validate-aculta420-mapped-classes.php`, `php scripts/validate-browser-validators.php`, `php scripts/validate-aculta420-foundation.php`, `php scripts/validate-aculta420-ui-styles.php` se aplicável, `npm run lint:css` se disponível, e render Drush SDC. Testar erros/sucesso e retorno, CPF/email, Google configurado ou não, Turnstile, CSRF, flood, não autenticado/autenticado; verificar visual em 320/390/768/1024/1440, claro/escuro, zoom 200%, teclado, Escape, foco, rolagem e leitor de tela. Nenhum resultado PASS sem execução.

**F6 - entrega:** atualizar `web/themes/custom/aculta420_v2/docs/components.md`, `docs/architecture.md`, `docs/ui-styles.md` (quando houver), `docs/gates.md`, `docs/roadmap.md`, `CHANGELOG.md`, status e `TODO.md` pertinentes; registrar evidências de render em manifesto, classificar versão apenas após aceitação. Commits atômicos para `origin/dev`, sem PR e sem force push, com preservação de arquivos de terceiros; sincronizar código no Runtime e `drush cr` se aplicável às regras vigentes, sem alteração destrutiva.

## 6. Critérios objetivos de aceite

1. O login real da barra usa o visual aprovado (neutral default) e institutional pode ser pré-visualizado no catálogo.
2. Em ambas as variantes, a assinatura tricolor fica no topo, nas cores canônicas; layouts claro/escuro em desktop/mobile se aproximam das imagens.
3. O catálogo lista o SDC com stories, sem repetir implementação nem publicar um formulário funcional na página de previews.
4. Fluxo de autenticação preserva validação Drupal, links, CAPTCHA, flood, CSRF, Google real, destino local seguro e fallback sem JS.
5. Nenhum segundo sistema de modal, nenhuma dependência Bootstrap/jQuery, nenhuma nova chamada Drupal AJAX, nenhuma URL fixa.
6. Keyboard, leitor de tela, zoom, contraste e responsividade aprovados em runtime; sem evidência o item permanece PENDENTE.
7. Documentação, testes, status e commit/push estão sincronizados. Relatar explicitamente `RUNTIME STATUS: DEFERRED` quando não validado.

## 7. Arquivos deste pacote

- Este plano: `docs/portal/ACULTA420-V2-LOGIN-SDC-PLANO.md`
- Prompt: `docs/portal/ACULTA420-V2-LOGIN-SDC-AGENTE.md`
- Referências: `docs/portal/login-sdc-referencias/comparativo-neutral.png` e `comparativo-institutional.png`

**Nota:** nenhum arquivo do tema, configuração, módulo ou runtime foi alterado na fase documental.
