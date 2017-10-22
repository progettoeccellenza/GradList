# Informazioni sul Dataset di Esempio
[![Version](https://img.shields.io/badge/version-1.0-green.svg)]()  
Questo dataset di esempio viene rilasciato per facilitare l'esecuzione di GradList senza dover usare un database MySQL.
## Formato
Il dataset viene fornito in formato Excel (*.xlsx*) e in formato CSV (*.csv*) per agevolarne l'utilizzo
## Struttura
Il dataset contiene *350* utenti divisi in *3* scuole ed iscritti a *5* corsi:
- _79 studenti_ per la scuola 1
- _123 studenti_ per la scuola 2
- _148 studenti_ per la scuola 3
  
I punteggi assegnati variano fra *30, 35, 40, 50, 55, 65, 75, 80, 85, 90, 98, 100*  
Non c'è suddivisione per classi: tutti gli studenti sono assegnati alla 1A

## Importazione
Per importare un file *.csv* con PHP mantendendo i valori della prima riga come chiavi di un array associativo si può usare il seguente codice:  
```PHP
// You're supposed to define $path_example_dataset = "/path/to/example_dataset.csv"
$csv_example_dataset = array_map('str_getcsv', file($path_example_dataset));
array_walk($csv_example_dataset, function(&$a) use ($csv_example_dataset) {
  $a = array_combine($csv_example_dataset[0], $a);
});
// Remove first row, used as header
array_shift($csv);
```
Utilizzare PHP >= 5.3 per eseguire questo codice in quanto il callback di *array_walk()* è una closure.  
