# Arquitetura do ACULTA Portal

## Papel do módulo

`aculta_portal` é a camada de integração e customização da plataforma ACULTA.

```text
Drupal Core + módulos contrib
            |
            v
      aculta_portal
 integração / orquestração
            |
      +-----+------+
      |            |
painel usuário  painel admin
      |            |
      +-----+------+
            |
            v
        tema ACULTA420
```

O Portal pode combinar informações de diferentes subsistemas em uma mesma tela,
adaptar textos e formulários, definir navegação multidomínio, adicionar
acessibilidade, integrar AJAX, aplicar cache metadata e oferecer painéis.

Ele não deve criar armazenamento paralelo quando Drupal Core ou um módulo
funcional já possui a informação.

## Limites

### O Portal pode

- agregar User, Profile, Address, Commerce, LMS, Wiki e Fórum;
- construir dashboards e resumos;
- adaptar formulários nativos;
- compor Views e render arrays;
- aplicar Domain purpose às rotas;
- construir links entre subdomínios;
- adaptar UX de CEP sem duplicar endpoint/cache;
- integrar participação do usuário;
- integrar administração;
- adicionar adapters pequenos quando uma API upstream exigir composição;
- manter comportamento AJAX com APIs do Drupal;
- adicionar cache contexts/tags/max-age apropriados.

### Authentication integration

Social Auth e CAPTCHA/Turnstile são integrações e políticas do `aculta_portal`.
O Portal decide quando apresentar um provider, aplica a política de formulários
protegidos e integra destinos, Domain purpose e a experiência de Conexões sem
copiar a implementação dos módulos upstream.

O tema não pode conhecer nomes de providers, secrets, engine CAPTCHA ou rotas
OAuth. Ele recebe estruturas/classes neutras de apresentação do Portal. Core e
contrib mantêm a implementação de protocolo/provider; a infraestrutura fornece
credenciais por Key/environment.

```text
Social Auth / CAPTCHA / Turnstile
          ↓
      aculta_portal
          ↓
neutral render contract
          ↓
       ACULTA420
```

### O Portal não pode

- criar autenticação própria;
- criar perfil/endereço paralelo;
- criar ledger financeiro;
- criar matrícula/progresso paralelo;
- criar entidades próprias de tópico/resposta quando Forum/Comment atendem;
- criar tabela própria de comentários;
- duplicar busca textual se Search API atender;
- duplicar bookmarks/follows se Flag atender;
- duplicar transporte AJAX genérico do Drupal sem justificativa;
- depender do tema para regras de negócio;
- colocar regra de negócio no tema.

## Painel do usuário

A Conta é uma experiência integrada, não uma fonte de verdade.

A evolução prevista inclui:

- visão geral;
- meus dados;
- endereço + CEP;
- foto;
- segurança;
- e-mail;
- conexões sociais;
- meu apoio;
- meus cursos;
- minha participação;
- contribuições Wiki;
- tópicos e comentários no Fórum;
- favoritos/acompanhamentos quando adotados.

Cada card ou seção consulta sua fonte real.

## Painel administrativo

O Portal deve evoluir para um hub de administração que **encaminha e resume** as
ferramentas existentes, sem recriar seus CRUDs.

Áreas previstas:

- pessoas/perfis;
- Domains;
- Wiki;
- Fórum e moderação;
- cursos/LMS/Group;
- Commerce/apoio/pagamentos;
- Webforms;
- requisitos;
- segurança.

## Multidomínio

Purposes atuais:

| Purpose | Produção | Homelab |
| --- | --- | --- |
| MAIN | aculta.org | aculta.toca.net.br |
| ACCOUNT | conta.aculta.org | conta.aculta.toca.net.br |
| SUPPORT | apoio.aculta.org | apoio.aculta.toca.net.br |
| MAGAZINE | revista420.aculta.org | revista420.aculta.toca.net.br |
| WIKI | wiki420.aculta.org | wiki420.aculta.toca.net.br |
| SHOP | loja.aculta.org | loja.aculta.toca.net.br |
| COURSES | cursos.aculta.org | cursos.aculta.toca.net.br |
| FORUM | growroom420.aculta.org | growroom420.aculta.toca.net.br |

FORUM é planejado e só passa a ser ativo quando a versão correspondente for
implementada e validada.

Rotas especializadas devem operar no purpose correto e, salvo decisão
arquitetural explícita, retornar 404 no host incorreto.

Toda rota humana exposta como navegação pública deve preferir slug amigável em
português, mantendo a mesma estrutura de path entre Homelab e produção. Rotas
técnicas de Core/contrib, callbacks, OAuth, AJAX, webhooks e admin podem manter
paths internos quando isso fizer parte da API correta.

Ver [FRIENDLY-PORTUGUESE-SLUGS.md](FRIENDLY-PORTUGUESE-SLUGS.md).

## Tema

O tema `web/themes/custom/aculta420` é responsável por apresentação visual,
componentes, Twig, CSS, responsividade e identidade.

O `aculta_portal` fornece render arrays, dados, forms e integração. Contexto institucional, breadcrumb e URLs por Domain purpose são preparados no Portal; o tema não chama serviços do Portal.

A apresentação pública segue o **ACULTA Bootstrap Component Design System** já
estabelecido pelo tema. O Portal prepara dados/estados e presenters; SDCs do
tema aplicam contratos visuais sobre Bootstrap. Ver
[COMPONENT-DESIGN-SYSTEM.md](COMPONENT-DESIGN-SYSTEM.md) e ADR-007.

A refatoração do tema está em fase avançada e é preservada como baseline.
Mudanças Portal não devem reiniciar, reestruturar em massa ou reescrever o tema
como efeito colateral.

## Bancos

Atual:

- Homelab Runtime: MariaDB;
- Estados: backups locais do banco, fora do Runtime;
- produção: MariaDB.

Código Portal deve usar Entity API, Form API, serviços públicos e Database API
portável. SQL específico de um banco exige justificativa e teste.

## Critério para código custom

Antes de adicionar uma classe/função custom, responder:

- Core já oferece isso?
- contrib instalado já oferece isso?
- outro contrib estável e coberto por Security Advisory oferece isso?
- Views/configuração resolve sem PHP?
- é realmente integração específica da ACULTA?

Se as quatro primeiras respostas forem negativas ou insuficientes, código
custom pode ser justificado.
