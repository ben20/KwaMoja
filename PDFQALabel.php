<?php

/* $Id: PDFQALabel.php agaluski $*/

include('includes/session.inc');

if (isset($_GET['GRNNo'])) {
	$GRNNo = $_GET['GRNNo'];
} else {
	$GRNNo = '';
}

$FormDesign = simplexml_load_file($PathPrefix . 'companies/' . $_SESSION['DatabaseName'] . '/FormDesigns/QALabel.xml');

// Set the paper size/orintation
$PaperSize = $FormDesign->PaperSize;
$line_height = $FormDesign->LineHeight;
include('includes/PDFStarter.php');
$PageNumber = 1;
$PDF->addInfo('Title', _('QA Label'));

if ($GRNNo == 'Preview') {
	$MyRow['itemcode'] = str_pad('', 15, 'x');
	$MyRow['itemdescription'] = str_pad('', 30, 'x');
	$MyRow['serialno'] = str_pad('', 20, 'x');
	$MyRow['reslot'] = str_pad('', 20, 'x');
	$SuppRow['suppname'] = str_pad('', 30, 'x');
	$MyRow['deliverydate'] = '0000-00-00';
	$MyRow['orderno'] = '0000000000';
	$NoOfGRNs = 1;
} else { //NOT PREVIEW

	$SQL = "SELECT grns.itemcode,
				grns.grnno,
				grns.deliverydate,
				grns.itemdescription,
				grns.supplierid,
				purchorderdetails.orderno
			FROM grns INNER JOIN purchorderdetails
			ON grns.podetailitem=purchorderdetails.podetailitem
			LEFT JOIN stockmaster
			ON grns.itemcode=stockmaster.stockid
			WHERE grnbatch='" . $GRNNo . "'";

	$GRNResult = DB_query($SQL);
	$NoOfGRNs = DB_num_rows($GRNResult);
	if ($NoOfGRNs > 0) { //there are GRNs to print

		$SQL = "SELECT suppliers.suppname
				FROM grns INNER JOIN suppliers
				ON grns.supplierid=suppliers.supplierid
				WHERE grnbatch='" . $GRNNo . "'";
		$SuppResult = DB_query($SQL, _('Could not get the supplier of the selected GRN'));
		$SuppRow = DB_fetch_array($SuppResult);
	}
} // get data to print
if ($NoOfGRNs > 0) {

	for ($i = 1; $i <= $NoOfGRNs; $i++) {
		if ($GRNNo != 'Preview') {
			$MyRow = DB_fetch_array($GRNResult);
		}
		$DeliveryDate = ConvertSQLDate($MyRow['deliverydate']);
		$SQL = "SELECT stockmaster.controlled
			    FROM stockmaster WHERE stockid ='" . $MyRow['itemcode'] . "'";
		$CheckControlledResult = DB_query($SQL, '<br />' . _('Could not determine if the item was controlled or not because') . ' ');
		$ControlledRow = DB_fetch_row($CheckControlledResult);

		if ($ControlledRow[0] == 1) {
			/*Then its a controlled item */
			$SQL = "SELECT stockserialmoves.serialno
					FROM stockmoves INNER JOIN stockserialmoves
					ON stockmoves.stkmoveno= stockserialmoves.stockmoveno
					WHERE stockmoves.stockid='" . $MyRow['itemcode'] . "'
					AND stockmoves.type =25
					AND stockmoves.transno='" . $GRNNo . "'";
			$GetStockMoveResult = DB_query($SQL, _('Could not retrieve the stock movement reference number which is required in order to retrieve details of the serial items that came in with this GRN'));
			while ($SerialStockMoves = DB_fetch_array($GetStockMoveResult)) {
				if ($PageNumber > 1) {
					$PDF->newPage();
				}
				$PDF->addJpegFromFile($_SESSION['LogoFile'], $FormDesign->logo->x, $Page_Height - $FormDesign->logo->y, $FormDesign->logo->width, $FormDesign->logo->height);
				$LeftOvers = $PDF->addText($FormDesign->ItemNbr->x, $Page_Height - $FormDesign->ItemNbr->y, $FormDesign->ItemNbr->FontSize, 'Item: ' . $MyRow['itemcode']);
				$LeftOvers = $PDF->addText($FormDesign->ItemDesc->x, $Page_Height - $FormDesign->ItemDesc->y, $FormDesign->ItemDesc->FontSize, 'Description: ' . $MyRow['itemdescription']);
				$LeftOvers = $PDF->addText($FormDesign->SupplierName->x, $Page_Height - $FormDesign->SupplierName->y, $FormDesign->SupplierName->FontSize, 'Supplier: ' . $SuppRow['suppname']);
				$LeftOvers = $PDF->addText($FormDesign->SupplierLot->x, $Page_Height - $FormDesign->SupplierLot->y, $FormDesign->SupplierLot->FontSize, 'Supplier Lot: ' . $SerialStockMoves['serialno']);
				$LeftOvers = $PDF->addText($FormDesign->Lot->x, $Page_Height - $FormDesign->Lot->y, $FormDesign->Lot->FontSize, 'Lot: ' . $SerialStockMoves['serialno']);
				$LeftOvers = $PDF->addText($FormDesign->ReceiptDate->x, $Page_Height - $FormDesign->ReceiptDate->y, $FormDesign->ReceiptDate->FontSize, 'Receipt Date: ' . $MyRow['deliverydate']);
				$LeftOvers = $PDF->addText($FormDesign->OrderNumber->x, $Page_Height - $FormDesign->OrderNumber->y, $FormDesign->OrderNumber->FontSize, 'P/O: ' . $MyRow['orderno']);
				$PageNumber++;
			} //while SerialStockMoves

		} //controlled item*/
		else {
			$PDF->addJpegFromFile($_SESSION['LogoFile'], $FormDesign->logo->x, $Page_Height - $FormDesign->logo->y, $FormDesign->logo->width, $FormDesign->logo->height);
			$LeftOvers = $PDF->addText($FormDesign->ItemNbr->x, $Page_Height - $FormDesign->ItemNbr->y, $FormDesign->ItemNbr->FontSize, 'Item: ' . $MyRow['itemcode']);
			$LeftOvers = $PDF->addText($FormDesign->ItemDesc->x, $Page_Height - $FormDesign->ItemDesc->y, $FormDesign->ItemDesc->FontSize, 'Description: ' . $MyRow['itemdescription']);
			$LeftOvers = $PDF->addText($FormDesign->SupplierName->x, $Page_Height - $FormDesign->SupplierName->y, $FormDesign->SupplierName->FontSize, 'Supplier: ' . $SuppRow['suppname']);
			//$LeftOvers = $PDF->addText($FormDesign->SupplierLot->x,$Page_Height-$FormDesign->SupplierLot->y,$FormDesign->SupplierLot->FontSize,'Supplier Lot: ' . $MyRow['serialno']);
			//$LeftOvers = $PDF->addText($FormDesign->Lot->x,$Page_Height-$FormDesign->Lot->y,$FormDesign->Lot->FontSize,'Lot: ' . $MyRow['serialno']);
			$LeftOvers = $PDF->addText($FormDesign->ReceiptDate->x, $Page_Height - $FormDesign->ReceiptDate->y, $FormDesign->ReceiptDate->FontSize, 'Receipt Date: ' . $MyRow['deliverydate']);
			$LeftOvers = $PDF->addText($FormDesign->OrderNumber->x, $Page_Height - $FormDesign->OrderNumber->y, $FormDesign->OrderNumber->FontSize, 'P/O: ' . $MyRow['orderno']);
			if ($PageNumber > 1) {
				$PDF->newPage();
			}
			$PageNumber++;
		} //else not controlled
	} //end of loop around GRNs to print


	$PDF->OutputD($_SESSION['DatabaseName'] . '_GRN_' . $GRNNo . '_' . date('Y-m-d') . '.pdf');
	$PDF->__destruct();
} else { //there were not GRNs to print
	$Title = _('GRN Error');
	include('includes/header.inc');
	prnMsg(_('There were no GRNs to print'), 'warn');
	echo '<br /><a href="' . $RootPath . '/index.php">' . _('Back to the menu') . '</a>';
	include('includes/footer.inc');
}
?>