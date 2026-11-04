#!/usr/bin/env bash
# Benchmark HTTP real contra Apache dentro del contenedor (evita el ruido del
# port-forward de Docker Desktop). Uso:
#   docker exec kpcrm-meridian-ui-app-1 bash docker/crm/bench/http-bench.sh [reps]
# Rutas protegidas sin sesión responden 302 -> /login, pero ejecutan el arranque
# completo de Laravel + middleware; sirven para medir el costo de bootstrap.
set -euo pipefail
REPS="${1:-8}"
BASE="${BENCH_BASE:-http://localhost}"
ROUTES=(/favicon.ico /login / /dashboard /clientes /clientes/22097866 /autorizacion /integracion/asignacion /reportes/pagos)

printf '%-26s %6s %9s %9s %9s %9s %9s\n' route code cold_ttfb warm_avg median max total_avg
for r in "${ROUTES[@]}"; do
  ttfbs=(); totals=(); code=""
  for i in $(seq 1 "$REPS"); do
    out=$(curl -s -o /dev/null -w '%{http_code} %{time_starttransfer} %{time_total}' "$BASE$r")
    code=${out%% *}; rest=${out#* }; ttfbs+=("${rest%% *}"); totals+=("${rest#* }")
  done
  printf '%s\n' "${ttfbs[@]}" > /tmp/ttfb.$$
  printf '%s\n' "${totals[@]}" > /tmp/tot.$$
  med=$(sort -n /tmp/ttfb.$$ | awk '{a[NR]=$1} END{print a[int((NR+1)/2)]}')
  awk -v r="$r" -v c="$code" -v med="$med" 'NR==FNR{t[NR]=$1; n=NR; next} {tot+=$1}
    END{cold=t[1]; s=0; for(i=2;i<=n;i++) s+=t[i]; wa=(n>1)?s/(n-1):cold;
        m=0; for(i=1;i<=n;i++){ if(t[i]>m)m=t[i] }
        printf "%-26s %6s %9.3f %9.3f %9.3f %9.3f %9.3f\n", r, c, cold, wa, med, m, tot/n}' /tmp/ttfb.$$ /tmp/tot.$$
  rm -f /tmp/ttfb.$$ /tmp/tot.$$
done
