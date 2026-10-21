# Auditoria de portabilidade — LinkStack

## Referência reproduzível

- Repositório: [LinkStackOrg/LinkStack](https://github.com/LinkStackOrg/LinkStack).
- Referência inicial auditada: tag `v4.8.0`, commit `ae6837e8c0cc2cd0e77f26952ceca97d0eebec35`.
- Release mais recente consultada em 2026-10-20: tag `v4.8.6`, commit
  `dca586e8b8946a779cb212d21791cff6308fa7c0`. A auditoria arquivo a arquivo
  dessa release ainda está pendente.
- Árvore consultada: controllers Laravel, `resources/views`, CSS/JS,
  `themes` e `blocks` na tag.
- `composer.json` declara `GPL-3.0-or-later`; o `LICENSE` dessa mesma tag contém
  AGPL-3.0. O README versionado também declara que o projeto está sob AGPL-3.0
  desde a versão 4.0.0 e exige que obras derivadas sigam seus termos. A decisão
  conservadora é classificar o código original como AGPL-3.0; o campo Composer é
  metadata divergente, ainda sem correção upstream.
- O GitHub Advisory Database lista `GHSA-mg7g-g2wv-6mhr` para LinkStack até
  `v4.8.6`: possível bypass de autorização em `UserController::saveLink`. Não
  portar o fluxo nem sua autorização; usar Entity Access e ownership Drupal.
- A árvore da tag contém `app/Models/{Page,Button,Link}.php`,
  `app/Http/Controllers/{Home,User}Controller.php`, rotas Laravel,
  `resources/views/studio/*`, `resources/views/panel/*`,
  `resources/views/linkstack/*`, `themes/*` e `blocks/*`. A amostragem identifica
  recursos, mas não substitui auditoria de licença por arquivo.

## Componentes avaliados

| Componente | Decisão | Destino previsto |
| --- | --- | --- |
| Dashboard/listagem de páginas | PORTAR COMPORTAMENTO | Drupal Forms, Views, rotas e access checks, sem copiar implementação Laravel. |
| Editor de links e itens | REIMPLEMENTAR | Form API/AJAX com fallback, ownership Drupal e revisão definida. |
| Ordenação/ativação | REIMPLEMENTAR | Entity/Form API; JS mínimo e interação acessível por teclado. |
| Preview público | PORTAR COMPORTAMENTO | SDC/Twig via ACULTA420, dados fornecidos pelo módulo. |
| Temas/presets | REIMPLEMENTAR | Presets governados por tokens; não executar tema arbitrário. |
| Blocks/extensões de terceiros | ADIAR | Sem contrato de extensão executável nesta fase. |
| Estatísticas/cliques | REIMPLEMENTAR | Queue/Cron e armazenamento minimizado, após análise LGPD. |
| QR Code | REIMPLEMENTAR | Endroid QR Code 6.1.3 gera SVG da URL canônica da página publicada; código LinkStack e serviço remoto descartados. |
| Slugs/redirect curto | REIMPLEMENTAR | APIs Drupal, política de slugs e DomainPurposeManager; proteger open redirect. |
| Autenticação, instalação, usuários Laravel | DESCARTAR | Drupal User e `aculta_user`. |
| Backup, editor de ambiente, updater, jobs/config Laravel | DESCARTAR | Operação/deploy e Configuration API Drupal. |
| Blade/controllers/Eloquent/rotas Laravel | REIMPLEMENTAR comportamento | Twig/SDC, Controllers, Entity API/Storage e Routing Drupal; sem trechos derivados. |
| CSS/JS/assets | DESCARTAR | Recriar apresentação em ACULTA420 a partir dos contratos e necessidades funcionais, sem reutilizar expressão original. |

## Auditoria funcional por arquivo da tag v4.8.6

Referência de todas as linhas abaixo: commit `dca586e8b8946a779cb212d21791cff6308fa7c0`.
O código da aplicação herda AGPL-3.0 pelo `LICENSE`/README do tag; a metadata
Composer diverge e declara `GPL-3.0-or-later`. As decisões abaixo são de
portabilidade funcional e não autorizam copiar arquivos. Classes de usuário,
autenticação, instalação e administração de contas são deliberadamente
excluídas do escopo de ACULTA.

| Arquivo upstream | Responsabilidade observada | Decisão e destino ACULTA |
| --- | --- | --- |
| `app/Http/Controllers/HomeController.php` | Landing e demonstração pública | PORTAR comportamento da landing somente onde útil; Drupal route/controller e conteúdo editorial existente. |
| `app/Http/Controllers/UserController.php` | Dashboard, links, ordenação, contagem, páginas, tema, perfil, import/export | PORTAR por operações isoladas; Entity API/Form API e `aculta_user`. Não portar `saveLink` (advisory GHSA-mg7g-g2wv-6mhr), autorização Laravel ou o arquivo monolítico. |
| `app/Http/Controllers/AdminController.php` | Usuários, configurações, arquivos `.env`, backup, phpinfo, temas e updater | DESCARTAR; administração global Drupal/deployer. Usuários, `.env`, phpinfo, backups e updater não pertencem ao Links. |
| `app/Http/Controllers/Admin/LinkTypeController.php` | CRUD de tipos de links/blocos | ADIAR; avaliar plugin Drupal depois de definir contrato e segurança de extensões. |
| `app/Http/Controllers/LinkTypeViewController.php` | Renderização e formulários dinâmicos de tipos/blocos | PORTAR conceitos como plugins/Sdc após o contrato; sem handler Blade ou execução de extensões de terceiros. |
| `app/Models/Page.php` | Página/proprietário/aparência | PORTAR responsabilidade para `aculta_link_page`; não copiar Eloquent. |
| `app/Models/Button.php` | Item ordenado e estado do botão | PORTAR responsabilidade para `aculta_link_item`; não copiar Eloquent. |
| `app/Models/Link.php` | Destino reutilizável | PORTAR responsabilidade para `aculta_link`; revalidar segurança e acesso Drupal. |
| `app/Models/LinkType.php` | Tipo/extensão de botão | ADIAR como plugin Drupal tipado, sem código PHP arbitrário. |
| `app/Models/User.php`, `app/Models/Admin.php`, `app/Models/Register.php`, `app/Models/SocialAccount.php`, `app/Models/UserData.php` | Conta, privilégios, cadastro e identidade LinkStack | DESCARTAR; Drupal User/`aculta_user`, sem autenticação paralela. |
| `app/View/Components/PageItemDisplay.php` | Apresentação de item público | PORTAR separação de dados e apresentação para presenter Drupal + ACULTA420 SDC. |
| `routes/home.php` | Raiz, página inicial e rota de perfil | PORTAR somente a ideia de seleção de landing; DomainPurposeManager e rotas ACULTA existentes. |
| `routes/web.php` | Rotas públicas, painel, CRUD e administração | PORTAR contratos selecionados para Routing Drupal; não copiar middleware, padrões de mutação ou endpoints de instalação. |
| `routes/auth.php`, `routes/api.php`, `routes/channels.php`, `routes/console.php` | Login, API/estado, canais e comandos | DESCARTAR; não existe segunda autenticação/API/console de LinkStack em ACULTA Links. |
| `resources/views/studio/add-link.blade.php` | Criar botão | PORTAR fluxo para Form API e referência Drupal; sem Blade. |
| `resources/views/studio/edit-link.blade.php` | Editar botão/destino | PORTAR fluxo para entidades e revisões Drupal. |
| `resources/views/studio/links.blade.php` | Listar e ordenar links | PORTAR UX para dashboard Drupal, tabledrag acessível e fallback de formulário. |
| `resources/views/studio/button-editor.blade.php` | Controles do botão | PORTAR campos seguros para Form API; sem HTML/estilos arbitrários. |
| `resources/views/studio/page.blade.php` | Editar título, slug e conteúdo da página | PORTAR para formulário de `aculta_link_page` e editor coeso; campos Core permanecem fonte de dados. |
| `resources/views/studio/theme.blade.php` | Escolher aparência | PORTAR seleção governada; ACULTA420 tokens/SDC em vez de instalar tema PHP. |
| `resources/views/studio/profile.blade.php` | Perfil de usuário | DESCARTAR; perfil é `aculta_user`. A página pode consumir apenas identidade pública opt-in. |
| `resources/views/studio/theme-updater.blade.php` | Instalar/atualizar tema | DESCARTAR; código executável e updater fora do produto. |
| `resources/views/linkstack/linkstack.blade.php` | Composição da página pública | PORTAR composição visual para SDC/Twig com dados preparados pelo módulo. |
| `resources/views/linkstack/layout.blade.php` | Layout público | REIMPLEMENTAR nos contratos de ACULTA420. |
| `resources/views/linkstack/elements/avatar.blade.php` | Avatar | DESCARTAR como asset/template; qualquer avatar futuro passa por `aculta_image` e política explícita de perfil. |
| `resources/views/linkstack/elements/bio.blade.php` | Biografia | DESCARTAR herança automática; não expor bio de `aculta_user` sem decisão e consentimento. |
| `resources/views/linkstack/elements/buttons.blade.php` | Botões públicos | PORTAR comportamento ao SDC de micropágina; sem HTML copiado. |
| `resources/views/linkstack/elements/heading.blade.php`, `icons.blade.php` | Cabeçalho e ícones | REIMPLEMENTAR com tokens, Bootstrap/SDC e catálogo de ícones do Portal. |
| `resources/views/linkstack/modules/admin-bar.blade.php`, `report-icon.blade.php` | Barra do proprietário e denúncia | DESCARTAR a barra Laravel; denúncia aguarda moderação ACULTA aprovada. |
| `resources/views/linkstack/modules/assets.blade.php`, `block-libraries.blade.php`, `theme.blade.php` | Carregamento de assets/temas/blocos | DESCARTAR injeção dinâmica e código arbitrário; Drupal Libraries/SDC são controlados pelo projeto. |
| `resources/views/linkstack/modules/dynamic-contrast.blade.php` | Ajuste dinâmico de contraste | DESCARTAR; usar tokens e contraste testado no ACULTA420. |
| `resources/views/linkstack/modules/meta.blade.php` | Metadados SEO | PORTAR responsabilidade a Metatag e canonical Domain já integrados. |
| `resources/views/linkstack/modules/footer.blade.php`, `share-button.blade.php` | Rodapé e compartilhamento | PORTAR somente comportamento quando UX/acessibilidade ACULTA estiver definida. |
| `resources/views/components/pageitems/link-display.blade.php` | Item de link | PORTAR para SDC seguro. |
| `resources/views/components/pageitems/{email,telephone,text,spacer,predefined}-display.blade.php` | Blocos de conteúdo | ADIAR; cada bloco exige tipo explícito e revisão de exposição/validação. |
| `resources/views/components/pageitems/{video,vcard}-display.blade.php`, `video-form.blade.php`, `predefined-form.blade.php` | Incorporação, vCard e formulários de blocos | ADIAR; validar conteúdo externo, SSRF, PII, acessibilidade e asset antes da implementação. |
| `resources/views/panel/index.blade.php`, `links.blade.php`, `pages.blade.php` | Resumo e filas administrativas | PORTAR necessidades para Views/admin Drupal, preservando handlers de Entity Access. |
| `resources/views/panel/theme.blade.php`, `site.blade.php` | Gestão global de tema/site | DESCARTAR instalação/configuração arbitrária; usar config Drupal governada. |
| `resources/views/panel/users.blade.php`, `edit-user.blade.php` | Administração de contas | DESCARTAR; Drupal User/admin existente. |
| `resources/views/panel/config-editor.blade.php`, `backups.blade.php`, `phpinfo.blade.php` | Editor de ambiente, backups e diagnóstico | DESCARTAR; segredos/deploy/operação permanecem em seus contratos ACULTA. |
| `resources/views/admin/linktype/{create,edit,index}.blade.php` | Administração de tipos de link | ADIAR junto do plugin de tipos; Forms/Views se aprovado. |
| `resources/views/auth/*.blade.php`, `installer/installer.blade.php`, `update.blade.php`, `backup.blade.php`, `maintenance.blade.php` | Autenticação, instalação e operação | DESCARTAR integralmente. |
| `resources/views/components/*.blade.php`, exceto `pageitems/*` acima | Widgets Blade de formulário, navegação, modal, flash, alertas e configuração | Não copiar; reconstruir com Form API/Claro e componentes existentes; widgets de conta/config/backup permanecem fora do Links. |
| `resources/views/layouts/*.blade.php` | Layouts Laravel para app, guest, idioma, fonts, analytics e admin | DESCARTAR; Twig/ACULTA420/Core, Metatag e política de telemetria do Portal. |
| `resources/views/vendor/env-editor/**`, `resources/views/vendor/self-update/**` | Templates de dependências Laravel para `.env` e updater | DESCARTAR; não são dependências de Drupal. |
| `resources/css/app.css`, `resources/js/app.js` | Aplicação Laravel e interações compiladas | DESCARTAR; recriar apenas comportamentos necessários com CSS/JS mínimo e bibliotecas Drupal. |
| `resources/css/bootstrap.min.css`, `resources/js/bootstrap.js` | Bootstrap empacotado | DESCARTAR; ACULTA420 já fornece seu Bootstrap, evitar segunda cópia. |
| `themes/PolySleek/**` (55 arquivos, incluindo templates, CSS, fontes, ícones e scripts) | Tema público externo | DESCARTAR arquivos; README local anuncia MIT para o tema e cita assets Skeleton/CodePen MIT, mas cada arte e asset continua sujeito a auditoria própria. Recriar aparência em ACULTA420 sem copiar. |
| `themes/galaxy/**` (15 arquivos) | Tema público externo | DESCARTAR arquivos; README local declara GPLv3 e atribui ativos Skeleton e projeto Linktree; não copiar nem assumir uma licença única para os ativos citados. |
| `blocks/{email,heading,link,spacer,telephone,text,vcard}/**` | Extensões Blade de blocos públicos | ADIAR; plugins Drupal somente após contrato de tipos, access, sanitização, licença individual e ameaça de execução. |
| `SECURITY.md` | Processo de reporte de falhas | CONSULTAR como referência; política ACULTA continua sendo a fonte normativa. |

### Conclusão do inventário

O inventário acima classifica os arquivos de runtime que correspondem aos
fluxos de micropágina, links, dashboard, painel, tema e blocks na árvore da tag.
Não houve cópia. A auditoria de licença individual e proveniência dos 70 arquivos
de temas/ativos e assets compilados ainda não foi concluída; por isso todos esses
arquivos ficam fora da implementação mesmo quando o README do tema declara MIT.
O código de referência relevante para comportamento pode continuar guiando
reimplementações Drupal, mas a autorização de cópia requer auditoria por arquivo
e análise de compatibilidade jurídica da obra combinada.

## Decisão operacional de licença

O README e o `LICENSE` versionados dão uma classificação conservadora suficiente
para conduzir o desenvolvimento sem cópia: nenhuma implementação ou asset do
LinkStack será incorporado. A auditoria por arquivo de assets permanece
desnecessária para a estratégia vigente, pois CSS, JavaScript, fontes, ícones,
imagens, temas e blocos são descartados. Se o escopo voltar a incluir código
original, será necessária revisão formal de compatibilidade e de cada arquivo,
incluindo dependências e créditos. O aviso de segurança também exclui a
reutilização do fluxo `UserController::saveLink`.
