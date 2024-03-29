-- ===================================================================
-- Copyright (C) 2021 Open-Dsi <support@open-dsi.fr>
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

create table llx_c_ecommerceng_attribute
(
  rowid						integer       AUTO_INCREMENT PRIMARY KEY,
  site_id					integer       NOT NULL,
  attribute_id				integer		  NOT NULL,
  attribute_name			varchar(255)  NOT NULL,
  attribute_slug			varchar(255)  NOT NULL,
  attribute_type			varchar(255)  NOT NULL,
  attribute_order_by		varchar(255)  NOT NULL,
  attribute_has_archives	tinyint(1)    NOT NULL,
  entity					integer       DEFAULT 1,
  active					tinyint       DEFAULT 1 NOT NULL
) ENGINE=InnoDB;
