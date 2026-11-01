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

## Regra de domínio (decisão do responsável, 2026-10-30)

As quatro rotas pertencem ao purpose `main`, junto da área administrativa de
Eventos. O `DomainPurposeRequestSubscriber` nega a integração no host de
Eventos e nos demais domínios, inclusive POST direto. O redirect URI do app
OAuth do OpenStreetMap deve apontar para o host MAIN do ambiente; o callback é
montado com `Url::fromRoute(..., ['absolute' => TRUE])`, então usa o host
atual (MAIN).
