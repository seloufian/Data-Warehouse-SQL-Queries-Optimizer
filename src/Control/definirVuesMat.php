<?php
session_start();

include_once '../Model/classes.php';
include_once '../Model/fonctions.php';
include_once '../Model/creationExplainPlan.php';

/* Supprimer les fichiers ".sql" qui existent dans le serveur pour l'utilisateur courant */
array_map('unlink', glob('scriptBD-'.$_SESSION['dbAddress'].'*.sql'));

date_default_timezone_set('Africa/Algiers');


if($_POST['uniteMo'] == 'true')
	$espaceAlloue = ((float) $_POST['espaceAlloue'])*1024*1024;
else
	$espaceAlloue = ((float) $_POST['espaceAlloue'])*1024*1024*1024;

$supprimerVM = $_POST['supprimerVM'];
$espaceVMs = 0;

/* La somme des coûts des VM créées (pour des statistiques ultérieures) */
$coutTOTAL_VMs = 0;

try {
	$db = new PDO("oci:dbname=//".$_SESSION['dbAddress'].":".$_SESSION['dbPort']."/".$_SESSION['dbCode'], $_SESSION['dbName'], $_SESSION['dbPassword']);
} catch (PDOException $e) {
		Die("Oups.. il y a un problème de connexion avec votre BD..");
	}


$nomScript = 'scriptBD-'.$_SESSION['dbAddress'].'-'.date('dmHi').'.sql';
$script = fopen($nomScript, 'w+');

fwrite($script, "/*******************************************************/".PHP_EOL);
fwrite($script, "/************ Créé le : ".date('d/m/Y H:i:s')." ************/".PHP_EOL);
fwrite($script, "/*******************************************************/".PHP_EOL.PHP_EOL);
fwrite($script, "/************* Requêtes AVANT optimisation *************/".PHP_EOL.PHP_EOL);


$requeteAttributsTables = array();

foreach ($_SESSION['requetesValides'] as $requete) {
	if(($db->exec('EXPLAIN PLAN FOR '.$requete)) !== 0) continue;

	$requeteAttributsTables[] = requeteAttributsTables($db, $requete);
}

$requetesValides = array();

foreach ($requeteAttributsTables as $cle => $requete) {
	$requetesValides[$cle] = ajouter_hash($requete['requete']);

	fwrite($script, "/***** Requête : ".($cle+1)." *****/".PHP_EOL);
	fwrite($script, $requete['requete'].";".PHP_EOL.PHP_EOL);
}


$requetesValidesPlan = array();
$requetesValidesPlan_AvecAgg_Avant = array();

foreach ($requetesValides as $cle => $requete) {
	$db->exec('EXPLAIN PLAN FOR '.suppAgregation($requete));

	$resultat = $db->query('SELECT plan_table_output FROM table(dbms_xplan.display(\'plan_table\', null, \'basic  +Rows +Bytes +cost +predicate\'))');

	$matrice = decouperExplainPlan($resultat);

	/* La requête valide numéro "cle" est prête à recevoir son MVPP */
	$requetesValidesPlan[$cle] = new MVPP();

	/* Créer le MVPP de la requête valide, ajouter tous les noeuds au MVPP */
	for ($j=0; $j < count($matrice); $j++) {
		$requetesValidesPlan[$cle]->ajouterNoeud( new Noeud($matrice[$j][0], $matrice[$j][1],
			trim($matrice[$j][2], " "), trim($matrice[$j][3], " "), $matrice[$j][4], $matrice[$j][5], str_replace(' ', '', $matrice[$j][6]), trim($matrice[$j][7]), " "));
	}

	$db->exec('EXPLAIN PLAN FOR '.$requete);

	$resultat = $db->query('SELECT plan_table_output FROM table(dbms_xplan.display(\'plan_table\', null, \'basic  +Rows +Bytes +cost +predicate\'))');

	$matrice = decouperExplainPlan($resultat);

	/* La requête valide numéro "cle" est prête à recevoir son MVPP */
	$requetesValidesPlan_AvecAgg_Avant[$cle] = new MVPP();

	/* Créer le MVPP de la requête valide, ajouter tous les noeuds au MVPP */
	for ($j=0; $j < count($matrice); $j++) {
		$requetesValidesPlan_AvecAgg_Avant[$cle]->ajouterNoeud( new Noeud($matrice[$j][0], $matrice[$j][1],
			trim($matrice[$j][2], " "), trim($matrice[$j][3], " "), $matrice[$j][4], $matrice[$j][5], str_replace(' ', '', $matrice[$j][6]), trim($matrice[$j][7]), " "));
	}
}


