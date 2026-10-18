# Auditoria e implementação do H5P próprio para o ACULTA LMS

Atualização em 2026-10-18. O pacote Drupal contrib `drupal/h5p` foi removido do
Composer. O código de integração foi copiado para o submódulo próprio
`aculta_lms_h5p`, com preservação da licença GPL e identificação da origem. Os
pacotes `h5p/h5p-core` e `h5p/h5p-editor` permanecem como motor PHP Composer;
o editor continua sujeito a um patch Composer de bloqueio de tipos restritos.

O fork está habilitado no Runtime de testes. A rota `/h5p/{id}/embed` foi removida e o formatter
recusa conteúdo que dependa de iframe independente. O armazenamento do player
usa `private://`; conteúdo é servido por rota que confere matrícula/`takeAccess`
e relação da atividade com o curso. Bibliotecas instaladas usam rota restrita
por caminho/extensão. Exportação, comunicação com o Hub e estatísticas externas
ficam desligadas por padrão. O adapter do editor aceita uploads de mídia do
curso após validar acesso Group e token H5P. Imagens raster passam pela política
compartilhada de `aculta_image` e pelo handler de arquivos gerenciados do Drupal;
outras mídias usam os limites PHP do Drupal e a allowlist H5P. Os arquivos ficam
privados e seus usos são rastreados pelo File API. Administradores podem instalar
pacotes `.h5p` locais pelo validador oficial, ignorando conteúdo e mantendo os
assets privados gerenciados; instalação pelo Hub e descoberta remota continuam
indisponíveis. O módulo está habilitado somente no Runtime de testes. O editor
visual e o carregamento de semântica passaram por smoke test; salvamento completo
e reprodução ainda dependem de validar um curso com aula, e a auditoria das APIs
legadas continua aberta.

## Decisão e limites da etapa

A integração Drupal original não é mais dependência nem fonte executável do
projeto. H5P Core/Editor continuam dependências externas GPL-3.0-only; o fork
Drupal deriva de `drupal/h5p` 2.0.0-beta1 (GPL-2.0-or-later). A licença e os
avisos de copyright foram mantidos no módulo.

