<?php
/*
Plugin Name: Remove Old Slug For Post/Pages
Description: This tool is used for removing the changed post slug permanently.
Version:     1.0
Author:      Sachin Londhe
Author URI:  https://profiles.wordpress.org/sach3932/
License:     GPL2

*/

/**
 * PART 1. Defining Custom Database Table
 * ============================================================================
 *
 * In this part you are going to define custom database table,
 * create it, update, and fill with some dummy data
 *
 * http://codex.wordpress.org/Creating_Tables_with_Plugins
 *
 */

/**
 * $rspp_db_version - holds current database version
 * and used on plugin update to sync database tables
 */
global $rspp_db_version;
$rspp_db_version = '1.1'; // version changed from 1.0 to 1.1

/**
 * register_activation_hook implementation
 *
 * will be called when user activates plugin first time
 * must create needed database tables
 */






/**
 * PART 2. Defining Custom Table List
 * ============================================================================
 *
 * In this part you are going to define custom table list class,
 * that will display your database records in nice looking table
 *
 * http://codex.wordpress.org/Class_Reference/WP_List_Table
 * http://wordpress.org/extend/plugins/custom-list-table-example/
 */
	
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Remove_Old_Slug_List_Table class that will display our custom table
 * records in nice table
 */
class Remove_Old_Slug_List_Table extends WP_List_Table
{
    /**
     * [REQUIRED] You must declare constructor and give some basic params
     */
    function __construct()
    {
        global $status, $page;

        parent::__construct(array(
            'singular' => 'remove_slug',
            'plural' => 'remove_slugs',
        ));
    }

	
	
    /**
     * [REQUIRED] this is a default column renderer
     *
     * @param $item - row (key, value array)
     * @param $column_name - string (key)
     * @return HTML
     */
    	
	function column_default($item, $column_name){
	
        switch($column_name){
            case 'post_name': return get_the_title($item['post_id']);
            case 'post_old_slug': return $item['meta_value'];
			case 'post_new_slug' : return basename(get_permalink($item['post_id']));
			break;	
            default:
					return $item[$column_name];
                //return print_r($item,true); //Show the whole array for troubleshooting purposes
        }
    }
	
	
    /**
     * [OPTIONAL] this is example, how to render column with actions,
     * when you hover row "Edit | Delete" links showed
     *
     * @param $item - row (key, value array)
     * @return HTML
     */
    function column_name($item)
    {
        // links going to /admin.php?page=[your_plugin_page][&other_params]
        // notice how we used $_REQUEST['page'], so action will be done on curren page
        // also notice how we use $this->_args['singular'] so in this example it will
        // be something like &remove_slug=2
        $actions = array(
                    'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', esc_attr($_REQUEST['page']), absint($item['post_id']), __('Delete', 'rospp')),
        );

