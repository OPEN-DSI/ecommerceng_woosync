# ChangeLog

## 4.0.50.0
- Correction lors de l'ajout d'un produit depuis WooCommerce (nettoie les charactères en utf8mb4 dans la description et la description courte)
- Ajout de la variable globale ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION_WITHOUT_ERRORS pour desactivé la reprise des lignes de synchro des webhooks qui ont eu une erreur préalablement lors de la prochaine synchro des webhooks
- Correction de la creation du reglement de la facture fournisseur

## 4.0.49.0
- Correction de l'ajout des lignes de la facture lorsque l'on ne créer pas de commande
- Correction de la creation des factures fournisseurs
- Correction lors du lancement de la tache planifier de syncho des webhooks

## 4.0.48.0
- Correction du cron de synchronisation de la file d'attente
- Correction du prix d'achat des produits lors de la synchronisation des commandes
- Correction du classement des factures en payées si les factures ont un montant égale à 0
- Correction du compatibilité v10+ lors de l'envoi du mail de la facture lors de la synchronisation d'une commande

## 4.0.47.0
- Reecriture de la synchronisation des produits, tiers, commandes pour le support des webhooks de WooCommerce (Creation et actualisation des commandes)
- Ajout de la gestion des codes promos sur les commandes (de WooCommerce vers Dolibarr seulement)
- Ajout du support de l'extension WooCommerce "PW WooCommerce Gift Cards Pro" (de WooCommerce vers Dolibarr seulement)
- Ajout du support de l'extension WooCommerce "WooCommerce Cost of Goods" (de WooCommerce vers Dolibarr seulement)
- Nouvelle gestion des TVA
- Ajout de la variable ECOMMERCE_KEEP_NEGATIVE_PRICE_LINES_WHEN_CREATE_INVOICE pour ne pas creer des reductions clients pour des lignes avec des prix négatifs lors de la création de la facture

## 4.0.46.0
- Ajout de la variable globale ECOMMERCENG_WOOCOMMERCE_QUERY_STRING_AUTH pour passer les identifiants dans l'url

## 4.0.45.0
- Correction lorsque de la synchronisation des tiers si le tiers trouvé par l'adresse email est le tiers par défaut (deplacement du contact avec l'adresse email dans le nouveau tiers ou aillant le même nom si c'est une companie).

## 4.0.44.0
- Correction lors de la recuperation du compte bancaire pour la creation des paiements sur la facture a la synchronisation d'une commande.
- Correction mineure
- Fix update product with UGS code and variations fail by mkdgs

## 4.0.43.0
- Correction de la mise a jour du statut "virtuel" du produit sur WooCommerce si le produit Dolibarr est un service

## 4.0.42.1
- Mise à jour de la documentation

## 4.0.42.0
- Correction de l'ajout du lien de la catégorie racine des produits

## 4.0.41.0
- Add Italian translations file (module version 4.0.34) by aschio
- Fix on order modification configuration is ignored by mkdgs
- Fix work with other custom dirs by altatof
- Fix create mother category link

## 4.0.40.0
- Ajout du choix des roles utilisateurs synchronisés dans les options du site.
- Seul les clients des roles supportés seront synchronisés.
- Seules les commandes invités ou ceux dont le client est enregistré seront synchronisées.
- Correction si la commande n'a pas de mode de paiement lors de la synchro.

## 4.0.39.0
- Correction de la récupération des frais stripe
- Correction mineure

## 4.0.38.0
- Correction de la récupération des modes de paiements de la boutique (libellé et ne recupère que ceux activés)
- Correction de la recherche du produit/service utilisé pour les frais de la facture fournisseur

## 4.0.37.0
- Ajout d'un bouton de mise à jour manuellement depuis un site sur la fiche du tiers
- Ajout d'une option pour ajout un utilisateur de type "Responsable suivi commande client" lors de la création d'une commande lors d'une synchro

