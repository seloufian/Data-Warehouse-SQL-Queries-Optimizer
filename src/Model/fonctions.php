<?php

function ajouter_hash($requete) {
	$ch = explode("FROM", $requete); /* la phrase qui se débute par from */
	$ch=explode(" WHERE ", $ch[1]); /* et se termine par where  */
	$ch=explode(",", $ch[0]); /*eclatant par la virgule */
	$sh = "";

	for($i=0; $i<count($ch); $i++) {
		$t=explode(" ", $ch[$i]);
		$j=count($t)-1; /* parcourir les cases vides */
		while(strcmp($t[$j],"")==0 ) $j--;
		$sh=$sh.' '.$t[$j];
	}

	$sh = "/*+ USE_HASH(". $sh ." ) */ ";  /*la chaine à ajouter*/
	$ch=explode(" ", $requete);
	$sh=$ch[0]." ".$sh;

	for($i=1;$i<count($ch);$i++) {
		$sh=$sh." ".$ch[$i];
	}

	return $sh;
}



function creerTableVars($requete) {
	/* Récupérer la chaine "FROM ... WHERE" */
	preg_match_all('#FROM(.+)WHERE#is', $requete, $chaineFromToWhere);

	/* Si la requête contient la chaine "FROM ... WHERE" */
	if(isset($chaineFromToWhere[1][0])) {
		/* Diviser la chaine ENTRE le mot "FROM" et "WHERE" en fonction du séparateur "," */
		$tablesVars = explode(',', $chaineFromToWhere[1][0]);

		/* Tableau avec deux colonnes : nom table SQL | varaible
		(Si une table n'a pas de variable, la 2éme colonne sera une chaine vide ['']) */
		$variablesTables = array();

		foreach ($tablesVars as $index => $valeur) {
			preg_match_all('#\s*(\w+)\s*(\w*)#', $valeur, $resultat);
			/* Ajouter le nom de la table SQL */
			$variablesTables[$index][0] = $resultat[1][0];
			/* Ajouter la variable utilisée pour cette table */
			$variablesTables[$index][1] = $resultat[2][0];
		}

		return($variablesTables);
	}

	/* La requête NE contient PAS la chaine "FROM ... WHERE". Vérifier si elle contient uniquement "FROM ..." */
	/* Récupérer la chaine "FROM ..." */
	preg_match_all('#FROM(.+)#is', $requete, $chaineFromToWhere);

	/* Si la requête contient la chaine "FROM ..." */
	if(isset($chaineFromToWhere[1][0])) { //var_dump($chaineFromToWhere);
		/* Diviser la chaine après le mot "FROM" en fonction du séparateur "," */
		$tablesVars = explode(',', $chaineFromToWhere[1][0]);

		/* Tableau avec deux colonnes : nom table SQL | varaible
		(Si une table n'a pas de variable, la 2éme colonne sera une chaine vide ['']) */
		$variablesTables = array();

		foreach ($tablesVars as $index => $valeur) {
			preg_match_all('#\s*(\w+)\s*(\w*)#', $valeur, $resultat);
			/* Ajouter le nom de la table SQL */
			$variablesTables[$index][0] = $resultat[1][0];
			/* Ajouter la variable utilisée pour cette table */
			$variablesTables[$index][1] = $resultat[2][0];
		}

		return($variablesTables);
	}

	/* Si la requête NE contient PAS la chaine "FROM ... WHERE" */
	return null;
}



