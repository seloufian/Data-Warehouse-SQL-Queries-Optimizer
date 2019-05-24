<?php
function dessinerTable($matrice) {

	echo '<table id="table_Affichage">
		<tr>
			<th>Id</th>
			<th>Depth</th>
			<th>Operation name</th>
			<th>Name</th>
			<th>Rows</th>
			<th>Bytes</th>
			<th>Cost (%CPU)</th>
			<th>Prédicat</th>
		</tr>';

	foreach($matrice as $ligne)
			echo '<tr>' .
				 '<td>' . $ligne[0] . '</td>' .
				 '<td>' . ($ligne[1]+1) . '</td>' .
				 '<td>' . $ligne[2] . '</td>' .
				 '<td>' . $ligne[3] . '</td>' .
				 '<td>' . $ligne[4] . '</td>' .
				 '<td>' . $ligne[5] . '</td>' .
				 '<td>' . $ligne[6] . '</td>' .
				 '<td>' . $ligne[7] . '</td></tr>';
	echo '</table>';
}
?>
