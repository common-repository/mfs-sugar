<?php
/*
 * Plugin Name: MFS Sugar
 * Plugin URI: http://www.mindfiresolutios.com
 * Description: A Basic WordPress Plugin to convert a comment into Sugar Case. To use this plugin, please enable curl on your server.
 * Author: Amrita Satpathy, Mindfire-Solutions
 * Version: 1.0
 * Author URI: http://www.mindfiresolutions.com
 */

/**
 * Class to differentiate the namespace 
 */
final class Mfs_Sugar {

    /**
     * check if version is > 3.0 
     */
    public static function check_version() {
        if( version_compare( get_bloginfo( 'version' ), '3.0', '<' ) ) {
            wp_die("You must update WordPress to use this plugin!");
        }
    }

    /**
     * all the activation code goes here
     */
    public static function activate() {
        self::check_version();
    }

    /**
     * registering all the action hooks goes here
     */
    public static function register_action_hooks() {
        //add in admin menu
        add_action('admin_menu', array('Mfs_Sugar', 'add_admin_menu'));
        //add sugar settings menu page
        add_action('admin_post_mfs_save_sugar_settings', array('Mfs_Sugar', 'save_sugar_settings'));
        //action to handle conversion to case
        add_action('admin_action_mfs_convert_to_case', array('Mfs_Sugar', 'convert_comment_to_case'));
        //action to show admin notices
        add_action('admin_notices', array('Mfs_Sugar', 'sugar_admin_notice'));
    }

    /**
     * 
     */
    public static function register_filter_hooks() {
        add_filter('comment_row_actions', array('Mfs_Sugar', 'add_convert_link'), 10, 2);
        add_filter('admin_comment_types_dropdown', array('Mfs_Sugar', 'comment_types_dropdown'));
    }

    /**
     * add admin menu here
     */
    public static function add_admin_menu() {
        add_submenu_page('options-general.php', 'SugarCRM Settings', 'SugarCRM Settings', 'manage_options', __FILE__ . '_display_contents', array('Mfs_Sugar', 'display_settings_contents'));
    }

    /**
     * function to save the sugar settings
     */
    public static function save_sugar_settings() {
        if( !current_user_can( 'manage_options' ) ) {
            wp_die(__('You are not allowed to be on this page.','mfs-sugar'));
        }
        // Check the nonce field
        check_admin_referer('mfs_op_verify');

        $options = array();

        if( isset( $_POST['sugar_url'] ) && !empty( $_POST['sugar_url'] )
            && isset( $_POST['sugar_username'] ) && !empty( $_POST['sugar_username'] )
            && isset( $_POST['sugar_password'] ) && !empty( $_POST['sugar_password'] ) ) {
            
            $options['mfs_sugar_url'] = esc_url_raw( $_POST['sugar_url'], array( 'http', 'https' ) );
            $options['mfs_sugar_username'] = sanitize_text_field( $_POST['sugar_username'] );
            $options['mfs_sugar_password'] = sanitize_text_field( $_POST['sugar_password'] );
            
        } else {
            wp_die(__('Please provide all the values','mfs-sugar'));
        }

        if( !self::is_valid_sugar_credentials( $options['mfs_sugar_url'], $options['mfs_sugar_username'], $options['mfs_sugar_password'] ) ) {
            wp_redirect( admin_url( "options-general.php?page=mfs_sugar/mfs-sugar.php_display_contents&m=0" ) );
            exit;
        }

        update_option( 'mfs_sugar_settings', $options );

        wp_redirect( admin_url( "options-general.php?page=mfs_sugar/mfs-sugar.php_display_contents&m=1" ) );
        exit;
    }

    /**
     * action to convert comment to ticket
     */
    public static function convert_comment_to_case() {
        global $wp_logs;
        $comment_id = $_GET['c'];
        $comment = get_comment($comment_id);

        /**
         * include all the Sugar CRM related files here
         */
        include 'sugar_rest/connector/rest.php';
        include 'sugar_rest/config.php';
        include 'sugar_rest/application/user.php';
        include 'sugar_rest/application/record.php';

        $options = get_option('mfs_sugar_settings');
        if( empty( $options ) )
            wp_redirect( admin_url( 'options-general.php?page=mfs_sugar/mfs-sugar.php_display_contents&m=0' ) );

        Config::set( "site_url", $options['mfs_sugar_url'] );
        Config::set( 'username', $options['mfs_sugar_username'] );
        Config::set( 'password', $options['mfs_sugar_password'] );

        try {
            User::doLogin();
            //Subject and the description of the case are set to comment contents
            $data = array(
                array('name' => 'name', 'value' => "$comment->comment_content"),
                array('name' => 'description', 'value' => "$comment->comment_content"),
            );
            $case = new Record("Cases", $data);
            $case->save();
            $wp_logs::add('Comment converted', "[$comment_id] : comment converted to case", 0);

            //update the comment type to Sugar Case []
            global $wpdb;
            $wpdb->query(
                    "
                    UPDATE $wpdb->comments 
                    SET comment_type = 'mfs_sugar_case'
                    WHERE comment_ID = $comment_id 
                    "
            );
        } catch (Exception $ex) {
            $wp_logs::add('Comment conversion failed', "[$comment_id] : ".$ex->getMessage(), 0, 'error');
            wp_redirect(admin_url('edit-comments.php?mfs_error=1'));
            exit;
        }

        wp_redirect(admin_url('edit-comments.php?mfs_success=1'));
        exit;
    }

