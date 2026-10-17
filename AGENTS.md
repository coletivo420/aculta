# ACULTA - Instruções para agentes

## Projeto

Este repositório contém o site institucional da:

ACULTA - Associação Cultural Antiproibicionista

Domínio de produção:

aculta.org

O site é institucional e pertence a uma associação cultural antiproibicionista sem fins lucrativos.

O objetivo inicial é construir um site institucional profissional, acessível, seguro, rápido e adequado à validação institucional do domínio pela Google for Nonprofits.

## Stack

- Drupal 11
- PHP 8.5
- Apache no Homelab e LiteSpeed na produção Hostinger Cloud Startup
- PHP-FPM no Homelab
- MariaDB para o Runtime de desenvolvimento (Homelab, banco `aculta_runtime`); a pasta `estados/` contém backups privados locais do banco, não versionados
- MariaDB em produção
- Composer
- Drush
- Git
- GitHub
- Codex CLI

Ambiente de desenvolvimento principal:

Debian Homelab + Apache + PHP-FPM + MariaDB

Produção:

Hostinger Cloud Startup + LiteSpeed + PHP + MariaDB

Document root do Drupal:

web/

## Ambientes

- `*.aculta.toca.net.br` é o **servidor de testes** (Homelab). Nos documentos de arquitetura aparece como Homelab; os dois termos designam o mesmo ambiente.
- `*.aculta.org` é **produção**.
- A pasta de trabalho do Homelab/servidor de testes é `/home/aculta/` (raiz da instalação: `config/sync`, `secrets/`, `vendor/`, `web/`, `public_html -> web`). O repositório aparece nela como `/home/aculta/aculta-site`. Para Drush e operações de runtime, use essa raiz. Os arquivos `web/sites/default/settings*.php` são `aculta:aculta` com modo `0640`: operar a partir de `/home/aculta` exige estar no grupo `aculta`. Detalhes em `docs/architecture/environments.md`, seção Diretório de trabalho. O Drupal e o MariaDB Runtime podem ser operados localmente a partir dela; não presumir que seja necessário acessar o próprio Homelab por SSH.
- **Antes de qualquer operação de ambiente**, confirme `hostname`, `pwd`, branch/estado Git e `php vendor/drush/drush/drush.php status`. Confirme no Drush o site URI e o banco conectados antes de executar comandos que alterem estado.
- Se `hostname` for `toca` e o Drush confirmar o Runtime `aculta_runtime` e um URI `*.aculta.toca.net.br`, trate a sessão como já estando no servidor de testes: use o checkout e o Drush locais. SSH só se justifica quando a operação necessária não estiver disponível localmente e o destino tiver sido confirmado pela documentação de ambientes.
- **Padrão de desenvolvimento funcional:** em mudanças destinadas ao Portal/tema, implemente no checkout local ligado ao Homelab e aplique imediatamente ao Runtime de testes assim que o escopo estiver coerente para smoke test. Faça cache rebuild e as atualizações estritamente necessárias, verificando antes URI, banco, estado da configuração e efeitos; nunca espere uma etapa final para só então atualizar o ambiente de teste. Mantenha a mudança testável enquanto o trabalho continua. Isso não autoriza operações destrutivas nem qualquer alteração em produção.
- Não executar `updb`, import/export de configuração, deploy ou outras operações persistentes sem primeiro inspecionar o estado, os efeitos e a possibilidade de recuperação; nunca apontar procedimentos de teste para produção.
- Não inventar ambientes, hosts ou nomes de servidor. Use somente os definidos em `docs/architecture/environments.md` e `docs/architecture/multidomain.md`.
- Testes em `127.0.0.1` com resolução de host própria simulam o servidor de testes, mas não são o servidor de testes; registre-os como tal.

## Estrutura do desenvolvimento customizado

Tema customizado:

web/themes/custom/aculta420/

Módulos customizados:

web/modules/custom/

Configuração exportável do Drupal:

config/sync/

## P5-extra-2 — economia de tokens e roteamento de agentes

Para pesquisa, inventário e tarefas básicas verificáveis, usar preferencialmente o modelo mais econômico disponível; para DI/Views/Entity API, escalar conforme risco; para revisão formal, autenticação, segurança, acesso/cache privado, Domain, Commerce, segredos e merge, reservar modelo de maior capacidade. A seleção é manual: o utilitário não muda o modelo por conta própria.

Consultar `docs/portal/AGENT-TOKEN-ECONOMY.md`. Usar `python3 scripts/portal-agent-budget.py route research` para recomendação de tier e `python3 scripts/portal-agent-budget.py context P5.2-A 3000` para contexto curto. Economizar tokens lendo arquivos e linhas relevantes, evitando dumps/roadmaps repetidos e mantendo relatório conciso; nunca dispensar revisão, documentação, gates ou evidências.

## Pesquisa obrigatória de melhores práticas Drupal 11+

- Antes de decidir arquitetura, API, mecanismo de roteamento, configuração por domínio, cache, segurança ou uma dependência Drupal/contrib, pesquisar na web as melhores práticas atuais para Drupal 11+ (api.drupal.org, drupal.org/docs, issues do módulo e changelog do Core/contrib).
- Não adivinhar mecanismos: se a pesquisa não confirmar, dizer que não foi confirmado e perguntar. Citar as fontes consultadas na resposta.
- Verificar o código instalado (`web/core`, `web/modules/contrib`) como fonte de verdade para a versão em uso, além da pesquisa.

## Padrão Drupal 11+ do ACULTA Portal

O módulo `web/modules/custom/aculta_portal` adota **Drupal Core 11.3+ como baseline arquitetural**.

Este padrão é obrigatório para:
- desenvolvedores humanos;
- Codex;
- ChatGPT e outros agentes;
- demais ferramentas de IA que produzam ou revisem código neste repositório.

O projeto em andamento é **Modernização Drupal 11+ Aculta Portal**. A PR #90 foi integrada à `main` (merge `dd5c8a4`); P0–P10 estão concluídas. Antes de qualquer alteração do Portal, ler `docs/portal/GUARDRAILS.md`, `docs/portal/DRUPAL-11-STANDARDS.md` e `docs/operations/DEBT-REGISTER.md`. A auditoria P10-R está mesclada (PR #91); o documento de auditoria foi removido na limpeza documental e sua cópia histórica está no commit `9c95420` (`git show 9c95420:docs/portal/P10-R-FINAL-AUDIT.md`). A etapa atual é o saneamento de dívidas em `docs/operations/DEBT-REGISTER.md`; o planejamento de produto vem depois da resolução dessas pendências. Novas fases exigem decisão do responsável.

