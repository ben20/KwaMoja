<?php

/* Add, Edit, Delete, and List MRP demand records. Table is mrpdemands.
 * Have separate functions for each routine. Use pass-by-reference - (&$StockId) -
 * to pass values of $StockId to functions. - when just used $StockId as variable,
 * got error: Catchable fatal error: Object of class mysqli could not be converted to string
 */

include('includes/session.inc');
$Title = _('MRP Demands');
include('includes/header.inc');

if (isset($_POST['DemandID'])) {
	$DemandID = $_POST['DemandID'];
} elseif (isset($_GET['DemandID'])) {
	$DemandID = $_GET['DemandID'];
}

if (isset($_POST['StockID'])) {
	$StockId = trim(mb_strtoupper($_POST['StockID']));
} elseif (isset($_GET['StockID'])) {
	$StockId = trim(mb_strtoupper($_GET['StockID']));
}

echo '<p class="page_title_text noPrint" ><img src="' . $RootPath . '/css/' . $Theme . '/images/inventory.png" title="' . _('Inventory') . '" alt="" />' . ' ' . $Title . '</p>';

if (isset($_POST['Search'])) {
	search($StockId);
} elseif (isset($_POST['submit'])) {
	submit($StockId, $DemandID);
} elseif (isset($_GET['delete'])) {
	delete($DemandID, '', $StockId);
} elseif (isset($_POST['deletesome'])) {
	delete('', $_POST['MRPDemandtype'], $StockId);
} elseif (isset($_GET['listall'])) {
	listall('', '');
} elseif (isset($_POST['listsome'])) {
	listall($StockId, $_POST['MRPDemandtype']);
} else {
	display($StockId, $DemandID);
}

function search(&$StockId) { //####SEARCH_SEARCH_SEARCH_SEARCH_SEARCH_SEARCH_SEARCH_#####

	// Search by partial part number or description. Display the part number and description from
	// the stockmaster so user can select one. If the user clicks on a part number
	// MRPDemands.php is called again, and it goes to the display() routine.

	// Work around to auto select
	if ($_POST['Keywords'] == '' and $_POST['StockCode'] == '') {
		$_POST['StockCode'] = '%';
	}
	if ($_POST['Keywords'] and $_POST['StockCode']) {
		$Msg = _('Stock description keywords have been used in preference to the Stock code extract entered');
	}
	if ($_POST['Keywords'] == '' and $_POST['StockCode'] == '') {
		$Msg = _('At least one stock description keyword or an extract of a stock code must be entered for the search');
	} else {
		if (mb_strlen($_POST['Keywords']) > 0) {
			//insert wildcard characters in spaces
			$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';

			$SQL = "SELECT stockmaster.stockid,
						stockmaster.description
					FROM stockmaster
					WHERE  stockmaster.description " . LIKE . " '" . $SearchString . "'
					ORDER BY stockmaster.stockid";

		} elseif (mb_strlen($_POST['StockCode']) > 0) {
			$SQL = "SELECT stockmaster.stockid,
						stockmaster.description
					FROM stockmaster
					WHERE  stockmaster.stockid " . LIKE . "'%" . $_POST['StockCode'] . "%'
					ORDER BY stockmaster.stockid";

		}

		$ErrMsg = _('The SQL to find the parts selected failed with the message');
		$Result = DB_query($SQL, $ErrMsg);

	} //one of keywords or StockCode was more than a zero length string

	// If the SELECT found records, display them
	if (DB_num_rows($Result) > 0) {
		echo '<form onSubmit="return VerifyForm(this);" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint">';
		echo '<div>';
		echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
		echo '<table cellpadding="2" class="selection">
				<tr>
					<th>' . _('Code') . '</th>
					<th>' . _('Description') . '</th>
				</tr>';

		$k = 0; //row colour counter
		$j = 0;
		while ($MyRow = DB_fetch_array($Result)) {
			if ($k == 1) {
				echo '<tr class="EvenTableRows">';
				$k = 0;
			} else {
				echo '<tr class="OddTableRows">';
				$k++;
			}
			$TabIndex = $j + 4;
			echo '<td><input tabindex="' . $TabIndex . '" type="submit" name="StockID" value="' . $MyRow['stockid'] . '" /></td>
				<td>' . $MyRow['description'] . '</td>
				</tr>';
			$j++;
		} //end of while loop

		echo '</table>';
		echo '</div>';
		echo '</form>';

	} else {
		prnMsg(_('No record found in search'), 'error');
		unset($StockId);
		display( $StockId, $DemandID);
	}


} // End of function search()


