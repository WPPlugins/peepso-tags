<?php
require_once(PeepSo::get_plugin_dir() . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'install.php');
/*
 * Performs installation process
 * @package PeepSoTags
 * @author PeepSo
 */
class PeepSoTagsInstall extends PeepSoInstall
{
	/*
	 * called on plugin activation; performs all installation tasks
	 */
	public function plugin_activation( $is_core = FALSE )
	{
		parent::plugin_activation($is_core);
		return (TRUE);
	}

	public function get_email_contents()
	{
		$emails = array(
			'email_tagged' => "Hello {userfirstname},

{fromfirstname} tagged you in a post!

You can view the post here:
{permalink}

Thank you.",

		);

		return $emails;
	}
}