A referência normativa é `docs/portal/DRUPAL-11-STANDARDS.md`. Antes de alterar o Portal, leia esse documento e preserve as fronteiras Core/contrib → `aculta_portal` → contrato neutro → ACULTA420.

Regras resumidas:
- Core/contrib continuam fonte de verdade; não criar storage paralelo quando a capacidade já existir;
- preferir dependency injection em classes; não introduzir novos service locators `\Drupal::...` em `src/`;
- hooks runtime novos/refatorados usam OOP `#[Hook]` quando suportado pelo Core instalado; lifecycle permanece procedural quando exigido;
- tratar como deprecada no projeto qualquer prática runtime legada contrária ao padrão moderno Drupal 11+ (por exemplo `#[FormAlter]`, hook procedural migrável, novo callback global ou novo service locator), mesmo quando ainda tolerada por compatibilidade; exceções precisam de evidência da API upstream;
- EntityQuery declara `accessCheck(TRUE|FALSE)` conscientemente;
- Form API em Drupal 11.3+ prefere callbacks resolvidos pelo `CallableResolver` e serviços DI;
- subscribers usam `EventSubscriberInterface` e o tag `event_subscriber`; prioridades funcionais não mudam por estética;
- Render API, access e cacheability fazem parte do contrato funcional;
- nenhuma mudança é chamada de “Drupal 12/13 ready” sem verificar Core, change records e módulos contrib;
- execute `php scripts/validate-aculta-portal-drupal11.php` em mudanças do Portal e elimine, não expanda, a dívida técnica registrada pelo gate.

A documentação deve registrar o motivo arquitetural das regras, não apenas sua forma.

## Servidor web

O Homelab usa Apache; a produção na Hostinger Cloud Startup usa LiteSpeed, confirmado por cabeçalhos HTTP públicos em 2026-10-15.

- Não introduzir novos exemplos, regras ou dependências específicas de Nginx.
- Não manter compatibilidade com Nginx como requisito do projeto.
- Preservar o `web/.htaccess` fornecido pelo Drupal e validar as diretivas em ambos os ambientes.
- No Homelab, validar `mod_rewrite`, `mod_headers` e a integração PHP-FPM por `proxy_fcgi`.
- Na produção, validar reescrita, headers, proteção de arquivos, processamento de `.htaccess` e handler PHP no LiteSpeed/Hostinger; não presumir acesso a VirtualHost ou `httpd.conf`.
- VirtualHosts, caminhos, usuários, certificados e configuração Virtualmin/Hostinger continuam específicos de cada ambiente.
- Referências a Nginx em relatórios antigos são históricas e não definem a arquitetura atual.

## Regras fundamentais

1. Nunca modificar arquivos do Drupal Core diretamente.

2. Nunca modificar código dentro de:

web/core/

3. Nunca modificar diretamente módulos contrib:

web/modules/contrib/

4. Nunca modificar diretamente temas contrib:

web/themes/contrib/

5. Todo desenvolvimento específico da ACULTA deve ficar preferencialmente em:

web/themes/custom/aculta420/

ou:

web/modules/custom/

6. Dependências PHP devem ser instaladas e gerenciadas pelo Composer.

7. Não adicionar vendor/ ao Git.

8. Nunca versionar:

web/sites/default/settings.php

9. Nunca inserir no código:

- senhas
- credenciais de banco
- tokens
- API keys
- segredos
- credenciais da Hostinger
- credenciais do GitHub

10. Não remover ou sobrescrever regras de segurança existentes no .gitignore sem necessidade explícita.

11. Manter compatibilidade com Drupal 11 e PHP 8.5.

12. Priorizar APIs oficiais e padrões do Drupal.

13. Evitar módulos adicionais quando a funcionalidade puder ser implementada adequadamente com Drupal Core.

14. Antes de adicionar uma dependência Composer, explicar sua necessidade.

15. Não executar alterações destrutivas no banco de dados sem autorização explícita.

16. Não apagar conteúdo existente sem autorização.

17. Fazer alterações incrementais e fáceis de revisar no Git.

## Git

Branch principal:

main

Repositório:

coletivo420/aculta-site

Antes de alterações significativas, verificar:

git status

Não fazer force push.

Não reescrever o histórico da branch main.

Não fazer commit de credenciais.

## Drush

No Homelab Debian, usar o Drush instalado pelo Composer:

```sh
php vendor/drush/drush/drush.php status
php vendor/drush/drush/drush.php cr
```

Em uma workstation Windows opcional, o equivalente é:

```powershell
php .\vendor\drush\drush\drush.php status
php .\vendor\drush\drush\drush.php cr
```

## Banco de teste (Kernel)

Os testes de Kernel usam o MariaDB dedicado `aculta_simpletest`, com a configuração em `secrets/simpletest-db.env`
(fora do Git). Procedimento, rotação e resultados: `docs/operations/BANCO-DE-TESTE.md`. Nunca usar o Runtime
`aculta_runtime` nem SQLite para testes; nunca imprimir o conteúdo do arquivo de segredos.

## Identidade institucional

Nome:

ACULTA - Associação Cultural Antiproibicionista

A ACULTA é uma associação cultural antiproibicionista.

O site deve comunicar de forma clara:

- identidade institucional
- finalidade da associação
- atuação cultural
- defesa dos direitos humanos
- debate público sobre políticas de drogas
- projetos e atividades
- transparência institucional
- formas de contato

Evitar linguagem que faça o site parecer uma loja, dispensário ou plataforma comercial de cannabis.

## Arquitetura inicial do site

### Home

Apresentação clara da ACULTA e de sua atuação.

### Institucional

- Quem somos
- Missão e objetivos
- Organização

### Atividades

Apresentação das atividades realizadas pela associação.

### Projetos

Projetos culturais e sociais da ACULTA.

### Notícias

Notícias, artigos e atualizações institucionais.

### Transparência

Área destinada a documentos institucionais, incluindo quando disponíveis:

