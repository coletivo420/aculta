# Regras específicas — consulta sob demanda

## Contrato de rota vigente após migração da raiz

A raiz pública `/` no purpose ACCOUNT apresenta
`aculta_studio.hub` (caminho técnico `/studio`, 404 direto).
A Minha Conta original está em **`/conta-legada` de forma
permanente**. Nunca restabelecer o legado na raiz, criar alias
temporário nem retirar o shell do Portal.

Experiências próprias: `/studio/conta` e `/studio/perfil`,
com Portal dono de cadastro/endereço/documentos/segurança e User
dono de identidade pública. `/studio/conta/documentos` só exibe
link se `aculta_id_cpf.account_document` estiver disponível.
Não duplicar CPF/CNPJ, nem incluir esse dado em Meu Perfil.
Comparar comportamento das duas interfaces antes de qualquer aceite.


## Decisão atual: experiências Conta/Perfil independentes

Não reintroduzir a experiência `pessoal`. Usar somente
`/studio/conta` e `/studio/perfil` como experiências separadas;
perfil público em `/u/{apelido}` é independente. O legado da Conta
permanece na raiz `/` de ACCOUNT **mesmo após a homologação**, com
`/minha-conta` técnico 404. Não fazer redirects temporários, não
retirar `AccountShellBuilder`, não misturar Form API cadastral com
editor público nem duplicar User/Profile.

[Contrato atual](../../../../../../docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
As regras históricas abaixo devem ser lidas à luz desta decisão.


## WikiTab availability and access (Studio dev.5)

The tab checks both the owner module `aculta_wiki` and its `wiki_entry` bundle.
Do not infer owner availability from retained configuration alone. The Unit
regression covers module disabled with bundle still present, both available,
and bundle absent.
`create wiki_entry content` alone is not a GanjaLab permission: contributor
authorship belongs in WE SATIVA. GanjaLab entry requires `edit any
wiki_entry content`, `view wiki_entry revisions`, or `moderate wiki proposals`;
the shared entry inventory requires `edit any`. The focal test passed 7/27.

## WE SATIVA — entrypoint e workspace existentes (Studio dev.3 / Wiki dev.76)

`/studio/we-sativa` pertence ao purpose `account` e usa o provider Studio;
`aculta_wiki.contributor_workspace` é dono dos dados, grants, paginação e links
Wiki. Meu Trabalho separa cursores para verbetes/propostas, usa SDCs e revalida
Entity Access. Criar/editar/contribuir/acompanhar continuam em rotas e Form API
do módulo Wiki, com link de retorno cacheável dos formulários ao Meu Trabalho
quando Studio e acesso estiverem disponíveis. A classe Kernel integrada passou
em Wiki dev.77 (23/1.039 para o ciclo Wiki, incluindo o workspace); Firefox/X11,
320 px, Olivero e Runtime seguem sem homologação.

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## ACULTA Studio (regra permanente)

### WE SATIVA start page (dev.6)

The `/studio/we-sativa/inicio` controller delegates to the Wiki owner. Unit
coverage verifies a provider with the optional start-page contract, a provider
without it, and owner-denied access (3/3 focal; Studio Unit suite 20/61). Kernel
Wiki integration verifies the route, access-conditioned links and rendered
ACULTA420 HTML (2/208). Runtime and
Firefox/X11 remain pending because the active Runtime container lacks the
workspace service and WE SATIVA routes.

- O Studio (`aculta_studio`, `/studio` no domínio conta) é a ferramenta transversal de trabalho por abas. Agrega
  ações dos donos e não é dono de conteúdo, permissões, discussões, galerias nem tabelas próprias equivalentes.
- Cada aba é um plugin `#[StudioTab]` com três contratos: `isAvailable()` (o dono está habilitado), `access()` (a
  permissão real do dono, nunca nome de papel) e `actions()` (ação com o acesso da rota do dono). Ação para outro
  domínio usa `DomainPurposeManager::routeUrl()` e avalia o acesso com `runInPurpose()`.
- Nenhuma rota ou ação do Studio contorna Form API, Entity Access, Content Moderation nem as permissões do dono.
- **Rotas canônicas, sem aliases:** `/studio` é o hub geral; `/studio/marley` é a entrada editorial; as áreas e seções de Marley usam `/studio/marley/{aba}`, `/studio/marley/{aba}/{secao}` e `/studio/marley/{aba}/{secao}/{sub}`. Ganjalab, WE SATIVA e pessoal mantêm seus prefixes canônicos. Não criar `/studio/{aba}` genérico nem redirects temporários de compatibilidade; URLs aposentadas respondem 404.
  Cada aba principal, opção de submenu e aba interna tem URL própria; o AJAX melhora a troca, mas não é a única forma de
  acesso. Segmento fora do alcance da conta responde 404. Os níveis são: áreas em abas superiores (uma por dono);
  submenu à esquerda com as seções da área; abas internas da seção, com as ações no painel. Cada área implementa `sections()` com esses níveis. Apresentação: tokens `data-portal-ui`
  (`studio-*`) e atributos `data-aculta-studio*` de comportamento; classes e aparência ficam no ACULTA420.
- O Studio tem página, shell, JavaScript (`js/studio-navigation.js`) e hooks próprios. Não usa o shell, o menu ou
  o `account-navigation.js` da Minha conta, e não aparece no menu da conta.
- Melhoria contínua: toda frente que criar função com potencial de aba ou ação deve investigar o Studio antes de
  concluir, propor ou implementar a aba dentro desse contrato, e atualizar a matriz em
  `docs/portal/STUDIO-MAGAZINE-ROADMAP.md`, o README do `aculta_studio` e o status do componente.
- Acessibilidade e recuo: troca de nível com foco no título, região aria-live para carregamento e erros, voltar e
  avançar do navegador, e links utilizáveis sem JavaScript. Ações de edição, aprovação, exclusão e publicação mantêm as
  verificações de acesso, CSRF e confirmação do Drupal.
- Guardrails: não adicionar aba sem permissão real e sem teste de acesso; não expor dados de terceiros; não copiar
  tabela ou fluxo de permissão do dono; não ampliar o acesso de uma aba para contornar o dono; não mover ou apagar
  dados por ação do Studio sem a operação do dono.
- Antes de encerrar uma mudança no Studio: `php scripts/validate-module-theme-boundary.php`,
  `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-project-status-index.php` e a suíte
  `tests/browser/aculta-studio/visual.mjs`, com o resultado registrado.
