# Storybook ACULTA420

Catálogo visual e documentação navegável do tema Drupal ACULTA420.

## Stack e fronteiras

- Drupal contrib `storybook` adiciona tags Twig e a rota protegida que renderiza stories dentro do tema Drupal.
- Stories são `*.stories.twig` junto dos SDCs e chamam o SDC real. O Drush do módulo as compila em `*.stories.json`, arquivos gerados e ignorados pelo Git.
- Storybook JS usa o renderer oficial `@storybook/server`, Vite como builder e MDX para páginas de documentação.
- SDC schema, Twig e CSS do tema continuam fonte dos contratos e componentes. UI Patterns Library continua usando seus `.story.yml`; não remova nem confunda os dois formatos.
- Storybook serve para documentação e desenvolvimento. Não é editor de conteúdo nem ambiente de produção.

## Início rápido

Pré-requisitos: Node.js 20+ (o workspace usa Node 22) e Drupal de desenvolvimento acessível, com o módulo `storybook` disponível e habilitado.

```sh
cd storybook
npm ci
npm run stories:compile
npm run storybook
```

Abra `http://localhost:6006`. Autentique-se no site Drupal no mesmo navegador com uma conta que tenha permissão de administrador. No console do navegador do Storybook, defina a URL do Drupal de desenvolvimento e recarregue: `localStorage.setItem('aculta420-storybook-drupal-url', 'https://SEU-URI-LOCAL')`. Os JSON gerados omitem o host; a URL fica somente no armazenamento local do navegador e não entra no Git. Não use produção.

O gerador sempre omite o host dos artefatos. Stories compiladas ficam ignoradas pelo Git.

## Proteção e cookies

O módulo conserva `storybook.development: false`. Essa opção é essencial: quando ativada, o código instalado do módulo ignora permissão e permite renderização a qualquer usuário. Deixe a permissão `render storybook stories` somente no papel Administrator. Não conceda a usuários anônimos.

A prévia envia credenciais com `credentials: include`; isso não contorna SameSite, CORS nem a permissão Drupal. Se Storybook e Drupal forem origens distintas, configure no serviço de desenvolvimento CORS para a origem exata do Storybook, método GET, headers mínimos e credenciais; nunca use origem `*`. Cookies `SameSite=Lax` podem não ser enviados em requests cross-site: prefira origens locais same-site suportadas pelo ambiente. Não enfraqueça a autenticação ou cookies para contornar esse limite.

Não habilite CORS para Storybook em produção. Não inclua credenciais, tokens, dados pessoais, URLs internas fixas ou host de produção nos JSON, stories, MDX ou configuração commitada.

## Escrever uma story

- Nome do arquivo: `components/<grupo>/<componente>/<componente>.stories.twig`.
- Agrupe com `{% stories ... %}`; defina cada exemplo com `{% story ... %}`.
- Renderize o SDC com `include('aculta420:<machine-name>', ..., with_context = false)`.
- Use props presentes no schema; os slots devem conter exemplos estáticos representativos.
- Documente variantes, estados vazio/erro/carregamento, teclado, acessibilidade e limites quando aplicável.
- Use somente conteúdo explicitamente demonstrativo. Não reproduza decisões de acesso, lógica de negócio, dados privados ou callbacks.
- Recompile stories com Drush e confira visualmente no Storybook.

Guardrail obrigatório: `php scripts/validate-aculta420-storybook.php`. Ele exige uma story para cada SDC e as páginas principais de documentação. A regra para agentes está em `AGENTS.md`.

## Documentação para agentes

Comece em **IA e agentes** na navegação do Storybook e siga os documentos normativos do repositório. O catálogo acelera descoberta visual, mas não substitui `AGENTS.md`, documentação Markdown, pesquisa upstream, gates, validação de cache/access ou revisão de código.

## Pesquisa e referências

- Drupal Storybook: https://www.drupal.org/project/storybook
- Storybook Server renderer: https://www.npmjs.com/package/@storybook/server
- Storybook docs/MDX: https://storybook.js.org/docs/writing-docs
- Storybook stories: https://storybook.js.org/docs/writing-stories
- Drupal SDC: https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components

Versões pinadas no `package-lock.json`; atualizações devem revisar compatibilidade do renderer, builder, addon de docs e Node antes de atualizar.
