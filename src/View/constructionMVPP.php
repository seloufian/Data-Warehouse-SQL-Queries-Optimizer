<?php
session_start();

/* Si quelqu'un tente d'accéder à cette page directement (sans passer par les pages précédentes), le-rediriger vers la page principale (index.php) */
if((! isset($_SESSION['dbAddress'])) OR (! isset($_SESSION['dbPort'])) OR (! isset($_SESSION['dbCode'])) OR (! isset($_SESSION['dbName'])) OR (! isset($_SESSION['dbPassword'])) OR (! isset($_SESSION['requetesValides']))) {
	header('Location: ../index.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<title>Définition des VMs</title>
	<meta charset="utf-8">

	<link rel="icon" href="../Model/Data_Warehouse.ico">
	<link rel="stylesheet" type="text/css" href="../Model/Styles/constructionMVPP.php.css">

	<script src="../Model/Plugins/ChartJS/Chart.min.js"></script>
	<script src="../Model/Plugins/ChartJS/Chart.bundle.min.js"></script>
	<script src="../Model/Plugins/JQuery/jquery-3.3.1.min.js"></script>
</head>
<body id="body">

<header>
	<h1>Définition des Vues Materialisées</h1>
</header>

<section id= formDefVM>
	<h2 hidden>Formulaire de définition des Vues Matérialisées</h2>

	<label for="espace_VMs">Espace alloué pour les VMs : </label>
	<input type="text" name="espace_VMs" id="espace_VMs" autocomplete="off">

	<input type="radio" name="unite" id="radio_Mo" class="agrandir" checked>
	<label for="radio_Mo">Mo</label>
	<input type="radio" name="unite" class="agrandir" id="radio_Go">
	<label for="radio_Go">Go</label>

	<br>

	<div class="groupeParametres">
		<input type="radio" name="priv_jointures" id="plusFreq" class="agrandir" checked>
		<label for="plusFreq">Commencer par les sous arbres de jointures les <strong>plus</strong> fréquents.</label>

		<br>

		<input type="radio" name="priv_jointures" id="moinsFreq" class="agrandir">
		<label for="moinsFreq">Commencer par les sous arbres de jointures les <strong>moins</strong> fréquents.</label>
	</div>

	<div class="groupeParametres">
		<input type="radio" name="supp_VMs" id="supprimerVM" class="agrandir" checked>
		<label for="supprimerVM">Supprimer TOUTES les VMs existantes (recommandé).</label>

		<br>

		<input type="radio" name="supp_VMs" id="garderVM" class="agrandir">
		<label for="garderVM">Garder les VMs déjà définies.</label>
	</div>

	<button type="button" class="styleHover" onclick="definirVM(this)">Définir les VMs</button>

</section>

<div id="partieGauche">
	<div id="conteneurChart">
		<canvas id="myChart"></canvas>
	</div>

	<section id="options">
		<h2 hidden>Options relatives au optimiseur</h2>

		<div id="groupeBtnOptions">
		</div>

		<div id="lienTelScript">
		</div>
	</section>
</div>

<div id="conteneurChart1">
	<canvas id="myChart1"></canvas>
</div>

<script>

function definirVM(bouton) {
	if(document.getElementById("espace_VMs").value.trim() == '') {
		document.getElementById("espace_VMs").value = '';
		alert("Champs d'allocation d'espace pour les VMs est vide. Veuillez le remplir !");
		return;
	}

	if(! /^(\d{1,4}|\d+\.\d{1,6})$/.test(document.getElementById("espace_VMs").value)) {
		document.getElementById("espace_VMs").value = '';
		alert("La valeur entrée dans le champs d'espace à allouer n'est pas valide !");
		return;
	}

	if((document.getElementById("radio_Mo").checked) && (document.getElementById("espace_VMs").value > 1024)) {
		if(document.getElementById("espace_VMs").value > 4096) {
			alert("Impossible d'allouer un espace plus grand que 4096 Mo (4 Go) !");
			return;
		}
		else {
			if(! confirm("L'espace défini est supérieur à 1024 Mo (1 Go), êtes-vous sûres d'allouer un tel espace ?"))
			return;
		}
	}
	else {
		if((document.getElementById("radio_Go").checked) && (document.getElementById("espace_VMs").value > 1)) {
			if(document.getElementById("espace_VMs").value > 4) {
				alert("Impossible d'allouer un espace plus grand que 4 Go (4096 Mo) !");
				return;
			}
			else {
				if(! confirm("L'espace défini est supérieur à 1 Go (1024 Mo), êtes-vous sûres d'allouer un tel espace ?"))
				return;
			}
		}
	}

	bouton.setAttribute("disabled", "true");
	bouton.classList.remove("styleHover");
	document.getElementById("espace_VMs").setAttribute("disabled", "true");
	document.getElementById("radio_Mo").setAttribute("disabled", "true");
	document.getElementById("radio_Go").setAttribute("disabled", "true");
	document.getElementById("plusFreq").setAttribute("disabled", "true");
	document.getElementById("moinsFreq").setAttribute("disabled", "true");
	document.getElementById("supprimerVM").setAttribute("disabled", "true");
	document.getElementById("garderVM").setAttribute("disabled", "true");

	$.post('../Control/definirVuesMat.php', {
			espaceAlloue: document.getElementById("espace_VMs").value,
			uniteMo: document.getElementById("radio_Mo").checked,
			plusFreq: document.getElementById("plusFreq").checked,
			supprimerVM: document.getElementById("supprimerVM").checked
		},

		function(data) {
			console.log(data);
			eval(jQuery.parseHTML(data, true)[1].innerHTML);
			eval(jQuery.parseHTML(data, true)[3].innerHTML);
			document.getElementById('groupeBtnOptions').innerHTML = jQuery.parseHTML(data, true)[5].outerHTML;
			document.getElementById('groupeBtnOptions').innerHTML += jQuery.parseHTML(data, true)[7].outerHTML;
			document.getElementById('lienTelScript').innerHTML = jQuery.parseHTML(data, true)[9].outerHTML;
		},
		'html'
	);
}

function allerInsertionRequetes() {
	document.location.href="insertionRequetes.html";
}

function seDeconnecter() {
	document.location.href="../index.php";
}

</script>

</body>
</html>
