<?php
/**
 *
 * Post Comments
 *
 * @copyright (c) 2018 Alec Repczynski
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace toxyy\postcomments\migrations;

class release_1_0_0_data extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['post_comments']);
	}

	public function update_data()
	{
		return array(
			// Add configs
			array('config.add', array('post_comments', '0')),
			array('config.add', array('post_comments_limit', '5')),
			array('config.add', array('post_comments_hide', '')),
			array('config.add', array('post_comments_ignore', '')),
			array('config.add', array('post_comments_type', 'y')),
			array('config.add', array('post_comments_time', '365')),
			array('config.add', array('post_comments_version', '1.0.0')),

			// Add ACP module
			array('module.add', array('acp', 'ACP_CAT_DOT_MODS', 'PC_TITLE_ACP')),
			array('module.add', array('acp', 'PC_TITLE_ACP',
				array(
					'module_basename'	=> '\toxyy\postcomments\acp\post_comments_module',
					'modes'			=> array('settings'),
				),
			)),
		);
	}
}