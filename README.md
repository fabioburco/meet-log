# meet-log

Questo script PHP estrae i log di google meet e per ogni giorno crea un foglio google spreadsheet dentro una cartella.

## Istruzioni per l'installazione

```
git clone https://gitlab.com/consorzisdb/gsuite-team-sync.git			
cd gsuite-team-sync.git
cp teams.yaml.dist teams.yaml
composer install
```

- Modificare il file ```teams.yaml``` con i dati dell'installazione.
- Lanciare lo script con il comando:
```php bin/console sync```
- Al primo avvio inserire i dati mancanti del token come indicato.


- Eventualmente inserire nel ```contab -e``` il comando ```@hourly php <percorso assoluto>/bin/console sync```.

 
## Nota importante
Lo script non cancella i gruppi che non sono più presenti. Ad esempio se aggiungo il gruppo A nel foglio condiviso e poi tolgo l'intera colonna, il gruppo A rimane in Gsuite.