function submit(&$StockId, &$DemandID) //####SUBMIT_SUBMIT_SUBMIT_SUBMIT_SUBMIT_SUBMIT_SUBMIT_SUBMIT####
	{
	// In this section if hit submit button. Do edit checks. If all checks pass, see if record already
	// exists for StockID/Duedate/MRPDemandtype combo; that means do an Update, otherwise, do INSERT.
	//initialise no input errors assumed initially before we test
	// echo "<br/>Submit - DemandID = $DemandID<br/>";
	$FormatedDuedate = FormatDateForSQL($_POST['Duedate']);
	$InputError = 0;

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible

	if (!is_numeric(filter_number_format($_POST['Quantity']))) {
		$InputError = 1;
		prnMsg(_('Quantity must be numeric'), 'error');
	}
	if (filter_number_format($_POST['Quantity']) <= 0) {
		$InputError = 1;
		prnMsg(_('Quantity must be greater than 0'), 'error');
	}
	if (!Is_Date($_POST['Duedate'])) {
		$InputError = 1;
		prnMsg(_('Invalid due date'), 'error');
	}
	$SQL = "SELECT * FROM mrpdemandtypes
			WHERE mrpdemandtype='" . $_POST['MRPDemandtype'] . "'";
	$Result = DB_query($SQL);

	if (DB_num_rows($Result) == 0) {
		$InputError = 1;
		prnMsg(_('Invalid demand type'), 'error');
	}
	// Check if valid part number - Had done a Select Count(*), but that returned a 1 in DB_num_rows
	// even if there was no record.
	$SQL = "SELECT * FROM stockmaster
			WHERE stockid='" . $StockId . "'";
	$Result = DB_query($SQL);

	if (DB_num_rows($Result) == 0) {
		$InputError = 1;
		prnMsg($StockId . ' ' . _('is not a valid item code'), 'error');
		unset($_POST['StockID']);
		unset($StockId);
	}
	// Check if part number/demand type/due date combination already exists
	$SQL = "SELECT * FROM mrpdemands
			WHERE stockid='" . $StockId . "'
			AND mrpdemandtype='" . $_POST['MRPDemandtype'] . "'
			AND duedate='" . $FormatedDuedate . "'
			AND demandid <> '" . $DemandID . "'";
	$Result = DB_query($SQL);

	if (DB_num_rows($Result) > 0) {
		$InputError = 1;
		prnMsg(_('Record already exists for part number/demand type/date'), 'error');
	}

	if ($InputError != 1) {
		$SQL = "SELECT COUNT(*) FROM mrpdemands
				   WHERE demandid='" . $DemandID . "'
				   GROUP BY demandid";
		$Result = DB_query($SQL);
		$MyRow = DB_fetch_row($Result);

		if ($MyRow[0] > 0) {
			//If $MyRow[0] > 0, it means this is an edit, so do an update
			$SQL = "UPDATE mrpdemands SET quantity = '" . filter_number_format($_POST['Quantity']) . "',
							mrpdemandtype = '" . trim(mb_strtoupper($_POST['MRPDemandtype'])) . "',
							duedate = '" . $FormatedDuedate . "'
					WHERE demandid = '" . $DemandID . "'";
			$Msg = _("The MRP demand record has been updated for") . ' ' . $StockId;
		} else {

			// if $MyRow[0] from SELECT count(*) is zero, this is an entry of a new record
			$SQL = "INSERT INTO mrpdemands (stockid,
							mrpdemandtype,
							quantity,
							duedate)
						VALUES ('" . $StockId . "',
							'" . trim(mb_strtoupper($_POST['MRPDemandtype'])) . "',
							'" . filter_number_format($_POST['Quantity']) . "',
							'" . $FormatedDuedate . "'
						)";
			$Msg = _('A new MRP demand record has been added to the database for') . ' ' . $StockId;
		}


		$Result = DB_query($SQL, _('The update/addition of the MRP demand record failed because'));
		prnMsg($Msg, 'success');
		echo '<br />';
		unset($_POST['MRPDemandtype']);
		unset($_POST['Quantity']);
		unset($_POST['StockID']);
		unset($_POST['Duedate']);
		unset($StockId);
		unset($DemandID);
	} // End of else where DB_num_rows showed there was a valid stockmaster record

	display($StockId, $DemandID);
} // End of function submit()


