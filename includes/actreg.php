<?php
include_once  ACTREG_PATH . '/includes/actreg-base.php';

/*
 *
 * Useful queries
 *
 *
 *
 */


class ActivityRegister extends ActivityRegisterBase {

    protected static $instance = NULL; // this instance

    // returns an instance of this class if called, instantiating if necessary
    public static function get_instance() {
        NULL === self::$instance and self::$instance = new self();
        return self::$instance;
    }

    // Do smart stuff when this object is instantiated.
    public function init() {
        $this->log('in Activity Register init');
        // register all relevant hooks
        $this->register_hooks();
        // create other necessary objects
        //register_activation_hook(ACTREG_FILE, array($this, 'activate'));
        // run activate...
        $this->activate();
        register_deactivation_hook(ACTREG_FILE, array($this, 'deactivate'));
    }

    /*
     * Hooks for WP actions
     */

    // initialise the hook methods
    public function register_hooks() {
        $this->log('in Activity Register register_hooks');
        /* See
         *https://core.trac.wordpress.org/browser/tags/4.7.3/src/wp-includes/ms-functions.php#L0
         */
        // register the hook methods
        // add a new site
        add_action('wpmu_new_blog', array($this, 'add_site'), 10, 6);
        // change an existing site do_action( 'update_blog_public', $blog_id, $value );
        add_action('update_blog_public', array($this, 'update_site'), 10, 2);
        // when an existing site is archived - do_action( 'archive_blog', int $blog_id )
        add_action('archive_blog', array($this, 'archive_site'), 10, 1);
        // remove an existing site - do_action( 'delete_blog', $blog_id, $drop );
        add_action('delete_blog', array($this, 'delete_site'), 10, 2);
        // a new user is registered - do_action( 'user_register', $user_id );
        add_action('user_register', array($this, 'add_user'), 10, 1);
        // an existing user logs in (starting new session) -  do_action( 'wp_login', $user->user_login, $user );
        add_action('wp_login', array($this, 'user_login'), 10, 2);
        // an existing user updates their profile - do_action()'profile_update', $user_id, $old_user_data );
        add_action('profile_update', array($this, 'update_user'), 10, 2);
        // do_action( 'add_user_to_blog', $user_id, $role, $blog_id );
        add_action('add_user_to_blog', array($this, 'add_user_to_site'), 10, 3);
        // do_action( 'remove_user_from_blog', $user_id, $blog_id );)
        add_action('remove_user_from_blog', array($this, 'remove_user_from_site'), 10, 2);
        // do_action( 'after_signup_site', $domain, $path, $title, $user, $user_email, $key, $meta );
        //add_action('after_signup_user', array($this, 'after_user_signup_to_site'), 10, 7);
    }

