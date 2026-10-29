# ACULTA Eventos OpenStreetMap

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

Páginas da integração: `/integracoes/openstreetmap/configuracao` e
`/integracoes/openstreetmap/importar`. OAuth usa `/autorizar` e `/retorno`, em popup,
com `no_cache`. Todas exigem `administer aculta events`.

## Credenciais e ambientes (exceção única)

Uma única aplicação OAuth do OpenStreetMap atende teste, desenvolvimento e
produção. Em `secrets/` ficam `OSM_PRODUCTION_CLIENT_ID`,
`OSM_PRODUCTION_CLIENT_SECRET` e `ACULTA_OSM_TOKEN_ENCRYPTION_KEY`; as variáveis
`OSM_TEST_*` do contrato não estão provisionadas. O site de teste só resolve a
credencial de produção com `aculta_openstreetmap_environment=production` (D-45);
sem essa seleção usa a API de desenvolvimento e não tem credencial.

O token do usuário é cifrado no State por ambiente. A sonda de 2026-10-29
confirmou `site=test`, ambiente efetivo `production`, credencial presente e
nenhum token conectado: a autorização OAuth ainda exige login humano no OSM. Cada
escrita no mapa público exige revisão e confirmação explícita (D-45). Nenhum
valor de credencial é versionado nem exibido em log.

## Testes

Kernel: `tests/src/Kernel/OpenStreetMapOAuthSafetyKernelTest.php` cobre permissão de rotas,
ambiente de desenvolvimento, recusa de ambiente cruzado, recusa sem credenciais,
armazenamento cifrado do token e escrita simulada no endpoint configurado. O ensaio de envio
real continua pendente até concluir a autorização OAuth e revisar uma alteração fictícia no
assistente; cada envio ainda exige confirmação explícita.
