<?php
session_start();

/* Supprimer les variables définies (lors de la déconnexion) */
if(isset($_SESSION['dbAddress'])) unset($_SESSION['dbAddress']);
if(isset($_SESSION['dbPort'])) unset($_SESSION['dbPort']);
if(isset($_SESSION['dbCode'])) unset($_SESSION['dbCode']);
if(isset($_SESSION['dbName'])) unset($_SESSION['dbName']);
if(isset($_SESSION['dbPassword'])) unset($_SESSION['dbPassword']);
if(isset($_SESSION['requetesValides'])) unset($_SESSION['requetesValides']);

if (isset($_POST['valider'])) {
	try {
	/* Connexion de test seulement. Les informations de CONNEXION seront conservées dans "$_SESSION" (si la connexion réussisse) */	
	$db = new PDO("oci:dbname=//".$_POST['dbAddress'].":".$_POST['dbPort']."/".$_POST['dbCode'], $_POST['dbName'], $_POST['dbPassword']);

	$_SESSION['dbAddress'] = $_POST['dbAddress'];
	$_SESSION['dbPort'] = $_POST['dbPort'];
	$_SESSION['dbCode'] = $_POST['dbCode'];
	$_SESSION['dbName'] = $_POST['dbName'];
	$_SESSION['dbPassword'] = $_POST['dbPassword'];

	/* Définir les cookies de connexion pour une journée (24 heures) */
	setcookie("dbAddress", $_POST['dbAddress'], time() + (86400 * 1));
	setcookie("dbPort", $_POST['dbPort'], time() + (86400 * 1));
	setcookie("dbCode", $_POST['dbCode'], time() + (86400 * 1));
	setcookie("dbName", $_POST['dbName'], time() + (86400 * 1));

	/* Passer à l'insertion des requêtes (page "insertionRequetes.html") */
	header('Location: View/insertionRequetes.html');
	} catch (PDOException $e) { /* Ne RIEN faire*/ }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<title>Connexion à la Base de données</title>
	<meta charset="utf-8">
	<link rel="stylesheet" type="text/css" href="Model/Styles/index.php.css">
	<link rel="icon" href="Model/Data_Warehouse.ico">
	<style>
		#interfaceConnexion {
			margin-top: <?php if (isset($_POST['valider'])) echo '3.5'; else echo '7'; ?>%;
		}
	</style>
</head>
<body>

<header>
	<h1>Connexion à la base de données</h1>
</header>

<?php if (isset($_POST['valider']))
	 echo '
	 <p id="erreurConn">
		✖ Oups... Il y a une problème de connxion à votre Base de Données ✖<br>
		Vérifier les données de connexion saisies et la configuration de votre BD
	 </p>
	 ';
?>

<section id="interfaceConnexion">
	<h2 hidden>Formulaire de connexion</h2>
	<form action=<?php echo '"'.$_SERVER['REQUEST_URI'].'"'; ?> method="POST">

		<input type="text" placeholder="Adresse de la BD" title="Adresse de la BD (@IP ou 'localhost')" autocomplete="on" name="dbAddress" spellcheck="false" pattern="(\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3}|localhost)" required maxlength="15" value=<?php if (isset($_POST['dbAddress'])) echo '"'.$_POST['dbAddress'].'"'; else if (isset($_COOKIE['dbAddress'])) echo '"'.$_COOKIE['dbAddress'].'"'; else echo '""'; ?>>

		<span id="deuxPoints">:</span>

		<input type="text" id="dbPort" placeholder="Port de la BD" title="Port de la BD" autocomplete="on" name="dbPort" spellcheck="false" pattern="\d{4,}" required maxlength="10" value=<?php if (isset($_POST['dbPort'])) echo '"'.$_POST['dbPort'].'"'; else if (isset($_COOKIE['dbPort'])) echo '"'.$_COOKIE['dbPort'].'"'; else echo '"1521"'; ?>>

		<br><br>

		<input type="text" placeholder="Code de la BD" title="Code de la BD ('root', 'XE', ...)" autocomplete="on" name="dbCode" spellcheck="false" pattern="\w{2,}" required maxlength="7" value=<?php if (isset($_POST['dbCode'])) echo '"'.$_POST['dbCode'].'"'; else if (isset($_COOKIE['dbCode'])) echo '"'.$_COOKIE['dbCode'].'"'; else echo '""'; ?>>

		<br><br>

		<input type="text" placeholder="Nom de la BD" title="Nom de la BD" autocomplete="on" name="dbName" spellcheck="false" required maxlength="20" value=<?php if (isset($_POST['dbName'])) echo '"'.$_POST['dbName'].'"'; else if (isset($_COOKIE['dbName'])) echo '"'.$_COOKIE['dbName'].'"'; else echo '""'; ?>>

		<br><br>

		<input type="password" placeholder="Mot de passe de la BD" title="Mot de passe de la BD" autocomplete="off" name="dbPassword" spellcheck="false" required maxlength="20" value="">

		<br><br>

		<input id="valider" type="submit" name="valider" value="Se connecter">
	</form>
</section>

</body>
</html>
