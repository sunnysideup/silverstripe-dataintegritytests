2019-05-14 08:08

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/upgrades/__upgradeto4__
php /var/www/upgrader/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /var/www/upgrades/__upgradeto4__/dataintegritytests  --root-dir=/var/www/upgrades/__upgradeto4__ --write -vvv --prompt
Writing changes for 8 files
Running upgrades on "/var/www/upgrades/__upgradeto4__/dataintegritytests"
[2019-05-14 20:08:12] Applying RenameClasses to DataIntegrityTestInnoDB.php...
[2019-05-14 20:08:12] Applying RenameClasses to DataIntegrityTest.php...
[2019-05-14 20:08:12] Applying RenameClasses to DataIntegrityTestYML.php...
[2019-05-14 20:08:12] Applying RenameClasses to DataIntegrityTestUTF8.php...
[2019-05-14 20:08:12] Applying RenameClasses to DataIntegrityTestRecentlyChanged.php...
[2019-05-14 20:08:12] Applying RenameClasses to DataIntegrityMoveFieldUpOrDownClassHierarchy.php...
[2019-05-14 20:08:12] Applying RenameClasses to CheckForMysqlPaginationIssuesBuildTask.php...
[2019-05-14 20:08:12] Applying RenameClasses to DataIntegrityTestDefaultEntries.php...
[2019-05-14 20:08:12] Applying RenameClasses to _config.php...
[2019-05-14 20:08:12] Applying UpdateConfigClasses to config.yml...
modified:	src/DataIntegrityTestInnoDB.php
@@ -2,8 +2,11 @@

 namespace Sunnysideup\DataIntegrityTest;

-use BuildTask;
-use DB;
+
+
+use SilverStripe\ORM\DB;
+use SilverStripe\Dev\BuildTask;
+




modified:	src/DataIntegrityTest.php
@@ -2,17 +2,30 @@

 namespace Sunnysideup\DataIntegrityTest;

-use BuildTask;
-use Config;
-use ClassInfo;
-use DB;
-use TestOnly;
-use DataObject;
-use Director;
-use Versioned;
-use DataExtension;
-use DatabaseAdmin;
-use SiteTree;
+
+
+
+
+
+
+
+
+
+
+
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\DataIntegrityTest\DataIntegrityTest;
+use SilverStripe\ORM\DataObject;
+use SilverStripe\Core\ClassInfo;
+use SilverStripe\ORM\DB;
+use SilverStripe\Dev\TestOnly;
+use SilverStripe\Control\Director;
+use SilverStripe\Versioned\Versioned;
+use SilverStripe\ORM\DataExtension;
+use SilverStripe\ORM\DatabaseAdmin;
+use SilverStripe\CMS\Model\SiteTree;
+use SilverStripe\Dev\BuildTask;
+



