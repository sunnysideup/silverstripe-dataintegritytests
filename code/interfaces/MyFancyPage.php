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
	 * @config
	 * @var array
	 */
	private static $allowed_children;

	/**
	 * The default child class for this page.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @config
	 * @var string
	 */
	private static $default_child;

	/**
	 * The default parent class for this page.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @config
	 * @var string
	 */
	private static $default_parent;

	/**
	 * Controls whether a page can be in the root of the site tree.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @config
	 * @var bool
	 */
	private static $can_be_root;

	/**
	 * List of permission codes a user can have to allow a user to create a page of this type.
	 * Note: Value might be cached, see {@link $allowed_chilren}.
	 *
	 * @config
	 * @var array
	 */
	private static $need_permission;

	/**
	 * If you extend a class, and don't want to be able to select the old class
	 * in the cms, set this to the old class name. Eg, if you extended Product
	 * to make ImprovedProduct, then you would set $hide_ancestor to Product.
	 *
	 * @config
	 * @var string
	 */
	private static $hide_ancestor;

	private static $db;

	private static $indexes;

	private static $many_many;

	private static $belongs_many_many;

	private static $many_many_extraFields;

	private static $casting;

	private static $defaults;

	private static $versioning;

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
	 * @config
	 * @var string
	 */
	private static $icon;

	/**
	 * @config
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


	private static $extensions;

	private static $searchable_fields;

	private static $field_labels;

	/**
	 * @config
	 */
	private static $nested_urls;

	/**
	 * @config
	*/
	private static $create_default_pages;

	/**
	 * This controls whether of not extendCMSFields() is called by getCMSFields.
	 */
	private static $runCMSFieldsExtensions;

	/**
	 * Cache for canView/Edit/Publish/Delete permissions.
	 * Keyed by permission type (e.g. 'edit'), with an array
	 * of IDs mapped to their boolean permission ability (true=allow, false=deny).
	 * See {@link batch_permission_check()} for details.
	 */
	private static $cache_permissions = array();

	/**
	 * @config
	 * @var boolean
	 */
	private static $enforce_strict_hierarchy = true;

	/**
	 * The value used for the meta generator tag.  Leave blank to omit the tag.
	 *
	 * @config
	 * @var string
	 */
	private static $meta_generator = 'SilverStripe - http://silverstripe.org';


}
