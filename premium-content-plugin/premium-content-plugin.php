<?php
/*
Plugin Name: Premium Content Plugin
Plugin URI: http://amereservant.github.io/WordPress-Premium-Content-Plugin
Description: Adds optional Post Status for Premium content.
Author: Amereservant
Version: 1.0
Author URI: http://amereservant.com
*/
// Slug used for premium content capability
defined('PPREMIUM_SLUG') || define('PPREMIUM_SLUG', 'premium_content');

// The WordPress option slug for this plugin's options
defined('PPREMIUM_OPTS') || define('PPREMIUM_OPTS', '_premium_user_content');

if( !class_exists('ppremium_user_content_base') )
{

class ppremium_user_content_base
{
   /**
    * Post Meta Key
    *
    * @var      string
    * @access   protected
    * @since    1.0
    */
    protected $meta_key;

   /**
    * User Meta Key (Technically this isn't a user meta value)
    *
    * @var      string
    * @access   protected
    * @since    1.0
    */
    protected $user_meta_key;
    
   /**
    * WordPress Option Name
    *
    * @var      string
    * @access   protected
    * @since    1.0
    */
    protected $option_name;

   /**
    * Plugin Options
    *
    * @var      array
    * @access   protected
    * @since    1.0
    */
    protected $options;
    
   /**
    * Capability Slug
    *
    * @var      string
    * @access   protected
    * @since    1.0
    */
    protected $capability;

   /**
    * Nonce Key
    *
    * @var      string
    * @access   protected
    * @since    1.0
    */
    protected $nonce;

   /**
    * Class Constructor
    */
    public function __construct()
    {
        $this->option_name = PPREMIUM_OPTS;
        $this->capability  = PPREMIUM_SLUG;
        $this->meta_key    = '_is_'. PPREMIUM_SLUG;
        $this->nonce       = PPREMIUM_SLUG .'_nonce';
        $this->user_meta_key = '_user'. $this->meta_key;

        // Set Options or create them if they don't exist
        if( !($this->options = get_option($this->option_name)) )
        {
            $default = array(
                'disable_premium_filtering' => '0',
                'required_capability'       => 'promote_users',
                'filter_tags'               => '0',
                'filter_categories'         => '0',
                'filter_title'              => '0'
            );
            add_option($this->option_name, $default);
            $this->options = $default;
        }
    }

   /**
    * Get Protected Property Values
    *
    * @param    string  $key    The name of the property to get the values of
    * @return   mixed
    * @access   public
    * @since    1.0
    */
    public function __get($key)
    {
        if( isset($this->{$key}) )
            return $this->{$key};
        return false;
    }

   /**
    * Get Plugin Option
    *
    * Returns specific value from the plugin's options.
    *
    * @param    string  $option     The option name to get the value for
    * @param    mixed   $default    The default value to return if the option doesn't exist
    * @access   public
    * @since    1.0
    */
    public function get_option($option, $default=false)
    {
        do_action('premium_content_get_option', $option, $default);
        if( isset($this->options[$option]) )
            return apply_filters('premium_content_option', $this->options[$option], $option, $default);

        return $default;
    }
}

} // Endof !class_exists('ppremium_user_content')



/**
 * @todo    Restrict post access marked as premium_content in admin
 */


