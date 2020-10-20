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

create table llx_c_ecommerceng_tax_rate
(
  rowid				integer       AUTO_INCREMENT PRIMARY KEY,
  site_id			integer       NOT NULL,
  tax_id			integer		  NOT NULL,
  tax_country		varchar(3)	  NOT NULL,
  tax_state			varchar(255)  NOT NULL,
  tax_postcode		varchar(255)  NOT NULL,
  tax_city			varchar(255)  NOT NULL,
  tax_rate			double  	  NOT NULL,
  tax_name			varchar(255)  NOT NULL,
  tax_priority		integer  	  NOT NULL,
  tax_compound		tinyint(1)    NOT NULL,
  tax_shipping		tinyint(1)    NOT NULL,
  tax_order			integer       NOT NULL,
  tax_class			varchar(255)  NOT NULL,
  entity			integer       DEFAULT 1,
  active			tinyint       DEFAULT 1 NOT NULL
) ENGINE=InnoDB;
