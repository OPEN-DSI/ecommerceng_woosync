-- ===================================================================
-- Copyright (C) 2020 Open-Dsi <support@open-dsi.fr>
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

create table llx_ecommerce_pending_webhooks
(
	rowid 				integer AUTO_INCREMENT PRIMARY KEY,
	site_id				integer			NOT NULL,
	delivery_id			varchar(255)	NOT NULL,
	webhook_id 			varchar(255)	NOT NULL,
	webhook_topic    	varchar(255)	NOT NULL,
	webhook_resource 	varchar(255)	NOT NULL,
	webhook_event	   	varchar(255)	NOT NULL,
	webhook_data		text			NOT NULL,
	webhook_signature	varchar(255)	NOT NULL,
	webhook_source     	varchar(255)	NOT NULL,
	status				integer(4)		NOT NULL DEFAULT 0,
	datep				datetime		NULL,
	datee				datetime		NULL,
	error_msg			text			NULL,
	datec				datetime		NOT NULL
) ENGINE=InnoDB;
