# Stato del progetto

Aggiornato al 16 ottobre 2026.

## Milestone 1 — operativa

Autenticazione, libri contabili per utente, conti, saldi ricostruiti, movimenti, categorie, controparti, riconciliazione, allegati, tema chiaro/scuro.

Verificato con `php artisan test`: 44 test passati, inclusi apertura, spesa, entrata, trasferimento con commissione, classificazione Netflix, id esterno duplicato, rimborso che non è un ricavo, storno, investimento che non è una spesa, vendita oltre il registrato, riconciliazione, scrittura non bilanciata, isolamento tra utenti, ambiguità del conto per l'assistente.

Migrazioni applicate su MySQL `atlas_finance`. Build frontend `npm run build` riuscita.

Verifica nel browser su `http://127.0.0.1:8000`: registrazione, conto Intesa Sanpaolo con saldo iniziale 1.000,00 €, spesa «Canone Netflix» da 15,99 € classificata in Abbonamenti, dashboard con liquidità e patrimonio 984,01 € e spese del mese 15,99 €, tema chiaro e scuro.

## Ancora da fare

1. Ricorrenze e canoni: previsione senza contabilizzare da soli alla scadenza.
2. Prestiti concessi e ricevuti, rimborsi su conti diversi, interessi distinti dal capitale.
3. Budget mensili per categoria e confronti sullo storico reale.
4. Dashboard con grafici, filtri di analisi e proiezione di cassa etichettata come stima.
5. Import CSV con anteprima e duplicati.
6. Bot Telegram, whitelist e conferma quando il messaggio è ambiguo. La logica richiamabile c'è già in `FinanceAssistant`.
7. Provider bancari, solo dove il supporto è verificato. Nessuno è attivo.

I dati dimostrativi restano nel seeder opzionale `DemoDataSeeder`.