/* Tableau contenant TOUTES les jointures en étoile (sous-arbres du EXPLAIN_PLAN) pour chaque requête valide */
$jointuresRequetes = array();

for ($i=0; $i < count($requetesValidesPlan); $i++) { /* Parcourir TOUTES les requêtes valides */
	$reqCourante = $requetesValidesPlan[$i];

	/* parcourir l'EXPALIN_PLAN de la requête courante. On commence par "1" car la première ligne est forcément "SELECT STATEMENT" */
	for ($j=1, $nb=-1; $j < $reqCourante->longeurMVPP(); $j++) {
		if(stripos($reqCourante->noeudById($j)->getOperationName(), "HASH JOIN") !== false) {
			$nb++;
			$jointuresRequetes[$i][$nb] = new MVPP();

			$jointuresRequetes[$i][$nb]->ajouterNoeud($reqCourante->noeudById($j)); /* Ajouter le père (HASH JOIN) */

			for ($k=$j+1; $k < $reqCourante->longeurMVPP(); $k++) {
				/* Si le noeud courant a une profondeur <= profondeur du HASH JOIN, alors le sous-arbre est fini */
				if($reqCourante->noeudById($k)->getDepth() <= $reqCourante->noeudById($j)->getDepth()) break;

				/* Ajouter le noeud comme fils du HASH JOIN */
				$jointuresRequetes[$i][$nb]->ajouterNoeud($reqCourante->noeudById($k));
			}
		}
	}
}


######################################################
###############  SOUS-ARBRES  COMMUNS  ###############
######################################################

/* Matrice (avec DEUX colonnes) qui contienra les sous-arbres (MVPPs) de jointure communs +ET+
les requêtes qui ont ce sous-arbre */
$sousArbresCommuns = array();

/* Un indice du nombre de sous-arbres communs */
$nombreSousArbresCommuns = 0;

/* Parcourir toutes les requêtes SAUF LA DERNIERE (pas besoin de la parcourir car
TOUTS ses MVPPs seront déjà vérifiés avec TOUS les MVPPs des autres requêtes */
for ($i=0; $i < (count($jointuresRequetes)-1); $i++) {
	/* "numRequetes_temp" contiendra temporairement les numéros des requêtes qui ont
	un sous-arbre de jointure commun avec un des sous-arbres de jointure de la requête courante */
	$numRequetes_temp = array();

	/* Parcourir TOUTS les sous-arbres (MVPP) de jointure de la requête courante */
	/* "foreach" utilisé car certaines cases peuvent-être vides (suite à la supression possible des MVPPs [en dessous]) */
	foreach ($jointuresRequetes[$i] as $jointureMvppCompare) {
		/* Comparer le MVPP courant avec TOUTES les autres requêtes */
		for ($j=$i+1; $j < count($jointuresRequetes); $j++) {
			/* Comparer le MVPP courant avec TOUTS les MVPPs de la requête actuelle */
			/* "foreach" utilisé car certaines cases peuvent-être vides (suite à la supression possible des MVPPs [en dessous]) */
			foreach ($jointuresRequetes[$j] as $cle => $jointureMvppCompareTo) {
				/* Comparer deux MVPPs (appel à une fonction statique [::]) */
				if( MVPP::comparerMVPP($jointureMvppCompare, $jointureMvppCompareTo) ) { /* Si les deux MVPPs sont égaux */
					/* Ajouter le numéro de la requête courante */
					$numRequetes_temp[] = $j;
					/* Supprimer le sous-arbre de cette requête (pour ne pas le parcourir une autre fois) */
					unset($jointuresRequetes[$j][$cle]);
				}
			}
		}
		if( count($numRequetes_temp) > 0) { /* S'il y a AU MOINS une requête avec le même sous-arbre de jointure */
			/* Ajouter la requête qui à été comparé avec les autres MVPPs */
			$numRequetes_temp[] = $i;
			/* Créer une nouvelle ligne avec DEUX colonnes : le sous-arbre de jointure +ET+ les requêtes qui ont ce sous-arbre */
			$sousArbresCommuns[$nombreSousArbresCommuns][0] = $jointureMvppCompare;
			$sousArbresCommuns[$nombreSousArbresCommuns][1] = $numRequetes_temp;
			/* Détruire le tableau "$numRequetes_temp" pour le vider complètement et le ré-utiliser dans la prochaine itération */
			unset($numRequetes_temp);
			/* Incrémenter le nombre de sous-arbres communs (pour le prochain ajout dans "$sousArbresCommuns[]") */
			$nombreSousArbresCommuns++;
		}
	}
}