if( !class_exists('ppremium_user_content') ) {

class ppremium_user_content extends ppremium_user_content_base
{
   /**
    * Class Constructor
    */
    public function __construct()
    {
        parent::__construct();
        
        if( $this->get_option('disable_premium_filtering') != '1' )
        {
            add_filter('the_content', array($this, '_content_filter'), 0);

            if( $this->get_option('filter_title') == '1' )
                add_filter('the_post', array($this, '_title_filter'), 0);

            if( $this->get_option('filter_tags') == '1' || $this->get_option('filter_category') == '1' )
                add_filter('get_the_terms', array($this, '_taxonomy_filter'), 10, 3);
        }
    }

   /**
    * Premium Content Filtering
    *
    * Adds filtering of content based on if whether or not the content being requested
    * is marked as premium content or not.
    *
    * @param    string  $content    The content being requested
    * @return   string              Either an unauthorized message or the content
    * @since    1.0
    */
    public function _content_filter( $content )
    {
        global $post;
        if( !is_a($post, 'WP_Post') )
            return $content;

        $premium = get_post_meta($post->ID, $this->meta_key, true);

        if( $premium != '1' )
            return $content;

        if( !current_user_can($this->capability) )
        {
            if( !is_user_logged_in() )
                $content = apply_filters('premium_content_not_logged_in', '<h2>Premium Content - Login Required</h2>'.
                    '<p>You must <a href="'. wp_login_url(get_permalink($post->ID)) .
                    '" title="Login">Login</a> to view this page.</p>', $post->ID, $content);
            else
                $content = apply_filters('premium_content_access_denied', '<h2>Premium Content - Permission Denied</h2>'.
                    '<p>You do not have permission to access this page.</p>', $post->ID, $content);

           return $content;
       }
       return $content;
    }

   /**
    * Premium Title Filtering
    *
    * Adds filtering of titles based on if whether or not the content being requested
    * is marked as premium content or not.
    *
    * @param    object  &$post   The WP_Post object to modify (it's passed-by-reference)
    * @return   void
    * @since    1.0
    */
    public function _title_filter( $post )
    {
        if( !is_a($post, 'WP_Post') )
            return;

        if( $this->get_option('filter_title') == '0' )
            return;
        $premium = get_post_meta($post->ID, $this->meta_key, true);

        if( $premium != '1' )
            return;

        if( !current_user_can($this->capability) )
        {
            if( !is_user_logged_in() )
                $post->post_title = apply_filters('premium_content_not_logged_in_title', 'Premium Content - Login Required', $post->ID);
            else
                $post->post_title = apply_filters('premium_content_access_denied_title', 'Premium Content - Permission Denied', $post->ID);

           return;
       }
    }

   /**
    * Premium Taxonomy Filtering
    *
    * Adds filtering of tags & categories based on if whether or not the content being requested
    * is marked as premium content or not.
    *
    * @param    object  &$post   The WP_Post object to modify (it's passed-by-reference)
    * @return   void
    * @since    1.0
    */
    public function _taxonomy_filter( $terms, $post_ID, $taxonomy )
    {
        if( !in_array($taxonomy, array('post_tag','category')) ) return $terms;

        if( !$post = get_post($post_ID)|| current_user_can($this->capability) )
            return $terms;
        
        $premium = get_post_meta($post->ID, $this->meta_key, true);

        if( $premium != '1' )
            return;

        
        if( $this->get_option('filter_tags') == '1' && $taxonomy == 'post_tag' )
            return array();

        if( $this->get_option('filter_category') == '1' && $taxonomy == 'category' )
            return array();

        return $terms;
    }

}
add_action('init', create_function('', 'new ppremium_user_content;'));
}