        return sprintf('%s %s',
            $item['name'],
            $this->row_actions($actions)
        );
    }

    /**
     * [REQUIRED] this is how checkbox column renders
     *
     * @param $item - row (key, value array)
     * @return HTML
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['post_id']
        );
    }

    /**
     * [REQUIRED] This method return columns to display in table
     * you can skip columns that you do not want to show
     * like content, or description
     *
     * @return array
     */
    function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
            'post_id' => __('Post ID', 'rospp'),
            'post_name' => __('Post Name', 'rospp'),
            'post_old_slug' => __('Old Slug', 'rospp'),
			'post_new_slug' => __('New Slug', 'rospp'),
			
        );
        return $columns;
    }

    /**
     * [OPTIONAL] This method return columns that may be used to sort table
     * all strings in array - is column names
     * notice that true on name column means that its default sort
     *
     * @return array
     */
    function get_sortable_columns()
    {
        $sortable_columns = array(
            'post_id' => array('post_id', true),
            'post_name' => array('post_name', false),
            'post_old_slug' => array('post_old_slug', false),
			'post_new_slug' => array('post_new_slug', false),
			
        );
        return $sortable_columns;
    }

    /**
     * [OPTIONAL] Return array of bult actions if has any
     *
     * @return array
     */
    function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    /**
     * [OPTIONAL] This method processes bulk actions
     * it can be outside of class
     * it can not use wp_redirect coz there is output already
     * in this example we are processing delete action
     * message about successful deletion will be shown on page in next part
     */
    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->postmeta; // do not forget about tables prefix

        if ('delete' === $this->current_action()) {
             
			$ids = array();
			if( isset($_REQUEST['id']) && !empty($_REQUEST['id']) )
			{
				$ids = $_REQUEST['id'];
				$ids = esc_attr(implode(',', $ids));
			} 
			
            if (!empty($ids)){
				
				$wpdb->query("DELETE FROM $table_name WHERE post_id IN($ids) AND `meta_key` = '_wp_old_slug'");
            }
        }
    }

    /**
     * [REQUIRED] This is the most important method
     *
     * It will get rows from database and prepare them to be showed in table
     */
    function prepare_items($search ='')
    {
		
		
        global $wpdb;
        $table_name = $wpdb->postmeta; // do not forget about tables prefix

        $per_page = 10; // constant, how much records will be shown per page

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        // here we configure table headers, defined in our methods
        $this->_column_headers = array($columns, $hidden, $sortable);

        // [OPTIONAL] process bulk action if any
        $this->process_bulk_action();

        // will be used in pagination settings
        $total_items = $wpdb->get_var("SELECT COUNT(meta_id) FROM $table_name WHERE `meta_key` = '_wp_old_slug'");

        // prepare query params, as usual current page, order by and order direction
        $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged'] -1) * $per_page) : 0;
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'name';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

        // [REQUIRED] define $items array
        // notice that last argument is ARRAY_A, so we will retrieve array\
		
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE `meta_key` = '_wp_old_slug' LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);
		
		if(!empty($search)){
			 
			//echo "<pre>"; print_r($this->items); die;
			$expr = '/^[1-9][0-9]*$/';
			if(!is_numeric($search)){
				$post_id = $wpdb->get_row("SELECT ID FROM $wpdb->posts WHERE `post_title` LIKE '%{$search}%' OR `post_name` LIKE '%{$search}%' ");
				if(empty($post_id) && !isset($post_id)){
					$post_id = $wpdb->get_row("SELECT post_id FROM $wpdb->postmeta WHERE `meta_key` = '_wp_old_slug' AND `meta_value`='%{$search}%' ");
					
				}
			}
			//echo $post_id->ID;
			if(!empty($post_id) && isset($post_id)){
				
				$this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE `meta_key` = '_wp_old_slug' AND `post_id` = $post_id->ID LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);
			
				
				$total_items = $wpdb->get_var("SELECT COUNT(meta_id) FROM $table_name WHERE `meta_key` = '_wp_old_slug' AND `post_id` = $post_id->ID");
			}	
			
			else if(is_numeric($search)) {
				
				
				$this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE `meta_key` = '_wp_old_slug' AND `post_id` = {$search} LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);
				
				$total_items = $wpdb->get_var("SELECT COUNT(meta_id) FROM $table_name WHERE `meta_key` = '_wp_old_slug' AND `post_id` = {$search}");
				
				
			}
			
			else if(empty($post_id) && !is_numeric($search) ){
				
				$this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE `meta_key` = '_wp_old_slug' AND `meta_value` LIKE '%{$search}%' LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);
				
				$total_items = $wpdb->get_var("SELECT COUNT(meta_id) FROM $table_name WHERE `meta_key` = '_wp_old_slug' AND `meta_value` LIKE '%{$search}%'");
				
			
			} 
        
		}
	
        // [REQUIRED] configure pagination
        $this->set_pagination_args(array(
            'total_items' => $total_items, // total items defined above
            'per_page' => $per_page, // per page constant defined at top of method
            'total_pages' => ceil($total_items / $per_page) // calculate pages count
        ));
		
		 

	
    }
	


}