function delete($DemandID, $DemandType, $StockId) { //####DELETE_DELETE_DELETE_DELETE_DELETE_DELETE_####

	// If wanted to have a Confirm routine before did actually deletion, could check if
	// deletion = "yes"; if it did, display link that redirects back to this page
	// like this - <a href=" ' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?&delete=confirm&StockID=' . "$StockId" . ' ">
	// that sets delete=confirm. If delete=confirm, do actually deletion.
	//  This deletes an individual record by DemandID if called from a listall that shows
	// edit/delete or deletes all of a particular demand type if press Delete Demand Type button.
	$where = " ";
	if ($DemandType) {
		$where = " WHERE mrpdemandtype ='" . $DemandType . "'";
	}
	if ($DemandID) {
		$where = " WHERE demandid ='" . $DemandID . "'";
	}
	$SQL = "DELETE FROM mrpdemands
		   $where";
	$Result = DB_query($SQL);
	if ($DemandID) {
		prnMsg(_('The MRP demand record for') . ' ' . $StockId . ' ' . _('has been deleted'), 'succes');
	} else {
		prnMsg(_('All records for demand type') . ' ' . $DemandType . ' ' . _('have been deleted'), 'succes');
	}
	unset($DemandID);
	unset($StockId);
	display($stockID, $DemandID);

} // End of function delete()


