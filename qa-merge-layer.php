<?php

class qa_html_theme_layer extends qa_html_theme_base {

	// theme replacement functions

	function doctype() {
		if (@$this->content['error'] == qa_lang_html('main/page_not_found') && preg_match('/^[0-9]+\//', $this->request) !== false) {
			$pid = preg_replace('/\/.*/', '', $this->request);
			$merged = qa_db_read_one_assoc(
				qa_db_query_sub(
					'SELECT p.postid, p.title FROM qa_posts p ' .
					'JOIN qa_postmeta pm ON p.postid = pm.meta_value ' .
					'WHERE pm.post_id = # AND pm.meta_key = $',
					$pid, 'merged_with'
				), true
			);
			if ($merged) {
				qa_redirect(qa_q_request($merged['postid'], $merged['title']), array('merged' => $pid));
			}
		} else if (qa_get('merged')) {
			$this->content['error'] = str_replace('^post', qa_get('merged'), qa_opt('merge_question_merged'));
		} else if (qa_post_text('merge_from') && qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) {
			$merged = noah_pm_merge_do_merge();
			if ($merged === true) {
				qa_redirect(qa_q_request(qa_post_text('merge_to'), null), array('merged' => qa_post_text('merge_from')));
			} else {
				$this->content['error'] = "Error merging posts.";
			}
		}

		if (qa_post_text('ajax_merge_get_from')) {
			return;
		}
		parent::doctype();
	}

	function getTitleFromResultsAndId($postsResults, $id) {
		foreach ($postsResults as $post) {
			if ($post['postid'] == $id) {
				return $post['title'];
			}
		}
		return '';
	}

	function mergingInfoAjaxCallHandler() {
		$idFrom = qa_post_text('ajax_merge_get_from');
		$idTo = qa_post_text('ajax_merge_get_to');
		if (isset($idFrom, $idTo)) {
			$idFrom = (int) $idFrom;
			$idTo = (int) $idTo;
			$posts = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT postid,title FROM ^posts ' .
				'WHERE postid IN (#, #) AND type LIKE $',
				$idFrom, $idTo , 'Q%'
			));
			$titleFrom = $this->getTitleFromResultsAndId($posts, $idFrom);
			$titleTo = $this->getTitleFromResultsAndId($posts, $idTo);
			$result = array(
				'from' => $titleFrom,
				'to' => $titleTo,
				'from_url' => qa_q_path_html($idFrom, $titleFrom),
				'to_url' => qa_q_path_html($idTo, $titleTo)
			);
			echo json_encode($result);
			return true; // AJAX call handled
		}
		return false; // AJAX call not handled
	}

	function html() {
		if ($this->mergingInfoAjaxCallHandler()) {  // AJAX call handled
			return;
		}
		parent::html();
	}

	function head_custom() {
		if ($this->template == 'admin') {
			$this->output("
	<script>
	function noah_pm_removeError(element) {
		element.closest('form').find('.qa-form-tall-error').closest('tr').remove();
	}
	function mergePluginGetPosts() {
		noah_pm_removeError($('#merge_from_out'));
		noah_pm_removeError($('#merge_to_out'));

		var from=jQuery('#merge_from').val();
		var to=jQuery('#merge_to').val();

		var dataString = 'ajax_merge_get_from='+from+'&ajax_merge_get_to='+to;
		jQuery.ajax({
		type: 'POST',
		url: '" . qa_self_html() . "',
		data: dataString,
		dataType: 'json',
		success: function(json) {
			jQuery('#merge_from_out').html('Merging from: <a href=\"'+json.from_url+'\">'+json.from+'</a>');
			jQuery('#merge_to_out').html('To: <a href=\"'+json.to_url+'\">'+json.to+'</a>');
		}
		});
		return false;
	}
	</script>");
		}
		parent::head_custom();
	}

	function q_view_clear() {

		// call default method output
		parent::q_view_clear();

		// return if not admin!
		if (qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN) {
			return;
		}

		// check if question is duplicate
		$closed = (@$this->content['q_view']['raw']['closedbyid'] !== null);
		if ($closed) {
			// check if duplicate
			$duplicate = qa_db_read_one_value(qa_db_query_sub('SELECT postid FROM `^posts`
																		WHERE `postid` = #
																		AND `type` = "Q"
																		;', $this->content['q_view']['raw']['closedbyid']), true);
			if ($duplicate) {
				$this->output('<div id="mergeDup" style="margin:10px 0 0 120px;padding:5px 10px;"><h3>Merge Duplicate:</h3>');

				// form output
				$this->output('
<FORM METHOD="POST">
<TABLE>
	<TR>
		<TD CLASS="qa-form-tall-label">
			From: &nbsp;
			<INPUT NAME="merge_from" id="merge_from" TYPE="text" VALUE="' . $this->content['q_view']['raw']['postid'] . '" CLASS="qa-form-tall-number">
			&nbsp; To: &nbsp;
			<INPUT NAME="merge_to" id="merge_to" TYPE="text" VALUE="' . $this->content['q_view']['raw']['closedbyid'] . '" CLASS="qa-form-tall-number">
		</TD>
	</TR>
	<TR>
		<TD CLASS="qa-form-tall-label">
		Text to show when redirecting from merged question:
		</TD>
	</TR>
	<TR>
		<TD CLASS="qa-form-tall-label">
		<INPUT NAME="merge_question_merged" id="merge_question_merged" TYPE="text" VALUE="' . qa_opt('merge_question_merged') . '" CLASS="qa-form-tall-text">
		</TD>
	</TR>
	<TR>
		<TD style="text-align:right;">
			<INPUT NAME="merge_question_process" VALUE="Merge" TITLE="" TYPE="submit" CLASS="qa-form-tall-button qa-form-tall-button-0">
		</TD>

	</TR>

</TABLE>
</FORM>');
				$this->output('</div>');
			}
		}
	}

}
