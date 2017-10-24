<?
//
//  GradList.php
//  Generazione Graduatorie Progetto Eccellenza
//
//  Created by Vittorio Grasso for Progetto Eccellenza on 10/09/17.
//  Copyright © 2017 Vittorio Grasso. All rights reserved.
//

/**
  * @class GradList
  * @desc Abstract class that generates Graduated Lists for users in a given dataset
  * @required
  *   dbConnectP(): returns a persistent connection to MySQL database
  *   e.g. return mysql_pconnect("host","username","password","database");
  * @version 1.4 2017-10-20
  * @php-version 5.3
*/
abstract class GradList {
  /**
    * @var $debugOutput
    * @desc It contains debug text generated throughout the code
  */
  public static $debugOutput = "";
  /**
    * @method getTempList()
    * @desc This method generates temporary Graduated List including everyone who had chosen the class given as first or second choice
    * @param $class: given Class ID (as could be found in ID row in database)
    * @param $max: Maximum number of admitted students for this class (as could be found in MaxSubs row in the database)
    * @return $retArray: contains temporary graduated list divided in admitted students and not admitted students
  */
  public static function getTempList($class, $max) {
    self::$debugOutput .= "Getting List for {$class}...\n";
    /*
      * @function dbConnectP()
      * @desc This function returns a persistent connection to MySQL database
      * e.g. return mysql_pconnect("host","username","password","database");
    */
    $DB = dbConnectP();
    $students = array(); // This array will be filled up with the graduated list. Every element will be a student.
    /*
      * @query Get from database students' list with current class ($class) as first or second choice
      * @table subscriptions_table: contains users' subscription details.
      * @param f_c: first choice class ID
      * @param s_c: second choice class ID
    */
    $subscriptions_table = mysql_query("SELECT * FROM subscriptions_table WHERE f_c = {$class} OR s_c = {$class}");
    while($subscription = mysql_fetch_array($subscriptions_table)) {
      /*
        * @param CID
        * @desc It contains current user ID formatted as [schoolcode]_[studentcode]
        *  @param schoolcode: identificates the school in database
        *  @param studentcode: identificates the student in his school's registry
      */
      $subscription['CID'] = explode("_", $subscription['CID']);
      /*
        * @query Get from database student personal information
        * @table registry_[school]: contains users' personal details.
        * @param ID: current user's ID (without "[schoolcode]_")
      */
      $student_registry = mysql_fetch_array(mysql_query("SELECT * FROM registry_".($subscription['CID'][0] == 1 ?  "School1" : ($subscription['CID'][0] == 2 ? "School2" : "School3"))." WHERE ID='{$subscription['CID'][1]}'"));
      $student['ID'] = implode("_", $subscription['CID']);
      $student['f_c'] = $subscription['f_c'];
      $student['s_c'] = $subscription['s_c'];
      $student['Name'] = $student_registry['Name'];
      $student['Surname'] = $student_registry['Surname'];
      $student['Class'] = $student_registry['Class'];
      $student['School'] = $student_registry['School'];
      $student['Status'] = 0;
      $student['Notes'] = ($subscription['f_c'] != $class ? "Seconda Scelta" : ""); // Annotation for second choice-users
      /*
        * @function Register::getAverageMark()
        * @desc Calculate school performace's score
        * @param $average: average of student's marks (approximated)
        * @param $promotion: student's promotion status (1 = passed, 2 = "debts", 3 = failed)
      */
      /*
        * @param f_c_mark or s_c_mark
        * @desc Interview score for first or second choice
      */
      /*
        * @param lett_f_c_mark or lett_s_c_mark
        * @desc Motivational Letter score
      */
      $student['Score'] = ($subscription['f_c'] != $class ? $subscription['s_c_mark'] : $subscription['f_c_mark']) + ($subscription['f_c'] != $class ? $subscription['lett_s_c_mark'] : $subscription['lett_f_c_mark']) + Register::getAverageMark($student_registry['Average'], $student_registry['Promotion']);
      // If student was not present to the interview or had not compiled the Motivational Letter, he is excluded from the Graduated List
      // This happens either for first or second choice
      if($subscription['f_c'] == $class && ($subscription['f_c_mark'] == 0 || $subscription['lett_f_c_mark'] == 0)) {
        self::$debugOutput .= "\tExcluding first choice {$student['ID']} {$student['Name']} {$student['Surname']}...\n";
        continue;
      }
      if($subscription['s_c'] == $class && ($subscription['s_c_mark'] == 0 || $subscription['lett_s_c_mark'] == 0)) {
        self::$debugOutput .= "\tExcluding second choice {$student['ID']} {$student['Name']} {$student['Surname']}...\n";
        continue;
      }
      self::$debugOutput .= "\tIncluding {$student['Name']} {$student['Surname']}...\n";
      array_push($students, $student);
    }
    // This is a workaround to sort students by score, since PHP 5.3 does not support spaceship operator (<=>)
    // Students are sorted in decreasing order
    usort($students, function ($item1, $item2) {
        if ($item1['Score'] == $item2['Score']) return 0;
        return $item1['Score'] > $item2['Score'] ? -1 : 1;
    });
    $admitted = array_slice($students, 0, $max);
    $not_admitted = array_slice($students, $max);
    mysql_close($DB);
    /*
      * @array $retArray
      * @desc It contains the temporary list
      * @key admitted: array of the first $max students admitted
      * @key not_admitted: array of the last students (after $max element) not admitted
      * @key max_admitted: $max students
    */
    $retArray = array(admitted => $admitted, not_admitted => $not_admitted, max_admitted => $max);
    self::$debugOutput .= "\n\n";
    return $retArray;
  }

