2019-05-14 08:08

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader

Warnings for src/DataIntegrityTest.php:
 - src/DataIntegrityTest.php:146 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 146

 - src/DataIntegrityTest.php:206 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 206

 - src/DataIntegrityTest.php:218 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 218

 - src/DataIntegrityTest.php:230 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 230

Warnings for src/CheckForMysqlPaginationIssuesBuildTask.php:
 - src/CheckForMysqlPaginationIssuesBuildTask.php:122 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 122

 - src/CheckForMysqlPaginationIssuesBuildTask.php:206 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 206

 - src/CheckForMysqlPaginationIssuesBuildTask.php:323 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 323

 - src/CheckForMysqlPaginationIssuesBuildTask.php:345 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 345

 - src/CheckForMysqlPaginationIssuesBuildTask.php:368 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 368

 - src/CheckForMysqlPaginationIssuesBuildTask.php:411 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 411

Warnings for src/DataIntegrityTest.php:
 - src/DataIntegrityTest.php:161 SilverStripe\ORM\DataObject::database_fields(): DataObject::database_fields() moved to DataObjectSchema->databaseFields(). Access through getSchema()
unchanged:	src/DataIntegrityTestRecentlyChanged.php
Warnings for src/DataIntegrityTestRecentlyChanged.php:
 - src/DataIntegrityTestRecentlyChanged.php:103 class: $this->class access has been removed (https://docs.silverstripe.org/en/4/changelogs/4.0.0#object-replace)
 - src/DataIntegrityTestRecentlyChanged.php:104 class: $this->class access has been removed (https://docs.silverstripe.org/en/4/changelogs/4.0.0#object-replace)
 - src/DataIntegrityTestRecentlyChanged.php:108 class: $this->class access has been removed (https://docs.silverstripe.org/en/4/changelogs/4.0.0#object-replace)
unchanged:	src/CheckForMysqlPaginationIssuesBuildTask.php
Warnings for src/CheckForMysqlPaginationIssuesBuildTask.php:
 - src/CheckForMysqlPaginationIssuesBuildTask.php:194 SilverStripe\ORM\DataObject::has_own_table_database_field(): DataObject::has_own_table_database_field() has been replaced with DataObjectSchema::fieldSpec(). Access through getSchema() (https://docs.silverstripe.org/en/4/changelogs/4.0.0#dataobject-has-own)
Writing changes for 1 files
