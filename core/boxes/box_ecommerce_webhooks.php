<?php
/* Copyright (C) 2020      Open-Dsi      <support@open-dsi.fr>
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
 *	\file       htdocs/ecommerceng/core/boxes/box_webhooks.php
 *	\ingroup    ecommerceng
 *	\brief      Box for web hooks in pending
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';


/**
 * Class to manage the box to show web hooks in pending
 */
class box_ecommerce_webhooks extends ModeleBoxes
{
    public $boxcode="ecommerce_webhooks";
    public $boximg="object_eCommerce@ecommerceng";
    public $boxlabel="ECommerceBoxWebHooks";
    public $depends = array("ecommerceng");

	/**
     * @var DoliDB Database handler.
     */
    public $db;

    public $enabled = 1;

    public $info_box_head = array();
    public $info_box_contents = array();


	/**
	 *  Constructor
	 *
	 *  @param  DoliDB	$db      	Database handler
     *  @param	string	$param		More parameters
	 */
	public function __construct($db, $param = '')
	{
		global $conf, $user;

		$this->db = $db;

		$this->hidden = !($user->rights->ecommerceng->read && empty($user->socid));
	}

	/**
     *  Load data for box to show them later
     *
     *  @param	int		$max        Maximum number of records to load
     *  @return	void
	 */
	public function loadBox($max = 20)
	{
		global $user, $langs, $conf;
		$langs->load("boxes");

		$this->max = $max;

		include_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
		dol_include_once('/ecommerceng/class/business/eCommercePendingWebHook.class.php');
		$webhooks_static = new eCommercePendingWebHook($this->db);

		$this->info_box_head = array('text' => $langs->trans("ECommerceBoxWebHooks"));

		if ($user->rights->ecommerceng->read) {
			$sql = "SELECT epw.status, count(*) AS nb";
			$sql .= " FROM " . MAIN_DB_PREFIX . "ecommerce_pending_webhooks AS epw";
			$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ecommerce_site AS es ON es.rowid = epw.site_id";
			$sql .= " WHERE es.entity IN (" . getEntity('ecommerceng') . ")";
			$sql .= " GROUP BY epw.status";
			$sql .= " ORDER BY epw.status";

			dol_syslog(get_class($this) . "::loadBox", LOG_DEBUG);
			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);

				$list_url = dol_buildpath('/ecommerceng/webhookslist.php', 1) . '?viewstatut=';

				$line = 0;
				while ($line < $num) {
					$objp = $this->db->fetch_object($result);

					$this->info_box_contents[$line][] = array(
						'td' => '',
						'text' => $webhooks_static->LibStatut($objp->status),
						'asis' => 1,
					);

					$this->info_box_contents[$line][] = array(
						'td' => 'class="right"',
						'url' => $list_url . $objp->status,
						'target' => '_blank',
						'text' => $objp->nb,
					);

					$line++;
				}

				if ($num==0) $this->info_box_contents[$line][0] = array('td' => 'class="center"','text'=>$langs->trans("ECommerceNoRecordedWebHooks"));

				$this->db->free($result);
			} else {
				$this->info_box_contents[0][0] = array(
					'td' => '',
					'maxlength' => 500,
					'text' => ($this->db->error() . ' sql=' . $sql)
				);
			}
		} else {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="nohover opacitymedium left"',
				'text' => $langs->trans("ReadPermissionNotAllowed")
			);
		}
	}

	/**
	 *	Method to show box
	 *
	 *	@param	array	$head       Array with properties of box title
	 *	@param  array	$contents   Array with properties of box lines
	 *  @param	int		$nooutput	No print, only return string
	 *	@return	string
	 */
    public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