- Estatuto
- CNPJ
- Atas
- Relatórios
- documentos institucionais

### Contato

Informações oficiais de contato.

### Política de Privacidade

Página com política de privacidade do site.

## Google for Nonprofits

Uma das prioridades iniciais do projeto é permitir que aculta.org represente claramente uma organização real e identificável.

O site deve apresentar claramente:

- nome da organização
- missão
- atividades
- projetos
- informações institucionais
- informações de contato
- transparência
- domínio institucional

Não criar afirmações institucionais ou jurídicas sem fonte fornecida pelo responsável pelo projeto.

Não inventar:

- CNPJ
- endereço
- nomes de dirigentes
- datas
- números de registro
- parceiros
- financiadores
- certificações

Quando uma informação institucional necessária não estiver disponível, utilizar placeholder claramente identificado ou solicitar a informação.

## Front-end

Tema público atual:

ACULTA420

Machine name:

aculta420

Provider funcional:

web/themes/custom/aculta420/

Não recriar provider, alias, shim ou camada de compatibilidade de tema com machine name diferente de `aculta420`.

Prioridades:

- mobile first
- responsividade
- acessibilidade
- HTML semântico
- boa performance
- CSS organizado
- JavaScript mínimo
- compatibilidade com Drupal
- SEO técnico
- facilidade de manutenção

Evitar page builders pesados.

Evitar dependências JavaScript desnecessárias.

## Direção de arte para imagens criadas por agentes

- Toda imagem criada por um agente para este projeto deve considerar a identidade da ACULTA como associação cultural canábica e antiproibicionista; quando a imagem representar a comunidade, a conquista ou a participação no Portal, a relação com cannabis, cultura, direitos ou superação da proibição deve ser reconhecível.
- Usar enquadramento cultural, comunitário, educativo e de direitos. Não representar o Portal como dispensário, loja de cannabis ou campanha de consumo.
- Antes de criar, inspecionar os assets próximos e preservar as imagens históricas ou fornecidas pelo responsável. Registrar origem, intenção e dimensões das novas artes na documentação do componente.
- Para ilustração raster nova, usar a ferramenta imagegen; redimensionar e otimizar a saída para o uso real. Preferir arte original sem texto incorporado, salvo quando o texto exato fizer parte do pedido.
- Consultar `docs/portal/IMAGE-ART-DIRECTION.md` para a paleta e a linguagem visual das conquistas.

## Imagens e arquivos de imagem (regra permanente)

- Toda imagem e todo arquivo de imagem do site passa pelo submódulo `aculta_image`
  (`web/modules/custom/aculta_portal/modules/aculta_image/`). Vale para código novo e antigo; código existente
  fora dele deve ser refatorado.
- Não usar `image_style` (renderização, referência ou configuração) fora do catálogo `ImageCatalog`. Pedir uma
  finalidade, não um estilo.
- Não criar limite de upload de imagem próprio: usar `UploadPolicy`. A exceção aprovada da conquista (aculta_game)
  fica só no formulário da conquista.
- Não processar imagem com `ImageFactory` nem excluir arquivos por fora do `aculta_image`. Imagens candidatas à limpeza do acervo seguem a quarentena; no ciclo normal, `aculta_image` pode excluir arquivo substituído/removido de entidade somente após revalidar que `file_usage` e `entity_usage` não apontam para ele. Temporários de imagem sem uso vencem segundo `system.file:temporary_maximum_age`.
- Campo novo de imagem nasce com a política do `aculta_image` em `third_party_settings`.
- Importação de qualquer origem externa (Wayback, LMS, arquivo recebido) passa pela ingestão do `aculta_image`.
- Gate: `php scripts/validate-aculta-image.php` (tolerância zero). Plano e fases: `aculta_image/docs/PLANO.md`.

## Segurança

Nunca expor mensagens de erro detalhadas em produção.

Nunca colocar credenciais no repositório.

Não alterar configurações de segurança sem explicar a mudança.

O ambiente local pode utilizar configurações de desenvolvimento diferentes das configurações de produção.

Credenciais de integrações devem usar Drupal Key com provider `env`; nunca
preencher segredo diretamente em config, exportar configuração com segredo,
mover credenciais para o tema ou hardcodar caminhos Homelab/Hostinger no
Portal. Adicionar credencial exige Key, nome de variável, atualização do
ACULTA Secrets Contract, gate anti-regressão e provisioning por ambiente.

## Bancos e Sistema de Estados

- Desenvolvimento (Homelab) usa o MariaDB `aculta_runtime`. Estados (`estados/`) são backups privados e não ficam no disco local; restaurações são carregadas no Runtime MariaDB.
- Os backups em `estados/` são imutáveis e integrais; não são sanitizados.
- Estados são backups privados dos servidores e não são versionados neste repositório: `estados/` está no `.gitignore`. A pasta `estados/` é local e está inteira no `.gitignore` (`/estados/`): nada dela, nem README ou manifesto, é versionado. Nunca adicionar deliberadamente senhas, API keys, tokens de serviços externos ou credenciais de produção ao Runtime/Estado.
- Produção continua usando MariaDB. Nunca implantar backups de `estados/` nem apontar produção para o Runtime.
- Não editar um Estado imutável. Mudanças operacionais depois do restore pertencem somente ao Runtime.
- Código custom deve utilizar APIs Drupal para acesso a dados. A portabilidade/migração entre bancos não é responsabilidade de `aculta_portal`; pertence ao projeto independente **DBTNG-2**.
- `web/sites/default/settings.local.php` e configurações locais permanecem fora do Git. Credenciais MariaDB de produção nunca entram em settings versionados.
- Alterações futuras de mecanismo de banco e procedimentos de migração devem ser acompanhados exclusivamente no projeto **DBTNG-2**; não fazem parte das fases de modernização do Portal.

## Integrações Google e serviços externos

- Consultar `docs/integrations/GOOGLE.md` antes de qualquer integração Google.
- Não inserir Google Tag, Analytics, Search Console verification, OAuth ou Classroom diretamente em Twig/JS do tema.
- Credenciais e secrets ficam em ambiente/Key; nunca no Git.
- Integrações devem poder permanecer desabilitadas sem quebrar o Drupal.
- Homelab não deve enviar telemetria real por padrão.
- Google Analytics/Tag exige revisão de consentimento e não pode enviar PII.
- Search Console deve preferir verificação de domínio/DNS quando possível.
- Google Classroom é integração futura; Drupal LMS continua fonte de verdade de cursos, matrícula e progresso.
- Não solicitar scopes OAuth que não correspondam a uma feature ativa e aprovada.
- Produtos Google for Nonprofits pós-aprovação não devem ser tratados como disponíveis antes da ativação real.

