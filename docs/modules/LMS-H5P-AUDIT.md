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
por caminho/extensão. Exportação, telemetria do Hub e estatísticas externas
ficam desligadas por padrão. Upload de pacote e endpoints do editor continuam
desligados até portar o editor inteiro com ingestão de imagens via `aculta_image`
e autorização Group. O módulo permanece desabilitado até validação funcional e
revisão integral das APIs legadas herdadas.

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
2. Refatorar hooks e classes herdados para o padrão Drupal 11.3+ do Portal: DI,
   hooks OOP quando suportados, auditoria de acesso/cacheability.
3. Testar conteúdo Div, isolamento entre estudantes, revogação de matrícula,
   persistência de tentativas, CSP, bibliotecas executáveis e limpeza de arquivos.
4. Não converter scores em notas LMS sem contrato autenticado e auditável.
5. Só então habilitar no Runtime após backup, plano de reversão e confirmação de
   schema/configuração; nenhum schema H5P foi instalado nesta etapa.

## Inventário de portabilidade do original (revisado em 2026-10-18)

| Parte original | Situação no fork | Prioridade / motivo |
| --- | --- | --- |
| Campo H5P, entidade/conteúdo, formatter e bibliotecas de reprodução | Adaptados; armazenamento privado e rota de asset checa matrícula e relação Group/conteúdo | Player não usa embed independente. Conteúdo antigo em `public://` exige inventário/migração explícitos; update hook não muda os arquivos automaticamente. |
| Administração de bibliotecas e configurações | Controladores/formulários e rotas base copiados; corrigidos requisitos de parâmetro e páginas ligadas ao painel | Relevante para curadoria; validar operações e CSRF/cache antes de habilitar. Upload de pacote continua bloqueado. |
| `h5peditor` (rotas, callbacks AJAX e bibliotecas Drupal) | **Ainda não portado** como integração funcional; o widget não é apresentado no formulário do curso | Essencial para educadores criarem/alterarem atividades. As rotas originais só verificavam permissão global, e callbacks usavam `exit()`; reimplementar com Request/Response, token e autorização por curso/Group, sem copiar o bypass. |
| Salvar resultado (`set-finished`) e estado do estudante | Endpoints adaptados: sessão, token, validação de nota e associação curso/conteúdo | Ainda precisa teste funcional entre estudantes/turmas e contrato separado antes de alimentar notas LMS. |
| Importação/upload de pacote e imagens incorporadas | Desabilitada | Relevante para adoção de conteúdo existente, mas exige ingestão via `aculta_image`, verificação de pacote e restrições de biblioteca. |
| Embed independente | Rota removida | Não portar sem autorização da entidade/curso pai; o issue upstream documenta exposição por URL direta. |
| Telemetria do H5P Hub e instalação automática de tipos | Desligadas por padrão; hooks de instalação não iniciam chamadas externas | Habilitar manualmente exige decisão explícita sobre consentimento, tráfego externo e política de bibliotecas. |
| Hooks de instalação/desinstalação e segurança `.htaccess` | Não contactam Hub, não alteram `.htaccess` e preservam arquivos privados na desinstalação | O schema de banco segue o lifecycle padrão Drupal; arquivos privados mantêm-se para recuperação e remoção manual controlada. |

Comparação conferida com a release `2.0.0-beta1`: o editor e suas rotas AJAX,
upload de biblioteca, tipos do Hub e callbacks do widget ainda precisam de um
adapter seguro por Group. Tipos restritos podiam ser enviados como pacote, então
uploads continuam fechados. A condição CTools `aculta_lms_h5p_group_has_activities`
expõe o contexto do Group para displays condicionais; Page Manager não está
instalado. Próxima unidade: editor read-only/listagem de bibliotecas por Group,
depois criação/salvamento somente após integração de uploads com `aculta_image`.

## Fontes da auditoria

- [Drupal H5P releases](https://www.drupal.org/project/h5p/releases/2.0.0-beta1)
- [H5P upload restriction issue #3548290](https://www.drupal.org/project/h5p/issues/3548290)
- [H5P embed access issue #3221018](https://www.drupal.org/project/h5p/issues/3221018)
- [H5P security model](https://h5p.org/documentation/installation/security)