  /**
    * @method correctLists()
    * @desc This method adjust students' position iterating on every list.
    *   e.g. removes student from second choice's list if he had been admitted in his first choice.
    * @param $provList: array containing all the temporary graduated list generated by getTempList()
    * @return $provList: corrected (and final) Graduated List
  */
  public static function correctLists($provList) {
    $changes = 0; // If there were changes in a graduated list (e.g. students admitted or removed) this is set to 1
    $repeat_after_zeros = 3; // This will be commented later in the code
    // Iterate through the lists since there will not be changes
    do {
      $changes = 0; // Clean up value after start
      self::$debugOutput .= "There're some changes... iterating again...\n";
      foreach($provList as $class_key => $classList) {
        self::$debugOutput .= "\tIterating Class {$class_key}...\n";
        // If the number of admitted students does not fit the maximum number of admittable students for this class, this code will add someone
        while(count($classList['admitted']) <= ($classList['max_admitted']-1)) {
          self::$debugOutput .= "\tThere're ".count($classList['admitted'])." students, less than ".$classList['max_admitted'].", in this List: let's add someone...\n";
          // If there are not other not-admitted students the code stops there
          if(count($provList[$class_key]['not_admitted']) == 0) {
            self::$debugOutput .= "\t\tThere ain't anyone available: stopping...\n";
            break;
          }
          self::$debugOutput .= "\t\tThere're others not admitted available: pushing ".$provList[$class_key]['not_admitted'][0]['ID']." ".$provList[$class_key]['not_admitted'][0]['Name']." ".$provList[$class_key]['not_admitted'][0]['Surname']." forward...\n";
          array_push($provList[$class_key]['admitted'], $provList[$class_key]['not_admitted'][0]);
          array_push($classList['admitted'], $provList[$class_key]['not_admitted'][0]);
          unset($provList[$class_key]['not_admitted'][0]);
          unset($classList['not_admitted'][0]);
          $provList[$class_key]['not_admitted'] = array_values($provList[$class_key]['not_admitted']);
          $classList['not_admitted'] = array_values($classList['not_admitted']);
          $changes = 1;
        }
        self::$debugOutput .= "\n\tLet's start with students...\n\n";
        foreach($classList['admitted'] as $student_key => $student) {
          self::$debugOutput .= "\t\tGot to ".$student['Name']." ".$student['Surname']."...\n";
          // Checks if the current class is student's second choice
          if($student['f_c'] != $class_key) {
            self::$debugOutput .= "\t\t\tThis class is his second choice...\n";
            // Workaround to check if the current student exists in another class' admitted list since in PHP 5.3 array_column() does not exists
            $student_exists = false;
            foreach($provList[$student['f_c']]['admitted'] as $k => $admittedStudent) {
              if($admittedStudent['ID'] == $student['ID']) {
                $student_exists = true;
              }
            }
            // If the student has not been admitted in his first choice then the code can leave him here
            if(!$student_exists) {
              self::$debugOutput .= "\t\t\t\tHe's not been admitted in his first choice ({$student['f_c']}): stopping!\n";
              $provList[$class_key]['admitted'][$student_key]['Status'] = 1;
              // The code has not moved anyone, so it does not need to update $changes
            }
            // Else: the student has been admitted to his first choice and the code can remove him from this list
            else {
              self::$debugOutput .= "\t\t\t\tHe's been admitted in his first choice: removing...\n";
              unset($provList[$class_key]['admitted'][$student_key]);
              // If there is one student not admitted, then the code push him in this class' admitted list
              if(isset($provList[$class_key]['not_admitted'][0])) {
                self::$debugOutput .= "\t\t\t\t\tThere're others not admitted available: pushing ".$provList[$class_key]['not_admitted'][0]['ID']." ".$provList[$class_key]['not_admitted'][0]['Name']." ".$provList[$class_key]['not_admitted'][0]['Surname']." forward...\n";
                array_push($provList[$class_key]['admitted'], $provList[$class_key]['not_admitted'][0]);
                unset($provList[$class_key]['not_admitted'][0]);
                $provList[$class_key]['not_admitted'] = array_values($provList[$class_key]['not_admitted']);
              }
              // There were changes, so the code set $changes to 1
              $changes = 1;
            }
          }
          // Else: this is student's first choice
          else {
            self::$debugOutput .= "\t\t\tThis class is his first choice: removing from second choice and stopping!\n";
            // The code has not moved anyone, so it does not need to update $changes

            // Check if the student has been inserted in his second choice admitted or not admitted list.
            // If yes, the code removes him from that list and set $changes to 1
            $second_choice_adm = $provList[$student['s_c']]['admitted'];
            foreach($second_choice_adm as $k => $sc_student) {
              if($sc_student['ID'] == $student['ID']) {
                self::$debugOutput .= "\t\t\t\tFound {$sc_student['ID']} in second choice admitted list: removing...\n";
                unset($provList[$student['s_c']]['admitted'][$k]);
                $changes = 1;
              }
            }
            $second_choice_nadm = $provList[$student['s_c']]['not_admitted'];
            foreach($second_choice_nadm as $k => $sc_student) {
              if($sc_student['ID'] == $student['ID']) {
                self::$debugOutput .= "\t\t\t\tFound {$sc_student['ID']} in second choice not admitted list: removing...\n";
                unset($provList[$student['s_c']]['not_admitted'][$k]);
                $provList[$student['s_c']]['not_admitted'] = array_values($provList[$student['s_c']]['not_admitted']);
                $changes = 1;
              }
            }
            $provList[$class_key]['admitted'][$student_key]['Status'] = 1;
          }
        }
        // This is a workaround to sort students by score, since PHP 5.3 does not support spaceship operator (<=>)
        // Students are sorted in decreasing order
        usort($provList[$class_key]['admitted'], function ($item1, $item2) {
            if ($item1['Score'] == $item2['Score']) return 0;
            return $item1['Score'] > $item2['Score'] ? -1 : 1;
        });
        self::$debugOutput .= "\tEnd of class {$class_key}\n\n";
      }
      self::$debugOutput .= "\n\n";
      // To clean up the Graduated List, after a zero-changes event, the iteration will start again $repeat_after_zeros' times
      if($repeat_after_zeros > 0 && $changes == 0) {
        self::$debugOutput .= "\nThis iteration ended with 0 changes: let's repeat it again to clean up...\n\n";
        $repeat_after_zeros--;
        $changes = 1;
      }
    } while($changes == 1);
    // This cycle checks if the first not admitted has the same score of the last admitted.
    // In that case the code will admit also the first not admitted for equity purpose.
    // This code will be repeated until first not admitted and last admitted have different scores

    // In this case $changes is used if with the following code a student is admitted in his first choice while he has been admitted in his second choice.
    // In this case he will be removed from second choice admitted list.
    $changes = 0;
    do {
      $changes = 0;
      self::$debugOutput .= "Final Class Checking\nThere are some changes: iterating again...\n";
      foreach($provList as $class_key => $classList) {
        self::$debugOutput .= "\tChecking class {$class_key}...\n";
        $last = end($provList[$class_key]['admitted']);
        while(isset($provList[$class_key]['not_admitted'][0]) && $provList[$class_key]['not_admitted'][0]['Score'] == $last['Score']) {
          self::$debugOutput .= "\t\tThe first not admitted and the last admitted have the same score: pushing ".$provList[$class_key]['not_admitted'][0]['Name']." ".$provList[$class_key]['not_admitted'][0]['Surname']." forward...\n";
          // The code removes the student from his second choice if he has been admitted there.
          // In this case the code will be re-executed setting $changes to 1.
          if($provList[$class_key]['not_admitted'][0]['f_c'] == $class_key) {
            $second_choice_adm = $provList[$provList[$class_key]['not_admitted'][0]['s_c']]['admitted'];
            foreach($second_choice_adm as $k => $sc_student) {
              if($sc_student['ID'] == $provList[$class_key]['not_admitted'][0]['ID']) {
                self::$debugOutput .= "\t\t\tFound {$sc_student['ID']} in second choice admitted list: removing...\n";
                unset($provList[$provList[$class_key]['not_admitted'][0]['s_c']]['admitted'][$k]);
                $changes = 1;
              }
            }
          }
          array_push($provList[$class_key]['admitted'],$provList[$class_key]['not_admitted'][0]);
          unset($provList[$class_key]['not_admitted'][0]);
          $provList[$class_key]['not_admitted'] = array_values($provList[$class_key]['not_admitted']);
          $last = end($provList[$class_key]['admitted']);
        }
      }
    } while ($changes == 1);
    // Returns the corrected Graduated Lists
    return $provList;
  }
}
?>
