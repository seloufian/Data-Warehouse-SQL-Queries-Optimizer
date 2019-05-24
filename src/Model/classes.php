<?php

class Noeud {

	private $id, $depth, $operationName, $name, $rows, $bytes, $cost, $predicat;

	function __construct($id, $depth, $operationName, $name, $rows, $bytes, $cost, $predicat) {
		$this->id = $id;
		$this->depth = $depth;
		$this->operationName = $operationName;
		$this->name = $name;
		$this->rows = $rows;
		$this->bytes = $bytes;
		$this->cost = $cost;
		$this->predicat = $predicat;
	}

	public function getId() { return $this->id; }
	public function getDepth() { return $this->depth; }
	public function getOperationName() { return $this->operationName; }
	public function getName() { return $this->name; }
	public function getRows() { return $this->rows; }
	public function getBytes() { return $this->bytes; }
	public function getCost() { return $this->cost; }
	public function getPredicat() { return $this->predicat; }
}



class MVPP {

	private $listeNoeuds;

	function __construct() { $this->listeNoeuds = array(); }

	public function getListeNoeuds() { return $this->listeNoeuds; }
	public function setListeNoeuds($listeNoeuds) { $this->listeNoeuds = $listeNoeuds; }

	public function ajouterNoeud($noeud) { $this->listeNoeuds[] = $noeud; }

	public function longeurMVPP() { return count($this->listeNoeuds); }

	public function noeudById($id) { return $this->listeNoeuds[$id]; }

	static public function comparerMVPP($mvpp1, $mvpp2) {
		/* Si la taille des deux MVPPs est différente, alors ils ne sont pas égaux (éliminer le cas le plus fréquent, et réduire le
		temps d'exécution de la fonction) */
		if($mvpp1->longeurMVPP() != $mvpp2->longeurMVPP()) return false;

		/* Sauvgarder la profondeur des HASH JOIN des deux MVPPs, pour comparer la profondeur relative des fils et leur père */
		$profondeurPereMvpp1 = $mvpp1->noeudById(0)->getDepth();
		$profondeurPereMvpp2 = $mvpp2->noeudById(0)->getDepth();

		/* TOUT élément du "mvpp1" doit exister dans "mvpp2" */
		for ($i=1; $i < $mvpp1->longeurMVPP(); $i++) {
			for ($j=1; $j < $mvpp2->longeurMVPP(); $j++) {
				$existe = false;
				if( (($mvpp1->noeudById($i)->getDepth() - $profondeurPereMvpp1) == ($mvpp2->noeudById($j)->getDepth() - $profondeurPereMvpp2)) &&
					(strcasecmp($mvpp1->noeudById($i)->getName(), $mvpp2->noeudById($j)->getName()) == 0) &&
					(strcasecmp($mvpp1->noeudById($i)->getOperationName(), $mvpp2->noeudById($j)->getOperationName()) == 0) &&
					(strcasecmp(suppVarPredicat($mvpp1->noeudById($i)->getPredicat()), suppVarPredicat($mvpp2->noeudById($j)->getPredicat())) == 0) ) {
					$existe = true;
					break;
				}
			}
			/* Il y a AU MOINS un élément qui existe dans "mvpp1" mais PAS dans "mvpp2", ils ne sont pas égaux ! */
			if(! $existe) return false;
		}

		/* TOUT élément du "mvpp2" doit exister dans "mvpp1" */
		for ($i=1; $i < $mvpp2->longeurMVPP(); $i++) {
			for ($j=1; $j < $mvpp1->longeurMVPP(); $j++) {
				$existe = false;
				if( (($mvpp2->noeudById($i)->getDepth() - $profondeurPereMvpp2) == ($mvpp1->noeudById($j)->getDepth() - $profondeurPereMvpp1)) &&
					(strcasecmp($mvpp2->noeudById($i)->getName(), $mvpp1->noeudById($j)->getName()) == 0) &&
					(strcasecmp($mvpp2->noeudById($i)->getOperationName(), $mvpp1->noeudById($j)->getOperationName()) == 0) &&
					(strcasecmp(suppVarPredicat($mvpp2->noeudById($i)->getPredicat()), suppVarPredicat($mvpp1->noeudById($j)->getPredicat())) == 0) ) {
					$existe = true;
					break;
				}
			}
			/* Il y a AU MOINS un élément qui existe dans "mvpp2" mais PAS dans "mvpp1", ils ne sont pas égaux ! */
			if(! $existe) return false;
		}

		/* Vérification bi-directionelle efféctuée (+ la taille des deux MVPPs est la même),
		TOUS les éléments du "mvpp1" existent dans "mvpp2" et vice-versa */
		return true;
	}
}
