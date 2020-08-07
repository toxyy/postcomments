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
 * @package acp
 */
class post_comments_module
{
        var $u_action;
        var $new_config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
        
	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;
        
        public function __construct()
	{
		global $phpbb_container;
                
		$this->db            = $phpbb_container->get('dbal.conn');
		$this->template      = $phpbb_container->get('template');
		$this->user          = $phpbb_container->get('user');
		$this->root_path     = $phpbb_container->getParameter('core.root_path');
		$this->php_ext       = $phpbb_container->getParameter('core.php_ext');
	}
        
        public function main($id, $mode)
        {
                global $db, $user, $auth, $template;
                global $config, $phpbb_admin_path;
                
                $this->user->add_lang_ext('toxyy/postcomments', 'acp_post_comments');

                $this->tpl_name = 'acp_post_comments';
                $this->page_title = $this->user->lang('PC_TITLE_ACP');

                switch($mode)
                {
                        default:
                                $this->page_title = 'PC_TITLE_ACP';
                                $this->tpl_name = 'acp_post_comments';

                                $forum_list = $this->get_forum_list();
                                foreach ($forum_list as $row)
                                {
                                        $this->template->assign_block_vars('forums', array(
                                                'FORUM_NAME'            => $row['forum_name'],
                                                'FORUM_ID'              => $row['forum_id'],
                                                'U_ADVANCED'		=> "{$this->u_action}&amp;action=advanced&amp;f=" . $row['forum_id'],
                                                'U_FORUM'               => append_sid("{$this->root_path}viewforum.{$this->php_ext}", 'f=' . $row['forum_id']),
                                        ));
                                }
                                break;
                }

        }
        
        	/**
	 * Get forums list
	 *
	 * @access protected
	 * @return array forum data rows
	 */
	protected function get_forum_list()
	{
		$sql = 'SELECT forum_id, forum_name
			FROM ' . FORUMS_TABLE . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		$forum_list = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $forum_list;
	}
}