## Política de domínio administrativo

- o painel administrativo Drupal é único e pertence ao purpose `main`;
- `/painel-administrativo/**` em subdomínio não representa um segundo painel: GET/HEAD deve canonicalizar para MAIN usando `DomainPurposeManager` e Domain Alias do ambiente;
- nunca hardcodar `aculta.org` ou hostname Homelab para essa canonicalização;
- requests administrativos mutáveis no host errado não são redirecionados entre Domains; falham fechado;
- wrong-purpose público continua 404 e não deve ser convertido em redirect genérico;
- preservar a exceção técnica do reset Core que reutiliza `entity.user.edit_form` em ACCOUNT com token one-time válido;
- ACULTA420/Twig/JavaScript não participam dessa política; ela pertence ao `aculta_portal` + Core/contrib access.
- redirects intencionais entre purposes usam `DomainPurposeManager` + `Drupal\Core\Routing\TrustedRedirectResponse`; não usar `Symfony RedirectResponse` cru para target em outro host;
- não depender de empate de prioridade entre response subscribers quando a ordem afeta redirect safety;
- sessão compartilhada entre purposes deve ser validada via `session.storage.options.cookie_domain` do ambiente; baseline `cookie_samesite: Lax`;
- AJAX/fetch do Portal é same-origin; não habilitar CORS para transportar estado/formulários entre purposes;
- carrinho/checkout/pagamento Drupal Commerce pertencem sempre ao purpose `main`, independentemente de origem SHOP/COURSES/SUPPORT; usar `DomainRoutePolicy`, nunca listas paralelas de rotas ou zona transacional por subdomínio;
- futura UI de carrinho na barra multidomínio deve usar presenter no Portal baseado em `CartProviderInterface`, preservando cache context `cart` e gerando `commerce_cart.page` no MAIN via `DomainPurposeManager`; não reutilizar o Cart Block cru se ele mantiver URL relativa ao host corrente; ACULTA420 só apresenta URL/contagem/estado neutros;
- centralizar a UI do carrinho em MAIN não proíbe o `Add to cart` nativo em SHOP/COURSES; esse formulário continua usando o Commerce para atualizar a mesma `commerce_order` antes da navegação ao carrinho MAIN;
- links de entrada no checkout devem apontar diretamente a MAIN quando o Portal puder resolvê-los; POST/PUT/PATCH/DELETE de payment/checkout em host errado não são redirecionados;
- referências canônicas: `docs/portal/ADMIN-DOMAIN-POLICY.md`, `docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md` e `docs/portal/PAYMENT-DOMAIN-POLICY.md`.

## Fronteira de autenticação e anti-bot

- ACULTA420 é dono somente da apresentação de login/formulários e de classes semânticas neutras;
- autenticação, Social Auth, disponibilidade de provider, destinos OAuth, callbacks complementares, account linking e política de CAPTCHA pertencem ao `aculta_portal`;
- Drupal Core/contrib mantém a implementação de protocolo, Social Auth, CAPTCHA e Turnstile;
- infraestrutura fornece credenciais via Drupal Key e environment; o tema não lê configuração de integração nem contém secrets;
- é proibido mover lógica OAuth para o tema, consultar configuração Social Auth em Twig/CSS/JS/PHP do tema, implementar CAPTCHA em Twig, adicionar dependências desses módulos ao tema ou colocar secrets nas settings do tema;
- integração nova segue: contrib/Core → Portal → contrato neutro de apresentação → ACULTA420.

## Tokens semânticos ACULTA420

