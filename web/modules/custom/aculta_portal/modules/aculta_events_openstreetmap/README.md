# ACULTA Eventos OpenStreetMap

Submódulo de integração do `aculta_events` com OAuth2 do OpenStreetMap.

## Estado

E10-A implementa a conexão pelo popup padrão do Portal, endpoint explícito por ambiente,
proteção contra seleção cruzada de ambiente e token cifrado no State API. E10-B busca um
elemento por tipo/ID na API ativa, permite selecionar e revisar os dados, grava o local e
envia somente diferenças após confirmação explícita.

O ambiente de teste usa a API de desenvolvimento do OSM como default seguro. Produção
exige configuração explícita correspondente ao ambiente do site. Credenciais e chave de
cifra vêm de `aculta_portal.secrets_manager`; nenhum valor é armazenado no código ou na
configuração versionada. O teste de envio aguarda credenciais próprias do servidor de
desenvolvimento em `secrets/` e deve usar dados fictícios.

Páginas da integração: `/integracoes/openstreetmap/configuracao` e
`/integracoes/openstreetmap/importar`. OAuth usa `/autorizar` e `/retorno`, em popup,
com `no_cache`. Todas exigem `administer aculta events`.

## Testes

Kernel: `tests/src/Kernel/OpenStreetMapOAuthSafetyKernelTest.php` cobre permissão de rotas,
ambiente de desenvolvimento, recusa de ambiente cruzado, recusa sem credenciais,
armazenamento cifrado do token e escrita simulada somente no endpoint de desenvolvimento.
Teste de envio real fica pendente até provisionar credenciais próprias do OSM de desenvolvimento.
