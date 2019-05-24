<?php session_start(); 
/* Si quelqu'un tente d'accéder à cette page directement (sans passer par les pages précédentes), le-rediriger vers la page principale (index.php) */
if((! isset($_SESSION['dbAddress'])) OR (! isset($_SESSION['dbPort'])) OR (! isset($_SESSION['dbCode'])) OR (! isset($_SESSION['dbName'])) OR (! isset($_SESSION['dbPassword']))) {
	header('Location: ../index.php');
}?>
<!DOCTYPE html>
<html>
<head>
 <title>Plan d'Exécution</title>
 <meta charset="utf-8" />
 <link rel="icon" href="../Model/Data_Warehouse.ico">

 <script src="../Model/Plugins/JQuery/jquery-3.3.1.min.js"></script>
 <script src="../Model/Plugins/Selectric/jquery.selectric.min.js"></script>
 <link rel="stylesheet" href="../Model/Plugins/Selectric/selectric.css">
 <link rel="stylesheet" href="../Model/Plugins/Highlight/railscasts.css">
 <script src="../Model/Plugins/Highlight/highlight.pack.js"></script>
 <script>hljs.initHighlightingOnLoad();</script>

 <link rel="stylesheet" href="../Model/Styles/affichageRequetes.php.css">
</head>

<body>
 <header>
	<a href="constructionMVPP.php">Optimiser les requêtes</a>

	<h2>Sélectionner la requête à afficher</h2>

	<select name="num_requete" id="num_requete" onchange="importer_Plan_Arbre_Exec();">
		<option value="-1">-------------------------------------------------------</option>
			<?php
				$requetes = explode(';', $_POST['post_envoie']);
				$requetesValides = array();
				$numReq = 0;
				for ($i=0; isset($requetes[$i]); $i++) {
					$req=$requetes[$i];
					$req=ltrim($req);
					$req=rtrim($req);
					if ($req !== "") {
						$requetesValides[$numReq] = strtoupper($req);
						echo '<optgroup label="Requête '. ($numReq+1) . '">';
						echo '<option value="'.$numReq.'">'.($requetesValides[$numReq]).'</option>';
						echo '</optgroup>';
						$numReq++;
					}
				}
				$_SESSION['requetesValides'] = $requetesValides;
			?>
		</select>

 </header>

 <a href="javascript:arbre_popup('afficherArbre.php')" id="Afficher_Arbre">Afficher l'arbre d'exécution</a>

 <div id="Requete_texte"></div>

 <div id="Plan_Arbre_Exec"></div>

 <script>
	$(function() { $('select').selectric(); });

	function importer_Plan_Arbre_Exec() {
		if(document.getElementById("num_requete").selectedIndex != 0) {
			var requeteChoix = <?php echo json_encode($requetesValides) ?>;
			var requeteChoisie = requeteChoix[document.getElementById("num_requete").selectedIndex-1];

			$.post('../Control/traitementPlanExec.php', {
				requete: requeteChoisie
			},

			function(data) {
				document.getElementById("Requete_texte").innerHTML = '<pre><code class="sql">' + requeteChoisie + '</code></pre>';
				$('pre > code').each(function() { hljs.highlightBlock(this); });

				if(data === "ERREUR") {
					document.getElementById("Afficher_Arbre").style.visibility = "hidden";
					document.getElementById("Plan_Arbre_Exec").innerHTML =
					"<strong>Requête érronée</strong><p id=\"erreurReq\">✖ Les requêtes érronées ne seront pas prises en compte lors de la construction du MVPP ✖</p>";
				}
				else {
					document.getElementById("Plan_Arbre_Exec").innerHTML = data;
					document.getElementById("Afficher_Arbre").style.visibility = "visible";
				}
			},
			'text');
		}
		else {
			document.getElementById("Plan_Arbre_Exec").innerHTML = "";
			document.getElementById("Requete_texte").innerHTML = "";
			document.getElementById("Afficher_Arbre").style.visibility = "hidden";
		}
	}

	function arbre_popup(page) {
	 window.open(page,"L'arbre d'exécution","resizable=no, menubar=no, status=no, scrollbars=no, menubar=no, width=1300, height=800");
	}
 </script>
</body>
</html>
