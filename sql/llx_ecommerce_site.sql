-- ===================================================================
-- Copyright (C) 2010 Auguria <franck.charpentier@auguria.net>
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

CREATE TABLE llx_ecommerce_site (
  rowid integer NOT NULL auto_increment PRIMARY KEY,
  name varchar(255) NOT NULL,
  type integer NOT NULL DEFAULT 1,
  webservice_address varchar(255) NOT NULL,
  user_name varchar(255) DEFAULT NULL,
  user_password varchar(255) DEFAULT NULL,
  price_level integer NULL DEFAULT 1,
  filter_label varchar(255) DEFAULT NULL,
  filter_value varchar(255) DEFAULT NULL,
  fk_cat_societe integer NOT NULL,
  fk_cat_product integer NOT NULL,
  fk_warehouse integer NULL,
  stock_sync_direction varchar(24) DEFAULT 'none',
  last_update datetime DEFAULT NULL,
  timeout integer NOT NULL DEFAULT 300,
  magento_use_special_price INTEGER NOT NULL DEFAULT 0,
  magento_price_type VARCHAR(3) NOT NULL DEFAULT 'HT'
) ENGINE=InnoDB;
