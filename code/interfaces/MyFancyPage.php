<?php

interface MyFancyPage {

	/**
	 * Indicates what kind of children this page type can have.
	 * This can be an array of allowed child classes, or the string "none" -
	 * indicating that this page type can't have children.
	 * If a classname is prefixed by "*", such as "*Page", then only that
	 * class is allowed - no subclasses. Otherwise, the class and all its
	 * subclasses are allowed.
	 * To control allowed children on root level (no parent), use {@link $can_be_root}.
	 *
	 * Note that this setting is cached when used in the CMS, use the "flush" query parameter to clear it.
	 *
	 * @var array
	 */
	private static $allowed_children;

	/**
	 * The default child class for this page.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @var string
	 */
	private static $default_child;

	/**
	 * The default parent class for this page.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @var string
	 */
	private static $default_parent;

	/**
	 * Controls whether a page can be in the root of the site tree.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @var bool
	 */
	private static $can_be_root;

	/**
	 * List of permission codes a user can have to allow a user to create a page of this type.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @var array
	 */
	private static $need_permission;

	/**
	 * If you extend a class, and don't want to be able to select the old class
	 * in the cms, set this to the old class name. Eg, if you extended Product
	 * to make ImprovedProduct, then you would set $hide_ancestor to Product.
	 *
	 * @var string
	 */
	private static $hide_ancestor;


	/**
	 * If a field is in this array, then create a database index
	 * on that field. This is a map from fieldname to index type.
	 * See {@link SS_Database->requireIndex()} and custom subclasses for details on the array notation.
	 *
	 * @var array
	 */
	private static $indexes;

	private static $db;
	private static $has_one;
	private static $many_many;
	private static $belongs_many_many;
	private static $many_many_extraFields;

	/**
	 * Use a casting object for a field. This is a map from
	 * field name to class name of the casting object.
	 *
	 * These are like pseudo-fields.  You will net to set up a method
	 * get{myCastedVariable} for each casted variable.
	 *
	 * @var array
	 */
	private static $casting;

	/**
	 * Inserts standard column-values when a DataObject
	 * is instanciated. Does not insert default records {@see $default_records}.
	 * This is a map from fieldname to default value.
	 *
	 *  - If you would like to change a default value in a sub-class, just specify it.
	 *  - If you would like to disable the default value given by a parent class, set the default value to 0,'',
	 *    or false in your subclass.  Setting it to null won't work.
	 *
	 * @var array
	 */
	private static $defaults;

	/**
	 * Multidimensional array which inserts default data into the database
	 * on a db/build-call as long as the database-table is empty. Please use this only
	 * for simple constructs, not for SiteTree-Objects etc. which need special
	 * behaviour such as publishing and ParentNodes.
	 *
	 * Example:
	 * array(
	 *  array('Title' => "DefaultPage1", 'PageTitle' => 'page1'),
	 *  array('Title' => "DefaultPage2")
	 * ).
	 *
	 * @var array
	 * @config
	 */
	private static $default_records = null;

	/**
	 * Sitree uses:
	 *     private static $versioning = array(
	 *       "Stage",  "Live"
	 *     );
	 * @var Arary
	 */
	private static $versioning;

	/**
	 * The default sort expression. This will be inserted in the ORDER BY
	 * clause of a SQL query if no other sort expression is provided.
	 * @var string
	 * @config
	 */
	private static $default_sort;

	/**
	 * If this is false, the class cannot be created in the CMS by regular content authors, only by ADMINs.
	 * @var boolean
	* @config
	*/
	private static $can_create;

	/**
	 * Icon to use in the CMS page tree. This should be the full filename, relative to the webroot.
	 * Also supports custom CSS rule contents (applied to the correct selector for the tree UI implementation).
	 *
	 * @see CMSMain::generateTreeStylingCSS()
	 * @var string
	 */
	private static $icon;

	/**
	 * @var String Description of the class functionality, typically shown to a user
	 * when selecting which page type to create. Translated through {@link provideI18nEntities()}.
	 */
	private static $description;

	/**
	 * standard SS variable
	 * @Var String
	 */
	private static $singular_name;
		function i18n_singular_name() { return _t("MyPage.SINGULARNAME", "My Page");}

	/**
	 * standard SS variable
	 * @Var String
	 */
	private static $plural_name;
		function i18n_plural_name() { return _t("MyPage.PLURALNAME", "My Pages");}

	/**
	 *
	 * @var Array
	 */
	private static $extensions;

	/**
	 * Default list of fields that can be scaffolded by the ModelAdmin
	 * search interface.
	 *
	 * Overriding the default filter, with a custom defined filter:
	 * <code>
	 *  static $searchable_fields = array(
	 *     "Name" => "PartialMatchFilter"
	 *  );
	 * </code>
	 *
	 * Overriding the default form fields, with a custom defined field.
	 * The 'filter' parameter will be generated from {@link DBField::$default_search_filter_class}.
	 * The 'title' parameter will be generated from {@link DataObject->fieldLabels()}.
	 * <code>
	 *  static $searchable_fields = array(
	 *    "Name" => array(
	 *      "field" => "TextField"
	 *    )
	 *  );
	 * </code>
	 *
	 * Overriding the default form field, filter and title:
	 * <code>
	 *  static $searchable_fields = array(
	 *    "Organisation.ZipCode" => array(
	 *      "field" => "TextField",
	 *      "filter" => "PartialMatchFilter",
	 *      "title" => 'Organisation ZIP'
	 *    )
	 *  );
	 * </code>
	 * @config
	 */
	private static $searchable_fields;

	/**
	 * User defined labels for searchable_fields, used to override
	 * default display in the search form.
	 * @config
	 */
	private static $field_labels;

	/**
	 *
	 * @var Boolan
	 */
	private static $create_default_pages;

	/**
	 * This controls whether of not extendCMSFields() is called by getCMSFields.
	 */
	private static $runCMSFieldsExtensions;

	/**
	 * @var boolean
	 */
	private static $enforce_strict_hierarchy = true;

	/**
	 * The value used for the meta generator tag.  Leave blank to omit the tag.
	 *
	 * @var string
	 */
	private static $meta_generator = 'SilverStripe - http://silverstripe.org';


}
