<?php
namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Zend\Db\Adapter\Adapter;
use Zend\Authentication\Result;

use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Session\Container;
use Zend\Form\Element;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;

class Qualifier extends AbstractHelper implements ServiceLocatorAwareInterface
{
    protected $connection = null;
    protected $sRowId ="";
    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return CustomHelper
     */
    public function __construct()
    {
        $this->auth = new AuthenticationService();
    }
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }
    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
    public function getQualifier($qualList,$type='') {

        $sQual = "Qual";
        if ($type =="R") $sQual = "QualR";

        $iRowCount=0;
        $dToatlQualAmt=0;

        $html_output = '<table class="table" style=" margin-bottom:0px;">
                        <thead>
                              <tr>
                               <th class="red-ths">&nbsp;</th>
                               <th class="red-ths">Ref No</th>
                               <th class="red-ths">Description</th>
                               <th class="red-ths">Expression</th>
                               <th style="text-align:right" class="red-ths">Base Value</th>
                               <th style="text-align:right" class="red-ths">Percentage (%)</th>
                               <th style="text-align:right" class="red-ths text-right">Amount</th>
                               <th class="red-ths">&nbsp;</th>
                              </tr>
                         </thead>
                         <tbody>';

        foreach($qualList as $row){
            $iRowCount = $iRowCount+1;
            $sChecked = "";
            if ($row['YesNo'] == "1") $sChecked = "checked";
            $html_output .= '<tr class="padi05 dont-hide">
                <td width="1%"><label>
                  <input type="checkbox" name="' . $sQual . '__1_YesNo_' . $iRowCount . '" id="' . $sQual . '__1_YesNo_' . $iRowCount . '"  class="ios_checkbox qualmainTrInput qualChange" '.$sChecked.'/>
                  <div class="ios_switch"><span></span></div>
                </label></td>
                <td width="2%"><input class="parent_text red-non qualmainTrInput" type="text" name="Qual__1_Ref_' . $iRowCount . '" id="' . $sQual . '__1_Ref_' . $iRowCount . '" value="' . $row['RefId'] .'" readonly/></td>
                <input type="hidden" name="' . $sQual . '__1_Id_' . $iRowCount . '" id="' . $sQual . '__1_Id_' . $iRowCount . '" value="' . $row['QualifierId'] .'"/>
                <input type="hidden"  class="qualTypeId" name="' . $sQual . '__1_TypeId_' . $iRowCount . '" id="' . $sQual . '__1_TypeId_' . $iRowCount . '" value="' . $row['QualifierTypeId'] .'"/>
                <td width="10%"><input class="parent_text red-non qualmainTrInput" type="text" name="' . $sQual . '__1_Desc_' . $iRowCount . '" id="' . $sQual . '__1_Desc_' . $iRowCount . '" value="' . $row['QualifierName'] .'" readonly/></td>
                <td width="10%"><input class="parent_text qualmainTrInput qualChange" type="text" name="' . $sQual . '__1_Exp_' . $iRowCount . '" id="' . $sQual . '__1_Exp_' . $iRowCount . '" value="' . $row['Expression'] .'" onkeypress="return isFormula(event);" /></td>
                <td width="10%"><input class="parent_padi05 text-right qualmainTrInput base" type="text" name="' . $sQual . '__1_ExpValue_' . $iRowCount . '" id="' . $sQual . '__1_ExpValue_' . $iRowCount . '" value="' . CommonHelper::sanitizeNumber($row['ExpressionAmt'],2,true) .'" readonly /></td>
                <td width="2%"><input class="parent_text text-right qualmainTrInput qualChange" type="text" name="' . $sQual . '__1_ExpPer_' . $iRowCount . '" id="' . $sQual . '__1_ExpPer_' . $iRowCount . '" value="' . CommonHelper::sanitizeNumber($row['ExpPer'],2,true) .'"  onkeypress="return isDecimal(event,this)"/></td>
                <td width="15%" style="position:relative;"><input class="parent_padi05 text-right qualmainTrInput signCh" type="text" name="' . $sQual . '__1_Amount_' . $iRowCount . '" id="' . $sQual . '__1_Amount_' . $iRowCount . '" value="' . CommonHelper::sanitizeNumber($row['NetAmt'],2,true) .'" readonly/><span class="pl-mi"><i id ="' . $sQual . '__1_SignC_' . $iRowCount . '"';
            if ($row['Sign'] == "-") $html_output .= 'class="fa fa-minus clr-red"';
            else $html_output .= 'class="fa fa-plus clr-gre"';

            $html_output .= 'onclick = "signChange(this)" ></i></span></td>
                <input type="hidden" name="' . $sQual . '__1_Sign_' . $iRowCount . '" id="' . $sQual . '__1_Sign_' . $iRowCount . '" value="' . $row['Sign'] .'"/>';

            if ($row['QualifierTypeId'] == 1 || $row['QualifierTypeId'] == 2) {
                $html_output .=  '<td width = "4%" class="action_btns_td" ><ul class="action_btns qualExpandbut" >
                    <li > <a href = "#" class="qualmainExp" > <span data - original - title = "Add lines" data - placement = "left" data - toggle = "tooltip" ><i class="fa fa-chevron-circle-down" ></i ></span ></a > </li >
                    </ul ></td >';
            }
            $html_output .= '</tr>';

            if ($row['QualifierTypeId'] == 1) {
                $html_output .= '<tr style="display:none;" class="qualsubTr">
                                                <td colspan="8" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                                                    <div class="col-lg-12">
                                                      <div class="table-responsive topsp">
                                                        <table class="table" style="margin-bottom:0px;">
                                                          <thead>
                                                            <tr>
                                                              <th class="red-ths">&nbsp;</th>
                                                              <th style="text-align:right" class="red-ths">Taxable</th>
                                                              <th style="text-align:right" class="red-ths">Tax</th>
                                                              <th style="text-align:right" class="red-ths">Cess</th>
                                                              <th style="text-align:right" class="red-ths">Edu.Cess</th>
                                                              <th style="text-align:right" class="red-ths">H.Edu.cess</th>
                                                              <th style="text-align:right" class="red-ths">Net</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody>
                                                            <tr class="padi05">
                                                              <td width="8%">Percentage (%)</td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" id="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['TaxablePer'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxPer_' . $iRowCount . '" id="' . $sQual . '__1_TaxPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['TaxPer'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_CessPer_' . $iRowCount . '" id="' . $sQual . '__1_CessPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['SurCharge'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_EduCessPer_' . $iRowCount . '" id="' . $sQual . '__1_EduCessPer_' . $iRowCount . '"  value="'. CommonHelper::sanitizeNumber($row['EDCess'],2).'" onkeypress="return isDecimal(event,this)"/></td>
                                                              <td width="10%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_HEduCessPer_' . $iRowCount . '" id="' . $sQual . '__1_HEduCessPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['HEDCess'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetPer_' . $iRowCount . '" id="' . $sQual . '__1_NetPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['NetPer'],2).'" onchange="netPer(this)" readonly /></td>
                                                            </tr>
                                                            <tr class="padi05">
                                                              <td width="8%">Amount</td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxableAmt'],2,true) . '" readonly /></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxAmt'], 2,true) . '" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_CessAmt_' . $iRowCount . '" id="' . $sQual . '__1_CessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['SurChargeAmt'], 2,true) . '" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_EduCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_EduCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['EDCessAmt'], 2,true) . '" readonly/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_HEduCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_HEduCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['HEDCessAmt'], 2,true) . '" readonly/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetAmt_' . $iRowCount . '" id="' . $sQual . '__1_NetAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['NetAmt'], 2,true) . '" onchange="netAmt(this)" readonly /></td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>
                                                  </div></td>
                                              </tr>';
            } else if  ($row['QualifierTypeId'] == 2) {

                $html_output .= '<tr style="display:none;" class="qualsubTr">
                                                <td colspan="8" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                                                    <div class="col-lg-12">
                                                      <div class="table-responsive topsp">
                                                        <table class="table" style="margin-bottom:0px;">
                                                          <thead>
                                                            <tr>
                                                              <th class="red-ths">&nbsp;</th>
                                                              <th style="text-align:right" class="red-ths">Taxable</th>
                                                              <th style="text-align:right" class="red-ths">Tax</th>
                                                              <th style="text-align:right" class="red-ths">KKCess</th>
                                                              <th style="text-align:right" class="red-ths">SB Cess</th>
                                                              <th style="text-align:right" class="red-ths">Net</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody>
                                                            <tr class="padi05">
                                                              <td width="8%">Percentage (%)</td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" id="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxablePer'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxPer_' . $iRowCount . '" id="' . $sQual . '__1_TaxPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxPer'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_KKCessPer_' . $iRowCount . '" id="' . $sQual . '__1_KKCessPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['KKCess'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_SBCessPer_' . $iRowCount . '" id="' . $sQual . '__1_SBCessPer_' . $iRowCount . '"  value="' .  CommonHelper::sanitizeNumber($row['SBCess'],2) .'" onkeypress="return isDecimal(event,this)"/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetPer_' . $iRowCount . '" id="' . $sQual . '__1_NetPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['NetPer'],2) .'" readonly /></td>
                                                            </tr>
                                                            <tr class="padi05">
                                                              <td width="8%">Amount</td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxableAmt'],2,true) .'" readonly /></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxAmt'],2,true) .'" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_KKCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_KKCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['KKCessAmt'],2,true) .'" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_SBCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_SBCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['SBCessAmt'],2,true) .'" readonly/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetAmt_' . $iRowCount . '" id="' . $sQual . '__1_NetAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['NetAmt'],2,true) .'" readonly /></td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>
                                                  </div></td>
                                              </tr>';
            }

            $dToatlQualAmt = $dToatlQualAmt + floatval($row['NetAmt']);
        }

        $html_output .=  '<tr class="dont-hide">
                                 <td colspan="5">&nbsp;</td>
                                 <td align="right" class="rate_pri">Total</td>
                                 <td width="8%" ><input class="parent_text text-right total-clr" type="text" name="' . $sQual . 'TotalAmt__1" id="' . $sQual . 'TotalAmt__1"  value="'. CommonHelper::sanitizeNumber($dToatlQualAmt,2,true) .'" readonly/></td>
                                 <input type="hidden" name="' . $sQual . 'RowId__1" id="' . $sQual . 'RowId__1" value="' .$iRowCount . '"/>
                                 <input type="hidden" name="' . $sQual . 'RowRef__1" id="' . $sQual . 'RowRef__1" value=""/>
                                 <td width="1%">&nbsp;</td>
                                </tr>
                               </tbody>
                            </table>';
        return $html_output;
    }

    public function getQualifierG($qualList) {

        $sQual = "QualG";

        $iRowCount=0;

        $html_output = '<table class="table" style=" margin-bottom:0px;">
                        <thead>
                              <tr>
                               <th class="red-ths">&nbsp;</th>
                               <th class="red-ths">Ref No</th>
                               <th class="red-ths">Description</th>
                               <th class="red-ths">Expression</th>
                               <th style="text-align:right" class="red-ths">Percentage (%)</th>
                               <th class="red-ths">&nbsp;</th>
                              </tr>
                         </thead>
                         <tbody>';

        foreach($qualList as $row){
            $iRowCount = $iRowCount+1;
            $sChecked = "";
            if ($row['YesNo'] == "1") $sChecked = "checked";
            $html_output .= '<tr class="padi05">
                <td width="1%"><label>
                  <input type="checkbox" name="' . $sQual . '_1_YesNo_' . $iRowCount . '" id="' . $sQual . '_1_YesNo_' . $iRowCount . '" class="ios_checkbox qualmainTrInput" '.$sChecked.'/>
                  <div class="ios_switch"><span></span></div>
                </label></td>
                <td width="2%"><input class="parent_text red-non qualmainTrInput" type="text" name="Qual_1_Ref_' . $iRowCount . '" id="' . $sQual . '_1_Ref_' . $iRowCount . '" value="' . $row['RefId'] .'" readonly/></td>
                <input type="hidden" name="' . $sQual . '_1_Id_' . $iRowCount . '" id="' . $sQual . '_1_Id_' . $iRowCount . '" value="' . $row['QualifierId'] .'"/>
                <input type="hidden"  class="qualTypeId" name="' . $sQual . '_1_TypeId_' . $iRowCount . '" id="' . $sQual . '_1_TypeId_' . $iRowCount . '" value="' . $row['QualifierTypeId'] .'"/>
                <td width="10%"><input class="parent_text red-non qualmainTrInput" type="text" name="' . $sQual . '_1_Desc_' . $iRowCount . '" id="' . $sQual . '_1_Desc_' . $iRowCount . '" value="' . $row['QualifierName'] .'" readonly/></td>
                <td width="10%"><input class="parent_text qualmainTrInput" type="text" name="' . $sQual . '_1_Exp_' . $iRowCount . '" id="' . $sQual . '_1_Exp_' . $iRowCount . '" value="' . $row['Expression'] .'" onkeypress="return isFormula(event);" /></td>
                <td width="10%" style="position:relative;"><input class="parent_text text-right qualmainTrInput" type="text" name="' . $sQual . '_1_ExpPer_' . $iRowCount . '" id="' . $sQual . '_1_ExpPer_' . $iRowCount . '" value="' . CommonHelper::sanitizeNumber($row['ExpPer'],2,true) .'" onkeypress="return isDecimal(event,this)"/><span class="pl-mi"><i id ="' . $sQual . '_1_SignC_' . $iRowCount . '"';

            if ($row['Sign'] == "-") $html_output .= 'class="fa fa-minus clr-red"';
            else $html_output .= 'class="fa fa-plus clr-gre"';

            $html_output .= 'onclick = "signChange(this)" ></i></span></td>
                <input type="hidden" name="' . $sQual . '_1_Sign_' . $iRowCount . '" id="' . $sQual . '_1_Sign_' . $iRowCount . '" value="' . $row['Sign'] .'"/>';

            if ($row['QualifierTypeId'] == 1 || $row['QualifierTypeId'] == 2) {
                $html_output .=  '<td width = "4%" class="action_btns_td" ><ul class="action_btns qualExpandbut" >
                    <li > <a href = "#" class="qualmainExp" > <span data - original - title = "Add lines" data - placement = "left" data - toggle = "tooltip" ><i class="fa fa-chevron-circle-down" ></i ></span ></a > </li >
                    </ul ></td >';
            }
            $html_output .= '</tr>';

            if ($row['QualifierTypeId'] == 1) {
                $html_output .= '<tr style="display:none;" class="qualsubTr">
                                                <td colspan="6" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                                                    <div class="col-lg-12">
                                                      <div class="table-responsive topsp">
                                                        <table class="table" style="margin-bottom:0px;">
                                                          <thead>
                                                            <tr>
                                                              <th class="red-ths">&nbsp;</th>
                                                              <th style="text-align:right" class="red-ths">Taxable</th>
                                                              <th style="text-align:right" class="red-ths">Tax</th>
                                                              <th style="text-align:right" class="red-ths">Cess</th>
                                                              <th style="text-align:right" class="red-ths">Edu.Cess</th>
                                                              <th style="text-align:right" class="red-ths">H.Edu.cess</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody>
                                                            <tr class="padi05">
                                                              <td width="8%">Percentage (%)</td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_TaxablePer_' . $iRowCount . '" id="' . $sQual . '_1_TaxablePer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['TaxablePer'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_TaxPer_' . $iRowCount . '" id="' . $sQual . '_1_TaxPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['TaxPer'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_CessPer_' . $iRowCount . '" id="' . $sQual . '_1_CessPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['SurCharge'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_EduCessPer_' . $iRowCount . '" id="' . $sQual . '_1_EduCessPer_' . $iRowCount . '"  value="'. CommonHelper::sanitizeNumber($row['EDCess'],2).'" onkeypress="return isDecimal(event,this)"/></td>
                                                              <td width="10%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_HEduCessPer_' . $iRowCount . '" id="' . $sQual . '_1_HEduCessPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['HEDCess'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>
                                                  </div></td>
                                              </tr>';
            } else if  ($row['QualifierTypeId'] == 2) {

                $html_output .= '<tr style="display:none;" class="qualsubTr">
                                                <td colspan="6" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                                                    <div class="col-lg-12">
                                                      <div class="table-responsive topsp">
                                                        <table class="table" style="margin-bottom:0px;">
                                                          <thead>
                                                            <tr>
                                                              <th class="red-ths">&nbsp;</th>
                                                              <th style="text-align:right" class="red-ths">Taxable</th>
                                                              <th style="text-align:right" class="red-ths">Tax</th>
                                                              <th style="text-align:right" class="red-ths">KKCess</th>
                                                              <th style="text-align:right" class="red-ths">SB Cess</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody>
                                                            <tr class="padi05">
                                                              <td width="8%">Percentage (%)</td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_TaxablePer_' . $iRowCount . '" id="' . $sQual . '_1_TaxablePer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxablePer'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_TaxPer_' . $iRowCount . '" id="' . $sQual . '_1_TaxPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxPer'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_KKCessPer_' . $iRowCount . '" id="' . $sQual . '_1_KKCessPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['KKCess'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right" type="text" name="' . $sQual . '_1_SBCessPer_' . $iRowCount . '" id="' . $sQual . '_1_SBCessPer_' . $iRowCount . '"  value="' .  CommonHelper::sanitizeNumber($row['SBCess'],2) .'" onkeypress="return isDecimal(event,this)"/></td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>
                                                  </div></td>
                                              </tr>';
            }

        }

        $html_output .=  '</tbody>
                            </table>';
        return $html_output;
    }
    public function getFAQualifier($qualList,$type='') {

        $sQual = "Qual";
        if ($type =="R") $sQual = "QualR";

        $iRowCount=0;
        $dToatlQualAmt=0;

        $html_output = '<table class="table" style=" margin-bottom:0px;">
                        <thead>
                              <tr>
                               <th class="red-ths">&nbsp;</th>
                               <th class="red-ths">Ref No</th>
                               <th class="red-ths">Description</th>
                               <th class="red-ths">Expression</th>
                               <th style="text-align:right" class="red-ths">Base Value</th>
                               <th style="text-align:right" class="red-ths">Percentage (%)</th>
                               <th style="text-align:right" class="red-ths text-right">Amount</th>
                               <th class="red-ths">&nbsp;</th>
                              </tr>
                         </thead>
                         <tbody>';

        foreach($qualList as $row){
            $iRowCount = $iRowCount+1;
            $sChecked = "";
            if ($row['YesNo'] == "1") $sChecked = "checked";
            $html_output .= '<tr class="padi05 dont-hide">
                <td width="1%"><label>
                  <input type="checkbox" name="' . $sQual . '__1_YesNo_' . $iRowCount . '" id="' . $sQual . '__1_YesNo_' . $iRowCount . '"  class="ios_checkbox qualmainTrInput qualChange" '.$sChecked.'/>
                  <div class="ios_switch"><span></span></div>
                </label></td>
                <td width="2%"><input class="parent_text red-non qualmainTrInput" type="text" name="Qual__1_Ref_' . $iRowCount . '" id="' . $sQual . '__1_Ref_' . $iRowCount . '" value="' . $row['RefId'] .'" readonly/></td>
                <input type="hidden" name="' . $sQual . '__1_Id_' . $iRowCount . '" id="' . $sQual . '__1_Id_' . $iRowCount . '" value="' . $row['QualifierId'] .'"/>
                <input type="hidden"  class="qualTypeId" name="' . $sQual . '__1_TypeId_' . $iRowCount . '" id="' . $sQual . '__1_TypeId_' . $iRowCount . '" value="' . $row['QualifierTypeId'] .'"/>
                <input type="hidden" name="' . $sQual . '__1_TaxAccountId_' . $iRowCount . '" id="' . $sQual . '__1_TaxAccountId_' . $iRowCount . '" value="' . $row['TaxAccountId'] .'"/>
                <input type="hidden" name="' . $sQual . '__1_TaxSubLedgerId_' . $iRowCount . '" id="' . $sQual . '__1_TaxSubLedgerId_' . $iRowCount . '" value="' . $row['TaxSubLedgerId'] .'"/>
                <td width="10%"><input class="parent_text red-non qualmainTrInput" type="text" name="' . $sQual . '__1_Desc_' . $iRowCount . '" id="' . $sQual . '__1_Desc_' . $iRowCount . '" value="' . $row['QualifierName'] .'" readonly/></td>
                <td width="10%"><input class="parent_text qualmainTrInput qualChange" type="text" name="' . $sQual . '__1_Exp_' . $iRowCount . '" id="' . $sQual . '__1_Exp_' . $iRowCount . '" value="' . $row['Expression'] .'" onkeypress="return isFormula(event);" /></td>
                <td width="10%"><input class="parent_padi05 text-right qualmainTrInput base" type="text" name="' . $sQual . '__1_ExpValue_' . $iRowCount . '" id="' . $sQual . '__1_ExpValue_' . $iRowCount . '" value="' . CommonHelper::sanitizeNumber($row['ExpressionAmt'],2,true) .'" readonly /></td>
                <td width="2%"><input class="parent_text text-right qualmainTrInput qualChange" type="text" name="' . $sQual . '__1_ExpPer_' . $iRowCount . '" id="' . $sQual . '__1_ExpPer_' . $iRowCount . '" value="' . CommonHelper::sanitizeNumber($row['ExpPer'],2,true) .'"  onkeypress="return isDecimal(event,this)"/></td>
                <td width="15%" style="position:relative;"><input class="parent_padi05 text-right qualmainTrInput signCh" type="text" name="' . $sQual . '__1_Amount_' . $iRowCount . '" id="' . $sQual . '__1_Amount_' . $iRowCount . '" value="' . CommonHelper::sanitizeNumber($row['NetAmt'],2,true) .'" readonly/><span class="pl-mi"><i id ="' . $sQual . '__1_SignC_' . $iRowCount . '"';
            if ($row['Sign'] == "-") $html_output .= 'class="fa fa-minus clr-red"';
            else $html_output .= 'class="fa fa-plus clr-gre"';

            $html_output .= 'onclick = "signChange(this)" ></i></span></td>
                <input type="hidden" name="' . $sQual . '__1_Sign_' . $iRowCount . '" id="' . $sQual . '__1_Sign_' . $iRowCount . '" value="' . $row['Sign'] .'"/>';

            if ($row['QualifierTypeId'] == 1 || $row['QualifierTypeId'] == 2) {
                $html_output .=  '<td width = "4%" class="action_btns_td" ><ul class="action_btns qualExpandbut" >
                    <li > <a href = "#" class="qualmainExp" > <span data - original - title = "Add lines" data - placement = "left" data - toggle = "tooltip" ><i class="fa fa-chevron-circle-down" ></i ></span ></a > </li >
                    </ul ></td >';
            }
            $html_output .= '</tr>';

            if ($row['QualifierTypeId'] == 1) {
                $html_output .= '<tr style="display:none;" class="qualsubTr">
                                                <td colspan="8" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                                                    <div class="col-lg-12">
                                                      <div class="table-responsive topsp">
                                                        <table class="table" style="margin-bottom:0px;">
                                                          <thead>
                                                            <tr>
                                                              <th class="red-ths">&nbsp;</th>
                                                              <th style="text-align:right" class="red-ths">Taxable</th>
                                                              <th style="text-align:right" class="red-ths">Tax</th>
                                                              <th style="text-align:right" class="red-ths">Cess</th>
                                                              <th style="text-align:right" class="red-ths">Edu.Cess</th>
                                                              <th style="text-align:right" class="red-ths">H.Edu.cess</th>
                                                              <th style="text-align:right" class="red-ths">Net</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody>
                                                            <tr class="padi05">
                                                              <td width="8%">Percentage (%)</td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" id="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['TaxablePer'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxPer_' . $iRowCount . '" id="' . $sQual . '__1_TaxPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['TaxPer'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_CessPer_' . $iRowCount . '" id="' . $sQual . '__1_CessPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['SurCharge'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_EduCessPer_' . $iRowCount . '" id="' . $sQual . '__1_EduCessPer_' . $iRowCount . '"  value="'. CommonHelper::sanitizeNumber($row['EDCess'],2).'" onkeypress="return isDecimal(event,this)"/></td>
                                                              <td width="10%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_HEduCessPer_' . $iRowCount . '" id="' . $sQual . '__1_HEduCessPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['HEDCess'],2).'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetPer_' . $iRowCount . '" id="' . $sQual . '__1_NetPer_' . $iRowCount . '" value="'. CommonHelper::sanitizeNumber($row['NetPer'],2).'" onchange="netPer(this)" readonly /></td>
                                                            </tr>
                                                            <tr class="padi05">
                                                              <td width="8%">Amount</td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxableAmt'],2,true) . '" readonly /></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxAmt'], 2,true) . '" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_CessAmt_' . $iRowCount . '" id="' . $sQual . '__1_CessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['SurChargeAmt'], 2,true) . '" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_EduCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_EduCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['EDCessAmt'], 2,true) . '" readonly/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_HEduCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_HEduCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['HEDCessAmt'], 2,true) . '" readonly/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetAmt_' . $iRowCount . '" id="' . $sQual . '__1_NetAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['NetAmt'], 2,true) . '" onchange="netAmt(this)" readonly /></td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>
                                                  </div></td>
                                              </tr>';
            } else if  ($row['QualifierTypeId'] == 2) {

                $html_output .= '<tr style="display:none;" class="qualsubTr">
                                                <td colspan="8" style="padding:0px !important; "><div class="subDiv" style="display:none;">
                                                    <div class="col-lg-12">
                                                      <div class="table-responsive topsp">
                                                        <table class="table" style="margin-bottom:0px;">
                                                          <thead>
                                                            <tr>
                                                              <th class="red-ths">&nbsp;</th>
                                                              <th style="text-align:right" class="red-ths">Taxable</th>
                                                              <th style="text-align:right" class="red-ths">Tax</th>
                                                              <th style="text-align:right" class="red-ths">KKCess</th>
                                                              <th style="text-align:right" class="red-ths">SB Cess</th>
                                                              <th style="text-align:right" class="red-ths">Net</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody>
                                                            <tr class="padi05">
                                                              <td width="8%">Percentage (%)</td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" id="' . $sQual . '__1_TaxablePer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxablePer'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_TaxPer_' . $iRowCount . '" id="' . $sQual . '__1_TaxPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxPer'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_KKCessPer_' . $iRowCount . '" id="' . $sQual . '__1_KKCessPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['KKCess'],2) .'" onkeypress="return isDecimal(event,this)" /></td>
                                                              <td width="8%"><input class="parent_text text-right qualChange" type="text" name="' . $sQual . '__1_SBCessPer_' . $iRowCount . '" id="' . $sQual . '__1_SBCessPer_' . $iRowCount . '"  value="' .  CommonHelper::sanitizeNumber($row['SBCess'],2) .'" onkeypress="return isDecimal(event,this)"/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetPer_' . $iRowCount . '" id="' . $sQual . '__1_NetPer_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['NetPer'],2) .'" readonly /></td>
                                                            </tr>
                                                            <tr class="padi05">
                                                              <td width="8%">Amount</td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxableAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxableAmt'],2,true) .'" readonly /></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" id="' . $sQual . '__1_TaxPerAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['TaxAmt'],2,true) .'" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_KKCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_KKCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['KKCessAmt'],2,true) .'" readonly/></td>
                                                              <td width="8%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_SBCessAmt_' . $iRowCount . '" id="' . $sQual . '__1_SBCessAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['SBCessAmt'],2,true) .'" readonly/></td>
                                                              <td width="10%"><input class="parent_padi05 text-right" type="text" name="' . $sQual . '__1_NetAmt_' . $iRowCount . '" id="' . $sQual . '__1_NetAmt_' . $iRowCount . '" value="' .  CommonHelper::sanitizeNumber($row['NetAmt'],2,true) .'" readonly /></td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>
                                                  </div></td>
                                              </tr>';
            }

            $dToatlQualAmt = $dToatlQualAmt + floatval($row['NetAmt']);
        }

        $html_output .=  '<tr class="dont-hide">
                                 <td colspan="5">&nbsp;</td>
                                 <td align="right" class="rate_pri">Total</td>
                                 <td width="8%" ><input class="parent_text text-right total-clr" type="text" name="' . $sQual . 'TotalAmt__1" id="' . $sQual . 'TotalAmt__1"  value="'. CommonHelper::sanitizeNumber($dToatlQualAmt,2,true) .'" readonly/></td>
                                 <input type="hidden" name="' . $sQual . 'RowId__1" id="' . $sQual . 'RowId__1" value="' .$iRowCount . '"/>
                                 <input type="hidden" name="' . $sQual . 'RowRef__1" id="' . $sQual . 'RowRef__1" value=""/>
                                 <td width="1%">&nbsp;</td>
                                </tr>
                               </tbody>
                            </table>';
        return $html_output;
    }
}
?>