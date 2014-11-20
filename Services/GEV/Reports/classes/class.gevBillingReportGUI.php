<?php
/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */#

/**
* Report "Billing"
* for Generali
*
* @author	Richard Klees <richard.klees@concepts-and-training.de>
* @version	$Id$
*
*/

require_once("Services/GEV/Reports/classes/class.gevBasicReportGUI.php");

class gevBillingReportGUI extends gevBasicReportGUI{
	public function __construct() {
		
		parent::__construct();

		$this->title = array(
			'title' => 'gev_rep_billing_title',
			'desc' => 'gev_rep_billing_desc',
			'img' => 'GEV_img/ico-head-rep-billing.png'
		);


		$this->table_cols = array
			( array("gev_bill_number", "billnumber")
			, array("status", "participation_status")
			, array("gev_training_fee_pretax_report", "fee_pretax")
			, array("gev_tax", "fee_tax")
			, array("gev_training_fee_posttax_report", "fee_posttax")
			, array("gev_coupon_pretax", "coupon_pretax")
			, array("gev_tax", "coupon_tax")
			, array("gev_coupon_posttax", "coupon_posttax")
			, array("gev_bill_amount_pretax", "amount_pretax")
			, array("gev_tax", "amount_tax")
			, array("gev_bill_amount_posttax", "amount_posttax")
			, array("gev_charged_agency", "cost_center")
			, array("create_date", "bill_finalized_date")
			, array("lastname", "lastname")
			, array("firstname", "firstname")
			, array("gender", "gender")
			, array("gev_org_unit_short", "org_unit")
			, array("gev_event_title", "title")
			, array("gev_number_of_measure", "custom_id")
			, array("gev_training_date", "date")
			, array("gev_venue", "venue")
			, array("", "bill_link")
			);
		
		require_once("Services/UIComponent/Toolbar/interfaces/interface.ilToolbarItem.php");
		require_once("Services/Form/classes/class.ilSubEnabledFormPropertyGUI.php");
		require_once("Services/Form/classes/class.ilDateTimeInputGUI.php");

		$created_since = new ilDateTimeInputGUI($this->lng->txt("gev_created_since").": ", "created_since");
		$created_since->setShowTime(false);
		
		$created_till = new ilDateTimeInputGUI($this->lng->txt("gev_created_till").": ", "created_till");
		$created_till->setShowTime(false);

		$this->filters = array(
			  "created_since" => $created_since
			, "created_till" => $created_till
			);

		$since_date = date("Y")."-01-01";
		if(isset($_POST["created_since"])) {
			$since_date = $_POST["created_since"]["date"]["y"]
					."-".$_POST["created_since"]["date"]["m"]
					."-".$_POST["created_since"]["date"]["d"];
			$_POST["created_since"] = urlencode($since_date);
		}
		
		$till_date = (date("Y") + 1)."-01-01";
		if(isset($_POST["created_till"])) {
			$till_date = $_POST["created_till"]["date"]["y"]
					."-".$_POST["created_till"]["date"]["m"]
					."-".$_POST["created_till"]["date"]["d"];
			$_POST["created_till"] = urlencode($till_date);
		}

		$this->digestSearchParameter("created_since", urlencode($since_date));
		$this->digestSearchParameter("created_till", urlencode($till_date));
		
		$this->created_since = new ilDate($this->filter_params["created_since"], IL_CAL_DATE);
		$created_since->setDate($this->created_since);
		
		$this->created_till = new ilDate($this->filter_params["created_till"], IL_CAL_DATE);
		$created_till->setDate($this->created_till);
		$this->table_row_template= array(
			"filename" => "tpl.gev_billing_row.html", 
			"path" => "Services/GEV/Reports"
		);
	}
	
	protected function getAdditionalFilters() {
		$ret = '';
		foreach ($this->filters as $filter_id =>$filter) {
			$ret .= $filter->getTitle().$filter->render()."<br /><br />";
		}
		return $ret;
	}
	
	protected function userIsPermitted () {
		return $this->user_utils->isAdmin() || $this->permissions->viewBillingReport();
	}

	protected function executeCustomCommand($a_cmd) {
		switch ($a_cmd) {
			case "deliverBillPDF":
				return $this->deliverBillPDF();
			default:
				return null;
		}
	}
	
