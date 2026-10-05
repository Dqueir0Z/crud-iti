#!/usr/bin/env bash
# Teste de ponta a ponta do upload (login + importação) usando o usuário de demonstração.
set -euo pipefail
B=http://localhost:8080; J=$(mktemp)
csrf() { grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
T=$(curl -s -c $J -b $J $B/login | csrf)
curl -s -c $J -b $J -o /dev/null -w "login: %{http_code} -> %{redirect_url}\n" --data-urlencode "email=demo@crud-iti.test" --data-urlencode "senha=$CRUD_ITI_SENHA" --data-urlencode "csrf=$T" $B/login
T=$(curl -s -c $J -b $J $B/importar | csrf)
for ARQ in "$@"; do
  echo "== $ARQ"
  curl -s -c $J -b $J -w "\nHTTP %{http_code} em %{time_total}s\n" -F "csrf=$T" -F "arquivo=@$ARQ" $B/importar \
    | sed -n '/<main/,/<\/main>/p' | sed 's/<[^>]*>/ /g' | tr -s ' \n' | grep -E "Importação concluída|AC N2|^ ?AR |AC [0-9]|Vínculos|alert|inválid|HTML|extensão|HTTP" | head -12
  T=$(curl -s -c $J -b $J $B/importar | csrf)
done
rm -f $J