/* Deux paramètres : $db (objet PDO) et $requete (chaine de caractères) */
function requeteAttributsTables($db, $requete) {

	/* Tableau retourné pas la fonction, il contient TROIS colonnes :
		Colonne 1 (requete) : La requête normalisé (sans variables).
		Colonne 2 (attributs) : Liste des attributs de SELECT (tableau avec DEUX colonnes : nom_table | attribut).
		Colonne 3 (tables) : Tables utilisées dans la requête (contenus dans la section "FROM ... WHERE").
		Colonne 4 (attPredicat) : Liste des attributs de WHERE (tableau avec DEUX colonnes : nom_table | attribut). */
	$requeteAttributsTables = array();

	/* Tableau avec DEUX colonnes : nom_table | variable_utilisée */
	$tableVars = creerTableVars($requete);

	/* Création de la TROISIÈME colonne du tableau retourné */
	for ($i=0; $i < count($tableVars); $i++) {
		$requeteAttributsTables['tables'][] = $tableVars[$i][0];
	}

	/* Remplacer les variables par les noms des tables correspondats ET enlever les variables définies pour les tables (entre "FROM ... WHERE") */
	for ($i=0; $i < count($tableVars); $i++) {
		if ( $tableVars[$i][1] !== '' ) { /* Si la table a une variable spécifique définie */
			$requete = preg_replace('#'.$tableVars[$i][0].'\s+'.$tableVars[$i][1].'#',  $tableVars[$i][0], $requete);
			$requete = preg_replace('#'.$tableVars[$i][1].'\.#',  $tableVars[$i][0].'.', $requete);
		}
	}

	/* Table associative (nom_table => attributs [table numérique]) qui contiendra, pour chaque, table utilisée
	dans la requête, TOUS les attributs de cette table */
	$attributsTablesRequete = array();

	for ($i=0; $i < count($tableVars); $i++) {
		/* Ajouter les attributs qui appartiennent à la table courante */
		foreach ($db->query("SELECT column_name FROM USER_TAB_COLUMNS WHERE table_name = '".$tableVars[$i][0]."'") as $attribut) {
			$attributsTablesRequete[$tableVars[$i][0]][] = $attribut['COLUMN_NAME'];
		}
	}

	/* Précéder les attributs sans variables avec les noms des tables auxquelles appartiennent */
	foreach ($attributsTablesRequete as $table => $toutsAttributs) {
		foreach ($toutsAttributs as $attribut) { /* Utilisation d'une REGEX avec ASSERTIONS */
			$requete = preg_replace('#(?<!\.)'.$attribut.'(?!\.)#',  $table.'.'.$attribut, $requete);
		}
	}

	/* Création de la PREMIÈRE colonne du tableau retourné */
	$requeteAttributsTables['requete'] = $requete;

	/* Récupérer les champs séléctionnés par la requête (dans la chaine "SELECT ... FROM") */
	preg_match_all('#SELECT(.+)FROM#is', $requete, $resultat);
	preg_match_all('#\w+\.\w+#is', $resultat[1][0], $resultat);

	/* Table numérique avec DEUX colonnes (nom_table | attribut) */
	$attributsSelect = array();

	for ($i=0; $i < count($resultat[0]); $i++) {
		$attributsSelect[] = explode('.', trim($resultat[0][$i]));
	}

	/* Création de la DEUXIÈME colonne du tableau retourné */
	$requeteAttributsTables['attributs'] = $attributsSelect;

	/* Récupérer les champs faisant partie de la section "WHERE" */
	preg_match_all('#WHERE(.*)#is', $requete, $resultat);

	/* Si la requête NE contient PAS la section "WHERE ..." */
	if(! isset($resultat[1][0])) {
		$requeteAttributsTables['attPredicat'] = array();
		return $requeteAttributsTables;
	}

	preg_match_all('#\w+\.\w+#is', $resultat[1][0], $resultat);

	/* Création de la QUATRIÈME colonne du tableau retourné */
	for ($i=0; $i < count($resultat[0]); $i++) {
		$requeteAttributsTables['attPredicat'][] = explode('.', trim($resultat[0][$i]));
	}

	return $requeteAttributsTables;
}



