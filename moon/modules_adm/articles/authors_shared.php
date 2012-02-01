<?php
class authors_shared extends moon_com {


//***************************************
//           --- EXTERNAL ---
//***************************************

//this method is used by other components
//
// returns IDs  of the authors
function assignAuthors($authorsString)
{
	$ids = array();

	$db = & $this->db();
	$authorsString = str_replace(';', ',', $authorsString);
	$chunks = explode(',' , trim($authorsString, ', '));
	$authors = array();
	$table = $this->table('Authors');
	foreach ($chunks as $chunk) {
		$chunk = trim($chunk);
		if ('' === $chunk) {
			continue;
		}
		$sql = "SELECT id FROM $table
		WHERE name LIKE '" . $db->escape($chunk, true) . "'
			AND duplicates=0 AND is_deleted=0";
		if (count( $is = $db->single_query($sql) )) {
			$ids[] = $is[0];
		}
		else {
			//reikia iterpti autoriu
			$id = $db->insert(
				array(
					'name' => $chunk,
					'created_on' => time()
				), $table, 'id');
			if ($id) $ids[] = $id;
		}
	}
	return implode(',', array_unique($ids));
}

//this method is used by other components
//
// returns names of the authors (as string)
function getAuthorsString($idsString)
{
	$a = is_array($idsString) ? $idsString : explode(',', $idsString);
	$ids = array();
	foreach ($a as $id) {
		if ($id = intval($id)) {
			$ids[] = $id;
		}
	}
	if (!count($ids)) return '';
	$m = $this->db->array_query_assoc(
		"SELECT duplicates,id FROM " . $this->table('Authors') .
		" WHERE id IN (" . implode(', ', $ids) . ") AND duplicates>0"
	);
	//pakeiciam id tu, kurie turi pakaitala
	foreach ($m as $v) {
		$k = array_search($v['id'],$ids);
		if (isset($ids[$k])) {
			$ids[$k] = $v['duplicates'];
		}
	}

    $authors = $this->db->array_query(
		"SELECT id,name FROM " . $this->table('Authors') .
		" WHERE id IN (" . implode(', ', $ids) . ") AND is_deleted=0",
		TRUE
	);
	$r = array();
	foreach ($ids as $id) {
		if (isset($authors[$id])) {
			$r[] = $authors[$id];
		}
	}
	return implode(', ',$r);
}


//this method is used by other components
//
// returns array of the authors (including duplicates)
function getAuthorsArray($idsString)
{
	$a = is_array($idsString) ? $idsString : explode(',', $idsString);
	$ids = array();
	foreach ($a as $id) {
		if ($id = intval($id)) {
			$ids[] = $id;
		}
	}
	if (!count($ids)) return '';
    $ids = array_unique($ids);
	$m = $this->db->array_query_assoc(
		"SELECT duplicates,id FROM " . $this->table('Authors') .
		" WHERE id IN (" . implode(', ', $ids) . ") AND duplicates>0"
	);
	//pakeiciam id tu, kurie turi pakaitala
	$old = array();
	foreach ($m as $v) {
		$old[$v['id']] = $v['duplicates'];
		$k = array_search($v['id'],$ids);
		if (isset($ids[$k])) {
			$ids[$k] = $v['duplicates'];
		}
	}
	$ids = array_unique($ids);

    $authors = $this->db->array_query_assoc(
		"SELECT id, name FROM " . $this->table('Authors') .
		" WHERE id IN (" . implode(', ', $ids) . ") AND is_deleted=0",
		'id'
	);

	foreach ($old as $id=>$newID) {
		if (isset($authors[$newID])) {
			$authors[$id] = $authors[$newID];
		}
	}
	return $authors;
}

}
?>