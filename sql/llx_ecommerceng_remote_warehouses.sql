-- ===================================================================
-- Copyright (C) 2011 Auguria <anthony.poiret@auguria.net>
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
--
-- ===================================================================

create table llx_ecommerceng_remote_warehouses
(
	rowid							integer			AUTO_INCREMENT PRIMARY KEY,
	site_id							integer			NOT NULL,
	remote_code						varchar(255)	NOT NULL,	--Remote warehouse code
    remote_id						varchar(255)	NOT NULL,	--Remote warehouse ID
	remote_name						varchar(255)	NOT NULL,	--Remote warehouse name
	warehouse_id					integer,					-- Dolibarr warehouse ID
	set_even_if_empty_stock			tinyint(1),					-- Flag for set the stock of the warehouse even if the stock is empty
	old_entry						tinyint(1),					-- Flag for set if this warehouse has been delete on WooCommerce
	entity							integer			DEFAULT 1
) ENGINE=InnoDB;