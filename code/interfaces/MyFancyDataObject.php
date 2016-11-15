<?php

interface MyFancyDataObject {

	private static $singular_name;
		
        function i18n_singular_name();

	private static $plural_name;
    
        function i18n_plural_name();

	private static $db;

	private static $has_one;

	/**
	 * A meta-relationship that allows you to define the reverse side of a {@link DataObject::$has_one}.
	 *
	 * This does not actually create any data structures, but allows you to query the other object in a one-to-one
	 * relationship from the child object. If you have multiple belongs_to links to another object you can use the
	 * syntax "ClassName.HasOneName" to specify which foreign has_one key on the other object to use.
	 *
	 * Note that you cannot have a has_one and belongs_to relationship with the same name.
	 *
	 * @var array
	 * @config
	 */
	private static $belongs_to;

	private static $has_many;

	private static $many_many;

	private static $belongs_many_many;

	private static $casting;

	private static $indexes;

	private static $default_sort;

	private static $required_fields;

	private static $summary_fields;

	private static $field_labels;

	/**
	 *
	 * PartialMatchFilter
	 */
	private static $searchable_fields;

	/**
	 * e.g.
	 *    $controller = singleton("MyModelAdmin");
	 *    return $controller->Link().$this->ClassName."/EditForm/field/".$this->ClassName."/item/".$this->ID."/edit";
 	 */
	public function CMSEditLink();

	public function getCMSFields();

	/**
	 * returns list of fields as they are exported
	 * @return array
	 * Field => Label
	 */
	public function getExportFields();

}
