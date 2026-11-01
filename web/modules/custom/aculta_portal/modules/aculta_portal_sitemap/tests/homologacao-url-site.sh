#!/usr/bin/env bash
# Homologação 0.1.0-J: regressão de cache por url.site.
# Alterna os hosts de cada purpose várias vezes e confere que título, canonical e
# meta robots de cada host são iguais à linha de base (primeira passagem).
# Uso: tests/homologacao-url-site.sh [CICLOS] [IP]   (padrão: 5 ciclos, 127.0.0.1)
# Somente GET. Servidor de testes (*.aculta.toca.net.br); não é o servidor de produção.
set -u
CYCLES="${1:-5}"
IP="${2:-127.0.0.1}"
BASE="toca.net.br"
HOSTS=("aculta" "apoio.aculta" "wiki420.aculta" "revista420.aculta" "cursos.aculta" "conta.aculta")
PATHS=("/" "/entrar")
fail=0

sig() {
  local host="$1" path="$2" body
  body=$(curl -sk --max-time 20 --resolve "$host.$BASE:443:$IP" "https://$host.$BASE$path" 2>/dev/null) || body=""
  local title canon robots
  title=$(printf '%s' "$body" | grep -oiE '<title>[^<]*' | head -1)
  canon=$(printf '%s' "$body" | grep -oiE '<link rel="canonical" href="[^"]*"' | head -1)
  robots=$(printf '%s' "$body" | grep -oiE '<meta name="robots"[^>]*>' | head -1)
  printf '%s|%s|%s' "$title" "$canon" "$robots"
}

declare -A BASELINE
for h in "${HOSTS[@]}"; do
  for p in "${PATHS[@]}"; do
    BASELINE["$h$p"]=$(sig "$h" "$p")
  done
done

for ((c = 1; c <= CYCLES; c++)); do
  for h in "${HOSTS[@]}"; do
    for p in "${PATHS[@]}"; do
      got=$(sig "$h" "$p")
      if [ "$got" != "${BASELINE["$h$p"]}" ]; then
        echo "FAIL ciclo $c $h.$BASE$p divergiu da linha de base"
        echo "  esperado: ${BASELINE["$h$p"]}"
        echo "  obtido:   $got"
        fail=1
      fi
    done
  done
done

# Expectativas de robots (0.1.0-H/Portal 0.2.0-dev.8): raiz da WIKI e de CURSOS indexáveis;
# /entrar com noindex; raiz do APOIO sem noindex.
expect() { # host path want(noindex|none)
  local got
  got=$(sig "$1" "$2")
  case "$got" in
    *'content="noindex'*) state=noindex ;;
    *) state=none ;;
  esac
  if [ "$state" != "$3" ]; then echo "FAIL $1.$BASE$2 robots=$state, esperado $3"; fail=1; else echo "PASS $1.$BASE$2 robots=$state"; fi
}
expect "wiki420.aculta" "/" none
expect "cursos.aculta" "/" none
expect "apoio.aculta" "/" none
expect "conta.aculta" "/entrar" noindex

echo "homologação url.site ($CYCLES ciclos, ${#HOSTS[@]} hosts, ${#PATHS[@]} caminhos): $([ $fail -eq 0 ] && echo PASS || echo FAIL)"
exit $fail
