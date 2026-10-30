# ACULTA Eventos OpenStreetMap

## Endereço no assistente de revisão

Por decisão D-54/D-55, a revisão de tags de endereço usa o formulário/adaptador
central de `aculta_portal` e `aculta_portal_cep/cep-address`, com país `BR`
fixo e sem seletor. Preserve a representação de tags OSM (incluindo
`addr:housenumber`) e o modelo de local/evento; essa adaptação não cria outro
formulário de endereço nem duplica a integração de CEP.

Submódulo de integração do `aculta_events` com OAuth2 do OpenStreetMap.

## Estado

E10-A implementa a conexão pelo popup padrão do Portal, endpoint explícito por ambiente,
proteção contra seleção cruzada de ambiente e token cifrado no State API. E10-B busca um
elemento por tipo/ID na API ativa, permite selecionar e revisar os dados, grava o local e
envia somente diferenças após confirmação explícita.

O default do ambiente de teste é a API de desenvolvimento do OSM. Por D-45, o checkout de
teste pode selecionar explicitamente a API ativa; essa seleção está configurada apenas em
`settings.local.php`, e suas credenciais vêm de `secrets/` via
`aculta_portal.secrets_manager`. Nenhum valor é armazenado no código ou em configuração
versionada. A autorização OAuth ainda não foi concluída e nenhum envio real foi feito.

Cada envio à API ativa pode alterar o mapa público. Use dados fictícios no ensaio, revise as
alterações no assistente e confirme cada envio explicitamente. A seleção live no servidor de
teste não autoriza alterações automáticas ou sem revisão.

Na revisão de tags de endereço, o assistente usa o elemento `address` e o
preenchimento CEP do `aculta_portal`/`aculta_portal_cep`, com país BR oculto.
Os valores preenchidos são apenas proposta no formulário: só as tags marcadas
e confirmadas são enviadas ao OSM. `addr:housenumber` conserva valores OSM
alfanuméricos e não altera a validação de número da Conta.

Páginas da integração: `/integracoes/openstreetmap/configuracao` e
`/integracoes/openstreetmap/importar`. OAuth usa `/autorizar` e `/retorno`, em popup,
com `no_cache`. Todas exigem `administer aculta events`.

## Fronteira de domínio (2026-10-30)

As quatro rotas pertencem ao purpose `eventos` (as administrativas com
`_aculta_domain_purpose_authoritative: true`). O `DomainPurposeRequestSubscriber`
nega a integração em outros domínios, inclusive POST direto. O redirect URI do
app OAuth do OpenStreetMap deve apontar para o host de Eventos do ambiente; o
callback é montado com `Url::fromRoute(..., ['absolute' => TRUE])`, então usa o
host atual (Eventos).

## Credenciais e ambientes (exceção única)

Uma única aplicação OAuth do OpenStreetMap atende teste, desenvolvimento e
produção. Em `secrets/` ficam `OSM_PRODUCTION_CLIENT_ID`,
`OSM_PRODUCTION_CLIENT_SECRET` e `ACULTA_OSM_TOKEN_ENCRYPTION_KEY`; as variáveis
`OSM_TEST_*` do contrato não estão provisionadas. O site de teste só resolve a
credencial de produção com `aculta_openstreetmap_environment=production` (D-45);
sem essa seleção usa a API de desenvolvimento e não tem credencial.

O token do usuário é cifrado no State por ambiente. Em 2026-10-29 a conta
autorizada conectou o app (`site=test`, ambiente efetivo `production`) e o
ensaio de escrita foi concluído com limpeza. Cada escrita no mapa público exige
revisão e confirmação explícita (D-45). Nenhum valor de credencial é versionado
nem exibido em log.

## Testes

Kernel: `tests/src/Kernel/OpenStreetMapOAuthSafetyKernelTest.php` cobre permissão de rotas,
ambiente de desenvolvimento, recusa de ambiente cruzado, recusa sem credenciais,
armazenamento cifrado do token e escrita simulada no endpoint configurado.

Ensaio real de 2026-10-29: nó fictício `14331309082` criado no Atlântico Sul
(changeset `191197897`), atualizado pelo `OpenStreetMapApiClient::update`
(changeset `191197899`, versão 2) e removido (changeset `191197900`); a leitura
posterior confirmou a ausência do nó. Nenhum token ou corpo sensível foi
impresso. Um envio fora do teste continua dependendo da revisão de termos ODbL
e atribuição.