- novos componentes usam semantic token existente para a função visual; não espalhar palette primitives para representar contexto;
- Light e dark são o mesmo ACULTA420; muda a luz, não a arquitetura.
- Color mode no ACULTA420 é uma variação de tokens, não uma variação de layout.
- modos compartilham DOM, markup, hierarquia, componentes, tipografia, espaçamento, dimensões, grid, breakpoints, posicionamento, shell, navegação e comportamento;
- somente semantic visual tokens variam; não criar seletores dark fora de `css/tokens.css`;
- validadores do tema devem reconhecer seletores dark alternativos inclusive dentro de pseudo-classes, operadores de atributo CSS que selecionem dark/light, flags `i`/`s` e regras em `tokens.css`; analisar recursivamente somente predicados de ternário Twig/JavaScript, labels PHP `switch` aninhados em ambas as sintaxes/`endswitch` e condições `match`, além de labels JavaScript `switch` e condições `if` balanceadas, sem tratar valores de resultado como branches;
- detectar writes simples/compostos de `dataset`, chamadas `setAttribute()` regulares/opcionais e atribuições `className` a classes de modo; switches aninhados não podem misturar labels de escopos distintos; comentários não devem causar falso positivo e mudanças de sintaxe exigem fixtures positivas e negativas;
- preservar o predicado externo de condições Twig com ternários, arms de objeto em ternários JavaScript, case labels encerrados por `;`, `?.`/`??` e regex literals durante o balanceamento; detectar `getColorScheme()`, classes compostas/protegidas e operadores de atributo que selecionem parcialmente `dark`/`light`; resolver a união de tokens ACULTA/Bootstrap em ambos os modos;
- o gate examina JavaScript inline em templates Twig, ignora regex literals em corpos de `switch`, reconhece `var()` CSS sem distinção de caixa e valida o RGB `--aculta-surface-page-rgb`; `tokens.css` contém somente os dois blocos de custom properties autorizados, sem regras CSS arbitrárias;
- examinar também style blocks Twig quanto a seletores e cores literais, inclusive propriedades `border-*-color`, e persistência color-mode nos scripts Twig inline; leituras/escritas de storage com chave de modo (métodos e propriedades), inicializadores explícitos, leituras `dataset`/`getAttribute`, mutações `classList` inclusive `replace`, `className` com strings/template literals e ternários com `;` dentro de strings devem ser detectados sem falsos positivos; scanners preservam delimitadores em strings/regex e comentários CSS só valem fora de strings; palavras que só aparecem em texto comum não são decisão de modo, statements CSS top-level em `tokens.css` falham;
- fora de `tokens.css`, o gate remove comentários CSS e rejeita hex, funções modernas de cor (`hwb`, `lab`, `lch`, `oklab`, `oklch`, `color()`, `color-mix()`, `device-cmyk()`) e nomes CSS em declarações color-bearing suportadas; não é parser completo de valores CSS e os fixtures definem esse escopo;
- validar mapeamentos Bootstrap de cor base e borda translúcida para fontes/valores aprovados separadamente em light/dark, além dos pares RGB e superfícies dark; alteração coordenada de cor e RGB não pode contornar o contrato; representações de cor numericamente equivalentes devem ser aceitas, slash-alpha moderno só aparece após os três canais, e sintaxe CSS legacy com vírgula não mistura alpha por barra;
- o contrato de superfície dark charcoal/graphite é explícito; resolver todos os tokens ACULTA/Bootstrap por modo, validar mappings Bootstrap light/dark e todos os pares RGB, rejeitar aliases/ciclos/referências ausentes/cores inválidas/duplicatas e calcular contraste com alpha composto;
- scripts PHP estáticos do tema devem aceitar roots absolutos POSIX e Windows (drive letter/UNC) sem traversal `..`, sem exigir Bash/WSL para executar;
- componente não conhece light, dark ou `prefers-color-scheme`; uma futura variante de asset de logo mantém espaço, dimensões e layout;
- não criar seletor, persistência ou JavaScript de modo antes da fase prevista;
- o tema nunca resolve Domain, hostname ou regra funcional; a apresentação por purpose chega por um único contrato `domain_presentation` preparado pelo Portal;
- a integração segue o Theme API moderno: módulo injeta em `#[Hook('preprocess_page')]`, tema consome depois em hook OOP; não criar novo `template_preprocess_*` legado;
- `domain_presentation` separa identidade escalar de renderables; valores para props permanecem simples e navigation/actions/brand media permanecem render arrays para futuros slots;
- Domain ID, hostname, aliases, `DomainInterface`, storage, negotiator e serviços do Portal não atravessam a fronteira para Twig/SDC;
- URLs, título, branding disponível, navegação e ações chegam já resolvidos; access/cache são acumulados no Portal via Render API/`CacheableMetadata` e devem borbulhar no render tree;
- o Portal nunca referencia `#component: aculta420:*`; apenas o tema escolhe SDC/Bootstrap e mapeia dados neutros para props/slots, evitando acoplamento inverso;
- menus devem usar Menu API/MenuLinkTree em vez de listas manuais quando aplicável, preservando access/cache;
- não espalhar `match ($purpose)` por hooks/controllers/templates nem criar contratos paralelos de shell;
- não criar SDC apenas para substituir uma classe/utilitário Bootstrap simples sem contrato reutilizável.

## Bootstrap Component Design System

- O tema `aculta420` implementa o **ACULTA420 Bootstrap Component Design System**.
- Bootstrap 5 é a infraestrutura estrutural/comportamental; SDC do Drupal Core é
  o mecanismo preferencial para componentes reutilizáveis.
- O `aculta_portal` prepara dados, access, cache, URLs e presenters; não move
  regra de negócio para SDC/Twig.
- Antes de criar markup/CSS custom do Portal, verificar Bootstrap + SDC já
  existentes.
- Não adotar `drupal/bootstrap_components`, UI Suite Bootstrap ou outra suíte
  concorrente sem nova decisão arquitetural.
- Não reiniciar a refatoração avançada do tema para adequá-la ao Portal.
- Consultar `docs/portal/COMPONENT-DESIGN-SYSTEM.md` e
  `web/themes/custom/aculta420/docs/design-system.md`.

## Regra de encerramento de fase

Toda fase ou subfase concluída deve terminar em **PR próprio**.

O PR deve:

- representar uma unidade lógica clara;
- listar entregas e limites;
- registrar `RUNTIME STATUS: DEFERRED` quando houver código não testado no Homelab;
- não misturar trabalho de outra fase;
- apontar a próxima fase;
- permanecer rastreável mesmo quando for integrado imediatamente.

Não considerar uma fase encerrada apenas porque existe commit local/branch.

## Coordenação Portal, tema e documentação

A evolução do Portal e a refatoração do tema são linhas separadas.

Para tarefas do `aculta_portal`:

- o modo atual é GitHub-first / Runtime-last; consultar `docs/portal/README.md` e `docs/operations/RELEASES.md`;
- `origin/main` é a base autoritativa; trabalho local antigo não publicado foi descartado;
- mudanças executáveis sem Runtime ficam em draft com `RUNTIME STATUS: DEFERRED`;
- quando o host for `toca` e o Drush confirmar `aculta_runtime` e um URI de teste, aplicar mudanças funcionais no Runtime de testes durante a implementação para smoke test contínuo; registrar como DEFERRED somente testes que realmente não foram executados nesse Runtime;
- não modificar `web/themes/custom/aculta420/**` sem autorização explícita;
- não recriar `web/themes/custom/aculta/` nem qualquer provider legado/alias do tema;
- consultar `docs/portal/` antes de implementar;
- manter as fontes de verdade definidas em `docs/portal/SOURCE-OF-TRUTH.md`;
- não instalar dependência planejada antes da versão correspondente;
- não reescrever roadmap/arquitetura por iniciativa própria;
- atualizar CHANGELOG e evidência de testes junto do código implementado;
- uma alteração lógica deve virar um commit atômico;
- documentação/preparação pode ser commitada sem Runtime;
- mudança funcional só é considerada concluída/mergeável/release após os testes adequados.

A documentação arquitetural e o roadmap são definidos fora da execução de
código. O Codex deve principalmente implementar, testar e registrar o resultado
da implementação.

O Portal usa tags `portal-vX.Y.Z`. Ver `docs/operations/RELEASES.md`.

## URLs e slugs públicos em português (obrigatório)

