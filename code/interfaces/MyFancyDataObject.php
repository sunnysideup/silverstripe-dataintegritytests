<?php

interface MyFancyDataObject {

	private static $singular_name;

	private static $plural_name;

	private static $db;

	private static $has_one;

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
	 * 		$controller = singleton("DesignPortfolioAdmin");
	 * 		return $controller->Link().$this->ClassName."/EditForm/field/".$this->ClassName."/item/".$this->ID."/edit";
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
