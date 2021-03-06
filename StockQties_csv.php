<?php

include('includes/session.inc');
$Title = _('Produce Stock Quantities CSV');
include('includes/header.inc');

echo '<p class="page_title_text noPrint" ><img src="' . $RootPath . '/css/' . $Theme . '/images/inventory.png" title="' . _('Inventory') . '" alt="" /><b>' . $Title . '</b></p>';

function stripcomma($str) { //because we're using comma as a delimiter
	return str_replace(',', '', $str);
}

echo '<div class="centre">' . _('Making a comma separated values file of the current stock quantities') . '</div>';

$ErrMsg = _('The SQL to get the stock quantities failed with the message');

$SQL = "SELECT stockid,
				SUM(quantity)
			FROM locstock
			INNER JOIN locationusers
				ON locationusers.loccode=locstock.loccode
				AND locationusers.userid='" .  $_SESSION['UserID'] . "'
				AND locationusers.canview=1
				GROUP BY stockid
				HAVING SUM(quantity)<>0";
$Result = DB_query($SQL, $ErrMsg);

if (!file_exists($_SESSION['reports_dir'])) {
	$Result = mkdir('./' . $_SESSION['reports_dir']);
}

$FileName = $_SESSION['reports_dir'] . '/StockQties.csv';

$fp = fopen($FileName, 'w');

if ($fp == FALSE) {

	prnMsg(_('Could not open or create the file under') . ' ' . $_SESSION['reports_dir'] . '/StockQties.csv', 'error');
	include('includes/footer.inc');
	exit;
}

while ($MyRow = DB_fetch_row($Result)) {
	$line = stripcomma($MyRow[0]) . ', ' . stripcomma($MyRow[1]);
	fputs($fp, "\xEF\xBB\xBF" . $line . "\n");
}

fclose($fp);

echo '<br /><div class="centre"><a href="' . $RootPath . '/' . $_SESSION['reports_dir'] . '/StockQties.csv ">' . _('click here') . '</a> ' . _('to view the file') . '</div>';

include('includes/footer.inc');

?>