- Toda URL pública navegável de conteúdo ou de ação humana usa slug em português: minúsculas, palavras separadas por hífen, sem acentos e sem IDs numéricos quando existir título estável.
- Não usar inglês nem termos técnicos de módulo, classe ou plugin no slug público (por exemplo `course`, `group`, `node`, `user`, `lesson`, `activity`). Usar `curso`, `grupo`, `licao`, `atividade`, `entrar`, `minha-conta`.
- Rotas técnicas não são navegação: callbacks, endpoints AJAX, APIs e a administração do Drupal (`/admin`) ficam como estão. Qualquer outra exceção é registrada em `docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`.
- Slug publicado é estável. Mudança de slug troca o caminho, e o caminho antigo responde 404. Esse é o comportamento padrão do projeto para endereços antigos; não se cria redirecionamento 301 de slug.
- Diretiva detalhada em `docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`. Gate: `php scripts/validate-public-slugs.php`.

## Endereço único (regra permanente)

O site tem **um único endereço**, chamado "Endereço". Não há endereço de cobrança nem de envio separados, porque o
pagamento é feito no Mercado Pago. O endereço do cliente é o mesmo em Meus dados > Endereço, no checkout, na revisão,
na conclusão, no detalhe do pedido e nos recibos. Não criar perfil de cobrança ou de envio novo.

- Código: `web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane/BillingAddressPane.php` (pane "Endereço"
  do checkout; usa o perfil `customer` do cliente quando o pedido ainda não tem um).
- Mercado Pago recebe o payer a partir desse endereço (módulo contrib; gateway com `collect_billing_information: false`).
- Envio (Commerce Shipping) usa o mesmo endereço: o pane "Envio" (`AcultaShippingInformation`) não tem formulário de endereço
  próprio e reaproveita a cópia de cobrança. Número/S/N e Bairro existem só no pane "Endereço".
- Detalhes e motivo: `docs/portal/CHECKOUT-BILLING.md`.

## Endereços dependentes de ambiente (regra permanente)

Todo endereço que muda entre ambientes (domínio, subdomínio, URL absoluta, redirect entre hosts, `back_url` e webhook
de gateway, link em e-mail, origem CORS/trusted_host, callback OAuth) deve estar declarado no mapeamento do
aculta_deployer (`web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json`, seções `environments` e
`environment_bound`) e **nunca** fixo no código, nos templates, nos assets ou na configuração exportada.

- Como declarar e como obter o endereço no código: `web/modules/custom/aculta_portal/modules/aculta_deployer/docs/ENDERECOS-AMBIENTE.md`.
- Gate: `php scripts/validate-environment-addresses.php` falha se `aculta.org` ou `aculta.toca.net.br` aparecer fora do mapeamento.

## Testes interativos no navegador

Rodadas de teste com agente de navegador e o responsável (Felipe), incluindo hosts, contas de teste, limites do agente de navegador e resultados, estão em `docs/portal/TESTES-NAVEGADOR.md`. Consultar antes de pedir teste de navegador e atualizar a seção de resultados a cada rodada.

- O navegador padrão para testes visuais e interativos é o Firefox. Chromium é alternativa quando houver limitação concreta do Firefox.
- A sessão gráfica disponível nesta máquina foi confirmada como LXQt sobre X11 (`DISPLAY=:0`, `XDG_SESSION_ID=1`), embora shells de agentes possam abrir em TTY sem `DISPLAY`. Antes de concluir que não há interface gráfica, inspecionar a sessão de `lxqt-session` (`pgrep -a lxqt-session` e `/proc/<PID>/environ`) e usar o ambiente dela: `DISPLAY=:0`, `XDG_RUNTIME_DIR=/run/user/1000` e `DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus`. Confirmar os valores atuais no processo; não presumir Wayland nem iniciar outro desktop.
- O socket X11 observado foi `/tmp/.X11-unix/X0`. Orca e AT-SPI estão disponíveis para revisão de acessibilidade. Não encerrar processos da sessão LXQt; encerrar somente processos de navegador, driver ou leitor de tela iniciados pela própria tarefa.

## Versionamento obrigatório de mudanças

O versionamento controla as mudanças e os avanços no código. Não criar tags no GitHub sem pedido explícito do responsável.

Antes de codar qualquer mudança no tema ou no Portal:

1. classificar o tipo (PATCH, MINOR ou MAJOR) segundo `web/themes/custom/aculta420/docs/versioning.md`, tabela "Classificar antes de codar";
2. anotar a versão alvo: commits usam `<linha>-dev.N`; o merge usa a próxima `<linha>.X` da sequência, marcada por commit de release antes do merge (`web/themes/custom/aculta420/docs/versioning.md`);
3. ao validar uma mudança principal, marcar a versão no código: `aculta420.info.yml`, `CHANGELOG.md` (com SHA e status de validação), `docs/roadmap.md` e a asserção do gate de Foundation;
4. não marcar versão de estado não validado;
5. mudança no `aculta_portal` marca a versão em `aculta_portal.info.yml` e entra no `CHANGELOG.md` do módulo;
6. os marcadores de release (info, changelog, roadmap e gate) devem ficar coerentes no mesmo PR;
7. criar tag Git somente quando o responsável pedir explicitamente.

A exceção de versão já registrada (0.3.1) não autoriza nova exceção sem decisão do responsável.

## Forma de trabalho esperada do Codex

### Diretório de trabalho obrigatório

- Trabalhar sempre no checkout local do repositório que o responsável abriu para a tarefa (`cwd` do projeto), mantendo o branch atual e as alterações visíveis.
- Não criar nem usar worktrees, clones ou diretórios temporários alternativos (por exemplo, `/tmp/...`) para implementar a tarefa, salvo pedido explícito do responsável.
- Antes de alterar arquivos, confirmar `pwd`, branch e `git status` nesse checkout. Não trocar de branch nem substituir arquivos com alterações locais; preservar trabalho existente e resolver conflitos com segurança.
- Quando a tarefa exigir interação com o servidor de testes, usar os meios de acesso autorizados a esse servidor; o checkout local ou um banco local não substitui a validação no servidor.

Antes de uma alteração relevante:

1. analisar os arquivos relacionados;
2. verificar a arquitetura existente;
3. explicar resumidamente o que será alterado;
4. implementar somente o necessário;
5. verificar erros;
6. informar os arquivos modificados;
7. indicar comandos Drupal/Drush necessários após a alteração.

Não refatorar partes não relacionadas à tarefa sem necessidade.

