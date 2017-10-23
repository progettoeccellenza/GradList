# GradList
[![Version](https://img.shields.io/badge/version-1.4-green.svg)]() [![PHPVersion](https://img.shields.io/badge/php-5.3-blue.svg)]()  
## Di cosa si tratta
Questo script viene utilizzato per generare automaticamente le Graduatorie di Progetto Eccellenza.   
_NB:_ Il codice viene pubblicato per trasparenza e perché possa essere riutilizzato in altri progetti simili a questo.  
Sentiti libero di aprire un [issue](https://github.com/progettoeccellenza/GradList/issues) nel caso dovessi trovare errori o bug nel codice: siamo umani anche noi e possiamo sbagliare!  

## Descrizione dei Files:
- _src/GradList.php:_ sorgente di GradList
- _example/index.php:_ esempio di utilizzo di GradList
- _example/dataset/example_dataset.csv:_ dataset di esempio da utilizzare per un'installazione locale ([maggiori informazioni sul dataset di esempio](https://github.com/progettoeccellenza/GradList/tree/master/example/dataset))

#### Nota rispetto alla versione utilizzata da Progetto Eccellenza
Ci sono alcune differenze rispetto alla versione pubblicata qui e quella utilizzata effettivamente dal server.  
Le modifiche apportate non influenzano il risultato dell'esecuzione e servono solo per migliorare la leggibilità del codice.  
Principali differenze:
- I nomi delle tabelle sul server e la loro struttura è differente da quella qui proposta.
- Gli array nei cicli *foreach()* di *GradList::correctLists()* vengono passati per riferimento nella versione sul server.

## Installazione
Per poter utilizzare lo script in locale è necessario installare PHP versione < 7.0, in quanto vengono utilizzate le funzioni *mysql_*, non presenti nelle versioni successive.  
L'array generato da *GradList::getTempList()* può essere riprodotto importando il dataset di esempio presente in *example/dataset/example_dataset.csv*.  
Si può quindi costruire direttamente l'array da passare a *GradList::correctLists()* che deve avere la seguente struttura:
```
Array {
  Corso1 : Array {
    admitted: Array {
      [0] : Array {
        ID: [codicescuola_codiceutente],
        f_c: [prima_scelta],
        s_c: [seconda_scelta],
        Name: [nome],
        Surname: [cognome],
        Class: [classe],
        School: 0,
        Notes: ["" oppure "Seconda Scelta"]
        Score: [punteggio]
      },
      [1] : Array {
        ...
      }
      ...
    },
    not_admitted: {
      ...
    },
    max_admitted: [massimo numero di ammessi per il corso]
  },
  Corso2 : Array {
    ...
  },
  ...
}
```
Utilizzare infine il codice presente in */example/index.php* per testare il funzionamento dello script.  
#### Nota
Importare un file *.csv* con PHP mantendendo i valori della prima riga come chiavi di un array associativo:  
```PHP
// You're supposed to define $path_example_dataset = "/path/to/example_dataset.csv"
$csv_example_dataset = array_map('str_getcsv', file($path_example_dataset));
array_walk($csv_example_dataset, function(&$a) use ($csv_example_dataset) {
  $a = array_combine($csv_example_dataset[0], $a);
});
// Remove first row, used as header
array_shift($csv_example_dataset);
```
Utilizzare PHP >= 5.3 per eseguire questo codice in quanto il callback di *array_walk()* è una closure.  
