<?php
/////////////////////////////////////////////
////////////// F O N C T I O N //////////////
/////////////////////////////////////////////
function nbEspacesDebut($chaine)
	{
	$nbEsp=0; /* Joue deux rôles : Un indicateur du caractère ET un compteur d'espaces */
	while($chaine[$nbEsp] == ' ') $nbEsp++;
	return $nbEsp;
	}

/////////////////////////////////////////////
////////////// F O N C T I O N //////////////
/////////////////////////////////////////////
function decouperExplainPlan($resultat)
{
/* Des lectures dans le vide, pour arriver à la table concernée */
$resultat->fetch();	$resultat->fetch();	$resultat->fetch();
$resultat->fetch();	$resultat->fetch();

$cpt=0; /* Compteur de la ligne du tableau */
$decoupeMatrice = array(); /* Création du tableau */

while($donnees = $resultat->fetch())
	{
	if($donnees[0][1] == '-') break; /* On est arrivé à la fin de la table */
	else
		{
		/* On supprimer le premier et le dernier '|' pour faciliter le découpage de la chaine */
		$donnees[0] = ltrim($donnees[0], '|'); /* Supprimer le 1er '|' */
		$donnees[0] = rtrim($donnees[0], '|'); /* Supprimer le dernier '|' */

		/* Découper la chaine en fonction du séparateur '|' */
		$ChDecoupe = explode('|', $donnees[0]);

		/* Création d'un tableau associé à la ligne "cpt",
			 pour contourner le problème d'inexistance de la notion du "Matrice" dans PHP */
		$decoupeMatrice[$cpt]= array();

		/* "id" de la ligne */
		$decoupeMatrice[$cpt][0] = (int) stristr($ChDecoupe[0], ' ');

		/* Le père */
		if(((nbEspacesDebut($ChDecoupe[1])) - 2) < 0)
			$decoupeMatrice[$cpt][1] = -1; /* La racine n'a pas de père */
		else
			/* Le(s) fils de la racine a(ont) deux espaces, l'indice de son(leur) père "2 -2 = 0"
				 et ainsi de suite */
			$decoupeMatrice[$cpt][1] = nbEspacesDebut($ChDecoupe[1]) - 2;

		/* Nom d'opération */
		$decoupeMatrice[$cpt][2] = $ChDecoupe[1];

		/* Name */
		$decoupeMatrice[$cpt][3] = $ChDecoupe[2];

		/* Rows */
		$decoupeMatrice[$cpt][4] = (int) $ChDecoupe[3];

		/* Bytes */
		$decoupeMatrice[$cpt][5] = (int) $ChDecoupe[4];

		/* Cost (% CPU) */
		$decoupeMatrice[$cpt][6] = $ChDecoupe[5];

		/* Predicate (Chaine de caractères vide) */
		$decoupeMatrice[$cpt][7] = '';

		$cpt++;

		}
	}

/* On récupère les prédicats possibles pour une ligne donnée */

/* La variable "refLigne" jouera les rôle d'une référence à la ligne qu'on l'a ajouté son "Predicate"
	(identificateur de la continuité de la ligne précédente) */
$refLigne = '';

/* La variable "idLigne" permet de définir le numéro de la ligne concernée par le "Predicate" */
$idLigne = '';

/* Lectures dans le vide, pour arriver à la section "Predicate" */
$resultat->fetch();	$resultat->fetch();
$resultat->fetch();	$resultat->fetch();

/* à la fin de la section du "Predicate", on sort de la boucle "While" automatiquement
(La dernière ligne du "Predicate" est elle-même la dernière ligne du "EXPLAIN_PLAN")  */
while($donnees = $resultat->fetch())
	{
		/* La ligne du prédicat ne contient pas la sous-chaine "filter" ou "access", il s'agit d'une continuation de ligne */
	$cpt = strpos($donnees[0], 'filter');

	if( $cpt === false )
		{
		$cpt = strpos($donnees[0], 'access');

		if( $cpt === false )
			$decoupeMatrice[$refLigne][7] = rtrim($decoupeMatrice[$refLigne][7], "\n") . $donnees[0];
		else
		/* On a trouvé la sous-chaine "access", il s'agit d'un nouvel prédicat */
		{
			$idLigne = ''; /* Réinitialisation de la variable "idLigne" */

				/* La variable "cpt" est un entier indiquant le début de la sous-chaine "access", la boucle s'arrête au blan avat "-" */
			for($i=0; $i<$cpt-3; $i++)
			$idLigne = $donnees[0][$i] . $idLigne;

			$idLigne = (int) $idLigne; /* Un cast en "entier" */

			/* Affecter la chaine "access(....)" au champ "Predicate" de la ligne donnée */
			$decoupeMatrice[$idLigne][7] = stristr($donnees[0], 'a');

			$refLigne = $idLigne; /* Sauvgarder la ligne (au cas où la prochaine ligne sera la continuation de la ligne courante) */
			}
		}
		else
		/* On a trouvé la sous-chaine "filter", il s'agit d'un nouvel prédicat */
		{
		$idLigne = ''; /* Réinitialisation de la variable "idLigne" */

			/* La variable "cpt" est un entier indiquant le début de la sous-chaine "filter", la boucle s'arrête au blan avat "-" */
		for($i=0; $i<$cpt-3; $i++)
			$idLigne = $donnees[0][$i] . $idLigne;

		$idLigne = (int) $idLigne; /* Un cast en "entier" */

		/* Affecter la chaine "filter(....)" au champ "Predicate" de la ligne donnée */
		$decoupeMatrice[$idLigne][7] = stristr($donnees[0], 'f');

		$refLigne = $idLigne; /* Sauvgarder la ligne (au cas où la prochaine ligne sera la continuation de la ligne courante) */
		}

	}

return $decoupeMatrice;
}

?>
