<?php
   session_start();
   include_once '../Model/afficherSchemaRequete.php';

   try {
       $db = new PDO("oci:dbname=//".$_SESSION['dbAddress'].":".$_SESSION['dbPort']."/".$_SESSION['dbCode'], $_SESSION['dbName'], $_SESSION['dbPassword']);

       $_SESSION['MatReqChoisie'] = afficherSchemaRequete($db, $_POST['requete']);

       if (! $_SESSION['MatReqChoisie']) {
           echo 'ERREUR';
       }
   } catch (PDOException $e) {
       Die("Oups.. il y a un problème de connexion avec votre BD..");
   }
?>
