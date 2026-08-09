#!/bin/bash
# Sauvegarde de la base de donnees (mysqldump), pensee pour tourner via cron sur le serveur
# de production. Les dumps sont ecrits dans documentation/backups/, un dossier volontairement
# hors Git (documentation/ est exclu du rsync de deploiement, voir
# .github/workflows/tests.yml "Synchroniser les fichiers vers Hostinger") : il ne vit que sur
# le serveur et n'est jamais ecrase par un deploiement.
#
# Credentials lus depuis .env.local (jamais committe, jamais copies dans ce script) : voir
# documentation/commandes.txt pour la regle "NE JAMAIS committer le mot de passe".
#
# Exemple de ligne crontab (toutes les 2 jours, 3h du matin) :
#   0 3 */2 * * /bin/bash APP_DIR/bin/backup-bdd.sh >> APP_DIR/documentation/backups/cron.log 2>&1
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$APP_DIR/.env.local"
BACKUP_DIR="$APP_DIR/documentation/backups"
RETENTION_JOURS=30

if [ ! -f "$ENV_FILE" ]; then
    echo "Fichier introuvable : $ENV_FILE" >&2
    exit 1
fi

DATABASE_URL="$(grep -m1 '^DATABASE_URL=' "$ENV_FILE" | cut -d'=' -f2- | tr -d '"')"
if [ -z "$DATABASE_URL" ]; then
    echo "DATABASE_URL introuvable dans $ENV_FILE" >&2
    exit 1
fi

# parse_url() gere un mot de passe contenant des caracteres speciaux, contrairement a un
# decoupage sed/cut naif sur le format mysql://user:pass@host:port/db.
read -r DB_USER DB_PASS DB_HOST DB_PORT DB_NAME <<EOF
$(php -r '
    $url = parse_url($argv[1]);
    echo $url["user"] . " " . rawurldecode($url["pass"] ?? "") . " " . $url["host"] . " " . ($url["port"] ?? 3306) . " " . ltrim($url["path"], "/");
' "$DATABASE_URL")
EOF

mkdir -p "$BACKUP_DIR"

DATE="$(date +%Y%m%d)"
DUMP_FILE="$BACKUP_DIR/${DATE}_backup.sql"

mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
    --default-character-set=utf8mb4 --single-transaction --quick \
    "$DB_NAME" > "$DUMP_FILE"

echo "Sauvegarde creee : $DUMP_FILE"

# Purge les dumps trop vieux pour ne pas remplir le quota disque de l'hebergement mutualise.
find "$BACKUP_DIR" -name '*_backup.sql' -mtime +"$RETENTION_JOURS" -delete
