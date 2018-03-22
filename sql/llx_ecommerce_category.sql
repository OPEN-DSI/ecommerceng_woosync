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

create table llx_ecommerce_category
(
	rowid 			integer AUTO_INCREMENT PRIMARY KEY,
	label 			varchar(255),                       
	type	       		tinyint DEFAULT 1 NOT NULL,         
	description 		text,                            
	fk_category     	integer NOT NULL,		   
	fk_site			integer NOT NULL,		
	remote_id		integer NOT NULL,		 
	remote_parent_id	integer DEFAULT NULL,	
	last_update		datetime DEFAULT NULL		  
) ENGINE=InnoDB COMMENT='Table transition remote site -  Dolibarr';


