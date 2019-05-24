<?php
/* Fonction récursive. Pour le premier appel de la fonction, "$i" doit-être égal à "-1" */
function dessinerArbre($matrice, $i) {
	$n = 0;

	while ($n < count($matrice)) {
		if ($matrice[$n][1] == $i) {
			echo '<li>' . $matrice[$n][2] . '<br/><span class=\'title\'>' . $matrice[$n][3] . '</span><ul>';
			dessinerArbre($matrice, $matrice[$n][0]);
		}
		$n++;
	}
	if($i !== -1) {
		echo '</ul></li>';
	}
}
?>
