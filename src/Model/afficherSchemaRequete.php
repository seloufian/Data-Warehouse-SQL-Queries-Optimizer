<?php
	include 'creationExplainPlan.php';
	include 'dessinerTable.php';

function afficherSchemaRequete($db, $requete) {

	if(($db->exec('EXPLAIN PLAN FOR '.$requete)) !== 0) return false ;

	$resultat = $db->query('SELECT plan_table_output FROM table(dbms_xplan.display
	(\'plan_table\', null, \'basic  +Rows +Bytes +cost +predicate\'))');

	$matrice = decouperExplainPlan($resultat);

	dessinerTable($matrice);

	return($matrice);
}
?>