/* Trier la matrice des sous arbres de jointure communs selon le choix de l'utilisateur */
if(isset($_POST['plusFreq'])) { /* Vérifier si la variable existe réellement (pour éviter les ERREURS) */
	if($_POST['plusFreq'] === 'true') /* Choix: Noeuds les PLUS fréquents (tri descendant [Plus grand -> Plus petit]) */
		$sousArbresCommuns = trierMatriceSousArbresCommuns($sousArbresCommuns, -1);
	else /* Choix: Noeuds les MOINS fréquents (tri ascendant [Plus petit -> Plus grand]) */
		$sousArbresCommuns = trierMatriceSousArbresCommuns($sousArbresCommuns, 1);
}

#####################################################
############# D E F I N I T I O N   V M #############
#####################################################

/*
	* $_SESSION['requetesValides'][i] : Table numérique, contient les requêtes SQL valides (Chaines de caractère).

	* $_SESSION['requetesValidesPlan'][i] : Table numérique, contient les MVPP des requêtes valides (Objet: MVPP).

	* $jointuresRequetes[i][j] : Table numérique, contient tous les sous-arbres de jointure d'une requête "i" (Objet: MVPP).

	* $sousArbresCommuns[i][j] : Matrice (avec DEUX colonnes) qui contient les sous-arbres (MVPPs) de jointure communs +ET+ les requêtes qui ont ce sous-arbre.

	* $requeteAttributsTables[i] : Matrice numérique qui contient, pour chaque requête "i", la requête (chaine de caractères), les tables utilisées, la liste des attributs sélectionnés et leur table respectifs et les attributs des prédicats.
*/

fwrite($script, PHP_EOL.PHP_EOL."/************* VMs définies *************/".PHP_EOL.PHP_EOL);

/* Intialiser le numéro de la VM par "-1" (sans le cas où aucune VM définie n'a pas come nom "VM<numéro>") */
$numeroVM = -1;

/* Si l'utilisateur a choisi de supprimer TOUTES les VMs définies dans sa BD */
if($supprimerVM === 'true') {
	/* Supprimer TOUTES les VMs définies */
	$res = $db->query("SELECT MVIEW_NAME, QUERY_LEN FROM USER_MVIEWS");

	/* Supprimer chaque VM du résultat */
	while($infos = $res->fetch()) {
		$db->exec("DROP MATERIALIZED VIEW ".$infos['MVIEW_NAME']);
		$db->exec("COMMIT");
	}
}
else {
	/* Chercher le dernier numéro des VMs définies sous forme de "VM<numéro>" */
	$res = $db->query("SELECT MVIEW_NAME, QUERY_LEN FROM USER_MVIEWS WHERE REGEXP_LIKE (MVIEW_NAME, '^VM\d+$') ORDER BY MVIEW_NAME");

	while($infos = $res->fetch())
		/* Affecter le numéro de la VM courante */
		$numeroVM = (int) trim(preg_replace('#VM#i', '', $infos['MVIEW_NAME']));
}

/* Tableau numérique contenant TROIS colonnes : la VUE MATERIALISÉE (requete) +ET+ la liste des attributs et leurs tables (attributs) +ET+ la liste des conditions */
$vuesMat = array();

