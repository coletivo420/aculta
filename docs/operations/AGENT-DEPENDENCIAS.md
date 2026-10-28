# Bibliotecas e dependências — regra para agentes

Vale para todos os agentes (Claude, Codex, DeepSeek). Complementa `AGENTS.md`; não repete as regras gerais.

## Quando se aplica

Toda vez que uma entrega **adiciona, troca ou atualiza** uma dependência do projeto:

- pacote Composer (`composer.json`), inclusive módulo Drupal core/contrib;
- pacote npm do tema (`web/themes/custom/aculta420/package.json`);
- biblioteca JavaScript ou CSS copiada para dentro de um módulo ou do tema;
- SDK ou biblioteca carregada de CDN (`sdk.mercadopago.com`, etc.);
- ferramenta de desenvolvimento usada pelo projeto (Drush, ESLint, Stylelint, PHPUnit).

Dependência transitiva não entra na tabela: registre só o que o projeto declara ou usa diretamente.

## O que atualizar, no mesmo commit

1. **`README.md` da raiz, seção `## Bibliotecas usadas`.**
   - Escolha a tabela certa: `### Módulos Drupal`, `### Bibliotecas PHP e integrações`,
     `### Front-end (JavaScript e CSS)` ou `### Ferramentas de desenvolvimento`.
   - Preencha as quatro colunas: **Biblioteca**, **Versão declarada**, **Origem**, **Uso no projeto**.
   - A versão é a do manifesto (`composer.json` / `package.json`), não a que o agente lembra. Confirme com
     `composer show <pacote>` ou `npm ls <pacote>` quando houver dúvida.
   - Se a biblioteca estava em **"Planejado, ainda não instalado"**, mova-a para a tabela ao instalar.
   - Se só a versão mudou e a tabela cita a versão, atualize-a.
2. **`README.md` do módulo ou tema que passou a depender dela.** Diga por que a dependência entrou, como é
   carregada (serviço, biblioteca, plugin, CDN) e o que deixa de funcionar sem ela. Se o módulo não tem README,
   crie a seção de dependências nele.
3. **Licença.** Confirme que a licença é compatível com GPLv3 antes de adicionar. Registre a origem e a licença
   na linha da tabela quando não for óbvia. Dúvida de licença: pare e pergunte ao responsável.

## Regras de precisão

- Não copie versão de memória, de release notes ou de outro projeto: leia o manifesto instalado.
- Não registre pacote que não está no manifesto nem é carregado pelo código.
- Não deixe a lista de "Planejado" crescer: ao instalar, mova para a tabela.
- Atualização de patch que não muda a versão declarada não exige nova linha; se a tabela cita a versão exata,
  ela precisa mudar.

## Verificação antes do commit

- `git diff --stat` deve mostrar o README da raiz e o README do módulo/tema junto com o manifesto.
- Se o diff adicionou uma dependência e não tocou em nenhum README, o commit está incompleto: corrija antes de
  commitar.
- Cite as bibliotecas na mensagem do commit, por exemplo:
  `feat(modulo): usar <biblioteca> para <uso>` e, no corpo, `Bibliotecas: <nome> <versão> (README atualizado)`.

## Exemplo de linha

```markdown
| `rlanvin/php-rrule` | ^3.0 | Composer | Recorrência de eventos (`aculta_calendar`, D-14, D-16) |
```
