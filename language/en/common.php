<?php

/**
* phpBB Extension - toxyy Post Comments
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$test = 1==1;

$lang = array_merge($lang, [
	'TOPICLISTTEST'	=> $test,
]);