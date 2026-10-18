# Auditoria inicial de H5P para o ACULTA LMS

Revisão em 2026-10-18. Esta é a primeira entrega da F10; inclui os pacotes H5P
no Composer, mas não habilita o módulo nem altera configuração Drupal ou o banco
do Runtime.

## Instalação inicial e decisão técnica

`drupal/h5p:^2.0@beta` foi resolvido como `2.0.0-beta1`, com `h5p/h5p-core`
1.28.0 e `h5p/h5p-editor` 1.25: três instalações, zero upgrades e zero
remoções no dry-run e na instalação. Composer validate passou (com warnings
preexistentes de versões exatas) e `composer audit` não reportou advisories.
Isso não equivale à cobertura de segurança Drupal: H5P não tem release estável
coberta atualmente.

O Composer scaffold inicialmente tentou substituir `web/.htaccess` e
`web/sites/default/default.settings.php`; restaurei o `.htaccess` e configurei o
scaffold para anexar a regra `.mjs` local ao arquivo Core em instalações futuras.
Uma execução Composer posterior atualizou `default.settings.php` a partir do
template da versão Core já instalada. Nenhuma configuração Drupal foi importada.
Drush confirmou o Runtime
`http://cursos.aculta.toca.net.br` / `aculta_runtime`; `h5p` e `h5p_editor`
continuam desativados e a consulta read-only não encontrou tabelas `h5p_*`.

O submódulo custom `aculta_patcher` está habilitado no Runtime de teste para
administrar o registro do patch. Foi feito backup privado antes da ativação;
nenhuma configuração H5P ou schema H5P foi instalado.

Não habilitar `drupal/h5p` ou `drupal/lms_h5p` neste momento. A
versão Drupal 11 disponível consultada é `2.0.0-beta1`; a página do projeto
informa que não há release estável atualmente coberta pela política de
advisories. O release lista como issue conhecida o upload de pacotes `.h5p`
com bibliotecas de tipos restritos, apesar da permissão negar a criação desses
tipos. A issue #3548290 classifica o caso como possível bypass de acesso e
permanece sem correção publicada na versão beta consultada. Esse risco impede
um upload de autoria não restrito a administrador.

O conector `drupal/lms_h5p` também não tem cobertura da política de segurança,
está em alpha e depende de H5P e LMS XAPI. Ele envia respostas por statements
XAPI a um LRS e pode pontuar automaticamente no Drupal. Isso não satisfaz o
contrato ACULTA atual: LMS continua a fonte única da tentativa e da nota, e
nenhuma nota externa é aceita sem API confiável, identidade de tentativa,
origem/autenticidade, repetição e autoria demonstradas.

O H5P ainda não está habilitado no Runtime e não há connector LMS. Não foram
baixadas bibliotecas de conteúdo H5P nem criadas entidades.

O código prepara nome e e-mail da conta corrente no objeto `H5PIntegration`
entregue ao JavaScript de conteúdo. Antes de habilitar conteúdo, é necessário
confirmar quais tipos consomem ou enviam esses dados e reduzir a superfície de
PII. A rotina de instalação comunica-se com o H5P Hub para atualizar o cache de
tipos; instalação/ativação também exige validar esse tráfego e a política de
telemetria no Homelab.

## Evidências consultadas

- A página de projeto H5P consultada lista apenas `2.0.0-beta1` para Drupal 11,
  declara suporte `^10.2 || ^11`, registra que não há release estável suportada
  e remete ao Composer.
- As notas do `2.0.0-beta1` registram correções relevantes, incluindo vazamento
  entre usuários por ausência de cache context; também listam como conhecido o
  bypass das restrições de tipos H5P no upload de pacote.
- A issue [#3548290](https://www.drupal.org/project/h5p/issues/3548290)
  documenta a reprodução e chama o caso de potencial bypass de acesso.
- A página do projeto
  [LMS H5P](https://www.drupal.org/project/lms_h5p) declara ausência de
  cobertura de security advisory, release alpha, dependência do LMS XAPI e
  suporte a score automático via XAPI/LRS.
- O source local de `H5PDrupal::getGenericH5PIntegrationSettings()` entrega nome
  e e-mail da conta autenticada ao JavaScript; `h5p_install()` anuncia
  comunicação periódica com o Hub e envio de estatísticas anônimas de uso.
- Foi feita inspeção inicial de permissões e rotas do source local. Não foram
  testados upload, CSP, sandbox/isolamento de iframe, política de bibliotecas ou
  integração de respostas; o módulo permanece desativado.

## Verificação do caminho de upload no tag instalado

Na revisão do checkout `2.0.0-beta1`, a rota
`h5peditor.library_upload` (`/h5peditor/{token}/{content_id}/library-upload`)
declara somente `_permission: 'access h5p editor'`. O callback encaminha o
arquivo para `H5PEditorAjax::libraryUpload()`; `processContent()` valida o
pacote e chama `H5PStorage::savePackage(..., TRUE)`. A validação de
`H5PCore::isValidPackage()` usa `mayUpdateLibraries()` para decidir se pode
instalar bibliotecas, mas não consulta `H5PPermission::CREATE_RESTRICTED`.

A permissão `create restricted h5p content types` aparece no Drupal adapter
para filtrar a lista de bibliotecas em `H5PEditorDrupalStorage::getLibraries()`.
Esse filtro da interface não bloqueava um pacote `.h5p` que contivesse uma
biblioteca restrita. Foi criado um patch Composer local para `h5p/h5p-editor`
1.25: antes de salvar ou instalar dependências no caminho de upload, ele coleta
as bibliotecas incluídas e dependências preloaded/dynamic, confere versão
major/minor contra o inventário local e nega o processamento quando encontra
tipo restrito sem `CREATE_RESTRICTED`. O pacote continua sem edição manual: o
patch é aplicado pelo `cweagans/composer-patches` durante Composer build.

O patch passou pelo `git apply --check` e foi aplicado por
`composer patches-repatch`; o comando Drush do `aculta_patcher` também validou
o estado aplicado. Ainda falta teste funcional de autorização contra um pacote
H5P representativo (conta sem permissão negada; conta com permissão aceita).
Por isso, H5P permanece desativado no Runtime até esse teste e os demais gates
de privacidade, CSP, isolamento e integração de nota passarem.

Este achado é inspeção estática do source instalado, não um teste de exploração
no Runtime. Não foi enviado arquivo de prova nem alterado banco ou configuração.

## Gate restante da F10

Antes de ativar, repetir a pesquisa em Drupal.org e Composer e exigir
release/patch com política de segurança e a correção da issue
de upload demonstrada no tag exato. Em seguida, revisar source e testes de
permissões, ingestão/validação de `.h5p`, bibliotecas executáveis, storage,
cacheability, CSP e sandbox de iframe. Exercitar acesso do estudante/educador,
isolamento por turma e remoção/revogação. Manter score desativado; qualquer
integração de resultado exige contrato LMS confiável e aprovação específica.

Até esses gates passarem, a F10 permanece em auditoria com ativação bloqueada.
