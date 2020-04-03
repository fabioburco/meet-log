# gsuite-team-sync

Questo script PHP prende un foglio gsuite spreadsheet condiviso e in base ai dati inseriti crea dei gruppi di email.

Esempio:

```
+-------------------------------+---------------------------------+-------------------------------+
| docenti.iti.gruppo1@issm.it   |   docenti.iti.gruppo2@issm.it   |   docenti@issm.it             |
+-------------------------------+---------------------------------+-------------------------------+
| a.gavagnin@issm.it            |   v.zen@issm.it                 |   docenti.iti.gruppo1@issm.it |
| v.zen@issm.it                 |   a.gavagnin@issm.it            |   docenti.iti.gruppo3@issm.it | 
|                               |   p.pellizzon@issm.it           |                               |
| m.cerchier@issm.it            |                                 |                               |
+-------------------------------+---------------------------------+-------------------------------+
			
```

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