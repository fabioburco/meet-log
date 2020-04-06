# meet-log

Questo script PHP estrae i log di google meet e per ogni giorno crea un foglio google spreadsheet dentro una cartella.

## Istruzioni per l'installazione

```
git clone https://gitlab.com/consorzisdb/meet-log.git
cd gmeet-log
cp meet.yaml.dist meet.yaml
composer install
```

### Creare account per le api

- login a https://console.developers.google.com/ come utente del dominio
- creare un nuovo progetto (interno) - esempio meet-log
- abilitare per il progetto le API: Google Sheets API // Google Drive API // Admin SDK
- creare le credenziali di tipo ID client OAuth 2.0


### Configurazione

- Modificare il file ```meet.yaml``` con i dati dell'installazione. In particolare:
  - indicare l'id del folder condivos dove creare i file di log
  - secrete e key delle credenziali ID client OAuth 2.0


- Lanciare lo script con il comando:
```php bin/console sync```
- Al primo avvio inserire i dati mancanti del token come indicato.

- Lanciare lo script con il comando:
```php bin/console sync```
- Verificare che nella cartella venga creato un file corrispondente alla data odierna.
- Se lo script funziona correttamente inserire nel ```contab -e``` il comando ```* * * * *  php <percorso assoluto>/bin/console sync```.

