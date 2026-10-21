# Visão geral

```text
Drupal Core + módulos funcionais
 User Profile Address Commerce LMS Group
 Node Taxonomy Comment Forum Views Search Domain
                    |
                    v
               aculta_portal
          integração e orquestração
                    |
                    v
               ACULTA420
       apresentação/design system
                    |
                    v
                Bootstrap5
```

## Regra arquitetural

`aculta_portal` integra capacidades existentes. Pode organizar rotas, Domain
purpose, conta, presenters, subscribers, adapters e integrações, mas não
substitui storage ou regras dos módulos especializados.

O tema `aculta420` cuida da apresentação. Não implementa autenticação,
pagamento, matrícula, progresso, autorização, fórum, Wiki ou integrações
externas.

ACULTA420 é a única linguagem visual pública do projeto. Bootstrap é
infraestrutura, não design system concorrente.

## Integrações externas

Integrações transversais ficam desacopladas do tema e documentadas em
[docs/integrations](../integrations/README.md).

## Hierarquia de kernels compartilhados

A descrição acima é uma visão técnica simplificada. A classificação normativa entre **Kernel ACULTA**, **subkernels independentes de Fórum/Galeria**, **consumidores funcionais** e **ACULTA Studio** está em [aculta-kernels.md](aculta-kernels.md). Não representa novas dependências de instalação. A Revista consome a Galeria; projetos são institucionais do domínio principal; eventos terão módulo próprio.
