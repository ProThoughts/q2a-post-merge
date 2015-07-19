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

qa_register_plugin_module('module', 'qa-module-admin.php', 'qa_merge_admin', 'Merge Admin');

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
		noah_pm_merge($from, $to);
		return true;
	}
}

function noah_pm_merge($postIdFrom, $postIdTo) {
	require_once QA_INCLUDE_DIR . 'db/votes.php';
	require_once QA_INCLUDE_DIR . 'app/posts.php';

	$followPosts = qa_post_get_question_commentsfollows($postIdFrom); // Could be questions or comments
	// Remove any questions fetched so that there are only answers and comments
	foreach ($followPosts as $followPostId => $followPost) {
		if ($followPost['basetype'] === 'Q') {
			unset($followPosts[$followPostId]);
		}
	}

	$answers = qa_post_get_question_answers($postIdFrom);

	$children = array_merge($answers, $followPosts);  // Contains answers to question, comments to question and comments to answers

	// Fetch questions
	$questions = qa_db_single_select(qa_db_posts_selectspec(null, array($postIdFrom, $postIdTo), true));
	$questionFrom = $questions[$postIdFrom];
	$questionTo = $questions[$postIdTo];

	// Unindex all children
	foreach ($children as $child) {
		qa_post_unindex($child['postid']);
	}

	// Remove selected answer and updates points of the owner of the answer
	$selectedAnswerId = $questionFrom['selchildid'];
	if (isset($children[$selectedAnswerId])) {
		qa_db_post_set_selchildid($postIdFrom, null, qa_get_logged_in_userid(), qa_remote_ip_address());
		qa_db_points_update_ifuser($child['userid'], 'aselects');
		qa_db_unselqcount_update();
	}

	// Change parents of all children that are child of the original question (excluding comments of answers)
	foreach ($children as $child) {
		if ($child['parentid'] == $postIdFrom) {
			qa_db_post_set_parent($child['postid'], $postIdTo);
		}
	}

	// Recalculate data for new question and the cache
	qa_db_post_acount_update($postIdTo);
	qa_db_hotness_update($postIdTo);
	qa_db_unaqcount_update();
	qa_db_unupaqcount_update();

	// Reindex only if all posts are visible and not queued for moderation
	if ($questionTo['type'] === 'Q') {
		foreach ($children as $child) {
			if ($child['type'] === 'A' || $child['type'] === 'C') {
				qa_post_index(
					$child['postid'], $child['type'], $postIdTo, $child['parentid'], null, $child['content'], $child['format'], qa_viewer_text($child['content'], $child['format']), null, $child['categoryid']
				);
			}
		}
	}

	// Delete the original post
	qa_post_delete($postIdFrom);

	// Add the deleted post to the ^postmeta table
	qa_db_query_sub(
		'INSERT INTO ^postmeta (post_id, meta_key, meta_value) ' .
		'VALUES (#, $, #)', $postIdFrom, 'merged_with', $postIdTo
	);
}

/*
	Omit PHP closing tag to help avoid accidental output
*/
