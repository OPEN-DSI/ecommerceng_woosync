-- ===================================================================
-- Copyright (C) 2022 Open-Dsi <support@open-dsi.fr>
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

-- v4.0.18
ALTER TABLE llx_ecommerce_site ADD COLUMN fk_anonymous_thirdparty integer NULL;
-- ALTER TABLE llx_ecommerce_site ADD COLUMN oauth_id varchar(255) NULL after user_password;
-- ALTER TABLE llx_ecommerce_site ADD COLUMN oauth_secret varchar(255) NULL after oauth_id;
ALTER TABLE llx_ecommerce_site ADD COLUMN price_level integer NULL DEFAULT 1;
ALTER TABLE llx_ecommerce_site CHANGE COLUMN magento_price_type ecommerce_price_type VARCHAR(3) NOT NULL DEFAULT 'HT';

-- v4.0.20
ALTER TABLE llx_ecommerce_site ADD COLUMN parameters TEXT NULL;

-- v4.0.27
ALTER TABLE llx_ecommerce_societe DROP INDEX uk_ecommerce_societe_fk_site_fk_societe;

-- v4.0.58
UPDATE llx_ecommerce_pending_webhooks SET datep = datee WHERE datee IS NOT NULL;
ALTER TABLE llx_ecommerce_pending_webhooks DROP COLUMN datee;
ALTER TABLE llx_ecommerce_pending_webhooks CHANGE COLUMN webhook_data webhook_data mediumtext NOT NULL;

-- v4.0.92
ALTER TABLE llx_ecommerce_site ADD COLUMN entity integer NULL DEFAULT 1;

-- v4.1.35
ALTER TABLE llx_ecommerce_site ADD COLUMN authentication_type varchar(255) NULL after webservice_address;
ALTER TABLE llx_ecommerce_site ADD COLUMN debug integer(1) NULL after timeout;
ALTER TABLE llx_ecommerce_site ADD COLUMN wordpress_authentication_type varchar(255) NULL after user_password;
ALTER TABLE llx_ecommerce_site CHANGE COLUMN oauth_id wordpress_authentication_login varchar(255) NULL;
ALTER TABLE llx_ecommerce_site CHANGE COLUMN oauth_secret wordpress_authentication_password varchar(255) NULL;
ALTER TABLE llx_ecommerce_site ADD COLUMN wordpress_authentication_login varchar(255) NULL after wordpress_authentication_type;
ALTER TABLE llx_ecommerce_site ADD COLUMN wordpress_authentication_password varchar(255) NULL after wordpress_authentication_login;
ALTER TABLE llx_ecommerce_site ADD COLUMN wordpress_timeout integer NOT NULL DEFAULT 30 after wordpress_authentication_password;
ALTER TABLE llx_ecommerce_site ADD COLUMN wordpress_debug integer(1) NULL after wordpress_timeout;
ALTER TABLE llx_ecommerce_product ADD COLUMN last_update_stock datetime default NULL after last_update;

-- v4.1.45
ALTER TABLE llx_ecommerce_site ADD COLUMN api_version varchar(255) NULL after webservice_address;

-- v4.1.54
ALTER TABLE llx_ecommerce_product ADD COLUMN lang varchar(255) default NULL after remote_id;
ALTER TABLE llx_ecommerceng_payment_gateways CHANGE COLUMN mail_model_for_send_invoice mail_model_for_send_invoice text;

-- v4.1.57
ALTER TABLE llx_ecommerceng_remote_warehouses DROP INDEX uk_ecommerceng_remote_warehouses;
ALTER TABLE llx_ecommerceng_remote_warehouses ADD UNIQUE INDEX uk_ecommerceng_remote_warehouses(site_id,remote_id,entity);