    /**
     * handle admin notice here
     */
    public static function sugar_admin_notice() {
        
        if( isset( $_REQUEST['mfs_success'] ) ) {
            echo "<div class='updated'>" . __('Comment Converted to Case','mfs-sugar') . "</div>";
        }elseif( isset($_REQUEST['mfs_error'] ) ) {
            echo "<div class='error'>" . __('Conversion Failed','mfs-sugar') . "</div>";
        }
    }

    /**
     * 
     * @param array $links
     * @param type $post
     * @return string
     */
    public static function add_convert_link( $links, $post ) {
        
        if( $post->comment_type != 'mfs_sugar_case' ) {
            
            $convert_uri = admin_url('admin.php?action=mfs_convert_to_case&c=' . $post->comment_ID);
            $links['mfs_convert_to_case'] = '<a href="' . $convert_uri . '">' . __('Convert to Case','mfs-sugar') . '</a>';
            
        }
        return $links;
    }

    /**
     * 
     * @param array $types
     * @return type
     */
    public static function comment_types_dropdown( $types ) {
        $types['mfs_sugar_case'] = __('Sugar Case','mfs-sugar');
        return $types;
    }

    /**
     * Settings page goes here
     */
    public static function display_settings_contents() {
        $options = get_option('mfs_sugar_settings');
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>
                <?php echo __('Sugar CRM Settings','mfs-sugar'); ?>
                <?php
                if (isset($_GET['m']) && $_GET['m'] == '1') {
                    ?>
                    <div id='message' class='updated fade'><p><strong><?php echo __('Settings saved. ','mfs-sugar'); ?></strong></p></div>
                    <?php
                }
                if (isset($_GET['m']) && $_GET['m'] == '0') {
                    ?>
                    <div id='message' class='error fade'><p><strong><?php echo __('Invalid Credentials. ','mfs-sugar'); ?></strong></p></div>
                    <?php
                }
                ?>
            </h2>
            <form method="post" action="admin-post.php">
                <input type="hidden" name="action" value="mfs_save_sugar_settings" />
                <table class="form-table">
                    <?php wp_nonce_field('mfs_op_verify'); ?>
                    <tr valign="top">
                        <th scope="row">
                            <label for="sugar_url"><?php echo __('Sugar CRM Url','mfs-sugar'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="sugar_url" value="<?php echo esc_html($options['mfs_sugar_url']); ?>"/>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="sugar_username"><?php echo __('Sugar CRM Username ','mfs-sugar'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="sugar_username" value="<?php echo esc_html($options['mfs_sugar_username']); ?>"/>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="sugar_password"><?php echo __('Sugar CRM Password ','mfs-sugar'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="sugar_password" value="<?php echo esc_html($options['mfs_sugar_password']); ?>"/>
                        </td>
                    </tr>
                </table>
                <p class="submit"> <input type="submit" value="Save Changes" class="button button-primary"/></p>
            </form>
        </div>
        <?php
    }

    /**
     * check by logging in if the sugar credentials provided are valid
     * @param type $url
     * @param type $username
     * @param type $password
     * @return boolean
     */
    public static function is_valid_sugar_credentials( $url, $username, $password ) {
        
        global $wp_logs;
        include 'sugar_rest/connector/rest.php';
        include 'sugar_rest/config.php';
        include 'sugar_rest/application/user.php';
        Config::set("site_url", $url);
        Config::set('username', $username);
        Config::set('password', $password);
        $valid = true;
        try {
            User::doLogin();
        } catch (Exception $ex) {
            $wp_logs::add('Sugar CRM : Invalid Credentials', "Credentials : [$url][$username][$password] : ".$ex->getMessage(), 0, 'error');
            $valid = false;
        }
        return $valid;
    }

}

/**
 * Looger for Wordpress from Pippin Williamson
 */
include_once 'wp-logging.php';
// Installation
register_activation_hook(__FILE__, array('Mfs_Sugar', 'activate'));
Mfs_Sugar::register_action_hooks();
Mfs_Sugar::register_filter_hooks();
?>
