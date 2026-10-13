#!/usr/bin/env bash
# Verifica, com o Chromium headless, se as páginas de teste carregam.
# Uso: scripts/check-browser-hosts.sh [URL ...]
# Sem argumentos, usa os hosts de teste (*.aculta.toca.net.br) e example.com como controle.
# Saída: resumo no terminal e, por URL, o HTML e o stderr em um diretório temporário.

set -u

CHROME="${CHROME:-chromium}"
TIMEOUT="${TIMEOUT:-15}"
OUT_DIR="${OUT_DIR:-/tmp/aculta-browser-check-$(date +%Y%m%d-%H%M%S)}"

if ! command -v "$CHROME" >/dev/null 2>&1; then
  echo "Chromium não encontrado: defina CHROME=/caminho/do/navegador" >&2
  exit 2
fi

if [ "$#" -gt 0 ]; then
  URLS=("$@")
else
  URLS=(
    "https://example.com/"
    "https://aculta.toca.net.br/"
    "https://links.aculta.toca.net.br/"
    "https://conta.aculta.toca.net.br/entrar"
    "https://apoio.aculta.toca.net.br/"
  )
fi

mkdir -p "$OUT_DIR"
SUMMARY="$OUT_DIR/resumo.txt"
: > "$SUMMARY"

printf '%-45s %-6s %-8s %s\n' "URL" "EXIT" "BYTES" "TITULO" | tee -a "$SUMMARY"

for url in "${URLS[@]}"; do
  safe=$(printf '%s' "$url" | tr -c 'A-Za-z0-9' '_')
  html="$OUT_DIR/$safe.html"
  err="$OUT_DIR/$safe.err"

  # DBUS_SESSION_BUS_ADDRESS faz o Chromium travar neste ambiente (diagnosticado em 2026-10-12); removê-la só para o navegador.
  env -u DBUS_SESSION_BUS_ADDRESS timeout "$TIMEOUT" "$CHROME" --headless=new --no-sandbox --disable-gpu \
    --dump-dom "$url" >"$html" 2>"$err"
  code=$?

  bytes=$(wc -c <"$html")
  title=$(grep -o '<title>[^<]*' "$html" | head -n 1 | sed 's/<title>//')
  [ -z "$title" ] && title="-"

  printf '%-45s %-6s %-8s %s\n' "$url" "$code" "$bytes" "$title" | tee -a "$SUMMARY"
done

echo
echo "Código 124 = tempo esgotado ($TIMEOUT s). Código 0 com bytes > 0 = página carregou."
echo "Arquivos em: $OUT_DIR"
