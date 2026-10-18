# Auditoria e implementação do H5P próprio para o ACULTA LMS

Atualização em 2026-10-18. O pacote Drupal contrib `drupal/h5p` foi removido do
Composer. O código de integração foi copiado para o submódulo próprio
`aculta_lms_h5p`, com preservação da licença GPL e identificação da origem. Os
pacotes `h5p/h5p-core` e `h5p/h5p-editor` permanecem como motor PHP Composer;
o editor continua sujeito a um patch Composer de bloqueio de tipos restritos.

O fork não está habilitado. A rota `/h5p/{id}/embed` foi removida e o formatter
recusa conteúdo que dependa de iframe independente. Uploads de pacote foram
desabilitados no widget e na tela de bibliotecas até a ingestão das imagens
embutidas via `aculta_image`; o gate de imagens agora passa sem exceções. A
implementação ainda contém APIs legadas herdadas e não está pronta para cursos
privados até a migração DI/Hook e revisão de autorização/cacheability.

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
A rota pública foi retirada do fork. Conteúdo não pode ser considerado isolado
por turma até o LMS autorizar o contexto pai em cada render e operação AJAX,
acumulando cache contexts/tags corretos.

## Próximas condições para habilitação

1. Refatorar hooks e classes herdados para o padrão Drupal 11.3+ do Portal: DI,
   hooks OOP quando suportados, acesso/cacheability revisados.
2. Vincular cada conteúdo H5P a uma aula/curso com autorização Group/LMS validada
   em render, edição, dados do usuário, exportação e operações AJAX.
3. Testar uploads com e sem `CREATE_RESTRICTED`, conteúdo Div e conteúdo iframe,
   isolamento entre estudantes, revogação de matrícula e limpeza de arquivos.
4. Revisar CSP, bibliotecas executáveis, dados pessoais expostos a JavaScript,
   telemetria Hub e persistência de tentativas; não converter scores em notas LMS
   sem contrato autenticado e auditável.
5. Só então habilitar no Runtime após backup, plano de reversão e confirmação de
   schema/configuração; nenhum schema H5P foi instalado nesta etapa.

## Fontes da auditoria

- [Drupal H5P releases](https://www.drupal.org/project/h5p/releases/2.0.0-beta1)
- [H5P upload restriction issue #3548290](https://www.drupal.org/project/h5p/issues/3548290)
- [H5P embed access issue #3221018](https://www.drupal.org/project/h5p/issues/3221018)
- [H5P security model](https://h5p.org/documentation/installation/security)
