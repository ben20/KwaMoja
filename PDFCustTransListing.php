<?php

include('includes/SQL_CommonFunctions.inc');
include('includes/session.inc');

$InputError = 0;
if (isset($_POST['Date']) and !is_date($_POST['Date'])) {
	$Msg = _('The date must be specified in the format') . ' ' . $_SESSION['DefaultDateFormat'];
	$InputError = 1;
	unset($_POST['Date']);
}

if (!isset($_POST['Date'])) {

	$Title = _('Customer Transaction Listing');
	/* Manual links before header.inc */
	$ViewTopic = 'ARReports';
	$BookMark = 'DailyTransactions';
	include('includes/header.inc');

	echo '<div class="centre">
			<p class="page_title_text noPrint" >
				<img src="' . $RootPath . '/css/' . $Theme . '/images/transactions.png" title="' . $Title . '" alt="' . $Title . '" />' . ' ' . _('Customer Transaction Listing') . '</p>';

	if ($InputError == 1) {
		prnMsg($Msg, 'error');
	}

	echo '<form onSubmit="return VerifyForm(this);" method="post" class="noPrint" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
	echo '<div><input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" /></div>';
	echo '<table class="selection" summary="' . _('Input criteria for report') . '">
	 		<tr>
				<td>' . _('Enter the date for which the transactions are to be listed') . ':</td>
				<td><input type="text" name="Date" required="required" minlength="1" maxlength="10" size="10" class="date" alt="' . $_SESSION['DefaultDateFormat'] . '" value="' . Date($_SESSION['DefaultDateFormat']) . '" /></td>
			</tr>';

	echo '<tr>
			<td>' . _('Transaction type') . '</td>
			<td><select required="required" minlength="1" name="TransType">
				<option value="10">' . _('Invoices') . '</option>
				<option value="11">' . _('Credit Notes') . '</option>
				<option value="12">' . _('Receipts') . '</option>';

	echo '</select></td></tr>
			</table>
			<div class="centre">
				<br />
				<input type="submit" name="Go" value="' . _('Create PDF') . '" />
			</div>
			</form>
			</div>';

	include('includes/footer.inc');
	exit;
} else {

	include('includes/ConnectDB.inc');
}

$SQL = "SELECT type,
			debtortrans.debtorno,
			transno,
			trandate,
			ovamount,
			ovgst,
			invtext,
			debtortrans.rate,
			decimalplaces
		FROM debtortrans INNER JOIN debtorsmaster
		ON debtortrans.debtorno=debtorsmaster.debtorno
		INNER JOIN currencies
		ON debtorsmaster.currcode=currencies.currabrev
		WHERE type='" . $_POST['TransType'] . "'
		AND date_format(inputdate, '%Y-%m-%d')='" . FormatDateForSQL($_POST['Date']) . "'";

$Result = DB_query($SQL, '', '', false, false);

if (DB_error_no() != 0) {
	$Title = _('Payment Listing');
	include('includes/header.inc');
	prnMsg(_('An error occurred getting the transactions'), 'error');
	if ($Debug == 1) {
		prnMsg(_('The SQL used to get the transaction information that failed was') . ':<br />' . $SQL, 'error');
	}
	include('includes/footer.inc');
	exit;
} elseif (DB_num_rows($Result) == 0) {
	$Title = _('Payment Listing');
	include('includes/header.inc');
	echo '<br />';
	prnMsg(_('There were no transactions found in the database for the date') . ' ' . $_POST['Date'] . '. ' . _('Please try again selecting a different date'), 'info');
	include('includes/footer.inc');
	exit;
}

include('includes/PDFStarter.php');

/*PDFStarter.php has all the variables for page size and width set up depending on the users default preferences for paper size */

$PDF->addInfo('Title', _('Customer Transaction Listing'));
$PDF->addInfo('Subject', _('Customer transaction listing from') . '  ' . $_POST['Date']);
$line_height = 12;
$PageNumber = 1;
$TotalAmount = 0;

include('includes/PDFCustTransListingPageHeader.inc');

while ($MyRow = DB_fetch_array($Result)) {

	$SQL = "SELECT name FROM debtorsmaster WHERE debtorno='" . $MyRow['debtorno'] . "'";
	$CustomerResult = DB_query($SQL);
	$CustomerRow = DB_fetch_array($CustomerResult);

	$LeftOvers = $PDF->addTextWrap($Left_Margin, $YPos, 160, $FontSize, $CustomerRow['name'], 'left');
	$LeftOvers = $PDF->addTextWrap($Left_Margin + 162, $YPos, 80, $FontSize, $MyRow['transno'], 'left');
	$LeftOvers = $PDF->addTextWrap($Left_Margin + 242, $YPos, 70, $FontSize, ConvertSQLDate($MyRow['trandate']), 'left');
	$LeftOvers = $PDF->addTextWrap($Left_Margin + 312, $YPos, 70, $FontSize, locale_number_format($MyRow['ovamount'], $MyRow['decimalplaces']), 'right');
	$LeftOvers = $PDF->addTextWrap($Left_Margin + 382, $YPos, 70, $FontSize, locale_number_format($MyRow['ovgst'], $MyRow['decimalplaces']), 'right');
	$LeftOvers = $PDF->addTextWrap($Left_Margin + 452, $YPos, 70, $FontSize, locale_number_format($MyRow['ovamount'] + $MyRow['ovgst'], $MyRow['decimalplaces']), 'right');

	$YPos -= ($line_height);
	$TotalAmount = $TotalAmount + ($MyRow['ovamount'] / $MyRow['rate']);

	if ($YPos - (2 * $line_height) < $Bottom_Margin) {
		/*Then set up a new page */
		$PageNumber++;
		include('includes/PDFCustTransListingPageHeader.inc');
	}
	/*end of new page header  */
}
/* end of while there are customer receipts in the batch to print */


$YPos -= $line_height;
$LeftOvers = $PDF->addTextWrap($Left_Margin + 452, $YPos, 70, $FontSize, locale_number_format($TotalAmount, $_SESSION['CompanyRecord']['decimalplaces']), 'right');
$LeftOvers = $PDF->addTextWrap($Left_Margin + 265, $YPos, 300, $FontSize, _('Total') . '  ' . _('Transactions') . ' ' . $_SESSION['CompanyRecord']['currencydefault'], 'left');

$ReportFileName = $_SESSION['DatabaseName'] . '_CustTransListing_' . date('Y-m-d') . '.pdf';
$PDF->OutputD($ReportFileName);
$PDF->__destruct();

?>