    // runs on activation and upgrade
    public function activate() {
        $this->log('in Activity Register activate');
        // create the database table
        global $wpdb;
        // we default to the current version to avoid installing updates unnecessarily.
        $installed_version = get_option(ACTREG_NAME.'_version', ACTREG_VERSION);
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $this->get_tablename();
        //$this->log('charset_collate: '.$charset_collate);
        // set up SQL statement to create the table
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                time datetime NOT NULL,
                user_id bigint(20) NOT NULL,
                site_id smallint(5) NOT NULL,
                type varchar(100)  NOT NULL DEFAULT '',
                event varchar(255) NOT NULL DEFAULT '',
                message text NOT NULL DEFAULT '',
                UNIQUE KEY id (id),
                KEY user_id (user_id),
                KEY site_id (site_id),
                KEY type (type)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            // submit the query
            dbDelta( $sql );
        } else {
            $this->log('table already exists - not changing!');
        }
        // record the current version of the plugin for future reference
        add_option(ACTREG_NAME.'_version', ACTREG_VERSION);
    }

    // deactivate
    public function deactivate() {
        $this->log('in Activity Register deactivate');
    }

    // individual registry entries
    // note: the timestamp is automatically set to "Now()": when the entry was posted
    public function entry($user_id, $site_id, $type, $event, $message) {
        global $wpdb;
        if (!$user_id) {
            $this->log('no user_id specified!');
            $user_id = get_current_user_id();
        }
        if (!$site_id) {
            $this->log('no site_id specified!');
            $site_id = get_current_blog_id();
        }
        $this->log('entry content: '.$user_id .', '.$site_id.', '.$type.', '.$event.', '.$message);
        // insert register entry - returns false if fails, 1 if succeeds
        $response = $wpdb->insert($this->get_tablename(), array(
            'time' => current_time('mysql', true),
            'user_id' => $user_id,
            'site_id' => $site_id,
            'type' => $type,
            'event' => $event,
            'message' => $message
        ));
        // check if previous insert worked
        if ($response) {
            $id = $wpdb->insert_id;
            //$this->log('inserted '. $id);
            return $id;
        }
        return false;
    }

    // get entry by id (or array of ids)
    public function get_entry_by_id($ids) {
        global $wpdb;
        $entry = array();
        $query = 'SELECT * FROM '.$this->get_tablename().' WHERE id ';
        if (is_array($ids)) {
            $count = count($ids);
            $query .= 'IN(';
            foreach($ids as $id) {
                $count--;
                $query .= ($count) ? $id.',': $id;
            }
            $query .= ') ORDER BY id DESC';
        } else {
            $query .= '= '.$ids;
        }
        $this->log('Activity Register query: '.$query);
        if ($entries = $wpdb->get_results($query, ARRAY_A)) {
            $this->log('Activity Register - successful query!');
            return $entries;
        }
        return false;
    }

    //
    // Hooks!
    //
    /**
     * add a site
     *
     * @param int    $blog_id Blog ID.
     * @param int    $user_id User ID.
     * @param string $domain  Site domain.
     * @param string $path    Site path.
     * @param int    $site_id Site ID. Only relevant on multi-network installs.
     * @param array  $meta    Meta data. Used to set initial site options.
     */
    public function add_site($blog_id, $user_id, $domain, $path, $site_id, $meta) {
        $this->log('in hook add_site');
        $this->entry($user_id, $blog_id, 'Site', 'Added Site', 'New Site '.
        $this->site_name($blog_id).' (id '.$blog_id.') with path '.$path.' added by user '.
            $this->user_name($user_id).' ('.$user_id.').');
        $this->log('Site creation meta: '.print_r($meta, true));
        // need to create a new Segment
    }
    /**
     * Fires after a blog is archived.
     *
     * @param int    $blog_id Blog ID.
     */
    public function archive_site($blog_id) {
        $this->log('in hook archive_site');
        $user_id = get_current_user_id();
        $this->entry($user_id, $blog_id, 'Site', 'Site Archived', 'Site '.
        $this->site_name($blog_id).' (id '.$blog_id.') archived by user '.
            $this->user_name($user_id).'  ('.$user_id.').');
    }

    /**
     * Fires after the current blog's 'public' setting is updated.
     *
     * @param int    $blog_id Blog ID.
     * @param string $value   The value of blog status.
     */
    public function update_site($blog_id, $value) {
        $this->log('in hook update_site');
        $user_id = get_current_user_id();
        $this->entry($user_id, $blog_id, 'Site', 'Updated Site', 'Site '.
        $this->site_name($blog_id).' (id '.$blog_id.') updated to '.$value.' by user '.
            $this->user_name($user_id).'  ('.$user_id.').');
    }

    /**
     * Fires before a site is deleted.
     * @param int  $site_id The site ID.
     * @param bool $drop    True if site's table should be dropped. Default is false.
     */
    public function delete_site($site_id, $drop) {
        $this->log('in delete_site hook');
        $msg = ($drop) ? "deleting tables." : "preserving tables.";
        $user_id = get_current_user_id();
        $this->entry($user_id, $site_id, 'Site', 'Remove Site', 'Removed Site '.
            $this->site_name($site_id).' (id '.$site_id.'): '.$msg);
    }

    /**
     * Fires immediately after a new user is registered.
     *
     * @param int $user_id User ID.
     */
    public function add_user($user_id) {
        $this->log('in user_register hook');
        $this->entry($user_id, $site_id, 'User', 'Add User', 'Added user '.
            $this->user_name($user_id).'  ('.$user_id.').');
    }
    /**
     * Fires after the user has successfully logged in.
     *
     * @param string  $user_login Username.
     * @param WP_User $user       WP_User object of the logged-in user.
     */
    public function user_login($user_login, $user) {
        $this->log('in user_register hook');
        $user_id = $user->ID;
        $site_id = get_current_blog_id();
        $this->entry($user_id, $site_id, 'User', 'User Login', 'User '.
            $user_login.'  ('.$user_id.'). logged in.');
    }

    /**
     * Fires immediately after an existing user is updated.
     * @param int    $user_id       User ID.
     * @param object $old_user_data Object containing user's data prior to update.
	 */
    public function update_user($user_id, $old_user_data) {
        $this->log('in profile_update hook');
        $site_id = get_current_blog_id();
        $this->log('old user data:'.print_r($old_user_data, true));
        // basic user data
        $new_user_data = get_userdata($user_id);
        // user meta data
        $meta = get_user_meta($user_id);
        // add meta dta to user data
        foreach($meta as $key => $val) {
            $new_user_data->data->$key = current($val);
        }
        $this->log('new user data:'.print_r($new_user_data, true));
        $this->entry($user_id, $site_id, 'User', 'Update User Profile', 'User '.
            $this->user_name($user_id).'  ('.$user_id.'). updated their profile.');
    }

    /**
     * Adds a user to a blog.
     *
     * @param int    $user_id User ID.
     * @param string $role    User role.
     * @param int    $blog_id Blog ID.
     */
    public function add_user_to_site($user_id, $role, $blog_id) {
        $this->log('in hook add_user_to_site');
        $this->entry($user_id, $blog_id, 'Site User', 'Added User to Site', 'User '.
            $this->user_name($user_id).' (id '.$user_id.') added to site '.
        $this->site_name($blog_id).' (id '.$blog_id.') with '.$role.' role.');
    }

    /**
     * Fires before a user is removed from a site.
     *
     * @since MU
     *
     * @param int $user_id User ID.
     * @param int $blog_id Blog ID.
     */
	  public function remove_user_from_site($user_id, $blog_id) {
        $this->log('in hook remove_user_from_site');
        $this->entry($user_id, $blog_id, 'Site User', 'Remove User from Site', 'User '.
            $this->user_name($user_id).' (id '.$user_id.') removed from site '.
            $this->site_name($blog_id).' (id '.$blog_id.').');
    }


