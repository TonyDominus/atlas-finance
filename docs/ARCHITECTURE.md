# Architettura

## Decisioni

- Laravel 13, Inertia, Vue 3 con Composition API e TypeScript, Tailwind, Pest, MySQL.
- L'interfaccia usa componenti propri vicini a shadcn (form, pannelli, stati vuoti, tema chiaro/scuro). shadcn-vue non è stato aggiunto: il suo kit ufficiale è ancora agganciato a Laravel 12 e avrebbe duplicato lo stack senza cambiare il modello contabile.
- Gli importi sono interi nell'unità minima della valuta (`amount_minor`). EUR 12,50 è `1250`. Niente `float`. Le valute diverse non vengono sommate e non esiste un cambio inventato.
- Il saldo di un conto è la somma delle righe del suo conto patrimoniale. Non c'è una colonna di saldo aggiornata a parte. `opening_balance_minor` conserva solo il dato dell'apertura, già registrato nel ledger.
- Ogni scrittura contabilizzata ha almeno due righe e dare uguale avere, nella stessa valuta. Le operazioni multi-riga stanno in una transazione.
- Le modifiche di importo, conto o data non si fanno sul posto. Si annulla con storno e si registra di nuovo. Descrizione, categoria, controparte, tag e note si possono correggere.
- I conti contabili di sistema (spese, entrate, commissioni, interessi, investimenti, crediti e debiti per prestiti, patrimonio iniziale, rettifiche) sono distinti dai conti finanziari. La categoria è un'etichetta analitica, non una seconda fonte del saldo.
- Ogni tabella finanziaria ha `user_id`. Le query e le policy rifiutano i dati di un altro utente. Un id altrui risponde 404.

## Scritture

| Operazione | Dare | Avere | Effetto sul patrimonio |
| --- | --- | --- | --- |
| Saldo iniziale positivo | Conto finanziario | Patrimonio iniziale | Aumenta |
| Spesa | Spese | Conto finanziario | Diminuisce |
| Commissione | Commissioni bancarie | Conto finanziario | Diminuisce |
| Entrata | Conto finanziario | Entrate, oppure interessi se la categoria è Interessi | Aumenta |
| Rimborso di un acquisto | Conto finanziario | Spese | Aumenta, senza contare come ricavo |
| Trasferimento | Conto destinazione | Conto origine | Invariato |
| Acquisto titolo | Investimenti, al controvalore | Conto di regolamento | Invariato |
| Vendita titolo | Conto di regolamento, al ricavo | Investimenti, al costo medio delle quote vendute | Varia del risultato |
| Plusvalenza realizzata | — | Risultati da investimenti | Aumenta |
| Minusvalenza realizzata | Risultati da investimenti | — | Diminuisce |
| Rettifica in aumento | Conto finanziario | Rettifiche | Aumenta |
| Storno | Righe invertite | Righe invertite | Annulla l'effetto |

Un trasferimento non passa dai conti di entrata o di spesa. La commissione collegata è un'altra scrittura, con `related_entry_id`.

Gli annullati e i loro storni restano nel libro, quindi i saldi tornano in pari, ma escono dai totali di entrate e spese.

## Sezioni del patrimonio

Ogni conto sta in una sezione: Liquidità, Fermi o Investimenti. Se `accounts.wealth_bucket` è vuoto vale la tipologia: corrente, carta, wallet e contanti sono liquidità; risparmio e altro sono fermi; investimento è investimenti. Si può scegliere a mano dal form del conto.

Solo la liquidità entra nella cassa, nella previsione e nella serie di liquidità dei grafici. Fermi e investimenti restano nel patrimonio netto. I conti esclusi dal patrimonio o archiviati non contano.

## Titoli

`instruments` descrive il titolo (simbolo, ISIN, tipo, valuta, borsa, simbolo per il fornitore). `security_trades` lega ogni acquisto o vendita alla sua scrittura. Quantità e prezzi sono interi con 6 decimali impliciti; i calcoli usano bcmath con arrotondamento a metà verso l'alto.

- La posizione si ricostruisce dalle operazioni attive, con il costo medio ponderato.
- La commissione è una scrittura separata su Commissioni bancarie, collegata con `related_entry_id`. Non entra nel costo del titolo.
- Non si vende più di quanto si ha. Le operazioni di una posizione vanno in ordine di data e si annulla solo l'ultima, dalla pagina Investimenti. L'annullo generico dei movimenti rifiuta le scritture dei titoli.
- La valuta del titolo deve essere quella del conto di regolamento: non c'è cambio.
- Il risultato realizzato va sul conto di patrimonio Risultati da investimenti, fuori da entrate e spese del mese.
- La parte del conto Investimenti non attribuita a un titolo (movimenti generici di investimento) resta visibile come «non assegnato».

## Quotazioni

`instrument_prices` tiene un prezzo per titolo, giorno e fonte. Le fonti sono `manual` e `alphavantage`. Si mostra un prezzo per giorno: se due fonti differiscono, vale l'ultimo salvato. Il valore stimato usa l'ultimo prezzo; un titolo senza prezzo è contato al costo e lo si dice.

Alpha Vantage (GLOBAL_QUOTE e TIME_SERIES_DAILY) si attiva con `ALPHAVANTAGE_KEY` nel `.env`. Senza chiave non parte nessuna chiamata. `atlas:fetch-quotes` gira nei giorni feriali alle 23:15 e senza chiave non fa nulla. I dati non sono in tempo reale.

## Schema principale

`users`, `institutions`, `accounts`, `ledger_accounts`, `journal_entries`, `journal_lines`, `categories`, `category_rules`, `counterparties`, `tags`, `reconciliation_records`, `attachments`, `recurring_rules`, `recurring_occurrences`, `budgets`, `loans`, `loan_disbursements`, `loan_repayments`, `loan_allocations`, `instruments`, `security_trades`, `instrument_prices`.

Le righe contabili e i prestiti impediscono di cancellare i conti a cui puntano. La cancellazione dell'utente passa da `DeleteUser`, che rimuove prima prestiti e scritture e poi il resto, in una transazione.

Le regole di classificazione confrontano descrizione o controparte. «Netflix» propone Abbonamenti. La categoria scelta a mano ha la precedenza. Si può correggere dopo, senza riscrivere gli importi.

`external_id` è univoco per utente e origine (`manual`, `telegram`, `csv`, `api`). Gli import duplicati con lo stesso id vengono rifiutati.

## Confini applicativi

La logica sta in azioni e servizi, non nei controller:

- `ProvisionUserBooks`
- `CreateAccount`, `UpdateAccount`, `ReconcileAccount`
- `RecordMovement`, `UpdateMovementDetails`, `VoidMovement`
- `JournalPoster`, `BalanceReader`, `PortfolioReader`
- `LoanBook`, `CashFlowForecast`, `AnalyticsReader`
- `TradeBook`, `SaveInstrument`, `RecordPrice`, `PositionReader`, `AlphaVantageQuotes`
- `DeleteUser`
- `FinanceAssistant` per saldo, conti, spesa, entrata, trasferimento e report

Un bot futuro chiama `FinanceAssistant`. Se il nome del conto non è univoco, il servizio rifiuta l'operazione invece di indovinare.

## Non ancora operativi

Import CSV e collegamenti bancari hanno il posto nel modello (origine `csv`, `api`) ma non sono attivi. Vedi `docs/STATUS.md`.

Non ci sono credenziali di home banking, né scraping, né API bancarie dichiarate disponibili.
