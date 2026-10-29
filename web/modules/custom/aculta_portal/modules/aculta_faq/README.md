# ACULTA FAQ

**Versão:** `0.1.0-rc.1`.

Fornece a página pública `/perguntas-frequentes`, o tipo de conteúdo de pergunta e o tipo de bloco editorial reutilizável.
Respostas e blocos aceitam somente o formato `aculta_editorial`: negrito, itálico, listas e links sanitizados pelo filtro
HTML do Drupal. Não habilita HTML livre, CSS, scripts nem mídia embutida.

O painel `/painel-administrativo/faq` exige `administer aculta faq`. A criação de conteúdo também exige as permissões
específicas do tipo e `use text format aculta_editorial`. Os defaults do módulo ficam em `config/install`; a configuração
ativa do tipo, campos, displays, bloco editorial e formato também é mantida em `config/sync` para que o ambiente possa ser
reconstruído sem remover o bundle ou seu conteúdo.

O conteúdo aprovado está em `docs/CONTEUDO-PERGUNTAS-FREQUENTES.md` e foi
cadastrado no Runtime de teste como seis perguntas públicas. Produção não foi
alterada. F1 cumpre os critérios do candidato RC; o próximo passo é aguardar
autorização para deploy/validação Alpha conforme o ciclo de release.

