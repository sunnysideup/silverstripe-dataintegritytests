<?php


/**
 *
 * @author: Nicolaas - modules [at] sunnysideup.co.nz
 * @help: // to see in action, add _configs per below, and go to http://www.mysite.com/dbintegritycheck/
 **/

Director::addRules(7, array(
	'dbintegritycheck//$Action/$ID/$OtherID' => 'DataIntegrityTest'
));

//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings
//===================---------------- START dataintegritytests MODULE ----------------===================
/*
DataIntegrityTest::set_fields_to_delete(
	array(
		"Member.UselessField1",
		"Member.UselessField2",
		"SiteTree.UselessField3"
	)
);
*/
//===================---------------- END dataintegritytests MODULE ----------------===================
