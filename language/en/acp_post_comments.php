<?php
/**
*
* Precise Similar Topics [English]
*
* @copyright (c) 2013 Matt Friedman
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'PC_TITLE_ACP'		=> 'Post Comments',
	'PC_EXPLAIN'		=> 'Post Comments allows you to comment on posts and comments, like reddit or facebook.',
	'PC_LEGEND1'		=> 'General settings',
	'PC_ENABLE'		=> 'Enable Post Comments',
	'PC_LEGEND2'		=> 'Default settings',
	'PC_MAX_DEPTH'		=> 'Absolute maximum comment depth',
	'PC_MAX_DEPTH_EXPLAIN'	=> 'Control the number of comment depth layers.<br>1 = can\'t comment on comments,<br>2 = can comment on comments and no further, etc.',
	'PC_MAX_SHOWN'          => 'Search period',
	'PC_MAX_SHOWN_EXPLAIN'	=> 'This option allows you to configure the Similar Topics search period. For example, if set to “5 days” the system will only show similar topics from within the last 5 days. The default is 1 year.',
	'PC_YEARS'		=> 'Years',
	'PC_MONTHS'		=> 'Months',
	'PC_WEEKS'		=> 'Weeks',
	'PC_DAYS'		=> 'Days',
	'PC_CACHE'		=> 'Similar Topics cache length',
	'PC_CACHE_EXPLAIN'	=> 'Cached similar topics will expire after this time, in seconds. Set to 0 if you want to disable the similar topics cache.',
	'PC_SENSE'		=> 'Search sensitivity',
	'PC_SENSE_EXPLAIN'	=> 'Set the search sensitivity to a value between 1 and 10. Use a lower number if you are not seeing any similar topics. Recommended settings: For “phpbb_topics” database tables using InnoDB use 1; for MyISAM use 5.',
	'PC_LEGEND3'		=> 'Forum settings',
	'PC_ENABLE_LIST'	=> 'Enable',
	'PC_NOSHOW_TITLE'	=> 'Do not display similar topics in',
	'PC_IGNORE_SEARCH'	=> 'Do Not Search In',
	'PC_IGNORE_TITLE'	=> 'Do not search for similar topics in',
	'PC_STANDARD'		=> 'Standard',
	'PC_ADVANCED'		=> 'Advanced',
	'PC_ADVANCED_TITLE'     => 'Click to set up advanced similar topic settings for',
	'PC_ADVANCED_EXP'	=> 'Here you can select specific forums to pull similar topics from. Only similar topics found in the forums you select here will be displayed in <strong>%s</strong>.<br /><br />Do not select any forums if you want similar topics from all searchable forums to be displayed in this forum.<br /><br />Select multiple forums by holding <samp>CTRL</samp> (or <samp>&#8984;CMD</samp> on Mac) and clicking.',
	'PC_ADVANCED_FORUM'     => 'Advanced forum settings',
	'PC_DESELECT_ALL'	=> 'Deselect all',
	'PC_LEGEND4'		=> 'Optional settings',
	'PC_WORDS'		=> 'Special words to ignore',
	'PC_WORDS_EXPLAIN'	=> 'Add special words unique to your forum that should be ignored when finding similar topics. (Note: Words that are currently regarded as common in your language are already ignored by default.) Separate each word with a space. Case insensitive. Max. 255 characters.',
	'PC_SAVED'		=> 'Similar Topics settings updated',
	'PC_FORUM_INFO'         => '“Do Not Display In”: Will not show similar topics in the selected forums.<br />“Do Not Search In” : Will not search for similar topics in the selected forums.',
	'PC_NO_COMPAT'		=> 'Similar Topics is not compatible with your forum. Similar Topics will only run on a MySQL or PostgreSQL database.',
	'PC_ERR_CONFIG'         => 'Too many forums were marked in the list of forums. Please try again with a smaller selection.',
));