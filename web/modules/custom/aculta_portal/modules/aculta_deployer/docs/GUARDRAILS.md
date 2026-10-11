# Guardrails

1. **Canonical com problema exige registro.** Qualquer página cujo canonical ou
   sitemap não aponte para produção, no servidor de testes, deve ter uma entrada no
   registro com `kind` `canonical` ou `sitemap`, o host atual e o host esperado em
   produção. A correção no código não é suficiente quando o canonical é gerado a
   partir do host da requisição.
2. **Entrada bloqueante impede o build de produção.** `build` recusa a execução com
   entradas `blocking: true` abertas. Só `--allow-open-blocking` contorna, e só para ensaio.
3. **Build fora do repositório.** O diretório de saída não pode ficar dentro do repositório.
4. **Nenhum host de teste em produção.** Aliases `*_toca_net_br` e `*_test_8080` são
   removidos; o restante do escopo tem o sufixo substituído.
5. **Fronteiras.** Tema e Portal (fora do submódulo) não referenciam a ferramenta.
   A ferramenta não depende de Drupal, Drush, vendor, tema ou Portal. Verifique com
   `boundaries` antes de cada alteração.
6. **Standalone.** A CLI roda sem Drupal. Qualquer dependência nova precisa de
   justificativa no `docs/` e de revisão do responsável.
7. **Sem credenciais.** A ferramenta não lê nem grava segredos. O registro não deve
   conter chaves, tokens ou senhas.
8. **Registro é a fonte única.** Correções de deploy não ficam espalhadas em
   comentários, planilhas ou dívidas soltas: ficam em `registry/deploy-registry.json`.
   A dívida técnica de código correspondente é registrada em
   `docs/operations/DEBT-REGISTER.md`.
9. **Indexação por ambiente.** Produção indexável em todos os domínios e subdomínios;
   servidor de testes com noindex. `build` recusa política de produção com noindex e
   `robots --env=production` é ensaio até a decisão do responsável de entregar em produção, no fim do projeto.
   Páginas privadas da conta mantêm noindex no Portal. O `robots --env=production` confere
   esses caminhos (`private_probes`) pelo cabeçalho ou pelo meta robots, e aceita 401, 403,
   404 ou 410. Não bloquear esses caminhos no `robots.txt`: um bloqueio impede o crawler de
   ler o noindex.
10. **Divergência entre ambientes passa pelo deployer.** Qualquer diferença de host, `base_url`,
   `robots.txt`, sitemap ou noindex entre o servidor de testes e a produção deve ser declarada em
   `config/deploy.json` e conferida por um comando do deployer (`sitemap`, `robots`, `verify`). Não
   se resolve por edição manual sem verificação. Um problema que o deployer não consegue
   verificar fica registrado em `registry/deploy-registry.json`.
11. **Credenciais nunca são impressas nem versionadas.** O relatório não contém credenciais. A ferramenta
   não importa, não exporta e não valida valores. Os valores ficam no arquivo de teste (modo sem escrita de
   grupo e sem acesso de outros) ou no banco criptografado de produção, ambos fora do Git.
