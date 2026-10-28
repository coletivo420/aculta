# ACULTA Eventos OpenStreetMap

Submódulo de integração do `aculta_events` com OAuth2 do OpenStreetMap.

## Estado

E10-A implementa a conexão pelo popup padrão do Portal, endpoint explícito por ambiente,
proteção contra seleção cruzada de ambiente e token cifrado no State API. O fluxo de
importação Find → Select → Review → Save, a criação de local e o envio confirmado ao OSM
permanecem pendentes (E10-B).

O ambiente de teste usa a API de desenvolvimento do OSM como default seguro. Produção
exige configuração explícita correspondente ao ambiente do site. Credenciais e chave de
cifra vêm de `aculta_portal.secrets_manager`; nenhum valor é armazenado no código ou na
configuração versionada. O teste de envio aguarda credenciais próprias do servidor de
desenvolvimento em `secrets/` e deve usar dados fictícios.

Página administrativa: `/integracoes/openstreetmap/configuracao`. Exige a permissão
`administer aculta events`.

## Testes

Kernel: `tests/src/Kernel/OpenStreetMapOAuthSafetyKernelTest.php` cobre ambiente de
desenvolvimento, recusa de ambiente cruzado, recusa sem credenciais e armazenamento
cifrado do token.
