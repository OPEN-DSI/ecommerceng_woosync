-- Script to clean corrupted data

delete from llx_ecommerce_societe where fk_societe not in (select rowid from llx_societe);
delete from llx_ecommerce_socpeople where fk_socpeople not in (select rowid from llx_socpeople);
delete from llx_ecommerce_product where fk_product not in (select rowid from llx_product);
delete from llx_ecommerce_commande where fk_commande not in (select rowid from llx_commande);
delete from llx_ecommerce_facture where fk_facture not in (select rowid from llx_facture);