Não criar funcionalidades que não foram solicitadas.

Quando houver dúvida arquitetural importante, perguntar antes de fazer uma alteração destrutiva.

## Papéis e hierarquia (regra permanente)

Motivo: o site tem uma hierarquia de papéis decidida pelo responsável. Quando o acesso é checado pelo nome do papel ou a
herança é ignorada, um gerente perde acesso que deveria ter e um papel ganha acesso que não deveria.

- Cadeia vigente (de baixo para cima, herança transitiva): `authenticated` < `email_confirmed` < `estudante` < `associado`
  < `lms_teacher` < `content_editor` < `gerente_loja` < `administrator`. `wiki_contributor` e `lms_admin` herdam de
  `authenticated`. Fonte: `config/sync/role_inheritance.settings.yml`; decisões em `docs/portal/PAPEIS-PARTICIPACAO.md`.
- Não crie hierarquia paralela no código nem lista de papéis fixa para decidir acesso. A herança é do `role_inheritance`.
- Para acesso novo, conceda **permissão** ao papel certo e verifique com `hasPermission()`. Não use `hasRole('nome')` nem
  `in_array('nome', getRoles())` para liberar funcionalidade: isso ignora a hierarquia de quem não está logado e cria
  exceção difícil de auditar. Checagens existentes por nome são dívida registrada no gate.
- Para mudar a cadeia, use ACULTA > Configurações > Hierarquia de papéis (`/painel-administrativo/configuracoes/aculta/papeis`,
  permissão `administer aculta role hierarchy`). Não edite `role_inheritance.settings` à mão no Runtime. Depois de mudar a
  cadeia, exporte a configuração e atualize a decisão em `docs/portal/PAPEIS-PARTICIPACAO.md` no mesmo commit.
- Ao mudar a cadeia, avalie o efeito: papel acima herda todas as permissões de baixo (ex.: `gerente_loja` herda o que é
  de `content_editor`). Confirme com `php scripts/validate-role-hierarchy.php` e com a tela do painel.
- Gate obrigatório: `php scripts/validate-role-hierarchy.php` (cadeia decidida, sem ciclo, administrator herda de todos,
  e contagem de checagens por nome de papel sem subir acima da linha de base).

## Indexação e sitemap (política do projeto)

### Política de indexação (decisão do responsável, 2026-10-09)

- **Produção indexável:** todos os domínios e subdomínios de produção (`aculta.org`,
  `conta.`, `apoio.`, `revista420.`, `wiki420.`, `loja.`, `cursos.`) não enviam `X-Robots-Tag`
  com `noindex`.
- **Servidor de testes não indexável:** `*.aculta.toca.net.br` envia `noindex, nofollow, noarchive`.
- **Páginas privadas da conta** continuam com `noindex` em nível de rota (meta `robots` do
  `aculta_portal`). Essa proteção não é removida pelo deploy.
- **Quem garante:** o `aculta_deployer` (`robots --env=production|test`, `build` com trava de
  política). Verificação pós-deploy por host; resultado FAIL bloqueia a publicação.
- Correção registrada: DEP-0003 (VirtualHost de teste com noindex) permanece bloqueante até o
  `robots --env=production` passar em todos os hosts.
- **Produção (decisão do responsável):** a entrega em produção não é requisito de fase, PR, release, RC nem pendência do agente. O responsável decide quando o projeto vai para produção, no fim de todo o projeto. Merge em `main` atualiza o código do repositório e não publica nada no Hostinger. As verificações de produção (`robots --env=production`, `sitemap --env=production`) são ensaios, não ações de deploy.
- **Divergência entre ambientes passa pelo `aculta_deployer`.** Qualquer diferença de host,
  `base_url` do `simple_sitemap`, `robots.txt`, diretiva `Sitemap:`, sitemap ou noindex entre o
  servidor de testes e a produção deve ser declarada em `config/deploy.json` e verificada por um
  comando da ferramenta (`sitemap`, `robots`, `verify`). Não corrija por edição manual sem
  verificação. Se a ferramenta ainda não verifica um caso, registre-o no roadmap do deployer
  (`web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md`) e no
  `registry/deploy-registry.json`, em vez de deixar a divergência só na documentação.
- O `aculta_deployer` é um complemento opcional do `aculta_portal` (submódulo, dependência do próprio módulo, desabilitado por padrão). O Portal não depende dele. Ele publica `var/deployer/status.json` e o painel do Portal lê esse arquivo; o Portal não executa a CLI.
- O `aculta_deployer` está em desenvolvimento (0.1.x). O roadmap dele é `docs/PLANEJAMENTO.md`.
  Mudanças de descoberta e sitemap por ambiente pertencem à fase 7 desse roadmap.

## Código no Aculta Portal, Design no ACULTA420 (regra permanente)

> **Código no Aculta Portal, Design no ACULTA420.**

- **Aculta Portal** (`web/modules/custom/aculta_portal/**`) = funções: rotas, controllers, forms, `#ajax`, validação
  (Número/S/N, CEP), endpoints, serviços, access, dados, cache, checkout panes, Commerce/Shipping, JS de
  **comportamento** (usa `data-portal-*` e `data-aculta-*`, nunca classes de design) e traduções.
- **ACULTA420** (`web/themes/custom/aculta420/**`) = design: templates Twig de apresentação, CSS, layout, cores,
  larguras, ícones, animações, estilo de mensagens e caixas, e preprocess de apresentação.
- O módulo **não** carrega CSS, **não** emite classes de design (`btn`, `alert`, `col-`, `bi-`, `portal-account__*`…)
  e **não** referencia o tema. Ele entrega **dados** ao tema (listas, links, estado, textos) e continua funcionando
  sem o ACULTA420 (com marcação neutra de fallback, sem aparência).
- O tema **não** tem rota, `form_alter`, serviço, `Drupal::`, nem classe ou biblioteca do módulo.
- **Contrato `data-portal-ui`:** o módulo marca elementos com tokens semânticos (`data-portal-ui="account-avatar"`); o
  ACULTA420 traduz em classes em `src/Hook/PortalUiHooks.php`. Novo token = entrada no mapa do tema. O gate confere.
