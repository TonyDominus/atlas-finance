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
| Acquisto investimento | Investimenti | Conto finanziario | Invariato |
| Vendita investimento | Conto finanziario | Investimenti | Invariato, se non supera il registrato |
| Rettifica in aumento | Conto finanziario | Rettifiche | Aumenta |
| Storno | Righe invertite | Righe invertite | Annulla l'effetto |

Un trasferimento non passa dai conti di entrata o di spesa. La commissione collegata è un'altra scrittura, con `related_entry_id`.

Gli annullati e i loro storni restano nel libro, quindi i saldi tornano in pari, ma escono dai totali di entrate e spese.

La liquidità somma i conti correnti, di risparmio, carta, wallet e contanti inclusi nel patrimonio e non archiviati. I conti investimento restano nel patrimonio, non nella liquidità. Gli strumenti comprati stanno nel conto di sistema Investimenti.

## Schema principale

`users`, `institutions`, `accounts`, `ledger_accounts`, `journal_entries`, `journal_lines`, `categories`, `category_rules`, `counterparties`, `tags`, `reconciliation_records`, `attachments`.

Le regole di classificazione confrontano descrizione o controparte. «Netflix» propone Abbonamenti. La categoria scelta a mano ha la precedenza. Si può correggere dopo, senza riscrivere gli importi.

`external_id` è univoco per utente e origine (`manual`, `telegram`, `csv`, `api`). Gli import duplicati con lo stesso id vengono rifiutati.

## Confini applicativi

La logica sta in azioni e servizi, non nei controller:

- `ProvisionUserBooks`
- `CreateAccount`, `UpdateAccount`, `ReconcileAccount`
- `RecordMovement`, `UpdateMovementDetails`, `VoidMovement`
- `JournalPoster`, `BalanceReader`, `PortfolioReader`
- `FinanceAssistant` per saldo, conti, spesa, entrata, trasferimento e report

Un bot futuro chiama `FinanceAssistant`. Se il nome del conto non è univoco, il servizio rifiuta l'operazione invece di indovinare.

## Non ancora nel ledger

Prestiti, ricorrenze, budget, previsioni, import CSV e collegamenti bancari hanno il posto nel modello (conti prestiti, stato `forecast`, origine `csv`) ma non sono ancora operativi. Vedi `docs/STATUS.md`.

Non ci sono credenziali di home banking, né scraping, né API bancarie dichiarate disponibili.
