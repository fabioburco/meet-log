# meet-log

Questo script PHP estrae i log di Google Meet per una data indicata in input e crea un Foglio Google contenente il riepilogo. Opzionalmente si può fornire l'elenco dei Meet interessati.

## Istruzioni per l'installazione

```
git clone https://github.com/fabioburco/meet-log
cd meet-log
cp meet.yaml.dist meet.yaml
composer install
```

### Creare account per le api

- login a https://console.developers.google.com/ come utente del dominio
- creare un nuovo progetto (interno) - esempio meet-log
- abilitare per il progetto le API: Google Sheets API // Google Drive API // Admin SDK
- creare un service account per il progetto e fornirgli la delega a livello di dominio
- scaricare la chiave privata e copiarla nella cartella col nome di ```googleAppsToken.json```


### Configurazione

- Modificare il file ```meet.yaml``` con i dati dell'installazione. In particolare:
  - indicare l'id della cartella condivisa dove creare i file di log
  - indicare l'id del foglio contenente la whitelist
  - indicare la mail dell'amministratore che verrà impersonato durante l'esecuzione dello script


- Per ottenere i log dello scorso giorno usare:
```php bin/console sync```

- Per ottenere i log di una data precisa:
```php bin/console sync <data>```

- Per ottenere i log di un Meet specifico usare:
```php bin/console sync <data> -m <meeting_code>```

- Se lo script funziona correttamente inserire in ```crontab -e``` il comando ```30 0 * * *  php <percorso assoluto>/bin/console sync```.

