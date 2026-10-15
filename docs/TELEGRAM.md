# Telegram

Il bot non è configurato. Non c'è un token e non c'è un processo in ascolto.

Le operazioni che il bot dovrà eseguire passano da `App\Services\Assistant\FinanceAssistant`, lo stesso strato usato dal web:

| Comando previsto | Metodo | Stato |
| --- | --- | --- |
| /saldo | `balance` | Disponibile |
| /conti | `accounts` | Disponibile |
| /spesa | `spend` | Disponibile |
| /entrata | `income` | Disponibile |
| /trasferimento | `transfer` | Disponibile |
| /report | `report` | Disponibile, mese corrente |
| /prestiti | `loans` | Risponde che la funzione non è ancora disponibile |
| /scadenze | `upcoming` | Risponde che le ricorrenze non sono ancora disponibili |

L'origine delle scritture create dall'assistente è `telegram`.

Se il nome del conto compare in più conti attivi, il servizio lancia `AmbiguousTargetException` con l'elenco. Il bot dovrà chiedere quale conto usare e non registrare nulla. Lo stesso vale se il conto non esiste.

La whitelist non è ancora una tabella. Quando il bot arriverà, gli aggiornamenti saranno accettati solo da `telegram_user_id` collegati all'utente dell'applicazione. Fino ad allora nessun messaggio esterno può scrivere nel ledger.

Esempi di messaggi futuri, dopo un parser dedicato:

- «Ho speso 12,50 euro al bar con HYPE.»
- «Ho prestato 100 euro a Luca da Intesa.» richiede i prestiti, oggi non disponibili.
- «Quanto ho speso in carburante questo mese?» richiede il report per categoria, oggi il report è solo il riepilogo del mese.

Il parser del linguaggio naturale non è incluso in questa versione, così una frase ambigua non diventa un movimento.
