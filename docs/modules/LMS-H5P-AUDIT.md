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

## Inventário de portabilidade do original (revisado em 2026-10-18)

| Parte original | Situação no fork | Prioridade / motivo |
| --- | --- | --- |
| Campo H5P, entidade/conteúdo, formatter e bibliotecas de reprodução | Copiados; formatter exige contexto de curso para exibir | Relevante, mas a reprodução só deve ser ativada quando todos os endpoints respeitarem matrícula e acesso do Group. |
| Administração de bibliotecas e configurações | Controladores/formulários e rotas base copiados; corrigidos requisitos de parâmetro e páginas ligadas ao painel | Relevante para curadoria; validar operações e CSRF/cache antes de habilitar. Upload de pacote continua bloqueado. |
| `h5peditor` (rotas, callbacks AJAX e bibliotecas Drupal) | **Ainda não portado** como integração funcional; o widget não é apresentado no formulário do curso | Essencial para educadores criarem/alterarem atividades. As rotas originais só verificavam permissão global, e callbacks usavam `exit()`; reimplementar com Request/Response, token e autorização por curso/Group, sem copiar o bypass. |
| Salvar resultado (`set-finished`) e estado do estudante | Código de armazenamento copiado, mas endpoints ainda têm permissão genérica e não validam matrícula | Relevante para continuidade e relatório. Portar depois de amarrar cada `content_id` a curso permitido; resultados não alimentam nota LMS sem contrato próprio. |
| Importação/upload de pacote e imagens incorporadas | Desabilitada | Relevante para adoção de conteúdo existente, mas exige ingestão via `aculta_image`, verificação de pacote e restrições de biblioteca. |
| Embed independente | Rota removida | Não portar sem autorização da entidade/curso pai; o issue upstream documenta exposição por URL direta. |
| Telemetria do H5P Hub e instalação automática de tipos | Ainda requer revisão | Opcional ao fluxo inicial; decidir consentimento, tráfego externo e política de bibliotecas antes de ativar. |

Comparação conferida com a release `2.0.0-beta1`: ela inclui as integrações do
widget e chamadas AJAX do editor, cache de tipos, upload de biblioteca e
correções para as chamadas AJAX do editor. A mesma release registra que tipos
restritos podiam ser enviados como pacote, portanto a função não pode ser
copiada sem nova validação. A próxima unidade técnica antes de liberar criação
de conteúdo é o adapter seguro de `h5peditor` por contexto de Group, seguido de
salvamento e leitura de estado do aluno com a mesma política.

## Fontes da auditoria

- [Drupal H5P releases](https://www.drupal.org/project/h5p/releases/2.0.0-beta1)
- [H5P upload restriction issue #3548290](https://www.drupal.org/project/h5p/issues/3548290)
- [H5P embed access issue #3221018](https://www.drupal.org/project/h5p/issues/3221018)
- [H5P security model](https://h5p.org/documentation/installation/security)