- **Implementações no Status:** todo helper reutilizável novo recebe a tag `aculta_portal.helper` e registro em
  `PortalHelperRegistry`; toda integração runtime aplicada recebe `aculta_portal.implementation` e registro em
  `PortalImplementationRegistry`. Ambos precisam de resumo e documentação vigente, ser exibidos em ACULTA Portal >
  Status e passar pelo gate `php scripts/validate-aculta-portal-drupal11.php`. Ver `docs/portal/IMPLEMENTATIONS.md`.
- **Autorização para agentes:** ao implementar uma função no Aculta Portal, o agente pode adicionar ou corrigir o
  ACULTA420 na mesma entrega, cada parte no seu dono.
- Inventário, dívida e motivo: `docs/portal/FRONTEIRA-MODULO-TEMA.md`. Gate obrigatório:
  `php scripts/validate-module-theme-boundary.php` (deve ser PASS; o cliquet de classes do módulo não pode subir).
- **Mapa módulo x tema (obrigatório):** todo módulo ou submódulo customizado e todo módulo contrib habilitado precisa de
  registro em `docs/portal/MAPA-MODULO-TEMA.md`, com os templates, CSS, componentes SDC, JS e tokens `data-portal-ui`
  do tema que lhe correspondem, ou com o motivo de não haver contraparte. Peça nova de tema para um módulo
  (template, CSS, JS, SDC ou token) entra no mapa no mesmo commit. Gate obrigatório:
  `php scripts/validate-module-theme-map.php` (deve ser PASS).
- **Busca antes de alterar módulo ou contrib:** o agente deve buscar as contrapartes de template, CSS, componentes e
  tokens do tema, nesta ordem: (1) a linha correspondente em `docs/portal/MAPA-MODULO-TEMA.md`; (2) a documentação do
  tema (`web/themes/custom/aculta420/docs/`) e `docs/portal/TOKENS-E-ATRIBUTOS.md`; (3) o código do tema (`templates/`,
  `css/`, `components/`, `js/`, `src/Hook/PortalUiHooks.php`); (4) na web, a documentação oficial do Drupal e do contrib
  na versão instalada, quando o contrato não estiver claro. Registro "Verificar" ou "Sem contraparte" não autoriza
  suposição. Se a busca não confirmar o contrato, o agente diz isso e pergunta antes de editar.

## Pagamentos: processados pelo Mercado Pago (regra permanente)

- Todo pagamento é processado pelo **Mercado Pago**. O portal **não guarda** nem **exibe** meios de pagamento salvos
  nem histórico de pagamentos da pessoa. Não existe "Meus pagamentos" na Minha conta, e `/pagamentos` redireciona
  (301) para `/pedidos`; as rotas de cadastro, edição, exclusão e padrão de meios de pagamento respondem 404.
- O papel autenticado não recebe a permissão `manage own commerce_payment_method`.
- Em "Meus pedidos" aparece a caixa "Todos os nossos pagamentos são processados pelo Mercado Pago" com link para a conta
  do Mercado Pago (URL em `aculta_portal.settings:mercado_pago_account_url`, abre em nova aba com `rel="noopener"`).
- Não reintroduzir cartões, métodos salvos ou extrato de pagamento na conta. Detalhes: `docs/portal/CHECKOUT-BILLING.md`.
- Fora desta regra (continuam): o apoio (Meu Apoio e contribuições), a etapa de pagamento do checkout e o webhook do
  gateway. Gate: `php scripts/validate-module-theme-boundary.php`, seção 13.

## Ícones de interface: Bootstrap Icons (regra permanente)

- O padrão de ícones do ACULTA420 são os **Bootstrap Icons** (`<i class="bi bi-…">`, já carregados pelo bootstrap5).
- Não usar outros pacotes de ícones nem SVG solto (inline ou arquivo) para ícone de interface.
- **Exceção:** logos de marcas de terceiros (ex.: Mercado Pago), em `web/themes/custom/aculta420/assets/brands/`, com `alt` do nome da marca.
- Gate: `php scripts/validate-module-theme-boundary.php`, seção 12 (sem `<svg` inline e sem SVG fora de `assets/brands/`).

## Política de commits para agentes de IA (regra permanente)

Motivo: trabalho que ficou só no working tree, em stash ou em branch sem PR gerou
duplicidade, código obsoleto e uma base de PR errada (PRs mesclados em branches antigos,
e não na `main`). Esta regra existe para que nenhum trabalho dependa de estado local.

- **Commit antes de encerrar.** Toda alteração concluída vira commit no próprio branch
  antes do fim da sessão. Não deixar trabalho só no working tree.
- **Um commit por tarefa.** O agente trabalha no branch de trabalho já aberto pelo responsável
  e registra a tarefa em um commit atômico, sem criar ou trocar de branch por causa da tarefa.
  Se a tarefa for ampliada e passar a conter entregas independentes, separar em commits atômicos
  por tarefa antes de encerrar.
- **Publicar cada commit** no branch atual com `git push` após concluir a tarefa. Não há commit
  "pendente" depois que a sessão termina.
- **Merge só no final.** O PR é mesclado uma única vez, quando a tarefa estiver concluída e o
  responsável autorizar. Não mesclar entregas parciais nem PRs intermediários.
- **Stash não é entrega.** Não guardar trabalho em `git stash`. Stash temporário só dentro
  de um comando, e nunca como destino final.
- **Diretório de trabalho:** usar o checkout local aberto pelo responsável, conforme a seção “Diretório de trabalho obrigatório”. Worktree ou clone temporário só pode ser usado a pedido explícito do responsável.
- **Verificar a base do PR antes de mesclar.** `gh pr view <n> --json baseRefName` deve
  apontar para `main` (ou para o branch base declarado). Trocar a base antes da fusão, e
  confirmar depois com `git merge-base --is-ancestor <commit> origin/main`.
- **Mesclar só com autorização explícita** do responsável. Commit e push não autorizam fusão.
- **Não commitar** credenciais, `settings.local.php`, backups locais, `.claude/settings.local.json`
  nem conteúdo de `secrets/`. Verificar o conteúdo staged antes do commit.
- **Não reescrever histórico** de branch compartilhado (sem force push).
- **Mensagem de commit** em português, com o trailer `Co-Authored-By` do agente.
- **Encerramento:** antes de encerrar a sessão, informar o estado de `git status`, o
  branch e o commit de cada entrega. Se algo ficou fora do git, dizer o motivo.
