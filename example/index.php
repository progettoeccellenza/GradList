<?
print("<html>");
print("<head>");
print("<title>Graduatorie Progetto Eccellenza</title>");
print("</head>");
print("<body>");
print("<pre>");

include_once("GradList.php");

$generatedList = array(); // This array will contain temporary lists and then corrected lists
$classes = array(1,2,3,4,5); // Classes' IDs
foreach($classes as $c) {
  global $generatedList;
  $generatedList[$c] = GradList::getTempList($c); // Generates temporary graduated list for each class
}
$correctedLists = GradList::correctLists($generatedList); // Corrects all the graduated lists

// Let's print every graduated list divided in admitted and not admitted
foreach($correctedLists as $key => $list) {
  print("Ammessi Corso {$key}\n");
  $count = 1; // Used to keep trace of current student's number inside class' list
  foreach($list['admitted'] as $k => $student) {
    print("\t{$count} {$student['Name']} {$student['Surname']} {$student['Score']} {$student['Status']} {$student['Notes']}\n");
    $count++;
  }
  print("\n");
  print("Non-Ammessi Corso {$key}\n");
  $count = 1;
  foreach($list['not_admitted'] as $k => $student) {
    print("\t{$count} {$student['Name']} {$student['Surname']} {$student['Score']} {$student['Status']} {$student['Notes']}\n");
    $count++;
  }
  print("\n\n");
}
print("</pre>");
print("</body>");
print("</html>");
?>
