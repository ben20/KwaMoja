<?php

include('includes/session.inc');
include('includes/SQL_CommonFunctions.inc');

$Title = _('Create GL Budgets');

$ViewTopic = 'GeneralLedger';
$BookMark = 'GLBudgets';
include('includes/header.inc');

if (isset($_POST['SelectedAccount'])) {
	$SelectedAccount = $_POST['SelectedAccount'];
} elseif (isset($_GET['SelectedAccount'])) {
	$SelectedAccount = $_GET['SelectedAccount'];
}

if (isset($_POST['Previous'])) {
	$SelectedAccount = $_POST['PrevAccount'];
} elseif (isset($_POST['Next'])) {
	$SelectedAccount = $_POST['NextAccount'];
}

if (isset($_POST['update'])) {
	prnMsg(_('Budget updated successfully'), 'success');
}

//If an account has not been selected then select one here.
echo '<p class="page_title_text noPrint" >
		<img src="' . $RootPath . '/css/' . $Theme . '/images/maintenance.png" title="' . _('Budgets') . '" alt="' . _('Budgets') . '" />' . ' ' . $Title . '
	</p>';
echo '<form onSubmit="return VerifyForm(this);" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint" id="selectaccount">';
echo '<div>';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<br />';
echo '<table summary="' . _('General ledger account election') . '">
		<tr>
			<td>' . _('Select GL Account') . ':</td>
			<td><select required="required" minlength="0" name="SelectedAccount" onchange="ReloadForm(selectaccount.Select)">';

$SQL = "SELECT accountcode,
				accountname
			FROM chartmaster
			INNER JOIN accountgroups
				ON accountgroups.groupname=chartmaster.group_
			WHERE pandl=1
			ORDER BY accountcode";

$Result = DB_query($SQL);
if (DB_num_rows($Result) == 0) {
	echo '</select></td>
		</tr>';
	prnMsg(_('No General ledger accounts have been set up yet') . ' - ' . _('budgets cannot be allocated until the GL accounts are set up'), 'warn');
} else {
	while ($MyRow = DB_fetch_array($Result)) {
		$Account = $MyRow['accountcode'] . ' - ' . htmlspecialchars($MyRow['accountname'], ENT_QUOTES, 'UTF-8', false);
		if (isset($SelectedAccount) and isset($LastCode) and $SelectedAccount == $MyRow['accountcode']) {
			echo '<option selected="selected" value="' . $MyRow['accountcode'] . '">' . $Account . '</option>';
			$PrevCode = $LastCode;
		} else {
			echo '<option value="' . $MyRow['accountcode'] . '">' . $Account . '</option>';
			if (isset($SelectedAccount) and isset($LastCode) and $SelectedAccount == $LastCode) {
				$NextCode = $MyRow['accountcode'];
			}
		}
		$LastCode = $MyRow['accountcode'];
	}
	echo '</select></td>
		</tr>';
}

if (!isset($PrevCode)) {
	$PrevCode = '';
}
if (!isset($NextCode)) {
	$NextCode = '';
}

echo '</table>';
echo '<input type="hidden" name="PrevAccount" value="' . $PrevCode . '" />';
echo '<input type="hidden" name="NextAccount" value="' . $NextCode . '" />';

echo '<br />
		<table summary="' . _('Account Navigation') . '">
		<tr>
			<td><input type="submit" name="Previous" value="' . _('Prev Account') . '" /></td>
			<td><input type="submit" name="Select" value="' . _('Select Account') . '" /></td>
			<td><input type="submit" name="Next" value="' . _('Next Account') . '" /></td>
		</tr>
		</table>
		<br />
	</div>
	</form>';

// End of account selection