// currently unused
    /**
     * Fires after a user's signup information has been written to the database.
     * @param string $user       The user's requested login name.
     * @param string $user_email The user's email address.
     * @param string $key        The user's activation key
     * @param array  $meta       Additional signup meta. By default, this is an empty array.
     */
    public function after_user_signup_to_site($user, $user_email, $key, $meta) {
        $this->log('in hook after_user_signup_to_site');
    }

	/**
	 * Fires after site signup information has been written to the database.
	 * @param string $domain     The requested domain.
	 * @param string $path       The requested path.
	 * @param string $title      The requested site title.
	 * @param string $user       The user's requested login name.
	 * @param string $user_email The user's email address.
	 * @param string $key        The user's activation key
	 * @param array  $meta       By default, contains the requested privacy setting and lang_id.
	 */
    public function after_site_creation($domain, $path, $title, $user, $user_email, $key, $meta) {
        $this->log('in hook after_user_signup_to_site');
    }
// end unused

    /*
     * End Hooks
     */

    // a consistent way to get the table name.
    protected function get_tablename() {
        global $wpdb;
        return $wpdb->base_prefix . ACTREG_TABLE;
    }

    // get the site name from site id
    protected function site_name($id) {
        $this->log('site id: '.$id);
        $info = get_blog_details($id);
        return $info->blogname;
    }

    // get the user name from user id
    protected function user_name($id) {
        $info = get_userdata($id);
        $name = '';
        if (! $info->first_name == '') {
            $name .= $info->first_name;
            if (isset($info->last_name)) {
                $name .= ' '.$info->last_name;
            }
        }
        $name .= ($name == '') ? $info->user_login : ' ('.$info->user_login.')';
        return $name;
    }

}
