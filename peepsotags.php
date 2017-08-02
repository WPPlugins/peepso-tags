<?php
/**
 * Plugin Name: PeepSo Core: Tags
 * Plugin URI: https://peepso.com
 * Description: Mention (tag) other users in a status or a comment
 * Author: PeepSo
 * Author URI: https://peepso.com
 * Version: 1.8.2
 * Copyright: (c) 2015 PeepSo LLP. All Rights Reserved.
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: peepso-tags
 * Domain Path: /language
 *
 * We are Open Source. You can redistribute and/or modify this software under the terms of the GNU General Public License (version 2 or later)
 * as published by the Free Software Foundation. See the GNU General Public License or the LICENSE file for more details.
 * This software is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY.
 */

class PeepSoTags
{
	private static $_instance = NULL;
	private $user_ids = array();

	const PLUGIN_VERSION = '1.8.2';
	const PLUGIN_RELEASE = ''; //ALPHA1, BETA1, RC1, '' for STABLE
	const SHORTCODE_TAG = 'peepso_tag';
	const MODULE_ID = 7;
	const PLUGIN_NAME = 'TagSo';
	const PLUGIN_EDD = 'tagso';
	const PLUGIN_SLUG = 'tagso';

	const PEEPSOCOM_LICENSES = 'http://tiny.cc/peepso-licenses';

	const PLUGIN_DEV = FALSE;

	/**
	 * Initialize all variables, filters and actions
	 */
	private function __construct()
	{
		add_action('peepso_init', array(&$this, 'init'));

		if (is_admin()) {
			add_action('admin_init', array(&$this, 'peepso_check'));
		}

		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_filter('peepso_remove_shortcodes', array(&$this, 'filter_remove_shortcode'));

		add_filter('peepso_all_plugins', array($this, 'filter_all_plugins'));

		register_activation_hook(__FILE__, array(&$this, 'activate'));
	}

	/*
	 * retrieve singleton class instance
	 * @return instance reference to plugin
	 */
	public static function get_instance()
	{
		if (NULL === self::$_instance)
			self::$_instance = new self();
		return (self::$_instance);
	}

