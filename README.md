
Data Integrity Tests
================================================================================


Adding this module allows you to check basic
database integrity (e.g. obsolete fields), missing
records, etc...

You can access it through
http://www.yoursite.co.nz/dev/tasks/DataIntegrityTest/


Developer
-----------------------------------------------
Nicolaas [at] sunnysideup.co.nz


Requirements
-----------------------------------------------
see composer.json


Documentation
-----------------------------------------------
Please contact author for more details.

Any bug reports and/or feature requests will be
looked at in detail

We are also very happy to provide personalised support
for this module in exchange for a small donation.


Installation Instructions
-----------------------------------------------
1. Find out how to add modules to SS and add module as per usual.

2. Review configs and add entries to mysite/_config/config.yml
(or similar) as necessary.
In the _config/ folder of this module
you can usually find some examples of config options (if any).


# example of moving a field

```php


abstract class DataIntegrityTest_MigrateFieldExample extends Buildtask
{
	protected $title = 'Move field example';

	protected $description = 'Move a field within table, example only';

	protected $enabled = false;

	/**
	 * @var string
	 */
	protected $fieldNameOld = "OldField";

	/**
	 * @var string
	 */
	protected $fieldNameNew = "NewField";

	/**
	 * @var string
	 */
	protected $tableNameOld = "TableName";

	/**
	 * @var string
	 */
	protected $tableNameNew = "TableName";

	public function run($request)
	{
		if(!$this->tableNameNew) {
			$this->tableNameNew = $this->tableNameOld;
		}

		//move data
		$migrationSucess = false;
		if($this->hasTableAndField($this->tableNameOld, $this->fieldNameOld)) {
			if($this->hasTableAndField($this->tableNameNew, $this->fieldNameNew)) {
				$join = '';
				if($this->tableNameOld !== $this->tableNameNew) {
					$join = ' INNER JOIN "'.$this->tableNameOld.'" ON "'.$this->tableNameOld.'"."ID" = "'.$this->tableNameNew.'"."ID" ';
				}
				DB::query('
					UPDATE "'.$this->tableNameNew.'"
					'.$join.'
					SET "'.$this->tableNameNew.'"."'.$this->fieldNameNew.'" = "'.$this->tableNameOld.'"."'.$this->fieldNameOld.'"
					WHERE (
						"'.$this->tableNameNew.'"."'.$this->fieldNameNew.'" = 0 OR
						"'.$this->tableNameNew.'"."'.$this->fieldNameNew.'" = \'\' OR
						"'.$this->tableNameNew.'"."'.$this->fieldNameNew.'" IS NULL
					)
					;'
				);
				$migrationSucess = true;
			}
		}
		if($migrationSucess) {
			DB::alteration_message("Migrated data from ".$this->tableNameOld.".".$this->fieldNameOld." to ".$this->tableNameNew.".".$this->fieldNameNew, "created");
		}
		else {
			DB::alteration_message("ERROR IN migrating data from ".$this->tableNameOld.".".$this->fieldNameOld." to ".$this->tableNameNew.".".$this->fieldNameNew, "deleted");
		}


		//make obsolete
		if($this->hasTableAndField($this->tableNameOld, $this->fieldNameOld)) {
			$db = DB::getConn();
			$db->dontRequireField($this->tableNameOld, $this->fieldNameOld);
			DB::alteration_message("removed ".$this->fieldNameOld." from ".$this->tableNameOld."", "deleted");
		}
		else {
			DB::alteration_message("ERROR: could not find ".$this->fieldNameOld." in ".$this->tableNameOld." so it could not be removed", "deleted");
		}
		if($this->hasTableAndField($this->tableNameOld, $this->fieldNameOld)) {
			DB::alteration_message("ERROR: tried to remove ".$this->fieldNameOld." from ".$this->tableNameOld." but it still seems to be there", "deleted");
		}
	}

	/**
	 * Returns true if the table and field (within this table) exist.
	 * Otherwise it returns false.
	 * @param string - $field - name of the field to be tested
	 * @param string - $table - name of the table to be tested
	 *
	 * @return Boolean
	 */
	protected function hasTableAndField($table, $field) {
		$db = DB::getConn();
		if($db->hasTable($table)) {
			$fieldArray = $db->fieldList($table);
			if(isset($fieldArray[$field])) {
				return true;
			}
		}
		return false;
	}


}



```
