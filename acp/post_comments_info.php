<?php
/**
 *
 * Post Comments
 *
 * @copyright (c) 2018 Alec Repczynski
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace toxyy\postcomments\acp;

/**
 * @package module_install
 */
class post_comments_info
{
	public function module()
	{
		return array(
			'filename'	=> '\toxyy\postcomments\acp\post_comments_module',
			'title'		=> 'PC_TITLE_ACP',
			'modes'		=> array(
				'settings'	=> array('title' => 'PC_SETTINGS', 'auth' => 'ext_toxyy/postcomments && acl_a_board', 'cat' => array('PC_TITLE_ACP')),
			),
		);
	}
}