if (isset($SelectedAccount) and $SelectedAccount != '') {

	$CurrentYearEndPeriod = GetPeriod(Date($_SESSION['DefaultDateFormat'], YearEndDate($_SESSION['YearEnd'], 0)));

	// If the update button has been hit, then update chartdetails with the budget figures
	// for this year and next.
	if (isset($_POST['update'])) {
		$ErrMsg = _('Cannot update GL budgets');
		$DbgMsg = _('The SQL that failed to update the GL budgets was');
		for ($i = 1; $i <= 12; $i++) {
			$SQL = "UPDATE chartdetails SET budget='" . round(filter_number_format($_POST[$i . 'last']), $_SESSION['CompanyRecord']['decimalplaces']) . "'
					WHERE period='" . ($CurrentYearEndPeriod - (24 - $i)) . "'
					AND  accountcode = '" . $SelectedAccount . "'";
			$Result = DB_query($SQL, $ErrMsg, $DbgMsg);
			$SQL = "UPDATE chartdetails SET budget='" . round(filter_number_format($_POST[$i . 'this']), $_SESSION['CompanyRecord']['decimalplaces']) . "'
					WHERE period='" . ($CurrentYearEndPeriod - (12 - $i)) . "'
					AND  accountcode = '" . $SelectedAccount . "'";
			$Result = DB_query($SQL, $ErrMsg, $DbgMsg);
			$SQL = "UPDATE chartdetails SET budget='" . round(filter_number_format($_POST[$i . 'next']), $_SESSION['CompanyRecord']['decimalplaces']) . "'
					WHERE period='" . ($CurrentYearEndPeriod + $i) . "'
					AND  accountcode = '" . $SelectedAccount . "'";
			$Result = DB_query($SQL, $ErrMsg, $DbgMsg);
		}
	}
	// End of update

	$YearEndYear = Date('Y', YearEndDate($_SESSION['YearEnd'], 0));

	/* If the periods dont exist then create them */
	for ($i = 1; $i <= 36; $i++) {
		$MonthEnd = mktime(0, 0, 0, $_SESSION['YearEnd'] + 1 + $i, 0, $YearEndYear - 2);
		$period = GetPeriod(Date($_SESSION['DefaultDateFormat'], $MonthEnd), false);
		$PeriodEnd[$period] = Date('M Y', $MonthEnd);
	}
	include('includes/GLPostings.inc'); //creates chartdetails with correct values
	// End of create periods

	$SQL = "SELECT period,
					budget,
					actual
				FROM chartdetails
				WHERE accountcode='" . $SelectedAccount . "'";

	$Result = DB_query($SQL);
	while ($MyRow = DB_fetch_array($Result)) {
		$Budget[$MyRow['period']] = $MyRow['budget'];
		$Actual[$MyRow['period']] = $MyRow['actual'];
	}


	if (isset($_POST['Apportion'])) {
		for ($i = 1; $i <= 12; $i++) {
			if (filter_number_format($_POST['AnnualAmountLY']) != '0' and is_numeric(filter_number_format($_POST['AnnualAmountLY']))) {
				$Budget[$CurrentYearEndPeriod + $i - 24] = round(filter_number_format($_POST['AnnualAmountLY']) / 12, 0);
			}
			if (filter_number_format($_POST['AnnualAmountTY']) != '0' and is_numeric(filter_number_format($_POST['AnnualAmountTY']))) {
				$Budget[$CurrentYearEndPeriod + $i - 12] = round(filter_number_format($_POST['AnnualAmountTY']) / 12, 0);
			}
			if (filter_number_format($_POST['AnnualAmount']) != '0' and is_numeric(filter_number_format($_POST['AnnualAmount']))) {
				$Budget[$CurrentYearEndPeriod + $i] = round(filter_number_format($_POST['AnnualAmount']) / 12, 0);
			}
		}
	}

	$LastYearActual = 0;
	$LastYearBudget = 0;
	$ThisYearActual = 0;
	$ThisYearBudget = 0;
	$NextYearActual = 0;
	$NextYearBudget = 0;

	// Table Headers

	echo '<form onSubmit="return VerifyForm(this);" id="form" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint">';
	echo '<div>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<br />
			<table class="selection" width="90%" summary="' . _('Budget Entry') . '">
			<tr>
				<th colspan="3">' . _('Last Financial Year') . '</th>
				<th colspan="3">' . _('This Financial Year') . '</th>
				<th colspan="3">' . _('Next Financial Year') . '</th>
			</tr>
			<tr>
				<th colspan="3">' . _('Year ended') . ' - ' . Date($_SESSION['DefaultDateFormat'], YearEndDate($_SESSION['YearEnd'], -1)) . '</th>
				<th colspan="3">' . _('Year ended') . ' - ' . Date($_SESSION['DefaultDateFormat'], YearEndDate($_SESSION['YearEnd'], 0)) . '</th>
				<th colspan="3">' . _('Year ended') . ' - ' . Date($_SESSION['DefaultDateFormat'], YearEndDate($_SESSION['YearEnd'], 1)) . '</th>
			</tr>
			<tr>';
	for ($i = 0; $i < 3; $i++) {
		echo '<th width="10%">' . _('Period') . '</th>
				<th width="10%">' . _('Actual') . '</th>
				<th width="10%">' . _('Budget') . '</th>';
	}
	echo '</tr>';

	// Main Table

	for ($i = 1; $i <= 12; $i++) {
		echo '<tr>';
		echo '<th>' . $PeriodEnd[$CurrentYearEndPeriod - (24 - $i)] . '</th>';
		echo '<td class="number">' . locale_number_format($Actual[$CurrentYearEndPeriod - (24 - $i)], $_SESSION['CompanyRecord']['decimalplaces']) . '</td>';
		echo '<td><input type="text" readonly="true" class="number" size="12" name="' . $i . 'last" value="' . locale_number_format($Budget[$CurrentYearEndPeriod - (24 - $i)], $_SESSION['CompanyRecord']['decimalplaces']) . '" /></td>';
		echo '<th>' . $PeriodEnd[$CurrentYearEndPeriod - (12 - $i)] . '</th>';
		echo '<td class="number">' . locale_number_format($Actual[$CurrentYearEndPeriod - (12 - $i)], $_SESSION['CompanyRecord']['decimalplaces']) . '</td>';
		echo '<td><input type="text" class="number" required="required" minlength="1" maxlength="12" size="12" name="' . $i . 'this" value="' . locale_number_format($Budget[$CurrentYearEndPeriod - (12 - $i)], $_SESSION['CompanyRecord']['decimalplaces']) . '" /></td>';
		echo '<th>' . $PeriodEnd[$CurrentYearEndPeriod + ($i)] . '</th>';
		echo '<td class="number">' . locale_number_format($Actual[$CurrentYearEndPeriod + $i], $_SESSION['CompanyRecord']['decimalplaces']) . '</td>';
		if ($i == 1) {
			echo '<td><input type="text" class="number" autofocus="autofocus" required="required" minlength="1" maxlength="12" size="12" name="' . $i . 'next" value="' . locale_number_format($Budget[$CurrentYearEndPeriod + $i], $_SESSION['CompanyRecord']['decimalplaces']) . '" /></td>';
		} else {
			echo '<td><input type="text" class="number" required="required" minlength="1" maxlength="12" size="12" name="' . $i . 'next" value="' . locale_number_format($Budget[$CurrentYearEndPeriod + $i], $_SESSION['CompanyRecord']['decimalplaces']) . '" /></td>';
		}
		echo '</tr>';
		$LastYearActual = $LastYearActual + $Actual[$CurrentYearEndPeriod - (24 - $i)];
		$LastYearBudget = $LastYearBudget + $Budget[$CurrentYearEndPeriod - (24 - $i)];
		$ThisYearActual = $ThisYearActual + $Actual[$CurrentYearEndPeriod - (12 - $i)];
		$ThisYearBudget = $ThisYearBudget + $Budget[$CurrentYearEndPeriod - (12 - $i)];
		$NextYearActual = $NextYearActual + $Actual[$CurrentYearEndPeriod + ($i)];
		$NextYearBudget = $NextYearBudget + $Budget[$CurrentYearEndPeriod + ($i)];
	}

	// Total Line

	echo '<tr>
			<th>' . _('Total') . '</th>
			<th class="number">' . locale_number_format($LastYearActual, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
			<th class="number">' . locale_number_format($LastYearBudget, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
			<th>' . _('Total') . '</th>
			<th class="number">' . locale_number_format($ThisYearActual, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
			<th class="number">' . locale_number_format($ThisYearBudget, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
			<th>' . _('Total') . '</th>
			<th class="number">' . locale_number_format($NextYearActual, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
			<th class="number">' . locale_number_format($NextYearBudget, $_SESSION['CompanyRecord']['decimalplaces']) . '</th>
		</tr>
		<tr>
			<td colspan="2">' . _('Annual Budget') . '</td>
			<td><input class="number" readonly="true" type="text" size="12" name="AnnualAmountLY" value="0.00" /></td>
			<td colspan="2">' . _('Annual Budget') . '</td>
			<td><input class="number" type="text" size="12" name="AnnualAmountTY" value="0.00" /></td>
			<td>' . _('Annual Budget') . '</td>
			<td><input type="submit" name="Apportion" value="' . _('Apportion Budget') . '" /></td>
			<td><input onchange="numberFormat(this,' . $_SESSION['CompanyRecord']['decimalplaces'] . ')" class="number" type="text" size="14" name="AnnualAmount" value="0.00" /></td>
		</tr>
		</table>';

	echo '<input type="hidden" name="SelectedAccount" value="' . $SelectedAccount . '" />';

	echo '<br />
		<div class="centre">
			<input type="submit" name="update" value="' . _('Update') . '" />
		</div>
		</div>
		</form>';

	$SQL = "SELECT MIN(periodno) FROM periods";
	$Result = DB_query($SQL);
	$MyRow = DB_fetch_array($Result);
	$FirstPeriod = $MyRow[0];

	$SQL = "SELECT MAX(periodno) FROM periods";
	$Result = DB_query($SQL);
	$MyRow = DB_fetch_array($Result);
	$LastPeriod = $MyRow[0];

	for ($i = $FirstPeriod; $i <= $LastPeriod; $i++) {
		$SQL = "SELECT accountcode,
					period,
					budget,
					actual,
					bfwd,
					bfwdbudget
				FROM chartdetails
				WHERE period ='" . $i . "'
				AND  accountcode = '" . $SelectedAccount . "'";

		$ErrMsg = _('Could not retrieve the ChartDetail records because');
		$Result = DB_query($SQL, $ErrMsg);

		while ($MyRow = DB_fetch_array($Result)) {

			$CFwdBudget = $MyRow['bfwdbudget'] + $MyRow['budget'];
			$SQL = "UPDATE chartdetails
					SET bfwdbudget='" . $CFwdBudget . "'
					WHERE period='" . ($MyRow['period'] + 1) . "'
					AND  accountcode = '" . $SelectedAccount . "'";

			$ErrMsg = _('Could not update the chartdetails record because');
			$updresult = DB_query($SQL, $ErrMsg);
		}
	}
	/* end of for loop */
}

include('includes/footer.inc');

?>