function listall($part, $DemandType) { //####LISTALL_LISTALL_LISTALL_LISTALL_LISTALL_LISTALL_LISTALL_####

	// List all mrpdemands records, with anchors to Edit or Delete records if hit List All anchor
	// Lists some in hit List Selection submit button, and uses part number if it is entered or
	// demandtype

	echo '<form onSubmit="return VerifyForm(this);" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint">';
	echo '<div>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	$where = " ";
	if ($DemandType) {
		$where = " WHERE mrpdemandtype ='" . $DemandType . "'";
	}
	if ($part) {
		$where = " WHERE mrpdemands.stockid ='" . $part . "'";
	}
	// If part is entered, it overrides demandtype
	$SQL = "SELECT mrpdemands.demandid,
				   mrpdemands.stockid,
				   mrpdemands.mrpdemandtype,
				   mrpdemands.quantity,
				   mrpdemands.duedate,
				   stockmaster.description,
				   stockmaster.decimalplaces
			FROM mrpdemands
			LEFT JOIN stockmaster on mrpdemands.stockid = stockmaster.stockid" . $where . " ORDER BY mrpdemands.stockid, mrpdemands.duedate";

	$ErrMsg = _('The SQL to find the parts selected failed with the message');
	$Result = DB_query($SQL, $ErrMsg);

	echo '<table class="selection">
		<tr>
			<th>' . _('Part Number') . '</th>
			<th>' . _('Description') . '</th>
			<th>' . _('Demand Type') . '</th>
			<th>' . _('Quantity') . '</th>
			<th>' . _('Due Date') . '</th>
			</tr>';
	$ctr = 0;
	while ($MyRow = DB_fetch_array($Result)) {
		$displaydate = ConvertSQLDate($MyRow[4]);
		$ctr++;
		echo '<tr><td>' . $MyRow['stockid'] . '</td>
				<td>' . $MyRow['description'] . '</td>
				<td>' . $MyRow['mrpdemandtype'] . '</td>
				<td>' . locale_number_format($MyRow['quantity'], $MyRow['decimalplaces']) . '</td>
				<td>' . $displaydate . '</td>
				<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?DemandID=' . $MyRow['demandid'] . '&amp;StockID=' . $MyRow['stockid'] . '">' . _('Edit') . '</a></td>
				<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?DemandID=' . $MyRow['demandid'] . '&amp;StockID=' . $MyRow['stockid'] . '&amp;delete=yes" onclick="return MakeConfirm(\'' . _('Are you sure you wish to delete this demand?') . '\', \'Confirm Delete\', this);">' . _('Delete') . '</a></td>
				</tr>';
	}

	//END WHILE LIST LOOP
	echo '<tr><td>' . _('Number of Records') . '</td>
				<td>' . $ctr . '</td></tr>';
	echo '</table>';
	echo '</div>';
	echo '</form><br/><br/><br/><br/>';
	unset($StockId);
	display($StockId, $DemandID);

} // End of function listall()


function display(&$StockId, &$DemandID) { //####DISPLAY_DISPLAY_DISPLAY_DISPLAY_DISPLAY_DISPLAY_#####

	// Display Seach fields at top and Entry form below that. This function is called the first time
	// the page is called, and is also invoked at the end of all of the other functions.
	// echo "<br/>DISPLAY - DemandID = $DemandID<br/>";
	echo '<form onSubmit="return VerifyForm(this);" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint">';
	echo '<div>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	if (!isset($StockId)) {
		echo '<table cellpadding="3" class="selection"><tr>
			<td>' . _('Enter text extracts in the') . ' <b>' . _('description') . '</b>:</td>
			<td><input tabindex="1" type="text" name="Keywords" size="20" minlength="0" maxlength="25" /></td>
			<td><b>' . _('OR') . '</b></td>
			<td>' . _('Enter extract of the') . ' <b>' . _('Stock Code') . '</b>:</td>
			<td><input tabindex="2" type="text" name="StockCode" size="15" minlength="0" maxlength="20" /></td>
			<td><b>' . _('OR') . '</b></td>
			<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?listall=yes">' . _('List All Demands') . '</a></td></tr>
			<tr><td colspan="7"><div class="centre"><input tabindex="3" type="submit" name="Search" value="' . _('Search Now') . '" /></div></td></tr></table>';
	} else {
		if (isset($DemandID)) {
			//editing an existing MRP demand

			$SQL = "SELECT demandid,
					stockid,
					mrpdemandtype,
					quantity,
					duedate
				FROM mrpdemands
				WHERE demandid='" . $DemandID . "'";
			$Result = DB_query($SQL);
			$MyRow = DB_fetch_array($Result);

			if (DB_num_rows($Result) > 0) {
				$_POST['DemandID'] = $MyRow['demandid'];
				$_POST['StockID'] = $MyRow['stockid'];
				$_POST['MRPDemandtype'] = $MyRow['mrpdemandtype'];
				$_POST['Quantity'] = locale_number_format($MyRow['quantity'], 'Variable');
				$_POST['Duedate'] = ConvertSQLDate($MyRow['duedate']);
			}

			echo '<input type="hidden" name="DemandID" value="' . $_POST['DemandID'] . '" />';
			echo '<input type="hidden" name="StockID" value="' . $_POST['StockID'] . '" />';
			echo '<table class="selection">
					<tr>
						<td>' . _('Part Number') . ':</td>
						<td>' . $_POST['StockID'] . '</td>
					</tr>';

		} else {
			if (!isset($_POST['StockID'])) {
				$_POST['StockID'] = '';
			}
			echo '<table class="selection">
					<tr>
						<td>' . _('Part Number') . ':</td>
						<td><input type="text" name="StockID" size="21" required="required" minlength="1" maxlength="20" value="' . $_POST['StockID'] . '" /></td>
					</tr>';
		}


		if (!isset($_POST['Quantity'])) {
			$_POST['Quantity'] = 0;
		}

		if (!isset($_POST['Duedate'])) {
			$_POST['Duedate'] = ' ';
		}

		echo '<tr><td>' . _('Quantity') . ':</td>
				<td><input type="text" name="Quantity" class="number" size="6" required="required" minlength="1" maxlength="6" value="' . $_POST['Quantity'] . '" /></td>
			</tr>
			<tr>
				<td>' . _('Due Date') . ':</td>
				<td><input type="text" class="date" alt="' . $_SESSION['DefaultDateFormat'] . '" name="Duedate" size="12" required="required" minlength="1" maxlength="12" value="' . $_POST['Duedate'] . '" /></td>
			</tr>';
		// Generate selections for Demand Type
		echo '<tr>
				<td>' . _('Demand Type') . '</td>
				<td><select minlength="0" name="MRPDemandtype">';

		$SQL = "SELECT mrpdemandtype,
						description
				FROM mrpdemandtypes";
		$Result = DB_query($SQL);
		while ($MyRow = DB_fetch_array($Result)) {
			if (isset($_POST['MRPDemandtype']) and $MyRow['mrpdemandtype'] == $_POST['MRPDemandtype']) {
				echo '<option selected="selected" value="';
			} else {
				echo '<option value="';
			}
			echo $MyRow['mrpdemandtype'] . '">' . $MyRow['mrpdemandtype'] . ' - ' . $MyRow['description'] . '</option>';
		} //end while loop
		echo '</select></td>
			</tr>
			</table>
			<br />
			<div class="centre">
				<input type="submit" name="submit" value="' . _('Enter Information') . '" />&nbsp;&nbsp;
				<input type="submit" name="listsome" value="' . _('List Selection') . '" />&nbsp;&nbsp;
				<input type="submit" name="deletesome" value="' . _('Delete Demand Type') . '" />';
		// If mrpdemand record exists, display option to delete it
		if ((isset($DemandID)) and (DB_num_rows($Result) > 0)) {
			echo '<br/><br/><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?delete=yes&amp;StockID=' . $StockId . '&amp;DemandID=' . $DemandID . '" onclick="return MakeConfirm(\'' . _('Are you sure you wish to delete this demand?') . '\', \'Confirm Delete\', this);">' . _('Or Delete Record') . '</a>';
		}
		echo '</div>';
	}
	echo '</div>
		</form>';

} // End of function display()

include('includes/footer.inc');
?>