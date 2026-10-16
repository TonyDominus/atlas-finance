# Stato del progetto

Aggiornato al 16 ottobre 2026.

## Milestone 1 — operativa

Autenticazione, libri contabili per utente, conti, saldi ricostruiti, movimenti, categorie, controparti, riconciliazione, allegati, tema chiaro/scuro.

## Milestone 2 — operativa

Ricorrenze e canoni solo come previsione, prestiti con rimborsi su conti diversi, budget mensili, grafici e proiezione di cassa. Il form «Nuovo movimento» mostra i campi essenziali e nasconde il resto dietro «Avanzate».

Verificato con `php artisan test`: 55 test passati. Inclusi canone non contabilizzato alla scadenza, collegamento di una ricorrenza a un movimento senza doppio addebito, prestito da 200 € rimborsato su PayPal, HYPE, contanti e UniCredit senza aumentare il reddito, blocco del capitale oltre il residuo, budget, proiezione che ignora la scadenza di un prestito aperto.

Migrazioni applicate su MySQL `atlas_finance`. Build frontend `npm run build` riuscita.

## Milestone 3 — operativa

- Menu a tendina: tutti i `<select>` nativi sono sostituiti da `SelectField`, con lo stesso stile dei campi e la tastiera.
- Patrimonio diviso in Liquidità, Fermi e Investimenti (pagina `/patrimonio` e cruscotto). I conti di risparmio sono ora «fermi»: restano nel patrimonio, escono dalla liquidità e dalla previsione di cassa.
- Investimenti (`/investimenti`): acquisti e vendite di titoli con quantità, costo medio, commissione separata, risultato realizzato e non realizzato.
- Mercato (`/mercato`): titoli seguiti, prezzi inseriti a mano con data, grafico, variazione sul giorno precedente. Con `ALPHAVANTAGE_KEY` scarica ultima quotazione e storico giornaliero.
- La cancellazione dell'account dal profilo funziona anche con movimenti, prestiti e titoli.

Verificato con `php artisan test`: 73 test passati. Verificato nel browser con un utente temporaneo poi cancellato.

Limiti: niente cambi tra valute, dividendi e cedole non sono modellati, i prezzi non sono in tempo reale, senza chiave Alpha Vantage i prezzi si inseriscono a mano.

## Ancora da fare

1. Import CSV con anteprima e duplicati.
2. Bot Telegram, whitelist e conferma quando il messaggio è ambiguo. La logica richiamabile c'è già in `FinanceAssistant`.
3. Provider bancari, solo dove il supporto è verificato. Nessuno è attivo.

I dati dimostrativi restano nel seeder opzionale `DemoDataSeeder`.