/* Parcourir TOUS les sous-arbres de jointure communs */
foreach ($sousArbresCommuns as $cle => $sousArbreJointure) {
	/* Si l'espace défini pour les VM a été atteint, ALORS stopper la création des VMs */
	if($espaceVMs == $espaceAlloue) break;

	/* Commencer par définir la VM numéro "cle + numéro_Dérnière_VM_Définie + 1" (même ordre que pour les sous-arbres de jointures communs) */
	$numeroVM++;
	$vuesMat[$cle]['requete'] = 'CREATE MATERIALIZED VIEW VM'.$numeroVM.' AS SELECT ';

	/* Récupérer la liste (nom_table | attribut) à partir du prédicat de HASH JOIN */
	$tablesJointure = tablesHashJoin($sousArbreJointure[0]);

	/* Ajouter les attributs et leur table à la liste des attributs sélectionnés par cette VM */
	for ($i=0; $i < count($tablesJointure); $i++) {
		$vuesMat[$cle]['attributs'][$i][0] = $tablesJointure[$i][0];
		$vuesMat[$cle]['attributs'][$i][1] = $tablesJointure[$i][1];
	}

	/* Parcourir les requêtes qui appartiennent à ce sous-arbre de jointure commun pour récupérer les attribus (qui doivent
	figurer dans les tables utilisées par cette VM) sélectionnés par chaque requête */
	for ($i=0; $i < count($sousArbreJointure[1]); $i++) {
		/* Parcourir la liste des attributs et leur table de la requête numéro "i" */
		for ($j=0; $j < count($requeteAttributsTables[$sousArbreJointure[1][$i]]['attributs']); $j++) {
			/* L'attribut courant de la requête courante doit appartenir à une des tables utilisées par cette VM */
			for ($k=0; $k < count($tablesJointure); $k++) {
				/* Si l'attribut appartient à une des tables utilisées par cette VM */
				if($requeteAttributsTables[$sousArbreJointure[1][$i]]['attributs'][$j][0] === $tablesJointure[$k][0]) {
					/* L'attribut NE doit PAS exister déjà parmi les attributs sélectionnés par cette VM */
					$existe = false;
					foreach ($vuesMat[$cle]['attributs'] as $valeur) {
						if(($requeteAttributsTables[$sousArbreJointure[1][$i]]['attributs'][$j][0] === $valeur[0]) AND ($requeteAttributsTables[$sousArbreJointure[1][$i]]['attributs'][$j][1] === $valeur[1])) {
							$existe = true;
							break;
						}
					}
					/* Si l'attribut actuel n'existe PAS déjà parmi les attributs sélectionnés par cette VM */
					if(! $existe) {
						$vuesMat[$cle]['attributs'][count($vuesMat[$cle]['attributs'])][0] = $requeteAttributsTables[$sousArbreJointure[1][$i]]['attributs'][$j][0];
						$vuesMat[$cle]['attributs'][count($vuesMat[$cle]['attributs'])-1][1] = $requeteAttributsTables[$sousArbreJointure[1][$i]]['attributs'][$j][1];
					}
				}
			}
		}
		/* Parcourir la liste des attributs des prédicats et leur table de la requête numéro "i" */
		for ($j=0; $j < count($requeteAttributsTables[$sousArbreJointure[1][$i]]['attPredicat']); $j++) {
			/* L'attribut courant de la requête courante doit appartenir à une des tables utilisées par cette VM */
			for ($k=0; $k < count($tablesJointure); $k++) {
				/* Si l'attribut appartient à une des tables utilisées par cette VM */
				if($requeteAttributsTables[$sousArbreJointure[1][$i]]['attPredicat'][$j][0] === $tablesJointure[$k][0]) {
					/* L'attribut NE doit PAS exister déjà parmi les attributs sélectionnés par cette VM */
					$existe = false;
					foreach ($vuesMat[$cle]['attributs'] as $valeur) {
						if(($requeteAttributsTables[$sousArbreJointure[1][$i]]['attPredicat'][$j][0] === $valeur[0]) AND ($requeteAttributsTables[$sousArbreJointure[1][$i]]['attPredicat'][$j][1] === $valeur[1])) {
							$existe = true;
							break;
						}
					}
					/* Si l'attribut actuel n'existe PAS déjà parmi les attributs sélectionnés par cette VM */
					if(! $existe) {
						$vuesMat[$cle]['attributs'][count($vuesMat[$cle]['attributs'])][0] = $requeteAttributsTables[$sousArbreJointure[1][$i]]['attPredicat'][$j][0];
						$vuesMat[$cle]['attributs'][count($vuesMat[$cle]['attributs'])-1][1] = $requeteAttributsTables[$sousArbreJointure[1][$i]]['attPredicat'][$j][1];
					}
				}
			}
		}
	}
	/* Tableau numérique temporaire de DEUX colonnes (nomTable | attribut) qui contiendra la liste du contenu "SELECT" de la VM (permet de s'assurer qu'un attribut n'est pas répété (cas de jointures) */
	$contenuSelectVM_temp = array();
	/* Indice pour le tableau temporaire */
	$nbSelectVM_temp = 0;
	/* Parcourir TOUTS les attributs (avec leurs tables respectifs) de la VM courante */
	foreach ($vuesMat[$cle]['attributs'] as $attributVM) {
		$existe = false;
		/* L'attribut courant NE doit PAS déjà exister parmi la liste des attributs "SELECT" de la VM courante */
		foreach ($contenuSelectVM_temp as $attribut_temp) {
			if(strcasecmp($attribut_temp[1], $attributVM[1]) == 0) {
				$existe = true;
				break;
			}
		}

		if(! $existe) { /* Ajouter (nomTable | attribut) SI ET SEULEMENT SI l'attribut n'existe PAS déjà */
			$contenuSelectVM_temp[$nbSelectVM_temp][0] = $attributVM[0];
			$contenuSelectVM_temp[$nbSelectVM_temp][1] = $attributVM[1];
			$nbSelectVM_temp++;
		}
	}

	/* Ajouter les attributs de la VM à la requête */
	/* Le PREMIER attribut est un cas à traiter à part (sans "virgule + espace" au début) */
	$vuesMat[$cle]['requete'] .= $contenuSelectVM_temp[0][0].'.'.$contenuSelectVM_temp[0][1];

	for ($i=1; $i < count($contenuSelectVM_temp); $i++) {
		$vuesMat[$cle]['requete'] .= ', '.$contenuSelectVM_temp[$i][0].'.'.$contenuSelectVM_temp[$i][1];
	}
	/* Supprimer la variable temporaire, pour qu'elle soit VIDE pour la prochaine itération */
	unset($contenuSelectVM_temp);

	/* Ajouter les tables de la VM à la requête */
	/* Le PREMIÈRE table est un cas à traiter à part (avec " FROM " sans "virgule + espace" au début) */
	$vuesMat[$cle]['requete'] .= ' FROM '.$vuesMat[$cle]['attributs'][0][0];

	/* Créer une table temporaire qui contiendra les noms des tables utilisées par la VM (pour NE PAS dupliquer les noms des tables) */
	$tablesFROM_temp[] = $vuesMat[$cle]['attributs'][0][0];

	for ($i=1; $i < count($vuesMat[$cle]['attributs']); $i++) {
		$existe = false;

		/* Chercher l'existence du nom de cette table auparavant dans la section "FROM" */
		for ($j=0; $j < count($tablesFROM_temp); $j++) {
			if(strcasecmp($tablesFROM_temp[$j], $vuesMat[$cle]['attributs'][$i][0]) == 0) {
				$existe = true;
				break;
			}
		}

		/* Si le nom de la table n'existe PAS auparavant, ajouter ce nom à la section "FROM" et mettre à jour les tables utilisées */
		if(! $existe) {
			$vuesMat[$cle]['requete'] .= ', '.$vuesMat[$cle]['attributs'][$i][0];
			$tablesFROM_temp[] = $vuesMat[$cle]['attributs'][$i][0];
		}
	}

	/* Supprimer la variable (table) temporaire pour qu'elle soit vide pour la prochiane VM */
	unset($tablesFROM_temp);

	/* Ajouter les conditions de la VM à la requête */
	/* Le PREMIÈRE condition est un cas à traiter à part (avec " WHERE " au début et non pas " AND ") */
	$vuesMat[$cle]['requete'] .= ' WHERE '.preg_replace('#(access|"|filter)#i', '', $sousArbreJointure[0]->noeudById(0)->getPredicat());

	$vuesMat[$cle]['conditions'][] = preg_replace('#(access|"|filter)#i', '', $sousArbreJointure[0]->noeudById(0)->getPredicat());
	for ($i=1; $i < $sousArbreJointure[0]->longeurMVPP(); $i++) {
		/* Ajouter le prédicat d'un noeud de jointure commune actuelle s'il existe (chaine de caractères NON-VIDE) */
		if(strcasecmp($sousArbreJointure[0]->noeudById($i)->getPredicat(), '') != 0) {
			$vuesMat[$cle]['requete'] .= ' AND '.preg_replace('#(access|"|filter)#i', '', $sousArbreJointure[0]->noeudById($i)->getPredicat());
			$vuesMat[$cle]['conditions'][] = preg_replace('#(access|"|filter)#i', '', $sousArbreJointure[0]->noeudById($i)->getPredicat());
		}
	}

	/* Créer la VM actuelle (dans la BD) */
	$db->exec($vuesMat[$cle]['requete']);

	/* Vérifier si l'espace défini pour les VMs n'a PAS été dépassé */
	$res = $db->query("SELECT QUERY_LEN FROM USER_MVIEWS WHERE MVIEW_NAME='VM".$numeroVM."'");
	$infos = $res->fetch();
	$volumeVM = (float) $infos['QUERY_LEN'];

	$espaceVMs += $volumeVM;

	/* Si l'espace l'espace défini pour les VMs a été dépassé */
	if($espaceVMs > $espaceAlloue) {
		/* Retrancher l'espace de la VM actuelle */
		$espaceVMs -= $volumeVM;
		/* Supprimer la VM actuelle */
		$db->exec("DROP MATERIALIZED VIEW VM".$numeroVM);
		$db->exec("COMMIT");

		$numeroVM--;

		/* Supprimer la VM définie */
		unset($vuesMat[$cle]);
	}
	else { /* Si le volume de la VM est accepté, ajouter cette VM au script */
		/* Ajouter le coût de la VM actuelle à la somme des coûts des VM créées (pour des statistiques ultérieures) */
		$coutTOTAL_VMs += $volumeVM;

		fwrite($script, "/***** VM : ".$numeroVM." *****/".PHP_EOL);
		fwrite($script, $vuesMat[$cle]['requete'].";".PHP_EOL.PHP_EOL);

		/* Actualiser le vecteur avec les numéros des requêtes (initialisé à 0) [Pour distinguer les requêtes avec une VM de celles sans aucune VM] */
		foreach ($sousArbreJointure[1] as $numReq) {
			$requetesAvecVM[$numReq] = 1;
		}
	}
}