A issue [#3548290](https://www.drupal.org/project/h5p/issues/3548290) descreve
o bypass de tipos restritos no upload do editor; o patch permanece aplicado ao
`h5p/h5p-editor`. O segundo caminho, widget de campo, agora tem a verificação
temporariamente indisponível no fork; reativá-lo depende da ingestão de imagens
embutidas por `aculta_image`, sem alterar `web/modules/contrib`.

A issue [#3221018](https://www.drupal.org/project/h5p/issues/3221018) registra
o acesso ao conteúdo pelo embed URL sem validar a entidade que contém o campo.
A rota pública foi retirada; o player e os endpoints de estado agora validam
acesso ao curso/conteúdo. Ainda é necessária auditoria funcional end-to-end e
teste de isolamento antes de considerar o módulo apto a cursos privados.

## Próximas condições para habilitação

1. Portar o editor por Group, sem endpoints herdados que chamam `exit()` ou
   leem superglobais; uploads agora passam pela ingestão `aculta_image`.
   O campo agora está ligado ao formulário Group e usa uma biblioteca Drupal
   própria. O controller que chamava `exit()` foi removido. Há um adapter
   Request/Response por curso para listar bibliotecas locais, carregar metadados,
   traduções, validar parâmetros e receber mídia privada. Ele exige autorização
   Group e token H5P; administradores instalam bibliotecas de pacote local pelo
   H5P Core. Kernel com entidades reais confirma que matrícula/`takeAccess`,
   papel de educador/`edit group`, referência do conteúdo e revogação de matrícula
   controlam o acesso, e avalia a condição CTools com contexto de Group. No
   navegador autenticado, a sala do curso carregou a lista de bibliotecas e a
   semântica `H5P.HelloWorld 1.0`; o formulário exibiu os campos do tipo de
   conteúdo. A rota AJAX usa propósito MAIN porque o formulário de edição do
   Group canonicaliza para esse host. O endpoint HTTP de biblioteca entregou
   `library.json` com `200` e MIME correto.
2. Publicar assets estáticos sem contornar `aculta_image`:
   `AssetFileStorage::mirrorAssets()` agora encaminha imagens estáticas Composer
   ao `aculta_image.PackagedAssetPublisher`, que mantém o caminho `public://h5p`
   compatível com `assets://`, valida PNG/JPEG/GIF/WebP e filtra SVG ativo,
   registra proveniência e preserva os caminhos relativos usados por CSS.
   Assets não-imagem continuam sendo publicados pelo adaptador H5P. A publicação
   e a visualização do editor passaram no Runtime. Rotas H5P usam limites
   explícitos de segmentos porque parâmetros de rota Drupal ocupam um segmento
   completo; os caminhos de assets H5P são divididos em segmentos antes do
   controller validar e resolver o arquivo.
3. Os hooks runtime de bibliotecas, cron e exclusão de usuário foram migrados
   para `#[Hook]` com DI; as assinaturas do framework H5P receberam contratos de
   retorno compatíveis com o H5P Core instalado. Controllers administrativos e
   de progresso usam DI. A lista de bibliotecas usa a descoberta de assets do
   Core sem cache paralelo; importação e exclusão invalidam a descoberta nativa
   após sucesso, validada em Kernel. Ainda faltam refatorar service locators
   internos do adapter e concluir a auditoria de cacheability no fluxo completo.
4. Kernel cobre isolamento por curso, revogação de matrícula, gravação/leitura
   privada de estado do estudante e salvamento do resultado pelo endpoint real.
   Ainda falta verificar conteúdo Div no navegador, continuidade de tentativas
   entre sessões, CSP final, execução de biblioteca instalada e limpeza/remoção
   de mídia. A habilitação registrada abaixo é apenas no Runtime de testes.
5. Não converter scores em notas LMS sem contrato autenticado e auditável.
6. O módulo foi habilitado no Runtime de testes após inspeção do estado; a
   instalação criou schema e configuração H5P. Produção não foi alterada. Antes
   de qualquer habilitação em produção, exigir backup, plano de reversão e
   validação visual/fim a fim no host de cursos.

## Inventário de portabilidade do original (revisado em 2026-10-18)

| Parte original | Situação no fork | Prioridade / motivo |
| --- | --- | --- |
| Campo H5P, entidade/conteúdo, formatter e bibliotecas de reprodução | Adaptados; armazenamento privado e rota de asset checa matrícula e relação Group/conteúdo | Player não usa embed independente. Conteúdo antigo em `public://` exige inventário/migração explícitos; update hook não muda os arquivos automaticamente. |
| Administração de bibliotecas e configurações | Controladores/formulários adaptados; pacote local usa validação H5P Core e escrita privada pelo File API, com imagens em `aculta_image` | Upload exige permissão administrativa; substituição silenciosa de arquivos instalados é recusada. Instalação de pacote e publicação dos assets Composer passaram no Runtime; remoção ainda precisa de smoke test. |
| `h5peditor` (rotas, callbacks AJAX e bibliotecas Drupal) | Adapter por Group para listagem, metadados, traduções, filtro e upload de mídia privado; acesso usa token H5P e permissões de biblioteca | Kernel valida acesso real por curso e CTools, e resolve rotas com paths H5P aninhados; navegador autenticado carregou listagem e semântica de biblioteca. Hub segue desligado. |
| Salvar resultado (`set-finished`) e estado do estudante | Endpoints adaptados: sessão, token, validação de nota e associação curso/conteúdo; Kernel exercita salvamento de pontuação, estado e isolamento de acesso | Falta teste HTTP/browser de continuidade de tentativa entre sessões e contrato separado antes de alimentar notas LMS. |
| Upload de mídia do editor | Ativo no adapter, sujeito a autorização Group e token; imagens raster passam por `aculta_image`, ficam privadas e têm File API usage | Falta teste Kernel de cópia, persistência e remoção, além de browser test. |
| Importação/upload de pacote | Pacote local habilitado para administradores; usa validador H5P, ignora conteúdo, preserva allowlist e mantém assets privados gerenciados | Pacote real benigno instalado no Runtime e seus assets passaram por HTTP; Kernel rejeita pacote malformado. Smoke test de remoção pendente; instalação remota/Hub continua desligada. |
| Embed independente | Rota removida | Não portar sem autorização da entidade/curso pai; o issue upstream documenta exposição por URL direta. |
| Telemetria do H5P Hub e instalação automática de tipos | Desligadas por padrão; hooks de instalação não iniciam chamadas externas | Habilitar manualmente exige decisão explícita sobre consentimento, tráfego externo e política de bibliotecas. |
| Hooks runtime, instalação/desinstalação e segurança `.htaccess` | Hooks runtime usam `#[Hook]` e DI; lifecycle usa procedimentos exigidos pelo Drupal. Não contactam Hub, não alteram `.htaccess` e preservam arquivos privados na desinstalação | O schema segue o lifecycle Drupal; arquivos privados mantêm-se para recuperação e remoção manual controlada. |

Comparação conferida com a release `2.0.0-beta1`: o editor tem um adapter
parcial por Group, com upload de mídia local e ciclo gerenciado por File API;
instalação local de bibliotecas usa validação H5P e armazenamento gerenciado,
enquanto tipos do Hub continuam indisponíveis. Assets estáticos de imagem usam a ingestão
controlada do `aculta_image`. O código de callbacks legado foi removido para que
nenhuma rota futura passe a chamar `exit()` ou ler arquivos globais sem revisão.
Tipos restritos podiam ser enviados como pacote; o instalador local usa o
validador H5P Core e instala somente bibliotecas permitidas. A condição CTools `aculta_lms_h5p_group_has_activities`
expõe o contexto do Group para displays condicionais; Page Manager não está
instalado. A implementação deve reutilizar `field_h5p_activities`, a entidade e
o schema H5P Core já portados, `H5PCourseAccessManager` para autorização e os
serviços existentes de CTools. `aculta_image.ImageIngester` é para arquivos
externos com origem rastreável; não é um uploader genérico, e `UploadPolicy`
define a política comum de imagem. Não criar outro catálogo, storage de conteúdo
ou política de upload no H5P. Próxima unidade: testar importação e remoção com
pacotes reais, concluir browser tests de edição e revisar autorização/cache antes
de habilitar o módulo.

## Atualização do smoke test — 2026-10-18

O editor abriu no formulário de edição da sala de curso e carregou
`H5P.HelloWorld 1.0`. Isso revelou e corrigiu a dispatch da rota `libraries`: o
H5P Editor usa essa mesma ação para a lista e para os metadados individuais,
com segmentos adicionais de máquina/versão. A resposta individual agora passa
pela verificação de acesso à biblioteca e preserva `language` e
`default-language`; o formato por query também é aceito.
O submit serializou os parâmetros e a biblioteca nos campos Drupal. O curso de
teste recusou salvar por não ter nenhuma aula, portanto não foi persistida nova
atividade. O próximo smoke test precisa usar curso de teste válido com aula,
salvar e reproduzir como estudante. Produção não foi acessada.

O Runtime também expôs uma definição de serviço compilada com a antiga ordem de
parâmetros do construtor de `H5PLibraryHooks`. A classe agora tem uma definição
explícita com `database`, `asset_file_manager` e `file_url_generator`; após
rebuild em `conta` e `cursos`, ambos os containers constroem o hook e o editor
carrega a semântica novamente. O watchdog mantém somente os erros históricos
anteriores à reconstrução; não houve novo erro de construção após a correção.

## Fontes da auditoria

- [Drupal H5P releases](https://www.drupal.org/project/h5p/releases/2.0.0-beta1)
- [H5P upload restriction issue #3548290](https://www.drupal.org/project/h5p/issues/3548290)
- [H5P embed access issue #3221018](https://www.drupal.org/project/h5p/issues/3221018)
- [H5P security model](https://h5p.org/documentation/installation/security)
