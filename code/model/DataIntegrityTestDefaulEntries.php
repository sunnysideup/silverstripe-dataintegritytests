<?php


class DataIntegrityTestDefaulEntries extends Object {

	public static function update($baseTable, $field, $value, $id = 0, $replace = false, $addLive = false) {
		$object = DataObject::get_one($baseTable);
		if($object) {
			$tableArray = array($baseTable);
			if($object instanceOf SiteTree) {
				$tableArray[] = $baseTable."_Live";
			}
			foreach($tableArray as $table) {
				$value = Convert::raw2sql($value);
				$sql = "UPDATE \"$table\" SET \"$table\".\"$field\" = '$value'";
				$where = array();
				if($id) {
					$where[] = "  \"$table\".\"ID\" = ".$id;
				}
				if(!$replace) {
					$where[] = " \"$table\".\"$field\" IS NULL OR \"$table\".\"$field\" = '' OR \"$table\".\"$field\" = 0 ";
				}
				$wherePhrase = '';
				if(count($where)) {
					$wherePhrase = " WHERE ( " . implode(") AND (", $where) . " )";
				}
				$result = DB::query("SELECT COUNT(\"$table\".\"ID\") C FROM \"$table\" ".$wherePhrase);
				if($result && $result->value() ) {
					$sql .= $wherePhrase;
					DB::query($sql);
					DB::alteration_message("Updated $field in $table to $value ", "added");
				}
			}
		}
	} 


}