	protected function fetchData(){
		if ($this->created_till->get(IL_CAL_UNIX) < $this->created_since->get(IL_CAL_UNIX) ) {
			return array();
		}
		
		//fetch retrieves the data 
		require_once("Services/GEV/Utils/classes/class.gevUserUtils.php");
		require_once("Services/Calendar/classes/class.ilDatePresentation.php");

		$no_entry = $this->lng->txt("gev_table_no_entry");
		$user_utils = gevUserUtils::getInstance($this->target_user_id);
		$data = array();


		//when ordering the table, watch out for date!
		//_table_nav=date:asc:0
		//btw, what is the third parameter?
		if(isset($_GET['_table_nav'])){
			$this->external_sorting = true; //set to false again, 
											//if the field is not relevant

			$table_nav_cmd = split(':', $_GET['_table_nav']);
			
			if ($table_nav_cmd[1] == "asc") {
				$direction = " ASC";
			}
			else {
				$direction = " DESC";
			}
			
			switch ($table_nav_cmd[0]) { //field
				case 'date':
					$direction = strtoupper($table_nav_cmd[1]);
					$sql_order_str = " ORDER BY crs.begin_date ";
					$sql_order_str .= $direction;
					break;
				
				//append more fields, simply for performance...

				default:
					$this->external_sorting = true;
					$sql_order_str = " ORDER BY ".$this->db->quoteIdentifier($table_nav_cmd[0])." ".$direction;
					break;
			}
		}

		$query = 	 "SELECT  bill.bill_number as billnumber"
					."		, IF(usrcrs.participation_status='fehlt entschuldigt' OR usrcrs.booking_status='kostenpflichtig storniert',
								 'fehlt entschuldigt/kostenpflichtig storniert', usrcrs.participation_status) as participation_status"
					."		, ROUND(SUM(IF(item.billitem_context_id = bill.bill_context_id, item.billitem_pta, 0)), 2) as fee_pretax"
					."		, ROUND(SUM(IF(item.billitem_context_id = bill.bill_context_id, item.billitem_pta, 0)) * bill.bill_vat/100, 2) as fee_tax"
					."		, ROUND(SUM(IF(item.billitem_context_id = bill.bill_context_id, item.billitem_pta, 0)) * (1 + bill.bill_vat/100), 2) as fee_posttax"
					."		, ROUND(SUM(IF(item.billitem_context_id IS NULL, item.billitem_pta, 0)), 2) as coupon_pretax"
					."		, ROUND(SUM(IF(item.billitem_context_id IS NULL, item.billitem_pta, 0)) * bill.bill_vat/100, 2) as coupon_tax"
					."		, ROUND(SUM(IF(item.billitem_context_id IS NULL, item.billitem_pta, 0)) * (1 + bill.bill_vat/100), 2) as coupon_posttax"
					."		, ROUND(SUM(item.billitem_pta), 2) as amount_pretax"
					."		, ROUND(SUM(item.billitem_pta) * bill.bill_vat/100, 2) as amount_tax"
					."		, ROUND(SUM(item.billitem_pta) * (1 + bill.bill_vat/100), 2) as amount_posttax"
					."		, bill.bill_cost_center as cost_center"
					."      , DATE_FORMAT(FROM_UNIXTIME(bill.bill_finalized_date), '%d.%m.%Y') as bill_finalized_date"
					."		, usr.firstname as lastname"
					."		, usr.lastname as firstname"
					." 		, usr.gender as gender"
					."		, usr.org_unit as org_unit"
					."		, crs.title as title"
					."		, crs.custom_id as custom_id"
					."		, crs.begin_date as begin_date"
					."		, crs.end_date as end_date"
					."		, crs.venue as venue"
					." FROM  bill "
					." INNER JOIN hist_course crs ON crs.crs_id = bill.bill_context_id AND crs.hist_historic = 0"
					." INNER JOIN hist_user usr ON usr.user_id = bill.bill_usr_id AND usr.hist_historic = 0"
					." INNER JOIN hist_usercoursestatus usrcrs ON usrcrs.usr_id = bill.bill_usr_id AND usrcrs.crs_id = bill.bill_context_id AND usrcrs.hist_historic = 0"
					." RIGHT JOIN billitem item ON bill.bill_pk = item.bill_fk"
					." WHERE bill.bill_final = 1"
					. $this->queryWhen($this->start_date, $this->end_date)
					. $this->queryCreatedSince()
					. $this->queryCreatedTill()
					." GROUP BY bill.bill_number"
					. $sql_order_str
					;