## 4.0.36.0
- Suppression de la variable ECOMMERCENG_SHIPPING_NOT_DISPLAY_IF_NULL (n'affiche pas de frais de port si la quantité est a 0 (pas de frais de port))
- Ajout de la gestion des modes de payments
- Ajout de la possibilité de créer une facture et une facture fournisseur pour les frais lors de la synchronisation de la commande
- Possibilité de ne créer que la facture
- Possibilité d'envoyer automatiquement la facture par email au client
- Suppression des extrafields, des modes de paiements et des tva liés au site lors sa suppression

## 4.0.35.0

- Correction des stocks lors des mises à jour avec les boutiques (arrondie à l'inferieur si le stock à des decimales)
- Ajout d'un script de mises à jour de tous les produits vers les boutiques.
- Correction des valeurs par default à la création d'un site.

## 4.0.34.0

- Correction de la modification de la ref dans le cas ou la boutique envoie une ref vide sur un produit deja synchronisé vers Dolibarr.

## 4.0.33.0

- Correction de la recherche des contacts d'une commande lors de sa synchronisation depuis Woocommerce.

## 4.0.32.0

- Correction des droits d'accès à la page de configuration des sites.

## 4.0.31.0

- Ajout de la variable global pour envoyer l'authorisation de connection vers l'API de WordPress dans les paramètres de l'URL avec OAUTH_WORDPRESS_AUTHORIZATION_METHOD_QUERY_STRING.
- Correction lors de la synchronisation des produits de WooCommerce vers Dolibarr lorsque le paramètrage du sens de synchronisation de la référence du produit à été mis sur 'De Dolibarr vers ECommerce'.

## 4.0.30.0

- Modification et deplacement des menus.
- Mise à jour et corrections variées par Ewake.
- Correction de la prise en compte de l'option de désactivation de la synchro en temps réel pour les commandes par mkdgs.

## 4.0.29.0

- Correction sur la date de synchronisation des clients qui se basais sur la date de modification et non celle de création.

## 4.0.28.0

- Ajout de la variable globale ECOMMERCENG_PRODUCT_IN_PURCHASE_WHEN_CREATED pour permettre de mettre les produit creer sur dolibarr lors de la synchronisation au statut 'En achat'.
- Ne met plus à jour le statut 'En achat' lors de la synchronisation.
- Ajout des corrections et améliorations de Ewake.

## 4.0.27.0

- Ajout d'une variable global ECOMMERCENG_WOOCOMMERCE_VAT_NUMBER_META_NAME pour renseigner le nom de la meta donnée correspondant à la TVA client.
- Correction de la prise en consideration du pays lors de la creation du tiers.
- Ajout d'une variable global ECOMMERCENG_WOOCOMMERCE_DEFAULT_LANG_OTHER_COUNTRY pour renseigner la langue par défaut si le pays du tiers est different de sa société sinon laisse vide
- Création d'un nouveau tiers si le nom de la société de facturation est different de celui de livraison sur une commande et rattache le contact de livraison à celui-ci.
- Ne synchronise maintenant que les nouveaux tiers de Woocmmerce vers Dolibarr.
- Récupère tous les types de tiers (quelques soit le role de l'utilisateur).
- Une société peut être lié à plusieurs utilisateurs de woocommerce.
- Corrections synchronisations des contacts lors de la synchronisation des tiers de Woocmmerce vers Dolibarr.
- Support la fusion des sociétés.

## 4.0.26.0

- Ajout du choix du type de prix sur WooCommerce (régulier ou de vente) synchronisé avec le prix de vente sur Dolibarr.
- Correction synchronisation du prix de vente minimum de ECommerce vers Dolibarr.

## 4.0.25.0

- La suppression des liens des produits supprime aussi les catégories de la boutique sur les produits.
- Amélioration de la synchronisation WooCommerce vers Dolibarr des sociétés, produits et commandes.
- Ajout d'un bouton pour retirer le lien d'un produit avec un site ECommerce dans Dolibarr sur la fiche d'un produit.
- Modification de l'export et import des prix des produits (enlevement de la date de modification, elle sera mise à jour automatiquement)
- Ajout d'un test si la ref du produit dans Dolibarr est deja lié à un autre produit distant dans la boutique
- Ajout d'une option de sens de synchronisation de la Réf. 

## 4.0.24.0

- Correction synchro des categories de ECommerce vers Dolibarr.
- Ajout de la direction des synchronisation des images dans les options du site. La variable global ECOMMERCENG_ENABLE_SYNCHRO_IMAGES a été enlevé.
- Ajout de la synchro manuelle des produits modifié de Dolibarr vers ECommerce.
- Ajout d'une erreur si la base du prix d'un produit est different d'es paramétrage du site.
- Amélioration lors de la synchro Ecommerce/Dolibarr (retrouve le produit dont la ref existe deja meme si le lien est different)
- Ajout export/import des prix des produit dans l'export/import standard Dolibarr

## 4.0.23.0

- N'affiche pas le bouton "synchronisation des images" sur la pages des fichiers joints de la fiche produit si il n'est pas lié à un site ECommerce.
- Ajout de la synchronisation des images à la création du produit sur le site ECommerce.

## 4.0.22.0

- Ajout de la synchronisation des attibuts supplémentaires avec les méta-données pour les produits et commandes (ainsi que les lignes).
- Ajout d'une globale ECOMMERCENG_WOOCOMMERCE_GET_EMAIL_ON_COMPANY pour récuperer l'email dans le tier lors de la synchro d'un client de type 'companie'
- Ajout de l'option de désactiver la synchro en temps reel de Dolibarr vers ECommerce
- Ajout du choix de la condition de paiement par défaut lors de la synchro des commandes de ECommerce vers Dolibarr
- Ajout de la synchro manuelle des catégories et produits de Dolibarr vers Ecommerce

## 4.0.21.0

- Mise à jour de l'attribut complémentaire "statut de la commande sur ECommerce" sur la fiche de la commande lors de la mise à jour de la commande de Dolibarr vers ECommerce.
- Correction bug du test pour ne changer le statut que si le niveau est plus élevé que le statut précédant lors de la synchro Dolibarr vers ECommerce.

## 4.0.20.0

- Correction erreur lors de la synchronisation des classes de TVA. (Correction du fichier de langue)
- Cacher le message de warning concernant le repertoire de cache WSDL pour la connexion soap (si ce n'est pas un site magento).
- Correction de l'enregistrement de la date de la commande lors de la synchro de ECommerce vers Dolibarr.

## 4.0.19.0

- Parametrage de la synchronisation des statuts des commandes.
- Les variables globales ECOMMERCENG_WOOCOMMERCE_FORCE_ORDER_STATUS_TO_DRAFT, ECOMMERCENG_WOOCOMMERCE_ORDER_PROCESSING_STATUS_TO_DRAFT ont été supprimées.

## 4.0.18.0

- Correction et généralisation de la mise à jour des liens lors d'une fiche lié à un site ou portant le tag du site. (gestion de la mise a jour des elements de la boutique vers dolibarr à revoir, la date de mise a jour etant la plus recente date de mise a jour des liens aux elements et non une date de la derniere mise a jour manuelle) 
- Création ou mise à jour du produit sur la boutique (si lors de la modification d'un produit non lié mais ayant le tag de la boutique, la reference du produit est trouvée sur la boutique)  
- Correction de la verification de l'appartenance du produit a la categorie de la boutique lors de la modification d'un produit sur Dolibarr.

## 4.0.17.0

- Correction d'un warning lors de la syncho.
- Correction de la liste des variantes du produit à mettre à jour qui se base la date de mise à jour de la variante (mise a jour sur la modification du stock via une commande) ou la date de mise à jour du produit parent (pour les autres cas).

## 4.0.16.0

- Correction de la fonction de suppression des produits dans la "Partie dangereuse" de la fiche du site, dut à un changement de paramètres passés à la fonction "delete" de la classe "Product" de la version 5.0 à la 6.0.
- Correction de la liste des variantes du produit à mettre à jour qui ne se base plus sur la date de mise à jour de la variante (elle n'est pas mise à jour et reste égale à la date de création).
- Correction de ne pas mettre à jour le stock si le produit est un service dans le cas de la synchro des stocks de Ecommerce vers Dolibarr.
- Correction de la synchro des images de Dolibarr vers Ecommerce (Attention l'API ne permet pas de supprimer l'image des variantes, ont ne peut que la réécraser).
- Correction de l'envoi des PDF des factures et des expéditions de Dolibarr vers Ecommerce à la validation et à la génération manuelle du document.
- Mise à jour de la date de mise à jour des liens "Ecommerce/Dolibarr" à la modification d'un élément dans Dolibarr (société, produit, ...) synchronisés vers Ecommerce.
- Correction de l'API client de Woocommerce pour récupérer les erreurs de l'API.

## 4.0.15.0

- Generation automatique du code barre que lors de la creation du produit sur Dolibarr lors de la synchro des produits.

## 4.0.14.0

- Precision sur les messages d'erreurs lors de la synchro des produits.

## 4.0.13.0

- Correction du test d'un produit deja existant par sa Ref lors de la synchro ECommerce vers Dolibarr.

## 4.0.12.0

- Correction de la liste des catégories d'une variante d'un produit récuperées depuis ECommerce.

## 4.0.11.0

- Correction d'une erreur de frappe sur le log system lors de la synchro du stock, pendant la synchro des produits, de ECommerce vers Dolibarr.

## 4.0.10.0

- Correction sur la precision de messages d'erreurs lors de la synchro

## 4.0.9.0

- Corrections suite au changement du format des données reçues par l'API Woocommerce depuis sa mise à jour en 2.0.0
- Correction mineur de la mise à jour des images lors de la modification d'un produit sur Dolibarr

## 4.0.8.0

- Mise a jour de l'api client Woocommerce 1.2.0 en 2.0.0
- Correction de la construction de l'url de callback pour OAuth2 pour qu'il garde le protocole https du site dans sa construction.

## 4.0.7.0

- Report de la construction de l'url de callback pour OAuth2 dans l'écran de paramètrage du site à l'identique de celle définie dans le script de la page de callback.

## 4.0.6.0

- Ajout de l'option query_string_auth lors de la connexion a WooCommerce si l'adresse de la boutique est en https.

## 4.0.5.0

- Désactivation de la mise à jour du client à la modification du tiers (il n'y avais que l'email de renseigné et qui n'a pas à être modifié)

## 4.0.4.0

- Modification de la gestion de mise à jour du statut de la commande

## 4.0.3.0

- Enregistre la société pour les clients anonymes pour chaque site et non dans l'option ECOMMERCENG_USE_THIS_THIRDPARTY_FOR_NONLOGGED_CUSTOMER 
- Correction de la quantités des éléments lié au site pour la fiche du site (en non pour tous les sites)
- Ajout d'une option ECOMMERCENG_NO_COUNT_UPDATE pour ne pas récupérer les quantités à mettre à jour et afficher tous les boutons de synchronisation sur la fiche du site.
- Correction de la recherche de correspondance lors de la synchro des catégories
- Correction de la récupération des produits et de ses variantes
- Correction de la recherche de la ref du produits dans Dolibarr lors de la synchro
- Ne re-télécharge pas les informations du client pour recupérer les adresses du clients lors de la synchro des sociétés
- Lors de la synchro des contacts, rempli le nom si vide par "\[nom non renseigné\]" ou "Pas de nom/prénom renseigné" en fonction des cas
- Similaire pour les nom des tiers
- Ajout de l'option ECOMMERCENG_WOOCOMMERCE_ORDER_PROCESSING_STATUS_TO_DRAFT pour ne forcer les commandes woocommerce au statut "En cours" redescende au statut "Brouillon" dans Dolibarr
- Les commandes woocommerce au statut "Remboursées" redescende au statut "Annulée" dans Dolibarr
- Definie la description d'une ligne de produit vide par "L'api n'a pas pu récupérer la description du produit"
- Cherche la societe du contact d'une commande par son adresse mail si fourni pour les commandes anonymes
- Correction de la recherche d'un contact par ses informations
- Correction d'une partie de la gestion des erreurs
- Les logs bas niveaux woocommerce sont au niveau DEBUG et plus au niveau INFO
- Corrections mineures
- Changement du nom du module en Woosync et version supportant que Woocommerce

## 4.0.2.0

- D'un champ complémetaire oublié pour la fiche d'un produit d'un site woocommerce
- Affiche du lien de test sur les parametres du site que si l'adresse du site est renseigné
- Ajout de la description de l'erreur lors du fonctionnement de l'OAuth 2
- Corrections mineures

## 4.0.1.0

- Ajout OAuth2 pour Wordpress
- Mise en commun du type de prix renvoyé par la boutique (HT / TTC)
- Lien de test en fonction du type du site
- Creation de champ complémentaire à l'ajout d'un site Woocommerce
- Ajout de la gestion des classes de TVA Woocommerce pour les produits + Dictionnaire
- Ajout des fonctions de recherche, insert et update à la classe eCommerceDict
- Ajout et modification de traductions
- Corrections du decodage de la reponse de l'API Woocommerce
- Ajout de la synchronisation du produit lors de l'ajout de la catégorie mère "E-Commerce" (Trigger)
- Ne synchronise pas le produit lorsque l'on envèle la catégorie mère "E-Commerce" (Trigger)
- Creation du produit sur le site depuis dolibarr (Trigger)
- Correction du statut réel de la commande lors de la synchro de Dolibarr vers E-Commerce (Trigger)
- Ajout test connection a l'appel de la fonction connect de la classe remote access de Woocommerce
- Correction de la gestion des dates lors des fonctions ToUpdate de Woocommerce
- Modification gestion des tiers (avec recherche doublon par email, nom, ...) et distinctions entreprise/particulier
- Modification gestion des contacts/adresses avec recherche doublon
- Modification gestion des catégories avec recherche doublon
- Modification de mise a jour du prix du produit
- Ajout de gestion des extrafields sur les produits et commandes
- Ajout de la synchro methode de paiment sur la commande dans la synchro E-Commerce vers Dolibarr
- Ajout ECOMMERCENG_WOOCOMMERCE_FORCE_ORDER_STATUS_TO_DRAFT pour forcer le statut de la commande en brouillon lors de la synchro Woocommerce vers Dolibarr- Ajout de la possibilité d'ignorer les commandes anonyme avec la variable ECOMMERCENG_PASS_ORDER_FOR_NONLOGGED_CUSTOMER
- Ajout synchronisation des images avec E-commerce (possibilité de l'activer avec la variable ECOMMERCENG_ENABLE_SYNCHRO_IMAGES) (Necessite paramétrage OAuth2 pour l'envoi des images vers Woocommerce)
- Ajout envoie PDF facture / expedition à la génération du PDF sur la commande Woocommerce via Wordpress (Necessite paramétrage OAuth2) (l'activer avec la variable ECOMMERCENG_ENABLE_SEND_FILE_TO_ORDER)
- Ajout synchro des catégories de Woocommerce vers Dolibarr
- Corrections diverses

## 3.9.1.0

- Add option ECOMMERCENG_THIRDPARTY_UNIQUE_ON to search existing thirdparties from email instead of name.
- Can define to_date=YYYYMMDDHHMMSS in url to limit date when searching updated records.
- Add option ECOMMERCENG_DEBUG to log Soap requests with magento.
- Add support for Woocommerce.
- Support for price level.
- A lot of fix/enhancement in error management.

## 3.9.0.0

- Initial version.