@@ -76,7 +89,7 @@
         if ($action = $request->getVar("do")) {
             $methodArray = explode("/", $action);
             $method = $methodArray[0];
-            $allowedActions = Config::inst()->get("DataIntegrityTest", "allowed_actions");
+            $allowedActions = Config::inst()->get(DataIntegrityTest::class, "allowed_actions");
             if (isset($allowedActions[$method])) {
                 if ($method == "obsoletefields") {
                     $deletesafeones = $fixbrokendataobjects = $deleteall = false;
@@ -97,7 +110,7 @@
                 user_error("could not find method: $method");
             }
         }
-        $warning = Config::inst()->get("DataIntegrityTest", "warning");
+        $warning = Config::inst()->get(DataIntegrityTest::class, "warning");
         echo "<h2>Database Administration Helpers</h2>";
         echo "<p><a href=\"".$this->Link()."?do=obsoletefields\">Prepare a list of obsolete fields.</a></p>";
         echo "<p><a href=\"".$this->Link()."?do=obsoletefields&amp;deletesafeones=1\" onclick=\"return confirm('".$warning."');\">Prepare a list of obsolete fields and DELETE! obsolete fields without any data.</a></p>";
@@ -124,7 +137,7 @@
     protected function obsoletefields($deleteSafeOnes = false, $fixBrokenDataObject = false, $deleteAll = false)
     {
         increase_time_limit_to(600);
-        $dataClasses = ClassInfo::subclassesFor('DataObject');
+        $dataClasses = ClassInfo::subclassesFor(DataObject::class);
         $notCheckedArray = [];
         $canBeSafelyDeleted = [];
         //remove dataobject
@@ -158,7 +171,7 @@
                                 if ($deleteAll) {
                                     $link = " !!!!!!!!!!! DELETED !!!!!!!!!";
                                 } else {
-                                    $warning = Config::inst()->get("DataIntegrityTest", "warning");
+                                    $warning = Config::inst()->get(DataIntegrityTest::class, "warning");
                                     $link = "<a href=\"".Director::absoluteBaseURL()."dev/tasks/DataIntegrityTest/?do=deleteonefield/".$dataClass."/".$actualField."/\" onclick=\"return confirm('".$warning."');\">delete field</a>";
                                 }
                                 if (!in_array($actualField, array("ID", "Version"))) {
@@ -222,9 +235,9 @@
                                     DB::alteration_message("Now trying to recreate missing items... COUNT = ".$objects->count(), "created");
                                     foreach ($objects as $object) {
                                         if (DB::query("SELECT COUNT(\"ID\") FROM \"$dataClass\" WHERE \"ID\" = ".$object->ID.";")->value() != 1) {
-                                            Config::modify()->update('DataObject', 'validation_enabled', false);
+                                            Config::modify()->update(DataObject::class, 'validation_enabled', false);
                                             $object->write(true, false, true, false);
-                                            Config::modify()->update('DataObject', 'validation_enabled', true);
+                                            Config::modify()->update(DataObject::class, 'validation_enabled', true);
                                         }
                                     }
                                     $objectCount = $dataClass::get()->count();
@@ -283,7 +296,7 @@
                     //not sure why we have this.
                     if ($obj instanceof DataExtension) {
                         $remove = false;
-                    } elseif (class_exists("Versioned") && $obj->hasExtension("Versioned")) {
+                    } elseif (class_exists(Versioned::class) && $obj->hasExtension(Versioned::class)) {
                         $remove = false;
                     }
                 } else {
@@ -334,7 +347,7 @@

     public function deletemarkedfields()
     {
-        $fieldsToDelete = Config::inst()->get("DataIntegrityTest", "fields_to_delete");
+        $fieldsToDelete = Config::inst()->get(DataIntegrityTest::class, "fields_to_delete");
         if (is_array($fieldsToDelete)) {
             if (count($fieldsToDelete)) {
                 foreach ($fieldsToDelete as $key => $tableDotField) {
@@ -381,7 +394,7 @@
     private function deleteField($table, $field)
     {
         $fields = $this->swapArray(DB::fieldList($table));
-        $globalExeceptions = Config::inst()->get("DataIntegrityTest", "global_exceptions");
+        $globalExeceptions = Config::inst()->get(DataIntegrityTest::class, "global_exceptions");
         if (count($globalExeceptions)) {
             foreach ($globalExeceptions as $exceptionTable => $exceptionField) {
                 if ($exceptionTable == $table && $exceptionField == $field) {
@@ -496,7 +509,7 @@
   */
                     $obj = DataObject::get_one($className);
                     if ($obj) {
-                        if ($obj->hasExtension("Versioned")) {
+                        if ($obj->hasExtension(Versioned::class)) {
                             DB::alteration_message("Removing all records from $table", "created");
                             DB::query("DELETE FROM \"$table\" ");
                         }

Warnings for src/DataIntegrityTest.php:
 - src/DataIntegrityTest.php:146 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 146

 - src/DataIntegrityTest.php:206 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 206

 - src/DataIntegrityTest.php:218 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 218

 - src/DataIntegrityTest.php:230 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 230

modified:	src/DataIntegrityTestYML.php
@@ -2,12 +2,18 @@

 namespace Sunnysideup\DataIntegrityTest;

-use BuildTask;
-use Config;
+
+
 use db;
-use Director;
+
 use Spyc;
-use SiteTree;
+
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\DataIntegrityTest\DataIntegrityTestYML;
+use SilverStripe\Control\Director;
+use SilverStripe\CMS\Model\SiteTree;
+use SilverStripe\Dev\BuildTask;
+



@@ -58,9 +64,9 @@
   * ### @@@@ STOP REPLACEMENT @@@@ ###
   */
         require_once 'thirdparty/spyc/spyc.php';
-        $filesArray = Config::inst()->get("DataIntegrityTestYML", "config_files");
-        $classesToSkip = Config::inst()->get("DataIntegrityTestYML", "classes_to_skip");
-        $variablesToSkip = Config::inst()->get("DataIntegrityTestYML", "variables_to_skip");
+        $filesArray = Config::inst()->get(DataIntegrityTestYML::class, "config_files");
+        $classesToSkip = Config::inst()->get(DataIntegrityTestYML::class, "classes_to_skip");
+        $variablesToSkip = Config::inst()->get(DataIntegrityTestYML::class, "variables_to_skip");
         foreach ($filesArray as $folderAndFileLocation) {
             db::alteration_message("<h2>Checking $folderAndFileLocation</h2>");
             $fixtureFolderAndFile = Director::baseFolder().'/'. $folderAndFileLocation;

Warnings for src/DataIntegrityTestYML.php:
 - src/DataIntegrityTestYML.php:148 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 148

modified:	src/DataIntegrityTestUTF8.php
@@ -2,9 +2,14 @@

 namespace Sunnysideup\DataIntegrityTest;

-use BuildTask;
-use DB;
-use Config;
+
+
+
+use SilverStripe\ORM\DB;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\DataIntegrityTest\DataIntegrityTestUTF8;
+use SilverStripe\Dev\BuildTask;
+



@@ -41,7 +46,7 @@
         ini_set('max_execution_time', 3000);
         $tables = DB::query('SHOW tables');
         $unique = [];
-        $arrayOfReplacements = Config::inst()->get("DataIntegrityTestUTF8", "replacement_array");
+        $arrayOfReplacements = Config::inst()->get(DataIntegrityTestUTF8::class, "replacement_array");
         foreach ($tables as $table) {
             $table = array_pop($table);
             DB::query("ALTER TABLE \"$table\" CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");

modified:	src/DataIntegrityTestRecentlyChanged.php
@@ -2,13 +2,21 @@

 namespace Sunnysideup\DataIntegrityTest;

-use BuildTask;
-use ClassInfo;
-use Injector;
-use TestOnly;
-use Config;
-use Director;
+
+
+
+
+
+
 use DateTime;
+use SilverStripe\ORM\DataObject;
+use SilverStripe\Core\ClassInfo;
+use SilverStripe\Core\Injector\Injector;
+use SilverStripe\Dev\TestOnly;
+use SilverStripe\Core\Config\Config;
+use SilverStripe\Control\Director;
+use SilverStripe\Dev\BuildTask;
+



@@ -49,7 +57,7 @@
             $date =  date(DATE_RFC2822, $ts);
             echo "<hr /><h3>changes in the last ".$this->minutesToTime($minutes)."<br />from: ".$date."<br />make sure you see THE END at the bottom of this list</h3><hr />";
             $whereStatementFixed = "UNIX_TIMESTAMP(\"LastEdited\") > ".$ts." ";
-            $dataClasses = ClassInfo::subclassesFor('DataObject');
+            $dataClasses = ClassInfo::subclassesFor(DataObject::class);
             array_shift($dataClasses);
             foreach ($dataClasses as $dataClass) {
                 if (class_exists($dataClass)) {

Warnings for src/DataIntegrityTestRecentlyChanged.php:
 - src/DataIntegrityTestRecentlyChanged.php:61 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 61

 - src/DataIntegrityTestRecentlyChanged.php:69 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 69

modified:	src/DataIntegrityMoveFieldUpOrDownClassHierarchy.php
@@ -2,10 +2,16 @@

 namespace Sunnysideup\DataIntegrityTest;

-use BuildTask;
-use DB;
-use Config;
-use SiteTree;
+
+
+
+
+use SilverStripe\ORM\DB;
+use SilverStripe\ORM\DataObject;
+use SilverStripe\Core\Config\Config;
+use SilverStripe\CMS\Model\SiteTree;
+use SilverStripe\Dev\BuildTask;
+



@@ -190,7 +196,7 @@
                             $completed[$testTable1."_".$testTable2] = $interSect;

                             $link["movetoparent"] = [];
-                            if (in_array("DataObject", $parentArray1)) {
+                            if (in_array(DataObject::class, $parentArray1)) {
                                 $modelFields1 = array_keys((array)Config::inst()->get($testTable1, "db", Config::UNINHERITED)) +
                                 $hasOneArray = array_keys((array)Config::inst()->get($testTable1, "has_one", Config::UNINHERITED));
                                 $hasOneArray = array_map(
@@ -209,7 +215,7 @@
                                 }
                             }
                             $link["movetochild"] = [];
-                            if (in_array("DataObject", $parentArray1)) {
+                            if (in_array(DataObject::class, $parentArray1)) {
                                 $modelFields2 = array_keys((array)Config::inst()->get($testTable2, "db", Config::UNINHERITED)) + array_keys((array)Config::inst()->get($testTable2, "has_one", Config::UNINHERITED));
                                 $hasOneArray = array_keys((array)Config::inst()->get($testTable2, "has_one", Config::UNINHERITED));
                                 $hasOneArray = array_map(

modified:	src/CheckForMysqlPaginationIssuesBuildTask.php
@@ -2,14 +2,23 @@

 namespace Sunnysideup\DataIntegrityTest;

-use BuildTask;
-use ClassInfo;
-use Injector;
-use FunctionalTest;
-use TestOnly;
-use DB;
-use DataObject;
-use Config;
+
+
+
+
+
+
+
+
+use SilverStripe\ORM\DataObject;
+use SilverStripe\Core\ClassInfo;
+use SilverStripe\Core\Injector\Injector;
+use SilverStripe\Dev\FunctionalTest;
+use SilverStripe\Dev\TestOnly;
+use SilverStripe\ORM\DB;
+use SilverStripe\Core\Config\Config;
+use SilverStripe\Dev\BuildTask;
+



@@ -46,7 +55,7 @@

         // give us some time to run this
         ini_set('max_execution_time', 3000);
-        $classes = ClassInfo::subclassesFor('DataObject');
+        $classes = ClassInfo::subclassesFor(DataObject::class);
         $array = [
             'l' => 'limit',
             's' => 'step',
@@ -103,7 +112,7 @@
                 continue;
             }
             // skip irrelevant ones
-            if ($class !== 'DataObject') {
+            if ($class !== DataObject::class) {
                 //skip test ones
                 $obj = Injector::inst()->get($class);
                 if ($obj instanceof FunctionalTest || $obj instanceof TestOnly) {

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

modified:	src/Api/DataIntegrityTestDefaultEntries.php
@@ -2,11 +2,17 @@

 namespace Sunnysideup\DataIntegrityTest\Api;

-use ViewableData;
-use DataObject;
-use SiteTree;
-use Convert;
-use DB;
+
+
+
+
+
+use SilverStripe\ORM\DataObject;
+use SilverStripe\CMS\Model\SiteTree;
+use SilverStripe\Core\Convert;
+use SilverStripe\ORM\DB;
+use SilverStripe\View\ViewableData;
+




Writing changes for 8 files
✔✔✔
# git add all
cd /var/www/upgrades/__upgradeto4__/dataintegritytests
git add . -A
✔✔✔
# commit changes: MAJOR: core upgrade to SS4 - STEP 1 (upgrade) on /var/www/upgrades/__upgradeto4__/dataintegritytests
cd /var/www/upgrades/__upgradeto4__/dataintegritytests
if [ -z "$(git status --porcelain)" ]
then
                    echo 'OKI DOKI - Nothing to commit'
else
                    git commit . -m "MAJOR: core upgrade to SS4 - STEP 1 (upgrade) on /var/www/upgrades/__upgradeto4__/dataintegritytests"
                fi
 create mode 100644 upgrade_notes.md
[temp-upgradeto4-branch 33289e7] MAJOR: core upgrade to SS4 - STEP 1 (upgrade) on /var/www/upgrades/__upgradeto4__/dataintegritytests
 9 files changed, 560 insertions(+), 62 deletions(-)
 create mode 100644 upgrade_notes.md
✔✔✔
# pushing changes to origin on the temp-upgradeto4-branch branch
cd /var/www/upgrades/__upgradeto4__/dataintegritytests
git push origin temp-upgradeto4-branch
   324fc6f..33289e7  temp-upgradeto4-branch -> temp-upgradeto4-branch
To github.com:sunnysideup/silverstripe-dataintegritytests.git
   324fc6f..33289e7  temp-upgradeto4-branch -> temp-upgradeto4-branch
✔✔✔


# --------------------
# Add PSR-4 Autoloading to the composer file. (AddPSR4Autoloading)
# --------------------
# Goes through all the folders in the code or src dir and adds them to the
# composer.json file as autoloader. This must run after the folder names have
# been changed to CamelCase (see: UpperCaseFolderNamesForPSR4).
# --------------------
# Adding autoload Page and Page controller details in /var/www/upgrades/__upgradeto4__/composer.json:  =>  --- in /var/www/upgrades/__upgradeto4__/composer.json
cd /var/www/upgrades/__upgradeto4__
php -r  '$jsonString = file_get_contents("/var/www/upgrades/__upgradeto4__/composer.json")
$data = json_decode($jsonString, true)
if(! isset($data["autoload"])) {
                $data["autoload"] = []
}
            if(! isset($data["autoload"]["psr-4"])) {
                $data["autoload"]["psr-4"] = []
}
        
            if(! isset($data["autoload"]["files"])) {
                $data["autoload"]["files"] = [
                    "app/src/Page.php",
                    "app/src/PageController.php"
                ]
}$newJsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
file_put_contents("/var/www/upgrades/__upgradeto4__/composer.json", $newJsonString)
'
✔✔✔
# Adding autoload psr-4 details in /var/www/upgrades/__upgradeto4__/composer.json: Sunnysideup\DataIntegrityTest\ => dataintegritytests/src/ --- in /var/www/upgrades/__upgradeto4__/composer.json
cd /var/www/upgrades/__upgradeto4__
php -r  '$jsonString = file_get_contents("/var/www/upgrades/__upgradeto4__/composer.json")
$data = json_decode($jsonString, true)
if(! isset($data["autoload"])) {
                $data["autoload"] = []
}
            if(! isset($data["autoload"]["psr-4"])) {
                $data["autoload"]["psr-4"] = []
}
        
            $data["autoload"]["psr-4"]["Sunnysideup\\DataIntegrityTest\\"] = "dataintegritytests/src/"
$newJsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
file_put_contents("/var/www/upgrades/__upgradeto4__/composer.json", $newJsonString)
'
✔✔✔
# Adding autoload psr-4 details in /var/www/upgrades/__upgradeto4__/dataintegritytests/composer.json: Sunnysideup\DataIntegrityTest\ => src/ --- in /var/www/upgrades/__upgradeto4__/dataintegritytests/composer.json
cd /var/www/upgrades/__upgradeto4__/dataintegritytests
php -r  '$jsonString = file_get_contents("/var/www/upgrades/__upgradeto4__/dataintegritytests/composer.json")
$data = json_decode($jsonString, true)
if(! isset($data["autoload"])) {
                $data["autoload"] = []
}
            if(! isset($data["autoload"]["psr-4"])) {
                $data["autoload"]["psr-4"] = []
}
        
                $data["autoload"]["psr-4"]["Sunnysideup\\DataIntegrityTest\\"] = "src/"
$newJsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
file_put_contents("/var/www/upgrades/__upgradeto4__/dataintegritytests/composer.json", $newJsonString)
'
✔✔✔
# run composer dumpautoload
cd /var/www/upgrades/__upgradeto4__
composer dumpautoload
Generating autoload filesGenerated autoload files containing 465 classes
Generating autoload filesGenerated autoload files containing 465 classes
✔✔✔
# git add all
cd /var/www/upgrades/__upgradeto4__/dataintegritytests
git add . -A
✔✔✔
# commit changes: MAJOR: upgrade to new version of Silverstripe - step: Add PSR-4 Autoloading to the composer file.
cd /var/www/upgrades/__upgradeto4__/dataintegritytests
if [ -z "$(git status --porcelain)" ]
then
                    echo 'OKI DOKI - Nothing to commit'
else
                    git commit . -m "MAJOR: upgrade to new version of Silverstripe - step: Add PSR-4 Autoloading to the composer file."
                fi