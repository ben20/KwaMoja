<?php

/* definition of the Contract class */

class Contract {

	var $ContractNo;
	/*auto generated contract no - but there for existing contracts */
	var $ContractRef;
	/*the contract short description used for stockid when contract submitted for quotation */
	var $ContractDescription;
	/*the description of the contract */
	var $DebtorNo;
	/*the customer that the contract is for */
	var $CustomerName;
	var $BranchCode;
	var $BranchName;
	var $Status;
	/* 100 = initiated - 1=quoted - 2=ordered - 3=completed */
	var $CategoryID;
	/* the category where the contract will be when converted to an item  for quotation*/
	var $OrderNo;
	/* the order number created when the contract is quoted */
	var $LocCode;
	/* the inventory location where the contract is to be performed */
	var $CustomerRef;
	var $Margin;
	/*the margin used in quoting for the contract */
	var $WO;
	/*the wo created when the quotation is converted to an order */
	var $RequiredDate;
	var $Drawing;
	/*a link to the contract drawing*/
	var $CurrCode;
	/*the currency of the customer to quote in */
	var $ExRate;
	/*the rate of exchange between customer currency and company functional currency used when quoting */
	var $BOMComponentCounter;
	var $RequirementsCounter;

	var $ContractBOM;
	/*array of stockid components  required for the contract */
	var $ContractReqts;
	/*array of other items required for the contract */

	function Contract() {
		/*Constructor function initialises a new Payment batch */
		$this->ContractBOM = array();
		$this->ContractReqts = array();
		$this->BOMComponentCounter = 0;
		$this->RequirementsCounter = 0;
		$this->Status = 0;
	}

	function Add_To_ContractBOM($StockId, $ItemDescription, $WorkCentre, $Quantity, $ItemCost, $UOM, $DecimalPlaces) {

		if (isset($StockId) and $Quantity != 0) {
			$this->ContractBOM[$this->BOMComponentCounter] = new ContractComponent($this->BOMComponentCounter, $StockId, $ItemDescription, $WorkCentre, $Quantity, $ItemCost, $UOM, $DecimalPlaces);
			$this->BOMComponentCounter++;
			return 1;
		}
		return 0;
	}

	function Remove_ContractComponent($ContractComponent_ID) {
		$Result = DB_query("DELETE FROM contractbom
											WHERE contractref='" . $this->ContractRef . "'
											AND stockid='" . $this->ContractBOM[$ContractComponent_ID]->StockID . "'");
		unset($this->ContractBOM[$ContractComponent_ID]);
	}


	/*Requirments Methods */

	function Add_To_ContractRequirements($Requirement, $Quantity, $CostPerUnit, $ContractReqID = 0) {

		if (isset($Requirement) and $Quantity != 0 and $CostPerUnit != 0) {
			$this->ContractReqts[$this->RequirementsCounter] = new ContractRequirement($Requirement, $Quantity, $CostPerUnit, $ContractReqID);
			$this->RequirementsCounter++;
			return 1;
		}
		return 0;
	}

	function Remove_ContractRequirement($ContractRequirementID) {
		$Result = DB_query("DELETE FROM contractreqts WHERE contractreqid='" . $this->ContractReqts[$ContractRequirementID]->ContractReqID . "'");
		unset($this->ContractReqts[$ContractRequirementID]);
	}

}
/* end of class defintion */

class ContractComponent {
	var $ComponentID;
	var $StockId;
	var $ItemDescription;
	var $WorkCentre;
	var $Quantity;
	var $ItemCost;
	var $UOM;
	var $DecimalPlaces;

	function ContractComponent($ComponentID, $StockId, $ItemDescription, $WorkCentre, $Quantity, $ItemCost, $UOM, $DecimalPlaces = 0) {

		/* Constructor function to add a new Contract Component object with passed params */
		$this->ComponentID = $ComponentID;
		$this->StockID = $StockId;
		$this->ItemDescription = $ItemDescription;
		$this->WorkCentre = $WorkCentre;
		$this->Quantity = $Quantity;
		$this->ItemCost = $ItemCost;
		$this->UOM = $UOM;
		$this->DecimalPlaces = $DecimalPlaces;
	}
}

class ContractRequirement {

	var $ContractReqID;
	/*Used to hold the database ID of the contractreqtID  - if an existing contract*/
	var $Requirement;
	/*The description of the requirement for the contract */
	var $Quantity;
	var $CostPerUnit;

	function ContractRequirement($Requirement, $Quantity, $CostPerUnit, $ContractReqID = 0) {

		/* Constructor function to add a new Contract Component object with passed params */
		$this->Requirement = $Requirement;
		$this->Quantity = $Quantity;
		$this->CostPerUnit = $CostPerUnit;
		$this->ContractReqID = $ContractReqID;
	}
}
?>