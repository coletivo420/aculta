# ADR-006: Apache como baseline definitivo

Status: Superseded by [ADR-010](ADR-010-hostinger-litespeed.md)

Data da revisão: 2026-10-06

## Contexto

A decisão original registrava Nginx no Homelab e Apache na produção Hostinger.
O servidor de desenvolvimento foi posteriormente migrado para Apache para
reduzir diferenças operacionais relevantes entre desenvolvimento e produção.

## Decisão

Apache é o baseline definitivo de servidor web do ACULTA.

Desenvolvimento/Homelab usa Debian + Apache + PHP-FPM + MariaDB.

Produção Hostinger usa Apache + PHP + MariaDB, com configuração própria de
hospedagem.

O projeto não mantém compatibilidade operacional com Nginx. Novos recursos,
testes, exemplos e documentação normativa devem partir de Apache.
Configuração Nginx remanescente pode ser preservada apenas como evidência
histórica de migração até sua limpeza segura.

Usar o mesmo servidor web não torna as configurações intercambiáveis.
VirtualHosts, aliases, módulos, permissões, integração PHP, certificados,
redirects, headers e proteção de arquivos continuam específicos de cada
ambiente.

O Drupal deve manter seu `.htaccess` e o deploy deve validar pelo menos
`mod_rewrite`, `mod_headers`, `AllowOverride`, integração PHP-FPM e proteção
de arquivos no ambiente alvo.

O tema `aculta` e os módulos custom não devem depender de comportamento
exclusivo de uma configuração Apache local.

## Consequências

- o Homelab pode validar comportamento Apache antes do deploy;
- não é mais necessário traduzir `location`, `try_files` ou `add_header`
  de Nginx para Apache;
- configurações do Virtualmin e da Hostinger não devem ser copiadas
  literalmente entre si;
- testes de segurança que antes aceitavam uma exceção porque Nginx ignorava
  `.htaccess` precisam ser reavaliados sob Apache;
- um processo Nginx ativo no Homelab deve ser tratado como regressão do
  baseline atual.

## Consequência histórica

Registros de testes executados antes da migração podem mencionar Nginx
corretamente como estado daquele momento. Esta ADR substitui a decisão
arquitetural anterior para o estado atual; não é necessário reescrever
evidência histórica apenas para trocar o nome do servidor.

## Não objetivos

Esta decisão não altera o banco de desenvolvimento, o Sistema de Estados,
a arquitetura Domain, a produção MariaDB, a refatoração do tema ou o
roadmap futuro de migração do Runtime do Homelab para MariaDB.
