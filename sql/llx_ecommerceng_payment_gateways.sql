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

create table llx_ecommerceng_payment_gateways
(
    rowid                           integer       AUTO_INCREMENT PRIMARY KEY,
    site_id                         integer       NOT NULL,
    payment_gateway_id              varchar(255)  NOT NULL,
    payment_gateway_label           varchar(255)  NOT NULL,
    payment_mode_id                 integer,                    -- Payment mode ID
    bank_account_id                 integer,                    -- Bank account ID
    create_invoice_payment          tinyint(1),                 -- Flag for create invoice payment
    mail_model_for_send_invoice     integer,                    -- Email model ID for send invoice by mail
    supplier_id                     integer,                    -- Supplier company ID
    product_id_for_fee              integer,                    -- Product ID for fee
    create_supplier_invoice_payment tinyint(1),                 -- Flag for create supplier invoice payment
    entity                          integer       DEFAULT 1
) ENGINE=InnoDB;
