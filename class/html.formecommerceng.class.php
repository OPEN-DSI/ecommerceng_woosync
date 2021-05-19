<?php
/* Copyright (c) 2021       Open-Dsi      <support@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       /htdocs/custom/ecommerceng/class/html.formecommerceng.class.php
 *  \ingroup    ecommerceng
 *	\brief      File of class with all html predefined components for ecommerceng
 */


/**
 *	Class to manage generation of HTML components
 *	Only common components must be here.
 *
 */
class FormECommerceNg
{
	public $db;
	public $error;
	public $num;

	/**
	 * @var FormProduct
	 */
	public static $formproduct;

    /**
     * Constructor
     *
     * @param   DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        if (!isset(self::$formproduct)) {
        	self::$formproduct = new FormProduct($this->db);
		}
    }

	/**
	 *  Return multi list of warehouses
	 *
	 *  @param  array	    $selected           Ids of preselected warehouses ('' for no value, 'ifone'=select value if one value otherwise no value)
	 *  @param  string      $htmlname           Name of html select html
	 *  @param  string      $filterstatus       warehouse status filter, following comma separated filter options can be used
	 *                                          'warehouseopen' = select products from open warehouses,
	 *                                          'warehouseclosed' = select products from closed warehouses,
	 *                                          'warehouseinternal' = select products from warehouses for internal correct/transfer only
	 * 	@param	int		    $disabled		    1=Select is disabled
	 * 	@param	int		    $fk_product		    Add quantity of stock in label for product with id fk_product. Nothing if 0.
	 *  @param	string	    $empty_label	    Empty label if needed (only if $empty=1)
	 *  @param	int		    $showstock		    1=Show stock count
	 *  @param	int	    	$forcecombo		    1=Force combo iso ajax select2
	 *  @param	array	    $events			            Events to add to select2
	 *  @param  string      $morecss                    Add more css classes to HTML select
	 *  @param	string	    $exclude            Warehouses ids to exclude
	 *  @param  int         $showfullpath       1=Show full path of name (parent ref into label), 0=Show only ref of current warehouse
	 *  @param  bool|int    $stockMin           [=false] Value of minimum stock to filter or false not not filter by minimum stock
	 *  @param  string      $orderBy            [='e.ref'] Order by
	 * 	@return string					        HTML multiselect
	 *
	 *  @throws Exception
	 */
	public function multiselectWarehouses($selected = array(), $htmlname = 'idwarehouse', $filterstatus = '', $disabled = 0, $fk_product = 0, $empty_label = '', $showstock = 0, $forcecombo = 0, $events = array(), $morecss = 'minwidth200', $exclude = array(), $showfullpath = 1, $stockMin = false, $orderBy = 'e.ref')
	{
		global $conf;

		$out = '';

		$out .= $this->multiselect_javascript_code($selected, $htmlname);

		$save_conf = $conf->use_javascript_ajax;
		$conf->use_javascript_ajax = 0;
		$out .= self::$formproduct->selectWarehouses(array(), $htmlname, $filterstatus, 0, $disabled, $fk_product, $empty_label, $showstock, $forcecombo, $events, $morecss, $exclude, $showfullpath, $stockMin, $orderBy);
		$conf->use_javascript_ajax = $save_conf;

		return $out;
	}

    /**
     *	Return multiselect javascript code
     *
     *  @param	array	$selected       Preselected values
     *  @param  string	$htmlname       Field name in form
     *  @param	string	$elemtype		Type of element we show ('category', ...)
     *  @return	string
     */
	public function multiselect_javascript_code($selected, $htmlname, $elemtype='')
    {
        global $conf;

        $out = '';

        // Add code for jquery to use multiselect
       	if (! empty($conf->global->MAIN_USE_JQUERY_MULTISELECT) || defined('REQUIRE_JQUERY_MULTISELECT'))
       	{
            $selected = array_values($selected);
       		$tmpplugin=empty($conf->global->MAIN_USE_JQUERY_MULTISELECT)?constant('REQUIRE_JQUERY_MULTISELECT'):$conf->global->MAIN_USE_JQUERY_MULTISELECT;
      			$out.='<!-- JS CODE TO ENABLE '.$tmpplugin.' for id '.$htmlname.' -->
       			<script type="text/javascript">
   	    			function formatResult(record) {'."\n";
   						if ($elemtype == 'category')
   						{
   							$out.='	//return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> <a href="'.DOL_URL_ROOT.'/categories/viewcat.php?type=0&id=\'+record.id+\'">\'+record.text+\'</a></span>\';
   								  	return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> \'+record.text+\'</span>\';';
   						}
   						else
   						{
   							$out.='return record.text;';
   						}
   			$out.= '	};
       				function formatSelection(record) {'."\n";
   						if ($elemtype == 'category')
   						{
   							$out.='	//return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> <a href="'.DOL_URL_ROOT.'/categories/viewcat.php?type=0&id=\'+record.id+\'">\'+record.text+\'</a></span>\';
   								  	return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> \'+record.text+\'</span>\';';
   						}
   						else
   						{
   							$out.='return record.text;';
   						}
   			$out.= '	};
   	    			$(document).ready(function () {
   	    			    $(\'#'.$htmlname.'\').attr("name", "'.$htmlname.'[]");
   	    			    $(\'#'.$htmlname.'\').attr("multiple", "multiple");
   	    			    //$.map('.json_encode($selected).', function(val, i) {
   	    			        $(\'#'.$htmlname.'\').val('.json_encode($selected).');
   	    			    //});
   	    			
       					$(\'#'.$htmlname.'\').'.$tmpplugin.'({
       						dir: \'ltr\',
   							// Specify format function for dropdown item
   							formatResult: formatResult,
       					 	templateResult: formatResult,		/* For 4.0 */
   							// Specify format function for selected item
   							formatSelection: formatSelection,
       					 	templateResult: formatSelection		/* For 4.0 */
       					});
       				});
       			</script>';
       	}

       	return $out;
    }
}