#############################################
########## RÉÉCRITURE DES REQUËTES ##########
#############################################

$requetesValidesPlan;
$requetesValidesPlan_AvecAgg_Apres = $requetesValidesPlan_AvecAgg_Avant;

/* Parcourir TOUTES les requêtes de TOUTES les sous-arbres de jointure communs */
foreach ($sousArbresCommuns as $cle => $sousArbreJointure) {
	/* Si la VM numéro "$cle" n'a PAS été définie (car son volume dépasse celui entré par l'utilisateur) */
	if(! isset($vuesMat[$cle])) continue;

	/* Parcourir TOUTES les requêtes de sous-arbre de jointure actuel */
	foreach ($sousArbreJointure[1] as $indexReqActuelle) {
		$requeteEcrite = '';

		/* Récupérer la premère partie de la requête "SELECT ... FROM" */
		preg_match_all('#SELECT.*FROM#is', $requeteAttributsTables[$indexReqActuelle]['requete'], $resultat);
		$requeteEcrite = $resultat[0][0];

		/* Ajouter les tables (à la section "FROM") de la requête actuelle SI ET SEULEMENT SI elles n'existent PAS dans la VM courante */
		foreach ($requeteAttributsTables[$indexReqActuelle]['tables'] as $idxTable => $nomTable) {
			$existe = false;

			foreach ($vuesMat[$cle]['attributs'] as $attributsVM) {
				/* Si le nom de la table existe déjà dans la VM courante */
				if(strcasecmp($nomTable, $attributsVM[0]) == 0) {
					$existe = true;
					break;
				}
			}

			if(! $existe) /* Si la table n'existe PAS dans la VM */
				$requeteEcrite .= ' '.$nomTable.',';

			/* Supprimer la table de la liste des tables de la VM, et trier les index */
			unset($requeteAttributsTables[$indexReqActuelle]['tables'][$idxTable]);
			sort($requeteAttributsTables[$indexReqActuelle]['tables']);
			}
		/* Ajouter la VM courante dans la section "FROM" */
		$requeteEcrite .= ' VM'.$cle;
		/* Ajouter la VM courante comme étant une table pour la requête actuelle */
		$requeteAttributsTables[$indexReqActuelle]['tables'][] = 'VM'.$cle;
		/* La variable "$nbConditionsReq" sert à indiquer si le prédicat actuel est le premier dans la section "WHERE" (pour bien plaçer les "AND" */
		$nbConditionsReq = 0;

		/* Ajouter les prédicats de la requête courante SI ET SEULEMENT SI ils n'existent PAS dans la VM courante */
		foreach ($requetesValidesPlan[$indexReqActuelle]->getListeNoeuds() as $noeudCourant) {
			/* Récupérer le prédicat formaté (sans "access", "filter", ...) de la ligne courante du plan d'exécution de la requête actuelle */
			$predicatCourant = preg_replace('#(access\(|"|\)|filter\()#i', '', $noeudCourant->getPredicat());
			/* Si le prédicat est vide, passer à la ligne suivante du plan d'exécution */
			if($predicatCourant == '') continue;
			/* On suppose, au début, que le prédicat n'existe PAS déjà dans la VM courante */
			$existe = false;

			/* Vérifier si le prédicat actuel de la requête courante existe déjà dans la VM courante */
			foreach ($vuesMat[$cle]['conditions'] as $conditionVMActuelle) {
				if(strcasecmp($predicatCourant, $conditionVMActuelle) == 0) { /* Si les deux prédicats sont égaux */
					$existe = true;
					break;
				}
			}

			if(! $existe) { /* Si le prédicat actuel n'existe PAS déjà dans la VM courante */
				if($nbConditionsReq === 0) { /* Le premire prédicat dans la section "WHERE" */
					$requeteEcrite .= ' WHERE ('.$predicatCourant.')';
					$nbConditionsReq++;
				}else /* Il y a déjà AU MOINS un prédicat dans la section "WHERE" */
					$requeteEcrite .= ' AND ('.$predicatCourant.')';
			}
		}
		/* Ajouter les clauses "GROUP BY ... ORDER BY ..." (dernières parties de la requête courante) */
		preg_match_all('#GROUP.*#is', $requeteAttributsTables[$indexReqActuelle]['requete'], $resultat);
		if(isset($resultat[0][0])) /* Si la requête courante contient les clauses "GROUP BY ... ORDER BY ..." */
			$requeteEcrite .= PHP_EOL."\t".$resultat[0][0];

		/* Remplaçer dans la requête actuelle les noms des tables qui appartiennent à la VM courante par son mon (VM0, VM1, ...) */
		foreach ($vuesMat[$cle]['attributs'] as $attributsVM) {
			$requeteEcrite = preg_replace('#'.$attributsVM[0].'#is', 'VM'.$cle, $requeteEcrite);
		}

		/* Actualiser la table contenant les informations détaillées sur la requête actuelle */
		$requeteAttributsTables[$indexReqActuelle] = requeteAttributsTables($db, $requeteEcrite);

		/* Actualiser le MVPP de la requête courante */
		$db->exec('EXPLAIN PLAN FOR '.suppAgregation($requeteAttributsTables[$indexReqActuelle]['requete']));

		$resultat = $db->query('SELECT plan_table_output FROM table(dbms_xplan.display
		(\'plan_table\', null, \'basic  +Rows +Bytes +cost +predicate\'))');

		$matrice = decouperExplainPlan($resultat);

		$requetesValidesPlan[$indexReqActuelle] = new MVPP();

		/* Créer le MVPP de la requête valide (courante), ajouter tous les noeuds au MVPP */
		for ($j=0; $j < count($matrice); $j++) {
			$requetesValidesPlan[$indexReqActuelle]->ajouterNoeud( new Noeud($matrice[$j][0], $matrice[$j][1],
				trim($matrice[$j][2], " "), trim($matrice[$j][3], " "), $matrice[$j][4], $matrice[$j][5], str_replace(' ', '', $matrice[$j][6]), trim($matrice[$j][7]), " "));
		}

		/* Actualiser le MVPP de la requête courante */
		$db->exec('EXPLAIN PLAN FOR '.$requeteAttributsTables[$indexReqActuelle]['requete']);

		$resultat = $db->query('SELECT plan_table_output FROM table(dbms_xplan.display
		(\'plan_table\', null, \'basic  +Rows +Bytes +cost +predicate\'))');

		$matrice = decouperExplainPlan($resultat);

		$requetesValidesPlan_AvecAgg_Apres[$indexReqActuelle] = new MVPP();

		/* Créer le MVPP de la requête valide (courante), ajouter tous les noeuds au MVPP */
		for ($j=0; $j < count($matrice); $j++) {
			$requetesValidesPlan_AvecAgg_Apres[$indexReqActuelle]->ajouterNoeud( new Noeud($matrice[$j][0], $matrice[$j][1],
				trim($matrice[$j][2], " "), trim($matrice[$j][3], " "), $matrice[$j][4], $matrice[$j][5], str_replace(' ', '', $matrice[$j][6]), trim($matrice[$j][7]), " "));
		}
	}
}


fwrite($script, "/************* Requêtes APRÈS l'optimisation *************/".PHP_EOL.PHP_EOL);

foreach ($requeteAttributsTables as $cle => $requete) {
	fwrite($script, PHP_EOL."/***** Requête : ".($cle+1)." *****/".PHP_EOL);
	fwrite($script, $requete['requete'].";".PHP_EOL.PHP_EOL);
}

?>

<script>
	var ctx = document.getElementById("myChart");
	var myChart = new Chart(ctx, {
		type: 'bar',
		data: {
			labels: [<?php
						for ($i=0; $i < count($requeteAttributsTables)-1; $i++)
							echo '"Requête '.($i+1).'", ';
						if(count($requeteAttributsTables) !== 0)
							echo '"Requête '.count($requeteAttributsTables).'"';
					?>],
			datasets: [{
				label: 'Avant',
				data: [<?php
						$coutTOTAL_Avant = 0;

						foreach ($requetesValidesPlan_AvecAgg_Avant as $requete) {

							for ($i=0; $i < $requete->longeurMVPP(); $i++) {
								$cout = $requete->noeudById($i)->getCost();
								if($cout != '') break;
							}

							preg_match_all('#(K|M)#i', $cout, $res);
							if(! isset($res[0][0])) $lettreTaille = '';
								else $lettreTaille = strtoupper($res[0][0]);

							switch ($lettreTaille) {
								case 'K':
									$coutTOTAL_Avant += ((int)$cout)*1024;
									echo (((int)$cout)*1024).', ';
									break;
								case 'M':
									$coutTOTAL_Avant += ((int)$cout)*1024*1024;
									echo (((int)$cout)*1024*1024).', ';
									break;
								default:
									$coutTOTAL_Avant += (int)$cout;
									echo ((int)$cout).', ';
							}
						}
					?>],
				backgroundColor: [<?php
									for ($i=0; $i < count($requeteAttributsTables); $i++)
										echo "'rgba(255, 99, 132, 1)', ";
								?>],
				borderWidth: 0
			},
			{ label: 'Après',
				data: [<?php
						$coutTOTAL_Apres = 0;

						foreach ($requetesValidesPlan_AvecAgg_Apres as $requete) {

							for ($i=0; $i < $requete->longeurMVPP(); $i++) {
								$cout = $requete->noeudById($i)->getCost();
								if($cout != '') break;
							}

							preg_match_all('#(K|M)#i', $cout, $res);
							if(! isset($res[0][0])) $lettreTaille = '';
								else $lettreTaille = strtoupper($res[0][0]);

							switch ($lettreTaille) {
								case 'K':
									$coutTOTAL_Apres += ((int)$cout)*1024;
									echo (((int)$cout)*1024).', ';
									break;
								case 'M':
									$coutTOTAL_Apres += ((int)$cout)*1024*1024;
									echo (((int)$cout)*1024*1024).', ';
									break;
								default:
									$coutTOTAL_Apres += (int)$cout;
									echo ((int)$cout).', ';
							}
						}
					?>],
				backgroundColor: [<?php
									for ($i=0; $i < count($requeteAttributsTables); $i++)
										echo "'rgba(54, 162, 235, 1)', ";
								?>],
				borderWidth: 0
			}
			]
		},
		options: {
			animation: {
					duration : 2000
			},
			responsive : true,
			title: {
				display: true,
				text: 'Comparaison des coûts des requêtes AVANT et APRÈS l\'optimisation',
				fontSize: 15
			}
		}
	});
</script>

<script>
	var ctx = document.getElementById('myChart1');
	window.myBar = new Chart(ctx, {
		type: 'bar',
		data:{
			labels: ['Gain de : <?php echo round((($coutTOTAL_Avant-($coutTOTAL_Apres+$coutTOTAL_VMs))*100)/$coutTOTAL_Avant, 2); ?> %'],
			datasets: [{
				label: 'Requêtes avant',
				backgroundColor: '#A93226',
				stack: 'Pile 0',
				data: [<?php echo $coutTOTAL_Avant ?>],
				borderWidth: 0
			}, {
				label: 'Requêtes après',
				backgroundColor: '#1D8348',
				stack: 'Pile 1',
				data: [<?php echo $coutTOTAL_Apres ?>],
				borderWidth: 0
			}, {
				label: 'VM définies',
				backgroundColor: '#21618C',
				stack: 'Pile 1',
				data: [<?php echo $coutTOTAL_VMs ?>],
				borderWidth: 0
			}]
		},
		options: {
			animation: {
				duration : 2000
			},
			title: {
				display: true,
				text: 'Comparaison du coût TOTAL des requêtes AVANT et APRÈS l\'optimisation (avec VM comprises)',
				fontSize: 14
			},
			tooltips: {
				mode: 'index',
				intersect: false
			},
		}
	});
</script>

<button type="button" onclick="allerInsertionRequetes()" id="revenirBtn">Revenir à l'insertion des requêtes</button>
<button type="button" onclick="seDeconnecter()" id="deconnexionBtn">Déconnexion</button>

<a href=<?php echo '"../Control/'.$nomScript.'"'; ?> title="Script d'optimisation des requêtes SQL">Télécharger le script (.sql)</a>