/* Un seul paramètre, de type : objet MVPP */
function tablesHashJoin($sousArbreJointure) {
	/* Tableau numérique qui contient, pour chaque, attribut de jointure en étoile d'un sous-arbre commun de jointure,
	le nom de l'attribut ET la table à laquelle il appartient */
	$tablesHashJoinCle = array();

	/* Parcourir tout le MVPP de jointure en étoile commun */
	foreach ($sousArbreJointure->getListeNoeuds() as $noeud) {
		if($noeud->getOperationName() === 'HASH JOIN') { /* Si c'est un noeud de jointure (HASH JOIN) */
			/* Supprimer les élément du prédicat non-nécessaires ['access(', 'filter(', '"' ou ')']*/
			$predicatHashJoin = explode('=', preg_replace('#(access\(|"|\)|filter\()#i', '', $noeud->getPredicat()));

			/* Ajouter le côté GAUCHE au tableau retourné pour ce noeud de jointure (HASH JOIN) */
			$tablesHashJoinCle[] = explode('.', $predicatHashJoin[0]);
			/* Ajouter le côté DROIT au tableau retourné pour ce noeud de jointure (HASH JOIN) */
			$tablesHashJoinCle[] = explode('.', $predicatHashJoin[1]);
		}
	}

	return $tablesHashJoinCle;
}



/* Fonction qui supprime les fonctions d'agrégat et les clauses GROUP BY et ORDER BY d'une requête SQL */
function suppAgregation($requete) {
	return preg_replace('#( GROUP.+| ORDER.+'.
		'|, SUM\(.*\)| SUM\(.*\),'.
		'|, COUNT\(.*\)| COUNT\(.*\),'.
		'|, AVG\(.*\)| AVG\(.*\),'.
		'|, MIN\(.*\)| MIN\(.*\),'.
		'|, MAX\(.*\)| MAX\(.*\),)#is', '', $requete);
}



function suppVarPredicat($chaine) { return( preg_replace('#"\w*"\.#', '', $chaine) ); }



/* Trier la matrice des sous-arbres de jointures communes selon la fréquence (nombre des requêtes impliquées dans une jointure) */
/* Deux paramètres : la matrice de jointures communes +ET+ le type de tri ($ascDes) : POSITIF = Ascendant, NÉGATIF ou NUL = Descendant */
function trierMatriceSousArbresCommuns($matrice, $ascDes) {
	if($ascDes > 0) { /* Trier la matrice en ascendant */
		for ($i=0; $i < count($matrice)-1; $i++) {
			$indicePermuter = $i;

			for ($j=$i+1; $j < count($matrice); $j++) {
				if(count($matrice[$j][1]) < count($matrice[$indicePermuter][1]))
					$indicePermuter = $j;
			}

			if($indicePermuter !== $i) {
				$matrice_temp[0][0] = $matrice[$indicePermuter][0];
				$matrice_temp[0][1] = $matrice[$indicePermuter][1];

				$matrice[$indicePermuter][0] = $matrice[$i][0];
				$matrice[$indicePermuter][1] = $matrice[$i][1];

				$matrice[$i][0] = $matrice_temp[0][0];
				$matrice[$i][1] = $matrice_temp[0][1];
			}
		}
	}
	else { /* Trier la matrice en descendant */
		for ($i=0; $i < count($matrice)-1; $i++) {
			$indicePermuter = $i;

			for ($j=$i+1; $j < count($matrice); $j++) {
				if(count($matrice[$j][1]) > count($matrice[$indicePermuter][1]))
					$indicePermuter = $j;
			}

			if($indicePermuter !== $i) {
				$matrice_temp[0][0] = $matrice[$indicePermuter][0];
				$matrice_temp[0][1] = $matrice[$indicePermuter][1];

				$matrice[$indicePermuter][0] = $matrice[$i][0];
				$matrice[$indicePermuter][1] = $matrice[$i][1];

				$matrice[$i][0] = $matrice_temp[0][0];
				$matrice[$i][1] = $matrice_temp[0][1];
			}
		}
	}

	return $matrice;
}
