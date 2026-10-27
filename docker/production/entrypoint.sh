#!/bin/sh
# Entrada da imagem de produção (E9). As variáveis de ambiente só existem no container: por
# isso, o cache da configuração, das rotas, dos eventos e das views é criado aqui, e não no build.
set -e

php artisan optimize --no-interaction

exec "$@"
