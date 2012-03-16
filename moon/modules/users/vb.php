<?php


class vb extends moon_com {


	function onload() {
		/* table Users*/
		$this->myTable = 'vb_user';
		$this->db = & moon::db('database-vb');
	}


	function users($ids) {
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		foreach ($ids as $k=>$v) {
			$ids[$k]=intval($v);
		}
		$ids = array_unique($ids);
		if (!count($ids)) {
			return array();
		}
		$sql='SELECT userid as id, username as nick FROM '.$this->myTable.' WHERE userid IN ('.implode(',',$ids).')';
		$n=$this->db->array_query_assoc(  $sql , 'id');
		foreach ($n as $id=>$v) {
			$n[$id]['avatar'] = '';
		}
		return $n;
	}


	function getUserIdByNick($nick='') {
		if (!$nick) return 0;
		$sql='SELECT userid FROM '.$this->myTable.' WHERE username = "' . $this->db->escape($nick) . '"';
		$n = $this->db->single_query($sql);
		return (count($n) ? $n[0] : 0);
	}


}

?>