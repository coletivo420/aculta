# Auditoria e implementação do H5P próprio para o ACULTA LMS

Atualização em 2026-10-18. O pacote Drupal contrib `drupal/h5p` foi removido do
Composer. O código de integração foi copiado para o submódulo próprio
`aculta_lms_h5p`, com preservação da licença GPL e identificação da origem. Os
pacotes `h5p/h5p-core` e `h5p/h5p-editor` permanecem como motor PHP Composer;
o editor continua sujeito a um patch Composer de bloqueio de tipos restritos.

O fork não está habilitado. A rota `/h5p/{id}/embed` foi removida e o formatter
recusa conteúdo que dependa de iframe independente. O armazenamento do player
usa `private://`; conteúdo é servido por rota que confere matrícula/`takeAccess`
e relação da atividade com o curso. Bibliotecas instaladas usam rota restrita
por caminho/extensão. Exportação, comunicação com o Hub e estatísticas externas
ficam desligadas por padrão. O adapter do editor aceita uploads de mídia do
curso após validar acesso Group e token H5P. Imagens raster passam pela política
compartilhada de `aculta_image` e pelo handler de arquivos gerenciados do Drupal;
outras mídias usam os limites PHP do Drupal e a allowlist H5P. Os arquivos ficam
privados e seus usos são rastreados pelo File API. Upload de pacote, instalação
pelo Hub e descoberta remota continuam indisponíveis. O módulo segue desabilitado
enquanto faltam testes Kernel/integração, validação visual e revisão das APIs
legadas herdadas.

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
   leem superglobais; manter uploads desabilitados até ingestão `aculta_image`.
   O campo agora está ligado ao formulário Group e usa uma biblioteca Drupal
   própria. O controller que chamava `exit()` foi removido. Há um adapter
   Request/Response por curso para listar bibliotecas locais, carregar metadados,
   traduções e validar parâmetros. Ele exige autorização Group e token H5P;
   uploads, Hub e instalação de pacotes respondem como indisponíveis. Isso ainda
   não habilita o editor para educadores: upload de arquivos de conteúdo segue
   bloqueado e o módulo permanece desabilitado até concluir e testar o ciclo de
   edição completo.
2. Corrigir a publicação de assets antes de expor qualquer parte do editor:
   `AssetFileStorage::mirrorAssets()` agora encaminha imagens estáticas Composer
   ao `aculta_image.PackagedAssetPublisher`, que mantém o caminho `public://h5p`
   compatível com `assets://`, valida PNG/JPEG/GIF/WebP e filtra SVG ativo,
   registra proveniência e preserva os caminhos relativos usados por CSS.
   Assets não-imagem continuam sendo publicados pelo adaptador H5P. O fluxo
   ainda precisa de Kernel test e smoke test visual antes da habilitação.
3. Os hooks runtime de bibliotecas, cron e exclusão de usuário foram migrados
   para `#[Hook]` com DI; as assinaturas do framework H5P receberam contratos de
   retorno compatíveis com o H5P Core instalado. Ainda faltam refatorar os
   service locators internos do adapter e auditar cacheability/acesso no fluxo.
4. Testar conteúdo Div, isolamento entre estudantes, revogação de matrícula,
   persistência de tentativas, CSP, bibliotecas executáveis e limpeza de arquivos.
5. Não converter scores em notas LMS sem contrato autenticado e auditável.
6. Só então habilitar no Runtime após backup, plano de reversão e confirmação de
   schema/configuração; nenhum schema H5P foi instalado nesta etapa.

## Inventário de portabilidade do original (revisado em 2026-10-18)

| Parte original | Situação no fork | Prioridade / motivo |
| --- | --- | --- |
| Campo H5P, entidade/conteúdo, formatter e bibliotecas de reprodução | Adaptados; armazenamento privado e rota de asset checa matrícula e relação Group/conteúdo | Player não usa embed independente. Conteúdo antigo em `public://` exige inventário/migração explícitos; update hook não muda os arquivos automaticamente. |
| Administração de bibliotecas e configurações | Controladores/formulários e rotas base copiados; corrigidos requisitos de parâmetro e páginas ligadas ao painel | Relevante para curadoria; validar operações e CSRF/cache antes de habilitar. Upload de pacote continua bloqueado. |
| `h5peditor` (rotas, callbacks AJAX e bibliotecas Drupal) | Adapter por Group para listagem, metadados, traduções, filtro e upload de mídia privado; acesso usa token H5P e permissões de biblioteca | Upload coberto parcialmente por Unit; faltam Kernel/integração e validação visual por curso. Operações de pacote, Hub e instalação falham fechadas. |
| Salvar resultado (`set-finished`) e estado do estudante | Endpoints adaptados: sessão, token, validação de nota e associação curso/conteúdo | Ainda precisa teste funcional entre estudantes/turmas e contrato separado antes de alimentar notas LMS. |
| Upload de mídia do editor | Ativo no adapter, sujeito a autorização Group e token; imagens raster passam por `aculta_image`, ficam privadas e têm File API usage | Falta teste Kernel de cópia, persistência e remoção, além de browser test; o módulo continua desabilitado. |
| Importação/upload de pacote | Desabilitada | Relevante para instalar tipos; requer inspeção segura do ZIP, validação de tipos restritos e controle de dependências antes de ativar. |
| Embed independente | Rota removida | Não portar sem autorização da entidade/curso pai; o issue upstream documenta exposição por URL direta. |
| Telemetria do H5P Hub e instalação automática de tipos | Desligadas por padrão; hooks de instalação não iniciam chamadas externas | Habilitar manualmente exige decisão explícita sobre consentimento, tráfego externo e política de bibliotecas. |
| Hooks runtime, instalação/desinstalação e segurança `.htaccess` | Hooks runtime usam `#[Hook]` e DI; lifecycle usa procedimentos exigidos pelo Drupal. Não contactam Hub, não alteram `.htaccess` e preservam arquivos privados na desinstalação | O schema segue o lifecycle Drupal; arquivos privados mantêm-se para recuperação e remoção manual controlada. |

Comparação conferida com a release `2.0.0-beta1`: o editor tem um adapter
parcial por Group, com upload de mídia local e ciclo gerenciado por File API;
upload de biblioteca e tipos do Hub continuam indisponíveis. Assets estáticos de imagem usam a ingestão
controlada do `aculta_image`. O código de callbacks legado foi removido para que
nenhuma rota futura passe a chamar `exit()` ou ler arquivos globais sem revisão.
Tipos restritos podiam ser enviados como pacote, então
uploads de pacote continuam fechados. A condição CTools `aculta_lms_h5p_group_has_activities`
expõe o contexto do Group para displays condicionais; Page Manager não está
instalado. A implementação deve reutilizar `field_h5p_activities`, a entidade e
o schema H5P Core já portados, `H5PCourseAccessManager` para autorização e os
serviços existentes de CTools. `aculta_image.ImageIngester` é para arquivos
externos com origem rastreável; não é um uploader genérico, e `UploadPolicy`
define a política comum de imagem. Não criar outro catálogo, storage de conteúdo
ou política de upload no H5P. Próxima unidade: concluir testes browser
de upload/edição, revisar autorização/cache, depois
avaliar importação segura de bibliotecas antes de habilitar o módulo.

## Fontes da auditoria

- [Drupal H5P releases](https://www.drupal.org/project/h5p/releases/2.0.0-beta1)
- [H5P upload restriction issue #3548290](https://www.drupal.org/project/h5p/issues/3548290)
- [H5P embed access issue #3221018](https://www.drupal.org/project/h5p/issues/3221018)
- [H5P security model](https://h5p.org/documentation/installation/security)
