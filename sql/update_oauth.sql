ALTER TABLE llx_ecommerce_site ADD COLUMN oauth_id varchar(255) NULL after user_password;
ALTER TABLE llx_ecommerce_site ADD COLUMN oauth_secret varchar(255) NULL after oauth_id;