		$bill_link_icon = '<img src="'.ilUtil::getImagePath("GEV_img/ico-key-get_bill.png").'" />';

		$res = $this->db->query($query);

		while($rec = $this->db->fetchAssoc($res)) {
			/*	
				modify record-entries here.
			*/			
			foreach ($rec as $key => $value) {
				
				if ($value == '-empty-' || $value == -1) {
					$rec[$key] = $no_entry;
					continue;
				}

				//date
				if( $rec["begin_date"] && $rec["end_date"] 
					&& ($rec["begin_date"] != '0000-00-00' && $rec["end_date"] != '0000-00-00' )
					){
					$start = new ilDate($rec["begin_date"], IL_CAL_DATE);
					$end = new ilDate($rec["end_date"], IL_CAL_DATE);
					$date = '<nobr>' .ilDatePresentation::formatPeriod($start,$end) .'</nobr>';
					//$date = ilDatePresentation::formatPeriod($start,$end);
				} else {
					$date = '-';
				}
				$rec['date'] = $date;
			}
			
			$this->ctrl->setParameter($this, "billnumber", $rec["billnumber"]);
			$target = $this->ctrl->getLinkTarget($this, "deliverBillPDF");
			//$this->ctrl->clearParameters();
			$this->ctrl->setParameter($this, "billnumber", null);
			$rec["bill_link"] = "<a href=\"".$target."\">".$bill_link_icon."</a>";
			
			$data[] = $rec;
		}

		return $data;
	}

	protected function queryWhen(ilDate $start, ilDate $end) {
		if ($this->query_when === null) {
			$this->query_when =
					 //" WHERE usr.user_id = ".$this->db->quote($this->target_user_id, "integer")
					//"  WHERE ".$this->db->in("usrcrs.function", array("Mitglied", "Teilnehmer", "Member"), false, "text")
					//."   AND ".$this->db->in("usrcrs.booking_status", array("gebucht", "kostenpflichtig storniert", "kostenfrei storniert"), false, "text")
					"   AND usrcrs.hist_historic = 0 "
					."   AND ( usrcrs.end_date >= ".$this->db->quote($start->get(IL_CAL_DATE), "date")
					."        OR usrcrs.end_date = '-empty-' OR usrcrs.end_date = '0000-00-00')"
					."   AND usrcrs.begin_date <= ".$this->db->quote($end->get(IL_CAL_DATE), "date")
					;
		}
		
		return $this->query_when;
	}
	
	protected function queryCreatedSince() {
		return "   AND bill.bill_finalized_date >= ".$this->db->quote($this->created_since->get(IL_CAL_UNIX), "integer"); 
	}
	
	protected function queryCreatedTill() {
		// we need to add one day, since unix-timestamp will be at the start of the
		// day, thus we do not get the bills created on said day.
		$date = new ilDate($this->created_till->get(IL_CAL_UNIX), IL_CAL_UNIX);
		$date->increment(ilDateTime::DAY, 1);
		return "   AND bill.bill_finalized_date <= ".$this->db->quote($this->created_till->get(IL_CAL_UNIX), "integer");
	}
	
	protected function deliverBillPDF() {
		$billnumber = $_GET["billnumber"];
		if (!preg_match("/\d{6}-\d{5}/", $billnumber)) {
			throw Exception("gevBillingReportGUI::deliverBillPDF: This is no billnumber: '".$billnumber."'");
		}
		
		require_once("Services/Utilities/classes/class.ilUtil.php");
		require_once("Services/GEV/Utils/classes/class.gevBillStorage.php");
		$filename = gevBillStorage::getInstance()->getPathByBillNumber($billnumber);
		ilUtil::deliverFile($filename, $billnumber.".pdf", "application/pdf");
	}

	//_process_ will modify record entries
	// xls means: only for Excel-Export
	// date is the key in data-array 
	protected function _process_xls_date($val) {
		$val = str_replace('<nobr>', '', $val);
		$val = str_replace('</nobr>', '', $val);
		return $val;
	}
}

?>
