# Troféus importados do Coletivo 420

Solicitação expressa do responsável em 2026-10-15. Game `0.1.0-dev.5`,
User `0.4.0-dev.6`, Portal `0.4.0-dev.38`; classificação MINOR.

| Troféu | Identificador preservado | Origem |
| --- | --- | --- |
| Fundadore | `fundadore`, novo, manual | [Página original](https://coletivo420.org.br/insignias/fundadore/) |
| Associado/a Antiproibicionista | `associado`, existente | [Página original](https://coletivo420.org.br/insignias/associado-a-antiproibicionista/) |
| Apoiadore | `apoiador`, existente | [Página original](https://coletivo420.org.br/insignias/apoiadore/) |

Três PNGs originais de 512×512, sem transformação, acompanham o Game como dados
do catálogo. Core Image apresenta essas imagens sem dependência do tema ou
WordPress. Não são ícones de interface. Manifesto com URLs, tamanhos, dimensões,
SHA-256 e autorização em `assets/trophies/ORIGENS.json`.

Nenhuma licença pública independente das imagens foi identificada. A licença
do código WordPress/GamiPress não define a licença desses materiais. A importação
foi solicitada pelo responsável; não atribuímos outra licença às imagens.
Não foi incorporado PHP/JavaScript, tracking ou conteúdo cadastral do site antigo.

O catálogo aparece na conta e na área Game mesmo antes de alguém obter o troféu.
No perfil público, imagens aparecem somente nas conquistas concedidas e visíveis.
Importar uma definição/imagem não concede o troféu nem comprova titularidade.
Não foram importadas listas de pessoas ou direitos estatutários descritos no site.

## Apoiadore: decisão de critérios

O responsável aprovou três caminhos: pedido concluído, apoio direto no sistema de
doações ou concessão administrativa. O catálogo visual preserva o gatilho existente
durante a importação; a fase administrativa seguinte registra os caminhos em
configuração editável. IDs/datas/revogações de concessões anteriores não mudam.
Conquistas não concedem benefícios financeiros ou permissões institucionais.

## Verificação

Assinatura PNG, 512×512 e SHA-256 conferidos. PHP lint e gates estáticos passaram;
os 17 fluxos HTTP de privacidade/administração foram repetidos após a integração
das imagens. O teste detectou e corrigiu a interpretação de um filho `#plain_text`
como sublista pelo preprocess de ItemList do Core: o filho recebe `#type` explícito.
Nenhuma conquista foi concedida para testar os materiais importados.