/**
 * PART 3. Admin page
 * ============================================================================
 *
 * In this part you are going to add admin page for custom table
 *
 * http://codex.wordpress.org/Administration_Menus
 */

/**
 * admin_menu hook implementation, will add pages to list remove_slugs and to add new one
 */
function rospp_admin_menu()
{
    add_menu_page(__('Remove Slugs', 'rospp'), __('Remove slugs', 'rospp'), 'activate_plugins', 'remove_slugs', 'rospp_remove_slugs_page_handler', 'dashicons-trash');
    
}

add_action('admin_menu', 'rospp_admin_menu');

/**
 * List page handler
 *
 * This function renders our custom table
 * Notice how we display message about successfull deletion
 * Actualy this is very easy, and you can add as many features
 * as you want.
 *
 * Look into /wp-admin/includes/class-wp-*-list-table.php for examples
 */
function rospp_remove_slugs_page_handler()
{
    global $wpdb;

    $table = new Remove_Old_Slug_List_Table();
	
	
    $table->prepare_items();

    $message = '';
    if ('delete' === $table->current_action()) {
        $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Slugs are removed: %d', 'rospp'), count($_REQUEST['id'])) . '</p></div>';
    }
    ?>
<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2 class="remove_slug_heading"><?php _e('Remove Old slugs', 'rospp')?> 
    </h2>
    <?php echo $message; ?>

    <form id="remove_slugs-table" method="GET">
        <input type="hidden" name="page" value="<?php echo esc_html($_REQUEST['page']); ?>"/>
<?php	
if( isset($_GET['s']) ){
	//echo "<pre>"; print_r($_POST['s']);
 $table->prepare_items($_GET['s']);
 } else {
 $table->prepare_items();
 }
 $table->search_box( 'search', 'search_id' );
 $table->display() ?>
    </form>

</div>
<?php


}


/**
 * PART 4. Form for adding andor editing row
 * ============================================================================
 *
 * In this part you are going to add admin page for adding andor editing items
 * You cant put all form into this function, but in this example form will
 * be placed into meta box, and if you want you can split your form into
 * as many meta boxes as you want
 *
 * http://codex.wordpress.org/Data_Validation
 * http://codex.wordpress.org/Function_Reference/selected
 */


/**
 * This function renders our custom meta box
 * $item is row
 *
 * @param $item
 */
function rospp_remove_slugs_form_meta_box_handler($item)
{
	
}

function rospp_admin_enqueue_scripts() {
	wp_enqueue_script('jquery');
    wp_register_style( 'rspp-style',  plugin_dir_url( __FILE__ ) . 'css/rospp.css' );
    wp_enqueue_style( 'rspp-style' );
	
}
add_action( 'admin_enqueue_scripts', 'rospp_admin_enqueue_scripts');

/**
 * Simple function that validates data and retrieve bool on success
 * and error message(s) on error
 *
 * @param $item
 * @return bool|string
 */


/**
 * Do not forget about translating your plugin, use __('english string', 'your_uniq_plugin_name') to retrieve translated string
 * and _e('english string', 'your_uniq_plugin_name') to echo it
 * in this example plugin your_uniq_plugin_name == rospp
 *
 * to create translation file, use poedit FileNew catalog...
 * Fill name of project, add "." to path (ENSURE that it was added - must be in list)
 * and on last tab add "__" and "_e"
 *
 * Name your file like this: [my_plugin]-[ru_RU].po
 *
 * http://codex.wordpress.org/Writing_a_Plugin#Internationalizing_Your_Plugin
 * http://codex.wordpress.org/I18n_for_WordPress_Developers
 */
function rospp_languages()
{
    load_plugin_textdomain('rospp', false, dirname(plugin_basename(__FILE__)));
}

add_action('init', 'rospp_languages');