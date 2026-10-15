# Backup e ripristino

I dati stanno nel database MySQL e in `storage/app/private` (allegati). Un backup utile copia entrambi.

## Database locale WAMP

```powershell
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysqldump.exe -u root --single-transaction --routines atlas_finance > atlas_finance.sql
```

Ripristino su un database vuoto:

```powershell
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -u root atlas_finance < atlas_finance.sql
```

Poi copia anche la cartella `storage\app\private\attachments`.

## Docker di produzione

MySQL non pubblica porte sull'host. Il dump esce dal container:

```powershell
docker compose -f compose.prod.yaml exec mysql mysqldump -u atlas -p --single-transaction atlas_finance > atlas_finance.sql
```

Il volume `atlas-mysql` contiene i file del database. Il volume `atlas-storage` contiene allegati e log applicativi. Per un ripristino, ricrea i container, importa il dump e rimetti i file di `storage`.

Dopo un ripristino:

```powershell
php artisan migrate --force
php artisan atlas:ledger-check
```

`atlas:ledger-check` controlla che ogni scrittura contabilizzata abbia dare e avere uguali. Lo scheduler la esegue ogni giorno alle 03:30.

Non mettere il dump in git e non loggare password o estratti conto.