	/**
	 * Loads the translation file for the PeepSo plugin
	 */
	public function load_textdomain()
	{
		$path = str_ireplace(WP_PLUGIN_DIR, '', dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR;
		load_plugin_textdomain('peepso-tags', FALSE, $path);
	}

	/*
	 * Initialize the PeepSoTags plugin
	 */
	public function init()
	{
		// set up autoloading
		PeepSo::add_autoload_directory(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR);
		PeepSoTemplate::add_template_directory(plugin_dir_path(__FILE__));

		if (is_admin()) {
			add_action('admin_init', array(&$this, 'peepso_check'));
		} else {
			add_shortcode(self::SHORTCODE_TAG, array(&$this, 'shortcode_tag'));

			add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
			add_action('peepso_activity_after_add_post', array(&$this, 'after_save_post'), 10, 2);
			add_action('peepso_activity_after_add_comment', array(&$this, 'after_save_comment'), 10, 2);

			add_filter('peepso_profile_notification_link', array(&$this, 'profile_notification_link'), 10, 2);
			add_filter('peepso_modify_link_item_notification', array(&$this, 'modify_link_item_notification'), 10, 2);
		}

		// used by Profile page UI to configure alerts and notifications setting
		add_filter('peepso_profile_alerts', array(&$this, 'profile_alerts'), 10, 1);

		add_filter('peepso_config_email_messages', array(&$this, 'config_email_tags'));
		add_filter('peepso_config_email_messages_defaults', array(&$this, 'config_email_messages_defaults'));

		// Compare last version stored in transient with current version
		if( $this::PLUGIN_VERSION.$this::PLUGIN_RELEASE != get_transient($trans = 'peepso_'.$this::PLUGIN_SLUG.'_version')) {
			set_transient($trans, $this::PLUGIN_VERSION.$this::PLUGIN_RELEASE);
			$this->activate();
		}
	}

	/**
	 * Plugin activation
	 * Check PeepSo
	 * @return bool
	 */
	public function activate()
	{
		if (!$this->peepso_check()) {
			return (FALSE);
		}

		return (TRUE);
	}

	/**
	 * Check if PeepSo class is present (ie the PeepSo plugin is installed and activated)
	 * If there is no PeepSo, immediately disable the plugin and display a warning
	 * Run license and new version checks against PeepSo.com
	 * @return bool
	 */
	public function peepso_check()
	{
		if (!class_exists('PeepSo')) {
			add_action('admin_notices', array(&$this, 'peepso_disabled_notice'));
			unset($_GET['activate']);
			deactivate_plugins(plugin_basename(__FILE__));
			return (FALSE);
		}

		return (TRUE);
	}

	/**
	 * Display a message about PeepSo not present
	 */
	public function peepso_disabled_notice()
	{
		?>
		<div class="error fade">
			<strong>
				<?php echo sprintf(__('The %s plugin requires the PeepSo plugin to be installed and activated.', 'peepso-tags'), self::PLUGIN_NAME);?>
				<a href="<?php echo self::PEEPSOCOM_LICENSES;?>" target="_blank">
					<?php _e('Get it now!', 'peepso-tags');?>
				</a>
			</strong>
		</div>
		<?php
	}

	/**
	 * Hooks into PeepSo Core for compatibility checks
	 * @param $plugins
	 * @return mixed
	 */
	public function filter_all_plugins($plugins)
	{
		$plugins[plugin_basename(__FILE__)] = get_class($this);
		return $plugins;
	}

	/**
	 * Registers the needed scripts and styles
	 */
	public function enqueue_scripts()
	{
		if (self::PLUGIN_DEV) {
			wp_register_script('peepsotags-tagging', plugin_dir_url(__FILE__) . 'assets/js/tagging.js', array('jquery', 'underscore', 'peepso'), self::PLUGIN_VERSION, TRUE);
			wp_register_script('peepsotags', plugin_dir_url(__FILE__) . 'assets/js/peepsotags.js', array('peepsotags-tagging', 'peepso-observer'), self::PLUGIN_VERSION, TRUE);
		} else {
			wp_register_script('peepsotags', plugin_dir_url(__FILE__) . 'assets/js/bundle.min.js', array('jquery', 'underscore', 'peepso', 'peepso-observer'), self::PLUGIN_VERSION, TRUE);
		}

		wp_enqueue_script('peepsotags');

		wp_localize_script('peepsotags', 'peepsotagsdata', array(
			'parser' => $this->get_tag_parser(),
			'template' => $this->get_tag_template()
		));
	}

	/**
	 * Returns the regular expression that matches the markup for the @ character.
	 * @return string
	 */
	public function get_tag_parser()
	{
		return (apply_filters('peepso_tags_parser', '\[peepso_tag id=(\d+)\]([^\]]+)\[\/peepso_tag\]'));
	}

	/**
	 * Returns the template used to render the layout as key/value pairs.
	 * @return string
	 */
	public function get_tag_template()
	{
		return (apply_filters('peepso_tags_template', '[peepso_tag id=<%= id %>]<%= title %>[/peepso_tag]'));
	}

	/**
	 * Renders the User's display name and profile link
	 * @return string
	 */
	public function shortcode_tag($atts, $content = '')
	{
		if (!isset($atts['id']) && empty($atts['id']))
			return;

		$user = PeepSoUser::get_instance($atts['id']);
		$name = $content;

		$display_name = trim(strip_tags($user->get_fullname()));

		// check if provided name is part on the display name string
		if (FALSE === strpos($display_name, $name)) {
			$name = $display_name;
		}

		return (sprintf('<a href="%s" title="%s">%s</a>', $user->get_profileurl(), $display_name, $name));
	}

	/**
	 * Fires once a post has been saved.
	 * @param int $post_id Post ID.
	 * @param int $act_id  The activity ID.
	 */
	public function after_save_post($post_id, $act_id)
	{
		$post_obj = get_post($post_id);
		$match = preg_match_all('/' . $this->get_tag_parser() . '/i', $post_obj->post_content, $matches);

		if ($match) {
			global $post;

			$PeepSoActivity  = PeepSoActivity::get_instance();
			// TODO: not always successful. Should check return value
			$post_act = $PeepSoActivity->get_activity($act_id);

			$post = $post_obj;
			setup_postdata($post);

			$user_author = PeepSoUser::get_instance($post->post_author);

			$data = array('permalink' => $PeepSoActivity->post_link(FALSE));
			$from_fields = $user_author->get_template_fields('from');

			$this->user_ids = $matches[1];

			$notifications = new PeepSoNotifications();

			$_notification = __('mentioned you', 'peepso-tags');
			foreach ($this->user_ids as $user_id) {
				$user_id = intval($user_id);

				// If self don't send the notification
				if (intval($post->post_author) === $user_id)
					continue;

				// Check access
				if (!PeepSo::check_permissions($user_id, PeepSo::PERM_POST_VIEW, intval($post->post_author)))
					continue;

				// check act_owner is current user_id
				if($user_id != $post_act->act_owner_id) {
					$user_owner = PeepSoUser::get_instance($user_id);
					$data = array_merge($data, $from_fields, $user_owner->get_template_fields('user'));
					// TODO: need to use an editable email message, not a constant string
					// SpyDroid: the constant string is an email subject and not an editable email message, the template for editable email is the 4th parameter 'tagged'
					PeepSoMailQueue::add_message($user_id, $data, __('You Were Tagged in a Post', 'peepso-tags'), 'tagged', 'tag', self::MODULE_ID);

					$notifications->add_notification(intval($post->post_author), $user_id, $_notification, 'tag', self::MODULE_ID, $post_id);
				}
		        else
		        {
		        	// if tagged, modify notification
					add_filter('peepso_notifications_data_before_add', array(&$this, 'modify_message_notification'), 10, 1);
		        }
			}
		}
	}

	/**
	 * Fires once a post has been saved.
	 * @param int $post_id Post ID.
	 * @param int $act_id  The activity ID.
	 */
	public function after_save_comment($post_id, $act_id)
	{
		$post_obj = get_post($post_id);
		$match = preg_match_all('/' . $this->get_tag_parser() . '/i', $post_obj->post_content, $matches);

		if ($match) {
			global $post;

			$PeepSoActivity = PeepSoActivity::get_instance();
			// TODO: not always successful. Should check return value
			$post_act = $PeepSoActivity->get_activity($act_id);
			$act_comment_object_id = $post_act->act_comment_object_id;
			$act_comment_module_id = $post_act->act_comment_module_id;
			//$comment_object_post = get_post($act_comment_object_id);

			$post = $post_obj;
			setup_postdata($post);

			$user_author = PeepSoUser::get_instance($post->post_author);
			$data = array('permalink' => $PeepSoActivity->post_link(FALSE));
			$from_fields = $user_author->get_template_fields('from');

			$this->user_ids = $matches[1];

			$notifications = new PeepSoNotifications();

			$_notification = __('mentioned you', 'peepso-tags');
			foreach ($this->user_ids as $user_id) {
				$user_id = intval($user_id);

				// If self don't send the notification
				if (intval($post->post_author) === $user_id)
					continue;

				// Check access
				if (!PeepSo::check_permissions($user_id, PeepSo::PERM_POST_VIEW, intval($post->post_author)))
					continue;

				// if parent is owner don't add new notification
				// notification already sent for parent activity owner in peepso-core
				/*if (intval($comment_object_post->post_author) === $user_id)
					continue;*/

				$users = $PeepSoActivity->get_comment_users($act_comment_object_id, $act_comment_module_id);
				$follower = array();
		        while ($users->have_posts()) {

		            $users->next_post();

		            $follower[] = $users->post->post_author;
		        }

		        // if not following post send tagged notification
		        if ((!in_array($user_id, $follower) && ($user_id != $post_act->act_owner_id)) || ($post_act->act_owner_id == $user_id && intval($post_act->act_comment_object_id) > 0)) {
		        	$user_owner = PeepSoUser::get_instance($user_id);
					$data = array_merge($data, $from_fields, $user_owner->get_template_fields('user'));
					// TODO: need to use an editable email message, not a constant string
					// SpyDroid: the constant string is an email subject and not an editable email message, the template for editable email is the 4th parameter 'tagged'
					PeepSoMailQueue::add_message($user_id, $data, __('Someone tagged you in a comment', 'peepso-tags'), 'tagged', 'tag_comment', self::MODULE_ID);

					$notifications->add_notification(intval($post->post_author), $user_id, $_notification, 'tag_comment', self::MODULE_ID, $post_id);
		        }
		        else
		        {
		        	// if tagged, modify notification
					add_filter('peepso_notifications_data_before_add', array(&$this, 'modify_message_notification'), 10, 1);
		        }
			}
		}
	}

	/**
	 * Modify message notification
	 * @param array $notification
	 * @return array modified $notification
	 */
	public function modify_message_notification($notification=array())
	{
		/*array(
				'not_user_id' => $to_user,
				'not_from_user_id' => $from_user,
				'not_module_id' => $module_id,
				'not_external_id' => $external,
				'not_type' => substr($type, 0, 20),
				'not_message' => substr($msg, 0, 200),
				'not_timestamp' => current_time('mysql')
			)*/
		if(count($notification) > 0 && in_array($notification['not_user_id'], $this->user_ids))
		{
			if($notification['not_type'] == 'wall_post') {
				$notification['not_message'] = __('wrote and mentioned you on your wall', 'peepso-tags') ;
			} else {
				$notification['not_message'] = __('mentioned you', 'peepso-tags') ;
			}
		}

		return $notification;
	}

	/**
	 * Modify link notification
	 * @param array $link
	 * @param array $note_data
	 * @return string $link
	 */
	public function profile_notification_link($link, $note_data) {

		if ('tag' === $note_data['not_type']) {

			// do nothing

		} else if ('tag_comment' === $note_data['not_type']) {

			$activities = PeepSoActivity::get_instance();

			$not_activity = $activities->get_activity_data($note_data['not_external_id'], PeepSoActivity::MODULE_ID);
			$parent_activity = $activities->get_activity_data($not_activity->act_comment_object_id, $not_activity->act_comment_module_id);

			if (is_object($parent_activity)) {

				$not_post = $activities->get_activity_post($not_activity->act_id);
				$parent_post = $activities->get_activity_post($parent_activity->act_id);
				$parent_id = $parent_post->act_external_id;

				// check if parent post is a comment
				if($parent_post->post_type == 'peepso-comment') {
					$comment_activity = $activities->get_activity_data($not_activity->act_comment_object_id, $not_activity->act_comment_module_id);
					$post_activity = $activities->get_activity_data($comment_activity->act_comment_object_id, $comment_activity->act_comment_module_id);

					$parent_comment = $activities->get_activity_post($comment_activity->act_id);

					$link = PeepSo::get_page('activity') . '?status/' . $parent_post->post_title . '/?t=' . time() . '#comment=' . $post_activity->act_id . '.' . $parent_comment->ID . '.' . $comment_activity->act_id . '.' . $not_activity->act_external_id;
				} else {
					$link = PeepSo::get_page('activity') . '?status/' . $parent_post->post_title . '/#comment=' . $parent_activity->act_id . '.' . $not_post->ID . '.' . $not_activity->act_id;
				}
			}
		}

		return $link;
	}

	/**
	 * Modify link item notification
	 * @param array array($print_link, $link)
	 * @param array $note_data
	 * @return string $link
	 */
	public function modify_link_item_notification($link, $note_data) {

		if ('tag' === $note_data['not_type']) {

			ob_start();
			echo ' ', __('in', 'peepso-tags'), ' ', __('a post', 'peepso-tags');

			$new_link = ob_get_clean();
		} else if ('tag_comment' === $note_data['not_type']) {
			ob_start();
			echo ' ', __('in', 'peepso-tags'), ' ', __('a comment', 'peepso-tags');

			$new_link = ob_get_clean();
		}

		if ( isset($new_link) ) {
			return $new_link;
		} else {
			return $link[0];
		}
	}


	/**
	 * Add the User Tagged Email to the list of editable emails on the config page
	 * @param  array $emails Array of editable emails
	 * @return array
	 */
	// TODO: move this into a PeepSoTaggingAdmin class
	public function config_email_tags($emails)
	{
		$emails['email_tagged'] = array(
			'title' => __('User Tagged Email', 'peepso-tags'),
			'description' => __('This will be sent to a user when a tagged in post.', 'peepso-tags')
		);

		return ($emails);
	}

	public function config_email_messages_defaults( $emails )
	{
		require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . '/install' . DIRECTORY_SEPARATOR . 'activate.php');
		$install = new PeepSoTagsInstall();
		$defaults = $install->get_email_contents();

		return array_merge($emails, $defaults);
	}

	/**
	 * Append profile alerts definition for peepsotags. Used on profile?alerts page
	 // TODO: document parameters
	 */
	public function profile_alerts($alerts)
	{
		$alerts['tags'] = array(
				'title' => __('Tags', 'peepso-tags'),
				'items' => array(
					array(
						'label' => __('Someone tagged me in a Post', 'peepso-tags'),
						'setting' => 'tag',
						'loading' => TRUE,
					),
					array(
						'label' => __('Someone tagged me in a Comment', 'peepso-tags'),
						'setting' => 'tag_comment',
						'loading' => TRUE,
					)
				),
		);
		// NOTE: when adding new items here, also add settings to /install/activate.php site_alerts_ sections
		return ($alerts);
	}

	/**
	 * Remove peepso tags shortcode
	 * @param string $string to process
	 * @return string $string
	 */
	public function filter_remove_shortcode($string)
	{
		$string = str_replace('[/peepso_tag]', '', $string);
		$string = preg_replace('/\[peepso_tag(?:.*?)\]/', '', $string);
		return $string;
	}
}

PeepSoTags::get_instance();

// EOF