if( !class_exists('ppremium_user_content_admin') )
{

/**
 * Options Page
 *
 * Adds the settings/options to the administrative section under 'Users'.
 *
 * @link    https://gist.github.com/amereservant/5779754
 * @since   1.0
 */
class ppremium_user_content_admin extends ppremium_user_content_base
{
   /**
    * Settings Group Name
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_option_group  = 'premium_user_options_group';

   /**
    * User Capability Required To Edit Settings
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_capability    = 'manage_options';

   /**
    * Settings Menu Slug
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_menu_slug     = 'premium-user-settings';

   /**
    * Settings Page Title
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_page_title    = 'Premium Content';

   /**
    * Settings Menu Title
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_menu_title    = 'Premium Content';

   /**
    * Settings Section ID
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_section_id;

   /**
    * More Information Page ID
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_moreinfo_page_id;

   /**
    * Settings Page ID
    *
    * @var      string
    * @access   private
    * @since    1.0
    */
    private $_settings_page_id;

   /**
    * Class Constructor
    */
    public function __construct()
    {
        if( is_admin() )
        {
            parent::__construct();
            add_action('admin_menu', array($this, '_add_plugin_page'));
            add_action('admin_init', array($this, '_page_init'));
            add_action('personal_options', array($this, '_add_premium_user_field'));
            add_action('personal_options_update', array($this, '_save_premium_user_capability'));
            add_action('edit_user_profile_update', array($this, '_save_premium_user_capability'));

            if( current_user_can($this->get_option('required_capability')) )
            {
                // Add Premium Content checkbox to post/page 
                add_action('post_submitbox_misc_actions', array($this,'_premium_post'), 0);
                //add_action('save_post', array($this,'_save_post_status')); // Only fires if something changes in MU
                add_action('pre_post_update', array($this,'_save_post_status'));
            }
        }
    }

    public function _premium_post()
    {
        global $post;
        $is_premium = get_post_meta( $post->ID, $this->meta_key, true );
        // Create nonce
        wp_nonce_field( plugin_basename(__FILE__), $this->nonce );
        ?>
        <div class="misc-pub-section">
            <input type="hidden" name="<?php echo $this->meta_key; ?>" value="0" />
            <p>
                <input type="checkbox" name="<?php echo $this->meta_key; ?>" value="1"<?php checked($is_premium, '1'); ?> />
                &nbsp;&nbsp;<label><?php _e('Premium Content'); ?></label>
            </p>
        </div><?php
    }

   /**
    * Save Premium Status for Post
    *
    * Checks if the status for premium content has been added and adds the value
    * to the post meta.
    *
    * This should only be called by the 'save_post' action hook!
    *
    * @param    integer $post_id    The current post ID, which may be a revision ID, etc.
    *                               and NOT the actual post's ID.  Don't use this for meta data!
    * @return   void
    * @since    1.0
    */
    public function _save_post_status( $post_id )
    {
        // Check if current user can edit page/post
        if( ($_POST['post_type'] == 'page' && !current_user_can('edit_page', $post_id)) &&
            ($_POST['post_type'] == 'post' && !current_user_can('edit_post', $post_id)) )
            return;

        if( !current_user_can($this->get_option('required_capability'), $post_id) )
            return;
    
        // Check our nonce
        if( !isset($_POST[$this->nonce]) || !wp_verify_nonce($_POST[$this->nonce],
            plugin_basename(__FILE__)) )
            return;
        
        $post_ID    = $_POST['post_ID'];
        $is_premium = $_POST[$this->meta_key];
        
        // Store our value
        add_post_meta($post_ID, $this->meta_key, $is_premium, true) || 
            update_post_meta($post_ID, $this->meta_key, $is_premium);
    }
    
   /**
    * Add Plugin Pages
    *
    * Adds the plugin settings to the menu under "Users" and a 'hidden' "More Info"
    * page containing more information about the plugin.
    * This should only be called by the 'admin_menu' action hook.
    *
    * @since    1.0
    */ 
    public function _add_plugin_page()
    {
        $this->_settings_page_id = add_users_page($this->_page_title, $this->_menu_title, $this->_capability,
            $this->_menu_slug, array($this, '_create_admin_page'));
        // Add "Hidden" information page
        $this->_moreinfo_page_id = add_submenu_page( null, 'More Info', 'More Info', $this->_capability, 
            $this->_menu_slug .'-moreinfo', array($this, '_create_more_info_page'));

        add_action('load-'. $this->_moreinfo_page_id, array($this, '_load_options_assets'));
        add_action('load-'. $this->_settings_page_id, array($this, '_add_help_tab'));
    }

   /**
    * Load Options Assets
    *
    * Loads necessary CSS and Javascript ONLY when the page is requested.
    *
    * @since    1.0
    */
    public function _load_options_assets()
    {
        if( strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') )
            $stylesheet = 'https://s3.amazonaws.com/amereservant/wordpress-files/options.gz.css';
        else
            $stylesheet = 'https://s3.amazonaws.com/amereservant/wordpress-files/options.css';

        wp_enqueue_style($this->_menu_slug, $stylesheet);
    }

   /**
    * Add Help Tab
    */
    public function _add_help_tab()
    {
        $screen = get_current_screen();
        
        if( $screen->id == $this->_settings_page_id )
        {
            // Add help_tab if current screen is Premium Content Settings Admin Page
            $screen->add_help_tab(array(
                'id'    => $screen->id .'_settings_help_tab',
                'title' =>  __('Settings'),
                'content' => '<p>'. __('<strong>Disable Premium Filtering</strong>: This will enable/disable filtering specified by the <em>Filtering</em> settings.  If filtering is disabled, ALL premium content settings will be ignored.') .'<br />'.
                __('<strong>Required Capability</strong>: Specify the required capability for editing the premium content status for <em>Pages</em>, <em>Posts</em>, and <em>Users</em>.  If their role does not allow them to edit these things, this value will be irrelevant.') .'</p>',
            ));

            $screen->add_help_tab(array(
                'id'    => $screen->id .'_filtering_help_tab',
                'title' => __('Filtering'),
                'content' => '<p><strong>Filter Title</strong>: Determines if Post/Page titles should be replaced for content the user doesn\'t have the proper permission to access.  If enabled, the title will be replaced with "Premium Content".<br />'.
                    '<strong>Filter Category</strong>: Determines if the Post/Page categories should be stripped or not.  If so, the Post/Page will appear to be in the <em>"Uncategorized"</em> category.<br />' .
                    '<strong>Filter Tags</strong>: Determines if the Post/Page tags should be stripped or not.  If so, no tags will be shown for the Post/Page.</p>'
            ));

            $screen->add_help_tab(array(
                'id'    => $screen->id .'_roles_help_tab',
                'title' => __('Roles'),
                'content' => '<p>This determines which Roles will have the <strong>'. PPREMIUM_SLUG .'</strong> capability.</p>'
           ));
        }
    }
    
   /**
    * Create Plugin Settings Page
    *
    * Renders the settings page contents.
    * This should only be called by the {@link _add_plugin_page()} as a callback to
    * the add_users_page() function.
    *
    * @since    1.0
    */
    public function _create_admin_page()
    { ?>
	    <div class="wrap">
	        <?php screen_icon(); ?>
	        <h2><?php echo $this->_page_title; ?></h2>
	        <?php if( isset($_GET['settings-updated']) )
	            if($_GET['settings-updated'] == 'true') { ?>
            <div id="message" class="updated">
                <p><strong><?php _e('Settings saved.'); ?></strong></p>
            </div><?php } else { ?>
            <div id="message" class="error">
                <p><strong><?php _e('ERROR: Failed to save settings!'); ?></strong></p>
            </div><?php } ?>
            <h4><a href="<?php echo admin_url('users.php?page=premium-user-settings-moreinfo'); ?>">Plugin Information Page</a></h4>
	        <form method="post" action="options.php">
	        <?php
                // This prints out all hidden setting fields
		        settings_fields($this->_option_group);	
		        do_settings_sections($this->_menu_slug); 
	            submit_button(); ?>
	        </form>
	    </div><?php
    }

    public function _create_more_info_page()
    { ?>
        <div class="wrap">
            <?php //screen_icon('edit-comments'); ?>
            <?php _ppremium_more_info_contents(); ?>
            <p></p>
        </div><?php
    }
   /**
    * Initialize Admin Settings Page
    *
    * This registers all of the settings and the setting fields that are presented
    * on the plugin's settings page.
    *
    * This should only be called by the 'admin_init' action hook.
    *
    * @uses     $_section_id    Sets the value according to what section is being added.
    * @uses     $_menu_slug     Uses the value for adding settings
    * @uses     _process_settings()     Registers this as a callback to register_setting()
    * @uses     _print_section_info()   Registers this as a callback to add_settings_section()
    * @uses     create_a_field()        Used to create the input fields for added settings field
    * @uses     _print_roles_section_info() Registers this as a callback to add_settings_section()
    * @uses     _print_powerpress_section_information() Registers this as a callback to add_settings_section()
    *
    * @since    1.0
    */
    public function _page_init()
    {
        register_setting($this->_option_group, $this->option_name, array($this, '_process_settings'));

        // Adds a "group" of settings on the settings page
        $this->_section_id = 'setting_section_id';
        add_settings_section(
            $this->_section_id,
            'Settings',
            array($this, '_print_section_info'),
            $this->_menu_slug
        );	
        // Adds a field to the "group" created with add_settings_section
        add_settings_field(
            'disable_premium_filtering', 
            'Disable Premium Filtering', 
            array($this, 'create_a_field'), 
            $this->_menu_slug,
            $this->_section_id,
            array(
                'label_for' => 'disable-premium-filtering',
                'option'    => 'disable_premium_filtering',
                'type'      => 'checkbox'
            )
        );
        
        add_settings_field(
            'required_capability', 
            'Required Capability', 
            array($this, 'create_a_field'), 
            $this->_menu_slug,
            $this->_section_id,
            array(
                'label_for' => 'required_capability-id',
                'option'    => 'required_capability',
                'type'      => 'select',
                'is_multi'  => false,
                'description' => 'Select the required <a href="http://codex.wordpress.org/Roles_and_Capabilities#Capability_vs._Role_Table" title="Roles and Capabilities" target="_blank">Capabilities</a> to edit premium status for posts/pages and users.',
                'options'   => $this->_get_capabilities()
            )
        );


        $this->_section_id = 'content_filtering';
        add_settings_section(
            $this->_section_id,
            'Filtering',
            array($this, '_print_content_filtering_info'),
            $this->_menu_slug
        );

        add_settings_field(
            'filter_title',
            'Filter Title',
            array($this, 'create_a_field'),
            $this->_menu_slug,
            $this->_section_id,
            array(
                'label_for' => 'filter_title-id',
                'option'    => 'filter_title',
                'type'      => 'checkbox'
            )
        );

        add_settings_field(
            'filter_category',
            'Filter Category',
            array($this, 'create_a_field'),
            $this->_menu_slug,
            $this->_section_id,
            array(
                'label_for' => 'filter_category-id',
                'option'    => 'filter_category',
                'type'      => 'checkbox'
            )
        );

        add_settings_field(
            'filter_tags',
            'Filter Meta Tags',
            array($this, 'create_a_field'),
            $this->_menu_slug,
            $this->_section_id,
            array(
                'label_for' => 'filter_tags-id',
                'option'    => 'filter_tags',
                'type'      => 'checkbox'
            )
        );
	    
        $this->_section_id = 'modify_roles_id';
        add_settings_section(
            $this->_section_id,
            'Roles',
            array($this, '_print_roles_section_info'),
            $this->_menu_slug
        );

        if( function_exists('powerpress_admin_tools') )
        {
            $this->_section_id = 'powerpress_settings';
            add_settings_section(
                $this->_section_id,
                'PowerPress',
                array($this, '_print_roles_section_info'),
                $this->_menu_slug
            );
        }
    }

   /**
    * Process/Save Settings
    *
    * This processes the settings upon saving them.
    * Since some options (such as roles) aren't to be stored, this method processes
    * them and then removes them before the plugin options are stored in the database.
    *
    * This should only be called as a callback to the register_setting() function.
    *
    * @uses     $wp_roles   Used to get the WordPress roles and add/remove the premium
    *                       capability from them.
    *
    * @param    array   $input  The plugin settings values to process
    * @return   array           The processed settings to store
    * @since    1.0
    */
    public function _process_settings($input)
    {
        global $wp_roles;
        $var = $input['roles_enabled'];

        // Remove/add premium_content to each role
        foreach( $var as $role => $val )
        {
            $wprole = $wp_roles->get_role($role);
            
            if($val == '0')
                $wprole->remove_cap('premium_content');
            else
                $wprole->add_cap('premium_content');
        }
        //var_dump($input);exit;
        // Remove roles before saving plugin options
        unset($input['roles_enabled']);
        return $input;
    }
	
    public function _print_section_info()
    { ?>
        <p>Configure the Premium User Content settings below:</p><?php
    }

    public function _print_roles_section_info()
    {
        global $wp_roles;
        $fieldname = $this->option_name .'[roles_enabled]';
        ?><a id="premium-user-roles"></a>
        <p>Choose which roles should or shouldn't have premium content automatically associated with it.<br />
            You can still add it to each individual user instead by editing their profile.</p>
        <table class="form-table">
            <?php foreach($wp_roles->get_names() as $role => $rname) { ?>
            <tr valign="top">
                <th scope="row"><label for="<?php echo $role; ?>-id"><?php echo $role; ?></th>
                <?php 
                $role_obj = $wp_roles->get_role($role);
                $has_cap  = $role_obj->has_cap('premium_content'); ?>
                <td>
                    <input type="hidden" name="<?php echo $fieldname .'['. $role .']'; ?>" value="0" />
                    <input type="checkbox" id="<?php echo $role; ?>-id" name="<?php echo $fieldname .'['. $role .']'; ?>" value="1"<?php checked($has_cap); ?> />
                </td>
            </tr>
            <?php } ?>
        </table>
        <?php 
    }

    public function _print_powerpress_section_information()
    {
        $ppsettings = get_option('powerpress_general');
    ?>
        <p>This plugin integrates with Blubrry PowerPress' User Capabilities found in
           the <a href="<?php echo admin_url('admin.php?page=powerpress/powerpressadmin_tools.php'); ?>" title="PowerPress Tools">Tools</a> section.</p><?php
    }

    public function _print_content_filtering_info()
    { ?>
    <p>Content filtering allows you to specify if specific attributes of a post/page
        should be filtered out for premium content or left as-is.<br />
       Any content checked will be removed from displaying on the page and replaced.</p><?php
    }

	
    public function create_a_field( $args )
    {
        $name      = $args['option'];
        $fieldname = $this->option_name .'['. $name .']';
        
        $args = wp_parse_args($args, array(
            'type'          => 'text',
            'label_for'     => $name .'-id',
            'description'   => '',
            'is_multi'      => false, // for select elements
            'options'       => array() // for select elements
        ));
        
        $val = $this->get_option($name);
        
        if( $args['type'] == 'select' )
        {
            if( !is_array($val) ) $val = array($val);
            $size = count($args['options']) > 5 ? 8 : 5;
        ?>
            <select id="<?php echo $args['label_for']; ?>" name="<?php echo $fieldname; if($args['is_multi']) { ?>[]" multiple="multiple" size="<?php echo $size; } ?>">
            <?php foreach($args['options'] as $var => $label) { ?>
            <option value="<?php echo $var; ?>"<?php selected(in_array($var, $val)); ?>><?php echo $label; ?></option>
            <?php } ?>
            </select><?php
        }
        elseif( $args['type'] == 'checkbox' )
        { ?>
            <input type="hidden" name="<?php echo $fieldname; ?>" value="0" />
            <input type="checkbox" name="<?php echo $fieldname; ?>" id="<?php echo $args['label_for']; ?>" value="1"<?php checked($val, '1'); ?> /><?php
        }
        else
        { ?>
        <input type="<?php echo $args['type']; ?>" id="<?php echo $args['label_for']; ?>" name="<?php echo $fieldname; ?>" value="<?php echo $val; ?>" /><?php
        }
        
        if( strlen($args['description']) > 0 )
            echo '<p class="description">'. $args['description'] .'</p>';
    }

   /**
    * Add Premium Content to User Editor
    *
    * Adds a field to the edit user page so that users can easily be granted
    * access to premium content.
    *
    * This should only be called by the 'personal_options' action hook!
    *
    * @param    object  $profileuser    The user object for the profile being viewed.
    *                                   This may or may not be the current user.
    * @return   void
    * @since    1.0
    */
    public function _add_premium_user_field( $profileuser )
    {
        if( !current_user_can($this->get_option('required_capability')) )
            return;
        wp_nonce_field( plugin_basename( __FILE__ ), $this->nonce );
        //if( user_can($profileuser, $this->capability
        $role_has_cap = $this->_check_user_roles_for_capability( $profileuser );
        var_dump(user_can($profileuser, $this->capability));
    ?>
        <tr class="premium-content-user">
            <th scope="row"><?php _e('Premium Content Access')?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span><?php _e('Premium Content Access') ?></span></legend>
                    <label for="premium_content_user">
                        <input type="hidden" name="<?php echo $this->user_meta_key; ?>" value="<?php
                            echo ($role_has_cap && user_can($profileuser, $this->capability) ? 1:0); ?>" />
                        <input name="<?php echo $this->user_meta_key; ?>" type="checkbox" id="premium_content_user" value="1"<?php
                            checked(user_can($profileuser, $this->capability)); ?><?php
                            if($role_has_cap){ echo ' disabled'; } ?> />
                            <?php _e('Grant access to premium content.'); ?>
                            <?php if( $role_has_cap ) { ?>
                            &nbsp;&nbsp;<em>(<a href="<?php echo admin_url('users.php?page=premium-user-settings-moreinfo#cannot-change-premium-checkbox'); ?>">Why can't I edit this?</a>)</em>
                            <?php } ?>
                    </label>
                </fieldset>
            </td>
        </tr><?php
    }

   /**
    * Save User's Premium Capability
    *
    * When saving a user's profile settings, this will update their capacity if
    * it has been changed and if the person editing it has permissions to do so.
    *
    * @param    integer $userID     The ID of the user being updated
    * @return   void
    * @since    1.0
    */
    public function _save_premium_user_capability( $userID )
    {
        if( !current_user_can($this->get_option('required_capability')) )
            return;
        
        if( !wp_verify_nonce($_POST[$this->nonce], plugin_basename(__FILE__)) ||
            !isset($_POST[$this->user_meta_key]) )
            return;
            
        $user = new WP_User( $userID );
        //var_dump($_POST['premium_content_user'] == '0' && $user->has_cap('premium_content'));exit;
        if( $_POST[$this->user_meta_key] == '1' && !$user->has_cap('premium_content') )
            $user->add_cap('premium_content');
        elseif( $_POST[$this->user_meta_key] == '0' && $user->has_cap('premium_content') )
            $user->remove_cap('premium_content');
    }
    
   /**
    * Get All Capabilities
    *
    * This retrieves and combines all user capabilities for all roles and returns
    * them as an array.
    *
    * @param    void
    * @return   array
    * @access   private
    * @since    1.0
    */
    private function _get_capabilities()
    {
        global $wp_roles;
        $merged = array();
        
        foreach($wp_roles->roles as $name => $role)
        {
            foreach($role['capabilities'] as $cap => $val){
                $merged[$cap] = $cap;
            }
        }
        ksort($merged);
        return $merged;
    }

   /**
    * Check User Role(s) Capabilities
    *
    * If the user's role(s) have the {@link $capability} capability, then setting
    * the user's capacity in {@link _add_premium_user_field()} will be of no value
    * and appear to fail.
    * So this method checks a given user's roles for this capability and returns
    * true if one of their roles has the capability.
    *
    * @param    object  $user   The user to check roles for
    * @return   bool            true if they do have a role with the capability, false if not
    * @access   protected
    * @since    1.0
    */
    protected function _check_user_roles_for_capability( $user )
    {
        if( !is_a($user, 'WP_User') )
            if( WP_DEBUG )
                trigger_error('The given user is NOT a user object!', E_USER_ERROR);
            else
                return;

        global $wp_roles;
        if( !isset($wp_roles) )
            $wp_roles = new WP_Roles();
        
        foreach($user->roles as $role)
        {
            $wprole = $wp_roles->get_role( $role );
            if( $wprole->has_cap($this->capability) )
                return true;
        }
        return false;
    }
}

if( is_admin() )
    add_action('init', create_function('', 'new ppremium_user_content_admin;'));

} // Endof !class_exists('ppremium_user_content_admin')


/**
 * More Info HTML
 *
 * This renders the plugin's "More Info" page in the admin section.
 * The only reason this is in a function is to keep all of the components to the
 * plugin in a single file.  This *could* be separated into a separate file and then
 * included.
 */
function _ppremium_more_info_contents()
{ ?>
<div class="icon32"><img src="data:image/png;base64,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" width="34" height="34" /></div>
            <h2>More Info</h2>
            
<h2 style="text-shadow:1px 1px 2px #949494;filter:dropshadow(color=#949494, offx=1, offy=1);">Premium Content Plugin</h2>
<p>The goal of the Premium Content Plugin was to provide a <u>minimalistic</u> way of controlling access to content that is meant to be kept private/restricted and limited to those who the author wants to share it with.<br /><br />
It is NOT an extensive content control plugin, such as one allowing for subscription services, etc.  There's already some of those out there.<br /><br />
This is just a simple one allowing an individual to control access to their WordPress website.</p>
<br />
<h2 style="text-shadow:1px 1px 2px #949494;filter:dropshadow(color=#949494, offx=1, offy=1);">Accessing Plugin Settings</h2>
<p>There's a couple of places this plugin allows you to configure/use it, so I figured I'd be kind enough to tell you where those are so there's no "hidden" or "surprise" places down the road.</p>
<h3 style="text-shadow:1px 1px 1px #CCCCCC;filter:dropshadow(color=#CCCCCC, offx=1, offy=1);margin:3em 0 1em">Settings Page</h3>
<p>The primary settings page is under the <strong>Users &gt; Premium Content</strong> menu:<br />
<div style="border:3px solid #FFF;display:inline-block;padding:4px;margin:10px 10px 20px;-webkit-box-shadow:3px 3px 8px 2px rgba(0, 0, 0, .5);box-shadow:3px 3px 8px 2px rgba(0, 0, 0, .5);-webkit-border-radius:15px;border-radius:15px;">
<img src="data:image/png;base64,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" width="162" height="125" /></div><br />
Details on the settings can be found in the <strong>Help</strong> tab in the upper-right corner.</p>

<h3 style="text-shadow:1px 1px 1px #CCCCCC;filter:dropshadow(color=#CCCCCC, offx=1, offy=1);margin:3em 0 1em">User Settings</h3>
<p>Under "Edit User" or "Edit Your Profile", you will see a new option called <strong>Premium Content Access</strong>:<br />
<div style="border:3px solid #FFF;display:inline-block;padding:4px;margin:10px 10px 20px;-webkit-box-shadow:3px 3px 8px 2px rgba(0, 0, 0, .5);box-shadow:3px 3px 8px 2px rgba(0, 0, 0, .5);-webkit-border-radius:15px;border-radius:15px;">
<img src="data:image/png;base64,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" width="442" height="69" /></div><br />
This option will appear IF the user has the capability <em>(depending on the value of <strong>Required Capability</strong> option)</em> to edit this value.</p>
<div style="border:1px solid #FF0000;display:inline-block;padding:15px;margin:10px 10px 20px;-webkit-box-shadow:1px 1px 3px 2px rgba(0, 0, 0, .3);box-shadow:1px 1px 3px 2px rgba(0, 0, 0, .3);-webkit-border-radius:10px;border-radius:10px;" id="cannot-change-premium-checkbox"><strong>NOTE:</strong> If the checkbox is greyed out and you cannot change the value, it's because the user's <em>Role(s)</em> are assigned the <?php echo PPREMIUM_SLUG; ?> capability and this value has no effect.<br />
If you wish to unassign this permission to the user, go to the <a href="<?php echo admin_url('users.php?page=premium-user-settings#premium-user-roles'); ?>" title="Roles" />Roles</a> section in the plugin options and remove the <strong><?php echo PPREMIUM_SLUG; ?></strong> from the user's role(s).</div>
<h3 style="text-shadow:1px 1px 1px #CCCCCC;filter:dropshadow(color=#CCCCCC, offx=1, offy=1);margin:3em 0 1em">Post/Page Publish Box</h3>
<p>In the Publish Box, there will be a new option named <strong>Premium Content</strong> <em>(of course, depending on the user's capacity)</em> that will assign the content the Premium Content status.</p>
<div style="border:3px solid #FFF;display:inline-block;padding:4px;margin:10px 10px 20px;-webkit-box-shadow:3px 3px 8px 2px rgba(0, 0, 0, .5);box-shadow:3px 3px 8px 2px rgba(0, 0, 0, .5);-webkit-border-radius:15px;border-radius:15px;">
<img src="data:image/png;base64,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" width="145" height="87" /></div><?php
}

