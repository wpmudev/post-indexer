function pi_loadsiteeditform() {
	tb_show(postindexer.siteedittitle, jQuery(this).attr('href'));
	return false;
}

function pi_sitesready() {
	jQuery('a.postindexersiteeditlink').click(pi_loadsiteeditform);
}

jQuery(document).ready(pi_sitesready);