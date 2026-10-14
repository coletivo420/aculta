# Inventário de integrações

Documento de referência. Dono: `aculta_messenger` no ACULTA Portal. Revisão:
2026-10-14. Versões publicadas verificadas nos projetos Drupal.org nesta data;
nenhum pacote foi instalado nesta etapa.

## Estado do repositório

| Pacote | `composer.lock` | Código em `web/modules/contrib` | Conclusão |
| --- | --- | --- | --- |
| `drupal/private_message` | Ausente | Ausente | Instalação e inspeção do código pendentes |
| `drupal/message` | Ausente | Ausente | Instalação e inspeção do código pendentes |
| `drupal/message_notify` | Ausente | Ausente | Instalação e inspeção do código pendentes |
| `drupal/message_subscribe` | Ausente | Ausente | Instalação e inspeção do código pendentes |
| `drupal/site_alert` | Ausente | Ausente | Instalação e inspeção do código pendentes |

Base encontrada: Drupal Core 11.4.8 no lock; `composer.json` declara PHP >=8.3.
Este ambiente executa PHP 8.4.26. Não há PHP 8.5 para validação local. Os
metadados Composer do projeto declaram plataforma PHP >=8.3; isso não substitui
lint, testes ou avaliação das dependências em PHP 8.5.

## Releases estáveis Drupal 11 identificados

| Projeto Drupal.org | Versão estável consultada | Drupal 11 declarado | Ponto de atenção |
| --- | --- | --- | --- |
| [Private Message](https://www.drupal.org/project/private_message) | 3.0.7 (`^3.0`) | Sim | Issues recentes relatam `TypeError` com valor nulo em D11/PHP 8.4; reproduzir/revisar antes de adotar. 4.x consultado é alpha, não base da primeira implementação. |
| [Message](https://www.drupal.org/project/message) | 8.x-1.9 (`^1.9`) | Sim | Projeto indicado como manutenção mínima; verificar issue queue e APIs usadas. |
| [Message Notify](https://www.drupal.org/project/message_notify) | 8.x-1.5 (`^1.5`) | Sim | Requer Message, Entity API e CTools conforme página do projeto; comparar a instalação real e Mailer existente. |
| [Message Subscribe](https://www.drupal.org/project/message_subscribe) | 2.1.0 (`^2.1`) | Sim | Requer Message, Message Notify, Flag, Entity API e CTools; página declara busca por co-maintainer. |
| [Site Alert](https://www.drupal.org/project/site_alert) | 8.x-1.4 (`^1.4`) | Sim | Entidade com agenda/severidade e bloco; documentação descreve renderização AJAX sem cache. Conferir acesso/cache e integração de tema. |

Compatibilidade declarada significa que o release anuncia suporte ao Drupal 11.
Não é uma validação de execução deste projeto nem uma afirmação de compatibilidade
formal com PHP 8.5.

## Requisitos transitivos a confirmar pelo Composer

As páginas de Message Notify e Message Subscribe listam Message, Entity API,
CTools e Flag como requisitos relevantes. Site Alert descreve Datetime Range
para a linha Drupal 8+. A dependência direta e a possibilidade de remover
requisitos já satisfeitos por Core devem vir do `.info.yml`/Composer metadata
dos pacotes exatos; não adicionar esses pacotes por suposição. O `composer
require --dry-run` deve resolver cada combinação com `composer.lock` real antes
de alterar o lock. Não alterar `config/sync` ou ativar modules nesta fase.

## Inventário de APIs pendente

Como nenhum dos cinco pacotes está instalado nesta base e os arquivos raw do
Git Drupal não puderam ser consultados pelo navegador usado na auditoria, estes
itens ficam **não confirmados**; não inferir seus identificadores ou assinaturas:

| Módulo | Entities/fields | Services/events | Rotas/permissões | Config e access |
| --- | --- | --- | --- | --- |
| Private Message | Não confirmados | Não confirmados | Não confirmados | Não confirmados |
| Message | Não confirmados | Não confirmados | Não confirmados | Não confirmados |
| Message Notify | Não confirmados | Não confirmados | Não confirmados | Não confirmados |
| Message Subscribe | Não confirmados | Não confirmados | Não confirmados | Não confirmados |
| Site Alert | Entidade mencionada pela documentação, ID/fields não confirmados | Não confirmados | Rota administrativa e permissão de administrar alertas descritas; machine names não confirmados | Config instalada/schema não confirmados |

Esse inventário é gate da Fase 1: obter os pacotes por Composer, ler seus
metadados/código instalados e registrar os nomes exatos, as dependências
obrigatórias versus opcionais, entidades, serviços, rotas, permissões,
configuração, cacheability, hooks/events e riscos conhecidos antes de declarar
compatibilidade funcional.

## Fontes públicas consultadas

- [Private Message 3.0.7](https://www.drupal.org/project/private_message/releases/3.0.7) e [guia de instalação](https://www.drupal.org/docs/contributed-modules/private-message/installing-the-module).
- [Message](https://www.drupal.org/project/message), [Message Notify](https://www.drupal.org/project/message_notify) e [Message Notify 8.x-1.4](https://www.drupal.org/project/message_notify/releases/8.x-1.4).
- [Message Subscribe](https://www.drupal.org/project/message_subscribe) e [releases](https://www.drupal.org/project/message_subscribe/releases).
- [Site Alert](https://www.drupal.org/project/site_alert), [releases](https://www.drupal.org/project/site_alert/releases) e [issue queue](https://www.drupal.org/project/issues/site_alert).
- [Issue queue de Private Message](https://www.drupal.org/project/issues/private_message?categories=All&status=All), para alertas de compatibilidade atuais.
