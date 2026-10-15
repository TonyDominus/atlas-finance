# Atlas Finance

Gestionale finanziario personale self-hosted. I saldi non sono un numero salvato a parte: si ricostruiscono dalle scritture in partita doppia.

Interfaccia in italiano, valuta predefinita EUR, fuso `Europe/Rome`.

## Requisiti locali

- PHP 8.3 con `pdo_mysql`, `bcmath`, `intl`, `mbstring`
- Composer 2
- Node.js 22
- MySQL 8 o superiore (in questo ambiente: MySQL 9.1 di WAMP)

Docker non è necessario per sviluppare su Windows. I file Compose servono per la VM Debian su Proxmox.

## Avvio su Windows

1. Avvia MySQL (WAMP). In questa macchina il servizio Windows richiede privilegi di amministratore; in alternativa il server può essere avviato con:

```powershell
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysqld.exe --defaults-file=C:\wamp64\bin\mysql\mysql9.1.0\my.ini
```

2. Crea il database, se non esiste:

```sql
CREATE DATABASE atlas_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Configura `.env` (già predisposto in locale su `root` senza password, database `atlas_finance`). Poi:

```powershell
composer install
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

In un altro terminale:

```powershell
php artisan serve
```

Apri `http://127.0.0.1:8000`, crea un account e aggiungi un conto. Non ci sono dati finanziari finché non li inserisci.

Coda e scheduler usano il driver `database`. In locale:

```powershell
php artisan queue:listen
php artisan schedule:work
```

`composer run dev` avvia server, coda, log e Vite insieme.

## Demo opzionale

Il seed predefinito non inserisce nulla.

```powershell
php artisan db:seed --class=DemoDataSeeder
```

Crea `demo@atlas.local` / `password` con alcuni conti e movimenti, solo se quell'utente non ha già conti. È un profilo locale di prova, non un account di produzione.

## Test

```powershell
php artisan test
```

I test usano SQLite in memoria. Non toccano il database MySQL di sviluppo.

## Docker

Sviluppo, con MySQL pubblicato solo su localhost porta 3307 per non scontrarsi con WAMP:

```powershell
docker compose up --build
```

Produzione sulla VM, senza porta MySQL verso l'host:

```powershell
docker compose -f compose.prod.yaml up -d --build
```

Prima imposta in `.env` di produzione `APP_ENV=production`, `APP_DEBUG=false`, `DB_HOST=mysql`, `DB_USERNAME=atlas`, `DB_PASSWORD` e `MYSQL_ROOT_PASSWORD`. L'app ascolta su `127.0.0.1:8080`. L'accesso remoto previsto è Tailscale, non una porta del database esposta su Internet.

Dopo il primo avvio del container di produzione:

```powershell
docker compose -f compose.prod.yaml exec app php artisan migrate --force
```

## Uso essenziale

- Un conto nuovo registra il saldo iniziale come scrittura di apertura.
- Una spesa addebita le spese e accredita il conto.
- Un'entrata addebita il conto e accredita le entrate.
- Un trasferimento sposta denaro tra due conti tuoi: il patrimonio non cambia. La commissione, se indicata, è una spesa separata.
- Un acquisto di investimento sposta liquidità verso gli strumenti. Non è una spesa di consumo.
- Annullare un movimento crea uno storno. L'originale resta nello storico.
- La riconciliazione confronta l'estratto con il libro. La rettifica viene scritta solo se la chiedi.

## Documentazione

- [Architettura e modello contabile](docs/ARCHITECTURE.md)
- [Stato del progetto](docs/STATUS.md)
- [Backup e ripristino](docs/BACKUP.md)
- [Integrazione Telegram](docs/TELEGRAM.md)
