<?php
/**
 * phpBB Extension - toxyy Post Comments
 *
 * @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
 * @license       GNU General Public License, version 2 (GPL-2.0)
 */

namespace toxyy\postcomments\event;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\request\request;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\event\data as event;

global $table_prefix;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var string */
	protected $table_prefix;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config              $config
	 * @param \phpbb\user                       $user
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\auth\auth                  $auth
	 * @param \phpbb\request\request            $request
	 * @param string                            $root_path
	 * @param string                            $php_ext
	 *
	 */
	public function __construct(
		config $config,
		user $user,
		driver_interface $db,
		auth $auth,
		request $request,
		$table_prefix,
		$root_path,
		$php_ext
	)
	{
		$this->config = $config;
		$this->user = $user;
		$this->db = $db;
		$this->auth = $auth;
		$this->request = $request;
		$this->table_prefix = $table_prefix;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	static public function getSubscribedEvents()
	{
		return [
			'core.user_setup'                            => 'core_user_setup',
			'core.viewforum_modify_topicrow'             => 'viewforum_modify_topicrow',
			'core.viewtopic_assign_template_vars_before' => 'viewtopic_assign_template_vars_before',
			'core.viewtopic_get_post_data'               => 'viewtopic_get_post_data',
			'core.viewtopic_post_rowset_data'            => 'viewtopic_post_rowset_data',
			'core.viewtopic_modify_post_row'             => 'viewtopic_modify_post_row',
			'core.modify_posting_parameters'             => 'modify_posting_parameters',
			'core.posting_modify_template_vars'          => 'posting_modify_template_vars',
			'core.modify_submit_post_data'               => 'modify_submit_post_data',
			'core.submit_post_modify_sql_data'           => 'submit_post_modify_sql_data',
			'core.submit_post_end'                       => 'submit_post_end',
			'core.handle_post_delete_conditions'         => 'handle_post_delete_conditions',
			'core.delete_post_after'                     => 'delete_post_after',
		];
	}

	public function core_user_setup(event $event)
	{
		$lang_set_ext = $event['lang_set_ext'];

		$lang_set_ext[] = [
			'ext_name' => 'toxyy/postcomments',
			'lang_set' => 'common',
		];

		$event['lang_set_ext'] = $lang_set_ext;
	}

	// had to edit viewforum.php line 976 to get this done, PR already made
	public function viewforum_modify_topicrow(event $event)
	{       // if the user doesn't have mod/admin, then we need to adjust postcount and pagination
		if (!$this->auth->acl_get('m_', $topic_row['FORUM_ID']))
		{
			$topic_row = $event['topic_row'];
			$postcount_query = "SELECT SUM(t.topic_posts_approved + t.topic_posts_softdeleted - 1) AS post_total
								FROM  " . TOPICS_TABLE . " AS t
								WHERE t.topic_id = $topic_row[TOPIC_ID]";

			$result = array();
			$result = $this->db->sql_query($postcount_query);

			$post_count = $topic_row['REPLIES'];
			while ($row = $this->db->sql_fetchrow($result))
			{
				$post_count = $row['post_total'];
			}
			unset($result);

			$topic_row['REPLIES'] = $post_count;

			$event['topic_row'] = $topic_row;
		}
	}

	// if post id in url is a comment, redirect to parent ancestor with depth 0
	// if this isn't done, we go to the wrong page.  Not sure of a better way to handle this as of now
	// (TODO) fix ordering & add posts per page compat
	// (TODO) if comment is > the # per row depth, it goes to top of page currently, need to figure out what to do in those cases - same as above, need to figure out php and ajax
	public function viewtopic_assign_template_vars_before(event $event)
	{
		$start = $event['start'];
		$post_id = $event['post_id'];
		$forum_id = $event['forum_id'];
		$topic_id = $event['topic_id'];
		$total_posts = $event['total_posts'];
		$closure_table = $this->table_prefix . 'posts_kinship';
		if ($this->request->variable('context', '') . length <= 0)
		{        // get ancestor of depth = 0 post_id, if this post_id is a comment
			$checkcomment_query = "SELECT p.post_id, p.parent_id
									FROM " . POSTS_TABLE . " AS p
										JOIN $closure_table AS c ON p.post_id = c.ancestor_id
									WHERE c.child_id = $post_id
										AND c.kin_depth != 0 AND p.post_depth = 0";

			$result = array();
			$result = $this->db->sql_query($checkcomment_query);

			$result_exists = 0;
			while ($row = $this->db->sql_fetchrow($result))
			{
				$post_id = $row['post_id'];
				$result_exists++;
			}
			unset($result);

			if ($result_exists > 0)
			{
				// get ordered position of parent post_id in topic if url has &p=post_id
				// (TODO) fix ordering & add posts per page compatability
				$position_query = "SELECT row_num FROM 
        					    	(   SELECT p.post_id,
											(@rownum := @rownum + 1) AS row_num
										FROM phpbb_posts as p
										JOIN (SELECT @rownum := 0) AS tmp
										WHERE p.topic_id = $topic_id
											AND p.post_depth = 0
										ORDER BY p.post_time ASC
									) AS tmp2
									WHERE post_id = $post_id";

				$result = array();
				$result = $this->db->sql_query($position_query);

				$position = 0;
				while ($row = $this->db->sql_fetchrow($result))
				{
					$position = $row['row_num'];
				}
				unset($result);

				// round to nearest 10, ie 9 => 0, 15 => 10, 62 => 60, et cetera
				$start = floor(($position - 1) / 10) * 10;
				//redirect(append_sid("{$this->root_path}viewtopic.$this->php_ext", "f=$forum_id&amp;t=$topic_id&amp;p=$post_id"));
			}

			$this->db->sql_freeresult($result);

			// fixes problems caused by allowing normal members to be able to see deleted posts
			// (TODO) - add compatability for viewforum, possibly other places
			if (!$this->auth->acl_get('m_edit', $event['row']['forum_id']))
			{
				$deletecount_query = "SELECT SUM(counter) as delete_count FROM 
										(   SELECT (p.post_visibility - 1) AS counter
											FROM " . POSTS_TABLE . " as p
											WHERE p.topic_id = $topic_id
										) AS tmp2";

				$result = array();
				$result = $this->db->sql_query($deletecount_query);

				$delete_count = 0;
				while ($row = $this->db->sql_fetchrow($result))
				{
					$delete_count = $row['delete_count'];
				}
				unset($result);

				$total_posts += $delete_count;

				$start = $this->request->variable('start', 0) ? $this->request->variable('start', 0) : $start;
			}
		}
		else
		{
			$start = $total_posts = 1;
		}

		$event['start'] = $start;
		$event['total_posts'] = $total_posts;
	}

	/**
	 * EDITED viewtopic.php LINE 1146 - need to make an event :( - event made! coming in 3.2.4-RC1
	 * retrieves and orders comments to depth of n
	 * (TODO) comments per depth level are also changeable
	 * (TODO) allows max comment depth to be set (per forum maybe..?)
	 * can order by name desc, or any numeric value asc/desc
	 * (TODO) can order by name desc
	 * update sql_ary with the new post ids for postrow to update
	 * update post_list with list of new ordered post ids
	 * still retrieve X posts per topic page, comments not included in the count
	 * (TODO) have last post link go to comment - half done - need to make show more button for php and ajax
	 */
	public function viewtopic_get_post_data(event $event)
	{       // max depth of comment tree, and their sql ordering parameter
		$max_depth = 5;
		// keep "ORDER BY kin_depth" in this, only change what's before and after for ordering
		$tree_order = 'p.post_time ORDER BY kin_depth DESC';

		$sql_ary = $event['sql_ary'];
		$post_list = $event['post_list'];
		$topic_data = $event['topic_data'];
		$closure_table = $this->table_prefix . 'posts_kinship';

		$context_page = $this->request->variable('context', '');
		// get post id incase we're on a context page
		$postlist_sql = $context_page . length > 0 ?
			$this->request->variable('p', '') . length > 0 ?
				$this->request->variable('p', '')
				// url needs &p=context_post_id, so default case if we dont have it
				: $post_list
			// default case
			: $post_list;

		/**
		 * gets a list of post and comment ids from the original post list
		 * creates a materialized path from $tree_order
		 *
		 * @super keeps track of current parent id, @child the number of children per parent
		 * tmp and tmp2 aren't used, they just declare @super and @count without using set
		 */
		$thread_query = "SELECT post_id, row_count, depth, post_comments FROM 
                			(   SELECT IF(@super = child.parent_id, @count := @count + 1, @count := 1) AS row_count,
								@super := child.parent_id,
								child.post_depth AS depth,
								child.post_comments,
								child.post_id,
								kin_depth,
								(   SELECT GROUP_CONCAT($tree_order)
									FROM $closure_table
									LEFT JOIN " . POSTS_TABLE . " AS p ON ancestor_id = p.post_id
									WHERE child_id = child.post_id
								) AS path
								FROM " . POSTS_TABLE . " AS child
								LEFT JOIN $closure_table ON child.post_id = child_id
								JOIN (SELECT @count := 1) AS tmp
								JOIN (SELECT @super := -1) AS tmp2
								WHERE ancestor_id IN 
								(   SELECT post_id
									FROM " . POSTS_TABLE . "
									WHERE {$this->db->sql_in_set('post_id', $postlist_sql)}
									AND post_depth = " . (($context_page . length > 0) ? ($context_page - 1) : '0') . "
								)
									AND kin_depth <= " . (($context_page . length > 0) ? ($context_page - 1) + $max_depth : $max_depth) . "
								ORDER BY path
						) AS tree
						WHERE IF(depth = 0, 1 = 1, row_count IN (1,2,3))";
		print_r($thread_query);
		$result = $thread_post_list = $last_comment_list = array();

		$result = $this->db->sql_query($thread_query);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$thread_post_list['depth'][] = $row['depth'];
			$thread_post_list['post_id'][] = $row['post_id'];
			$thread_post_list['row_count'][] = $row['row_count'];
			$thread_post_list['post_comments'][] = $row['post_comments'];
		}

		$this->db->sql_freeresult($result);
		unset($result);

		// reoder posts in template
		$post_list = $thread_post_list['post_id'];

		// update sql_ary to retrieve post data for comment ids too
		$sql_ary['WHERE'] = $this->db->sql_in_set('p.post_id', $post_list) . '
                        AND u.user_id = p.poster_id';

		$limit = count($post_list);

		// gets all last comments in their respected nest,
		// adds them to an array (need this for show/hide div css)
		if ($limit > 1)
		{   // placeholder to check if we've seen the last post of the thread yet, used only one time
			$last_post = 0;
			for ($x = 0; $x < $limit; $x++)
			{   // check only posts with comments as we're getting the last comment list
				if ($thread_post_list['post_comments'][$x] > 0)
				{   // this comment branch's depth
					$current_branch_depth = $thread_post_list['depth'][$x] + 1;
					// run another loop until end of posts, or until its proceeding conditions breaks the loop
					for ($y = ($x + 1); $y < $limit; $y++)
					{   // make sure we're only checking comments at the current branch's depth
						$branch_checker = $thread_post_list['depth'][$y] == $current_branch_depth;
						$this_depth = $thread_post_list['depth'][$y];
						$next_index = $y + 1;
						// we aren't at the last post on the page, right?
						if ($branch_checker && ($next_index < $limit))
						{
							$next_depth = $thread_post_list['depth'][$next_index];
							switch (true)
							{   // still in current branch or branch inside this one, carry on
								case $next_depth >= $this_depth:
								break;
								// if next depth is < this depth, then we're at the last comment in the current chain this - next many times, adjusting for context
								case $next_depth < $this_depth:
									for ($i = 0; $i < ($this_depth - $next_depth); $i++)
										array_push($last_comment_list, $thread_post_list['post_id'][$y]);
									// break from switch and $y loop, need to find more comment branches
								break 2;
							}
						}
						elseif ($branch_checker && ($last_post == 0) && ($next_index == $limit))
						{   // we're at least depth i deep, and we dont count depth of 0. if we're on a context page, start loop at context - 1 deep
							for ($i = ($context_page . length > 0) ? ($context_page - 1) : 0; $i < $this_depth; $i++)
								array_push($last_comment_list, $thread_post_list['post_id'][$y]);

							$last_post += 1;
							// break from $y, sibling comments might have their own branches
							break;
						}
					}
				}
			}
		}

		$topic_data = array_merge($topic_data, array(
			'last_comment_list' => $last_comment_list,
		));

		$event['sql_ary'] = $sql_ary;
		$event['post_list'] = $post_list;
		$event['topic_data'] = $topic_data;
	}

	// get parent id in postrow sql query
	public function viewtopic_post_rowset_data(event $event)
	{
		$parent_id = $event['row']['parent_id'];
		$post_depth = $event['row']['post_depth'];
		$post_comments = $event['row']['post_comments'];
		$rowset = $event['rowset_data'];

		$rowset = array_merge($rowset, array(
			'parent_id'     => $parent_id,
			'post_depth'    => $post_depth,
			'post_comments' => $post_comments,
		));

		$event['rowset_data'] = $rowset;
	}

	// allow template to use parent id and post_depth, create comment link
	// show deleted posts to members
	public function viewtopic_modify_post_row(event $event)
	{
		// from quote allowed code in viewtopic.php 1880 - 1885
		$comment_allowed = $this->auth->acl_get('m_edit', $event['row']['forum_id']) || ($event['topic_data']['topic_status'] != ITEM_LOCKED &&
				($this->user->data['user_id'] == ANONYMOUS || $this->auth->acl_get('f_reply', $event['row']['forum_id']))
			);
		$comment_allowed = ($comment_allowed && $event['row']['post_visibility'] == ITEM_APPROVED) ? true : false;

		$topic_data = $event['topic_data'];
		$post_id = $event['row']['post_id'];
		$topic_id = $topic_data['topic_id'];
		$forum_id = $event['row']['forum_id'];
		$parent_id = $event['row']['parent_id'];
		$post_depth = $event['row']['post_depth'];
		$post_comments = $event['row']['post_comments'];
		$last_comment_list = $topic_data['last_comment_list'];
		$post_row = $event['post_row'];

		// count number of times a post is the last comment
		$last_comment_count = count($last_comment_list);
		$count = 0;
		if (in_array($post_id, $last_comment_list))
		{
			for ($i = 0; $i < $last_comment_count; $i++)
			{
				$count = ($post_id == $last_comment_list[$i]) ? $count + 1 : $count;
			}
		}

		$is_staff = !empty($post_row['U_MCP_APPROVE']);

		// delete info from the deleted post hidden div so sneaky members cant find out who it was
		if (!empty($post_row['L_POST_DISPLAY']) && !$is_staff)
		{
			$post_row['L_POST_DISPLAY'] = $post_row['POST_AUTHOR'] = $post_row['POST_SUBJECT'] =
			$post_row['S_FRIEND'] = $post_row['MESSAGE'] = $post_row['SIGNATURE'] =
			$post_row['EDITED_MESSAGE'] = $post_row['EDIT_REASON'] = $post_row['DELETED_MESSAGE'] =
			$post_row['DELETE_REASON'] = $post_row['BUMPED_MESSAGE'] = $post_row['ONLINE_IMG'] =
			$post_row['S_ONLINE'] = $post_row['U_PM'] = $post_row['U_EMAIL'] =
			$post_row['U_JABBER'] = $post_row['U_MINI_POST'] = $post_row['U_NOTES'] =
			$post_row['POST_AUTHOR_COLOUR'] = $post_row['POST_AUTHOR_FULL'] = $post_row['POST_DATE'] = '';
			$post_row['L_POST_DELETED_MESSAGE'] = 'Deleted';
		}

		// fixes hidden fields
		if (1 == 1)
		{
			$qr_hidden_fields = array(
				'topic_cur_post_id' => (int) $topic_data['topic_last_post_id'],
				'lastclick'         => (int) time(),
				'parent_id'         => (int) $post_id,
				'topic_id'          => (int) $topic_id,
				'forum_id'          => (int) $forum_id,
			);

			$s_watching_topic = array(
				'link'         => '',
				'link_toggle'  => '',
				'title'        => '',
				'title_toggle' => '',
				'is_watching'  => false,
			);

			if ((int) $this->config['allow_topic_notify'])
				$notify_status = (isset($topic_data['notify_status'])) ? $topic_data['notify_status'] : null;

			$s_attach_sig = (int) $this->config['allow_sig'] && $this->user->optionget('attachsig') && $this->auth->acl_get('f_sigs', $forum_id) && $this->auth->acl_get('u_sig');
			$s_smilies = (int) $this->config['allow_smilies'] && $this->user->optionget('smilies') && $this->auth->acl_get('f_smilies', $forum_id);
			$s_bbcode = (int) $this->config['allow_bbcode'] && $this->user->optionget('bbcode') && $this->auth->acl_get('f_bbcode', $forum_id);
			$s_notify = (int) $this->config['allow_topic_notify'] && ($this->user->data['user_notify'] || $s_watching_topic['is_watching']);

			(!$s_bbcode) ? $qr_hidden_fields['disable_bbcode'] = 1 : true;
			(!$s_smilies) ? $qr_hidden_fields['disable_smilies'] = 1 : true;
			(!(int) $this->config['allow_post_links']) ? $qr_hidden_fields['disable_magic_url'] = 1 : true;
			($s_attach_sig) ? $qr_hidden_fields['attach_sig'] = 1 : true;
			($s_notify) ? $qr_hidden_fields['notify'] = 1 : true;
			($topic_data['topic_status'] == ITEM_LOCKED) ? $qr_hidden_fields['lock_topic'] = 1 : true;
		}

		// add quick reply per forum config
		$quickreply_bit = (1 == 1) ? 'qreply=1&' : '';
		$comment_link = append_sid("{$this->root_path}posting.$this->php_ext", "mode=comment&amp;{$quickreply_bit}c=1&amp;f={$event['row']['forum_id']}&amp;p={$event['row']['post_id']}");
		$context_page = $this->request->variable('context', '');
		// add qr hidden fields config
		$post_row_append = [
			'PARENT_ID'                => $parent_id,
			'POST_DEPTH'               => $post_depth,
			'POST_COMMENTS'            => $post_comments,
			'LAST_COMMENT'             => $count,
			'HIDDEN_CSS'               => ($is_staff) ? 0 : $post_row['S_POST_HIDDEN'],
			'U_COMMENT'                => ($comment_allowed) ? $comment_link : '',
			'U_COMMENT_CONTEXT'        => append_sid("{$this->root_path}viewtopic.$this->php_ext", "f={$event['row']['forum_id']}&amp;p={$event['row']['post_id']}&amp;context=" . ++$post_depth),
			'S_IS_CONTEXT'             => ($post_id == $this->request->variable('p', '')) ? $context_page : 0,
			'S_CONTEXT_DEPTH'          => $context_page,
			'QR_COMMENT_HIDDEN_FIELDS' => (1 == 1) ? build_hidden_fields($qr_hidden_fields) : '',
		];
		foreach ($post_row_append as $key => $value)
			$post_row[$key] = $value;

		$event['post_row'] = $post_row;
	}

	// mode=comment to quote for internal functions, quote also has post_id we use
	public function modify_posting_parameters(event $event)
	{
		$mode = $event['mode'];

		$mode = ($mode === 'comment') ? 'quote' : $mode;

		$event['mode'] = $mode;
	}

	/**
	 * gets the c variable from url to see if we're commenting or not, appends it to post action url
	 * clear quote in message body
	 */
	public function posting_modify_template_vars(event $event)
	{
		$data = $event->get_data();

		// keep messages if we're doing quick reply
		$data['page_data']['MESSAGE'] = ($this->request->variable('qreply', '') . length > 0) ? $data['page_data']['MESSAGE'] : '';
		$data['page_data']['S_POST_ACTION'] .= '&c=' . $this->request->variable('c', '') . '&amp;';

		$event->set_data($data);
	}

	// add variables to $data for use in submit_post_modify_sql_data
	public function modify_submit_post_data(event $event)
	{
		$data = $event['data'];

		$data = array_merge($data, array(
			'parent_id' => $data['post_id'],
		));

		$event['data'] = $data;
	}

	// add post_depth and , if we're really commenting, and add parent id to database
	public function submit_post_modify_sql_data(event $event)
	{
		$sql_data = $event['sql_data'];
		$data = $event['data'];

		// if commenting, set depth equal to 1 + parent's depth
		$post_depth = 0;
		if ($data['parent_id'] != 0)
		{
			$depth_query = "SELECT post_depth
                            FROM " . POSTS_TABLE . "
                            WHERE post_id = $data[parent_id]";

			$result = array();
			$result = $this->db->sql_query($depth_query);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$post_depth = 1 + $row['post_depth'];
			}

			$this->db->sql_freeresult($result);
		}

		$comment_mode = $this->request->variable('c', '');

		$sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
			'parent_id'  => ($comment_mode == 1) ? $data['parent_id'] : 0,
			'post_depth' => $post_depth,
		));

		$event['sql_data'] = $sql_data;
	}

	// add right amount rows into closure table for the post or
	public function submit_post_end(event $event)
	{
		$data = $event['data'];
		var_dump(json_encode($data));
		$closure_table = $this->table_prefix . 'posts_kinship';

		// closure table row for this post: (post_id, post_id, 0)
		$this_closure_row = "SELECT $data[post_id], $data[post_id], 0";

		/**
		 * inserts X amount of rows for the post into the closure table accounting for depth and parent_id
		 * if parent_id == 0 (just a normal post to the topic), only one row is inserted ($this_closure_row)
		 * if not, the rest after the colon is inserted, which includes $this_closure_row
		 * tmp is not used, just a necessary alias to get the query to work
		 */
		$closure_query = "INSERT INTO $closure_table (ancestor_id, child_id, kin_depth) " .
		$data['parent_id'] == 0 ? $this_closure_row : "
							SELECT * FROM 
							(   SELECT c.ancestor_id,
							  $data[post_id] AS child_id, 
							  c.kin_depth + 1 AS kin_depth
							  FROM $closure_table AS c
							  WHERE c.child_id = $data[parent_id]
							  UNION ALL $this_closure_row
							) AS tmp";

		$result = array();
		$result = $this->db->sql_query($closure_query);
		$this->db->sql_freeresult($result);
		unset($result);

		// set comment topic_id to 0 to maintain topic count and pagination
		// decrement topic_posts_approved to do the same
		// increment parent comment count
		if ($data['parent_id'] != 0)
		{
			/*$comment_topic_query = 'UPDATE ' . POSTS_TABLE . '
									SET topic_id = 0
									WHERE post_id = ' . $data['post_id'];

			$result = array();
			$result = $this->db->sql_query($comment_topic_query);
			$this->db->sql_freeresult($result);
			unset($result);
			*/
			$posts_approved_query = "UPDATE " . TOPICS_TABLE . "
                                     SET topic_posts_approved = topic_posts_approved - 1
                                     WHERE topic_id = $data[topic_id]";

			$result = array();
			$result = $this->db->sql_query($posts_approved_query);
			$this->db->sql_freeresult($result);
			unset($result);

			$parent_count_query = "UPDATE " . POSTS_TABLE . "
                                   SET post_comments = post_comments + 1
                                   WHERE post_id = $data[parent_id]";

			$result = array();
			$result = $this->db->sql_query($parent_count_query);
			$this->db->sql_freeresult($result);
		}
	}

	// adjust parent comment count and topic posts approved if deleted post is a comment
	public function handle_post_delete_conditions(event $event)
	{
		$post_id = $event['post_id'];
		$topic_id = $event['topic_id'];
		$post_data = $event['post_data'];
		$parent_id = $post_data['parent_id'];

		// for some reason comments run this event twice, this makes sure that on the second time we don't update the tables
		$select_from_closure = "SELECT parent_id FROM " . POSTS_TABLE . "
								WHERE post_id = $post_id";

		$result = $parent_checker = array();
		$result = $this->db->sql_query($select_from_closure);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$parent_checker = $row['parent_id'];
		}
		unset($result);

		if (($parent_id == $parent_checker) && $parent_id > 0)
		{       // decrement parent's comment count by 1
			$parent_count_query = "UPDATE " . POSTS_TABLE . "
                                   SET post_comments = post_comments - 1
                                   WHERE post_id = $parent_id";
			$result = array();
			$result = $this->db->sql_query($parent_count_query);
			unset($result);

			$posts_approved_query = "UPDATE " . TOPICS_TABLE . "
                                     SET topic_posts_approved = topic_posts_approved + 1
                                     WHERE topic_id = $topic_id";
			$result = array();
			$result = $this->db->sql_query($posts_approved_query);
			unset($result);
		}
	}

	// i feel like there's a better way to do this... using delete_post? using delete_posts?
	// adapted some from functions_posting.php if statemant at line 1406
	// delete nested posts from posts table, notifications table, and closure table
	// topics posted table too if that user doesn't have any more posts in the topic
	// (TODO) add notifications table path... what is it?
	public function delete_post_after(event $event)
	{
		$data = $event['data'];
		$is_soft = $event['is_soft'];
		$post_id = $event['post_id'];
		$topic_id = $event['topic_id'];
		$forum_id = $event['forum_id'];
		$next_post_id = $event['next_post_id'];
		$softdelete_reason = $event['softdelete_reason'];

		// only way to transfer info from the recursive call I made in this event
		if (strlen($softdelete_reason) > 10)
		{
			$delete_runner = substr($softdelete_reason, -10);

			// not sure how much use this is
			$softdelete_reason = substr($softdelete_reason, 0, -10);
		}

		if (!$is_soft && $delete_runner != "DO~NOT~RUN")
		{       // get post ids of every post that will be deleted except this post
			$select_from_closure = "SELECT child_id FROM phpbb_posts_kinship
									WHERE child_id IN
									(   SELECT child_id
										FROM phpbb_posts_kinship
										WHERE ancestor_id = $post_id
										AND child_id != $post_id
									)";

			$result = $post_list = array();
			$result = $this->db->sql_query($select_from_closure);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$post_list[] = $row['child_id'];
			}
			unset($result);

			$delete_from_closure = "DELETE FROM phpbb_posts_kinship
									WHERE child_id IN
									(   SELECT * FROM
										(   SELECT child_id
											FROM phpbb_posts_kinship
											WHERE ancestor_id = $post_id
										) AS tmp
									)";
			$result = array();
			$result = $this->db->sql_query($delete_from_closure);
			unset($result);

			// remove repeats, more efficient than array_unique
			$post_list = array_unique($post_list);

			$comment_count = count($post_list);

			// so the post we're deleting has comments...
			if ($comment_count > 0)
			{
				$post_data_query = "SELECT post_visibility, post_reported, post_time, poster_id, post_postcount
									FROM " . POSTS_TABLE . "
									WHERE post_id IN (" . implode(',', $post_list) . ")";

				$result = $post_data = array();
				$result = $this->db->sql_query($post_data_query);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$post_data['post_visibility'][] = $row['post_visibility'];
					$post_data['post_reported'][] = $row['post_reported'];
					$post_data['post_time'][] = $row['post_time'];
					$post_data['poster_id'][] = $row['poster_id'];
					$post_data['post_postcount'][] = $row['post_postcount'];
				}
				unset($result);

				// add the number of comments we're about to delete to topic_posts_approved since delete_post decrements it
				$posts_approved_query = "UPDATE " . TOPICS_TABLE . "
										SET topic_posts_approved = topic_posts_approved + $comment_count
										WHERE topic_id = $topic_id";
				$result = array();
				$result = $this->db->sql_query($posts_approved_query);
				unset($result);

				for ($i = 0; $i < $comment_count; $i++)
				{
					$data['post_visibility'] = $post_data['post_visibility'][$i];
					$data['post_reported'] = $post_data['post_reported'][$i];
					$data['post_time'] = $post_data['post_time'][$i];
					$data['poster_id'] = $post_data['poster_id'][$i];
					$data['post_postcount'] = $post_data['post_postcount'][$i];

					$next_post_id = delete_post($forum_id, $topic_id, $post_list[$i], $data, false, "~DO~NOT~RUN");
				}
				/*// get user id list from given post list, we use this a couple of times
				$select_users_from_posts = function($this_post_list)
				{
						$this_post_list_string = implode(',', $this_post_list);

						$select_user_query = 'SELECT poster_id FROM ' . POSTS_TABLE . '
												WHERE post_id IN (' . $this_post_list_string . ')';

						$result = $user_id_list = array();
						$result = $this->db->sql_query($select_user_query);

						while($row = $this->db->sql_fetchrow($result))
						{
								$user_id_list[] = $row['user_id'];
						}
						unset($result);

						// remove duplicate user ids and return
						return array_unique($user_id_list);
				};

				// let's get the comments' user id list
				$comment_user_ids = $select_users_from_posts($post_list);

				$delete_from_posts = 'DELETE FROM ' . POSTS_TABLE . '
										WHERE topic_id = ' . $topic_id . '
										AND post_id IN (' . implode(',', $post_list) . ')';

				$result = array();
				$result = $this->db->sql_query($delete_from_posts);
				unset($result);

				// after deleting, check to see if the users from the post ids we deleted still have posts in that topic
				$comment_check_user_ids = $select_users_from_posts($post_list);
				$deleted_user_ids = array_diff($comment_user_ids, $comment_check_user_ids);

				// oh so there were users who only had one post? we've got to remove them from the topics posted table now
				if($deleted_user_ids[0] !== NULL)
				{
						$delete_from_topics_posted = 'DELETE FROM ' . TOPICS_POSTED_TABLE . '
														WHERE topic_id = ' . $topic_id . '
														AND user_id IN (' . implode(',', $deleted_user_ids) . ')';

						$result = array();
						$result = $this->db->sql_query($delete_from_topics_posted);
						unset($result);
				}

				$delete_from_notifications = 'DELETE FROM phpbb_notifications
												WHERE item_parent_id = ' . $topic_id . '
												AND item_id IN (' . implode(',', $post_list) . ')';

				$result = array();
				$result = $this->db->sql_query($delete_from_notifications);
				unset($result);*/
			}
		}

		$event['data'] = $data;
		$event['next_post_id'] = $next_post_id;
		$event['softdelete_reason'] = $softdelete_reason;
	}
}