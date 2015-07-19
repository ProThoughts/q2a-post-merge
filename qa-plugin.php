<?php

/*
  Plugin Name: Post Merge
  Plugin URI: https://github.com/pupi1985/q2a-post-merge
  Plugin Description: Provides question posts merging capabilities
  Plugin Version: 0.3.2
  Plugin Date: 2015-05-25
  Plugin Author: NoahY (Extended by pupi1985)
  Plugin Author URI: http://question2answer.org/qa/user/pupi1985
  Plugin License: GPLv3
  Plugin Update Check URI: https://raw.githubusercontent.com/pupi1985/q2a-post-merge/master/qa-plugin.php
  Plugin Minimum Question2Answer Version: 1.4
 */

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

qa_register_plugin_layer('qa-merge-layer.php', 'Merge Layer');

qa_register_plugin_module('module', 'qa-php-widget.php', 'qa_merge_admin', 'Merge Admin');

function noah_pm_merge_do_merge() {
	qa_opt('merge_question_merged', qa_post_text('merge_question_merged'));

	$from = (int) qa_post_text('merge_from');
	$to = (int) qa_post_text('merge_to');

	$titles = qa_db_read_all_assoc(
		qa_db_query_sub(
			'SELECT postid,title,acount FROM ^posts ' .
			'WHERE postid IN (#, #)', $from, $to
		)
	);
	if (count($titles) != 2) {
		$error1 = null;
		$error2 = null;
		if (empty($titles)) {
			$error1 = 'Post not found.';
			$error2 = $error1;
		} else if (isset($titles[0]['postid']) && $titles[0]['postid'] == $from) {
			$error2 = 'Post not found.';
		} else if (isset($titles[0]['postid']) && $titles[0]['postid'] == $to) {
			$error1 = 'Post not found.';
		} else {
			$error1 = 'unknown error.';
		}
		return array($error1, $error2);
	} else {
		$acount = (int) $titles[0]['acount'] + (int) $titles[1]['acount'];

		qa_db_query_sub(
				"UPDATE ^posts SET parentid=# WHERE parentid=#", $to, $from
		);

		qa_db_query_sub(
				"UPDATE ^posts SET acount=# WHERE postid=#", $acount, $to
		);

		qa_db_query_sub(
				"INSERT INTO ^postmeta (post_id,meta_key,meta_value) VALUES (#,'merged_with',#)", $from, $to
		);

		require_once QA_INCLUDE_DIR . 'qa-app-posts.php';
		qa_post_delete($from);
		return true;
	}
}

/*
	Omit PHP closing tag to help avoid accidental output
*/
