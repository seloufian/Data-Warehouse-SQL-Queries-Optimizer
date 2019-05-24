<?php session_start(); 
/* Si quelqu'un tente d'accéder à cette page directement (sans passer par les pages précédentes), le-rediriger vers la page principale (index.php) */
if((! isset($_SESSION['dbAddress'])) OR (! isset($_SESSION['dbPort'])) OR (! isset($_SESSION['dbCode'])) OR (! isset($_SESSION['dbName'])) OR (! isset($_SESSION['dbPassword']))) {
	header('Location: ../index.php');
}?>
<!DOCTYPE html>
<html>
<head>
	<title>L'arbre d'exécution</title>
	<meta charset="utf-8" />
	<link rel="icon" href="../Model/Data_Warehouse.ico">

	<link rel="stylesheet" href="../Model/Plugins/OrgChart/orgchart-style.css"/>
	<link rel="stylesheet" href="../Model/Plugins/OrgChart/jquery.orgchart.css"/>

	<script src="../Model/Plugins/OrgChart/jquery.min.js"></script>
	<script src="../Model/Plugins/OrgChart/jquery.orgchart.js"></script>
	<script src="../Model/Plugins/OrgChart/jquery.orgchart.min.js"></script>
	<script>$(function() { $("#organisation").orgChart({container: $("#main")}); }); </script>

	<link rel="stylesheet" href="../Model/Styles/afficherArbre.php.css"/>
</head>

<body>
<?php
	include_once '../Model/dessinerArbre.php';

	/* Les "echo" (avant et après l'appel de la fonction) sont obligatoires */
	echo '<div id="left"><ul id="organisation">';

	/* Fonction récursive, le deuxième paramètre doit-être égal à "-1" */
	dessinerArbre($_SESSION['MatReqChoisie'], -1);

	echo '</ul></div><div id="content"><div id="main"></div></div>';
?>
</body>
</html>
