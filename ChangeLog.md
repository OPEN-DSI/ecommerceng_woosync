# ChangeLog

## 4.1.20.0
- Recherche le tiers dans l'ordre suivant email -> nom + code postal -> nom
- Prise en compte de la synchronisation des kits de WooCommerce vers Dolibarr (Support partiel du module Woocommerce "WPC Product Bundles for WooCommerce" v6.1.1)
  Pour rappel, seul la synchronisation des produits simples et des produits variables (sans le produit parent) est supporté.
  Un bundle constitué d'un produit variable parent ne pourra pas être synchronisé et générera une erreur. 

## 4.1.19.0
- Correction des la desactivation des taches planifiés (avec perte de la configurtaion) a la réactivation du module
- Correction de l'enregistrement, avec pertes de la configuration, de la synchro des status de la commandes lors de plusieurs sites 

## 4.1.18.0
- Correction des doublons dans les tables de liens

## 4.1.17.0
- Ajout du support du module "F4 Shipping Phone and E-Mail for WooCommerce" version 1.0.16

## 4.1.16.0
- Correction de la synchronisation du produit lors de sa creation si lié à la catégorie du site

## 4.1.15.0
- Correction lors de la synchro d'une commande dont le client est un utilisateur woocommerce dont le role n'est pas supporté

## 4.1.14.0
- Ajout d'une nouvelle option pour considéré les lignes de frais comme des lignes de services
- Lors de la creation d'un nouveau tiers lors de la synchronisation d'une commande, l'adresse est renseignée sur le tiers
- Lors de la creation d'une facture lors de la synchronisation d'une commande, vérifie si il n'y a pas déjà une facture liée

## 4.1.13.0
- Suppression du test pour que les prix des produits dolibarr soit dans le meme type (HT ou TTC) que le site lors de la maj de dolibarr vers le site, il envoie le prix attendu dans le type du site

## 4.1.12.0
- Ajout d'un nouveau parametre dans la configuration du site et une nouvelle tache planifié pour la gestion des depassement de volumétrie de webhooks en attentes de traitement
- Les emails notifies doivent etre renseignés dans la variable globale ECOMMERCE_NOTIFY_EMAIL_ERRORS_CHECK_WEBHOOKS_VOLUMETRY
- Suppression de la variable globale ECOMMERCE_CHECK_WEBHOOKS_STATUS
- Ajout d'un script pour activé le partage des images des produits en masses 'scripts/activate_sharing_product_images.php'

## 4.1.11.0
- Correction de la date de la facture qui est maintenant egale à la date de paiement (le cas echeant celle de la commande)
- Correction compatibilité multi-entité
- Correction recherche sur l'id distant (requetes SQL)

## 4.1.10.0
- Correction synchronisation des commandes
- Correction ordre de synchronisation des webhook du plus recent au plus ancien
- Correction clé unique sur le remote id dans les tables de liens
- Correction du retour de l'erreur lors d'une synchro dolibar vers woocommerce

## 4.1.9.0
- Correction de l'option de creation de la facture meme si le montant est egale a 0

## 4.1.8.0
- Ajout d'une option pour creer la facture meme si le montant est egale a 0

## 4.1.7.0
- Mouvement de stock à la validation d'une commande synchronisée si la synchro des stocks est paramètré dolibarr vers WooCommerce et qu'il n'y a qu'un entrepot de selectionné

## 4.1.6.0
- Correction d'ajout de la categorie de la boutique sur le tiers lors que celui-ci est créer a partir d'une commande depuis un client anonyme

## 4.1.5.0
- Correction synchronisation du champ complémentaire status sur le site dans une commande

## 4.1.4.0
- Correction des scripts pour supporter le multi-entités

## 4.1.3.0
- Correction de la récupération des catégories filtrées par site

## 4.1.2.0
- Ajout d'un script CLI de synchronisation des categories, produits, tiers, commandes du site vers dolibarr dans une plage d'ID
- FIX: handle CSRF token by marc-dll
- FIX: when creating standlone invoice, use anonymous thirdparty if none provided by marc-dll
- FIX: oauth: bad token redirection by marc-dll
- Corrections

## 4.1.1.0
- Correction de la reference client de la commande

## 4.1.0.0
- Correction synchronisation d'une commande d'un invité
- Correction de la modification du poids d'un produit

## 4.0.109.0
- Ajout de 2 variables globales ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD et ECOMMERCENG_PRODUCT_REF_MANDATORY pour permettre de synchroniser les commandes sans avoir à synchroniser les produits/categories
- Rattache la commande au bon tiers si la commande est anonyme et que le tiers de facturation a été trouvé

## 4.0.108.0
- Correction de l'enregistrement des options sur les modes de paiements

## 4.0.107.0
- Recupere les information du taux de tva depuis la commande si fournit, sinon utilise les données de tva synchronisé

## 4.0.106.0
- Ne supprime plus les anciens modes de paiements synchronisés lors de la synchronisation des modes de paiements du site

## 4.0.105.0
- Enregistrement de l'url du produit de la boutique dans le produit dolibarr lors qu'il est creer et synchronisé depuis dolibarr

## 4.0.104.0
- Correction de l'import des prix

## 4.0.103.0
- Le lien de l'image fournt a woocommerce comptient le nom reel de l'image
- Le nom de l'image sans son extension est fournit comme nom et nom alternatif a woocommerce
- Ajout de la synchronisation de la hauteur, largeur et profondeur du produit avec woocommerce
- Correction de l'envoi de la description du produit vers woocommerce 

## 4.0.102.0
- Ajout d'un script executant l'action du bouton de synchro des produits de Dolibarr vers le site

## 4.0.101.0
- Compatibilité v13+ pour la gestion des champs complémentaires
- Ajout du triggers sur la creation du lien d'une commande

## 4.0.100.0
- Correction de synchronisation de la classe de TVA du produit du site vers Dolibarr

## 4.0.99.0
- Correction de synchronisation des attributs d'un produit Woocommerce sur des champs complémentaires d'un produit Dolibarr

## 4.0.98.0
- Ajout du support de synchronisation entre les attributs d'un produit Woocommerce et les champs complémentaires d'un produit Dolibarr
- Modification de la synchronisation des images de Dolibarr vers WooCommerce sans passé par l'api Wordpress (nécéssitera peut etre la modification du fichier ecommerceng/document/.htaccess)
- Correction filtre par date

## 4.0.97.0
- Le test de droit de synchronisation de l'utilisateur utilisé pour lancer la tache CRON de traitement des webhooks a été retiré

## 4.0.96.0
- Augmente la taille de TEXT vers MEDIUMTEXT de la colonne webhook_data sur la table llx_ecommerce_pending_webhooks

## 4.0.95.0
- Correction du nom du constructeur des classes d'origines du module

## 4.0.94.0
- Ajout d'un script cli pour retraiter l'ajout des paiements des factures impayées liées à un site.
- Correction mineures.

## 4.0.93.0
- Correction compatibilité multi-entités et gestion des menus.
- Correction remonter des produits (bouton de synchro de dolibarr vers le site).
- Ajout de la global ECOMMERCE_INITIAL_DATA_PRODUCT pour forcer l'etat initial des données (description, stock, prix et tva).
- Ajout de la global ECOMMERCE_DONT_UNSET_CATEGORIE_OF_PRODUCT_WHEN_DELINK pour ne pas supprime les catégorie lié a la boutique sur le produit si il est délier du site

## 4.0.92.0
- Correction compatibilité multi-entités.

## 4.0.91.0
- Les 'Code promo' et 'Cartes cadeaux' redecendent en lignes libres si aucuns services n'est définit dans la configuration du site.

## 4.0.90.0
- Correction du contrôle de l'id distant pour une même ref si l'option sur les variantes 'Tous => un' est choisi.
- Ajout de la variable ECOMMERCE_USER_AGENT pour changer le 'user agent' utiliser dans la requete CURL pour récupérer les images des produits
- Ajout de la variable ECOMMERCE_CURL_VERBOSE pour affiche le verbose de la requete CURL pour récupérer les images des produits dans le fichier curl_verbose.txt a la racine du dossier des documents dolibarr

## 4.0.89.0
- La synchronization des images du produit n'est plus bloquante.

## 4.0.88.0
- Affichage de l'heure de la date du traitement sur la liste des WebHooks.
- Ajout de log en LOG_ALERT (avec l'activation de la globale ECOMMERCENG_LOG_PROCESSING_TIMES) sur le temps d'execution du traitement global d'une synchronisation et d'un appel à l'API de WooCommerce
- Suppression de l'appel à l'API pour tester la connexion dans la fonction connect()
- Corrections diverses

## 4.0.87.0
- Ajout dans la configuration d'une option sur les statuts pour synchronisation de la commande depuis WooCommerce. 

## 4.0.86.0
- Ajout dans la configuration des comptes comptables par défault à la création des produits/services synchronisés dans Dolibarr
- Ajout de la compatibilité avec la gestion des produits composés (notion de ligne de produit parente)
- Corrections diverses

## 4.0.85.0
- Correction compatibilité multi-entités

## 4.0.84.0
- Correction gestion des erreurs lors de la verifiction des webhooks activé sur WooCommerce
- Maj des fichiers de langues FR par Romain Deschamps

## 4.0.83.0
- Correction typo ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZEATION en ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION
- Permet de mettre les webhook en erreur au statut traité dans les actions en masses

## 4.0.82.0
- Ajout des options pour la generation de la facture d'acompte (options sur les lignes)
- Correction de la suppression du lien entre la commande WooCommerce et la facture Dolibarr si l'on a choisi de ne générer que la facture lors de la synchronisation
- Affichage de l'erreur survenue d'un changement de la référence d'un produit WooCommerce dont la référence existe deja sur un autre produit Dolibarr
- Passe la synchronisation des webhooks concernant des produits effacés avant leurs traitement
- N'affiche les meta-data dans la description des lignes que si elle ne sont pas de type object ou array
- Correction diverses

## 4.0.81.0
- Correction de la définition de la variable in_stock pour les produit simple lors de la mise a jour d'un produit
- Lors du traitement des webhooks, recupère la derniere version du produit si le webhook ne fournit pas d'UGS et la ref est obligatoire dans dolibarr
- Correction d'un bug possible lors du merge des meta-datas du produit variant et du produit racine pour la gestion des extrafields
- Affiche l'erreur completement dans la liste des webhooks (popup retiré)
- Correction du cas lorsqu'un webhook d'un produit arrivé en tant que variant est transformer en produit simple avant son traitement dans Dolibarr
- Correction des filtres sur la liste des webhooks
- Correction de la creation possible de tiers en doublon lors de la synchronisation des contacts d'une commande WooCommerce pendant la creation de la commande/facture sur Dolibarr
- Vérifie avant de synchroniser le client de la commande si la commande doit etre synchronisé et non après, idem pour la facture
- Correction d'accumulation de warnings lors du traitement des commandes
- Prend le premier tiers qui n'est pas le tiers anonyme si il trouve plus de 1 tiers lors de la recherche du tiers de la commande grace a son email
- Correction suppression du message d'erreur lorsque le webhooks est passé au statut traité
- Correction des fichiers de langues anglaises

## 4.0.80.0
- Correction de la gestion des liens des produits dans le cas où un produit simple deviens un produit variable et inversement

## 4.0.79.0
- Correction trigger si une commande est cloturé depuis une expédition 

## 4.0.78.0
- Ajout la gestion des actions de restauration et de suppression de produit dans les webhooks
- Delie les produits pour les produits distants standard transformés en variants ou les variants qui auraient été supprimés

## 4.0.77.0
- Fix requête sql qui supprimer les liens en double pour un même ID distant sur les produits

## 4.0.76.0
- Correction mineure compatibilité V13+ avec la note d'un produit lors de sa creation sur Dolibarr pendant d'une synchro

## 4.0.75.0
- Fix sql request by removing ) by pierrejego

## 4.0.74.0
- Eclaircissement de la methode de synchronisation des produits variables
- Recupere les informations du produit parent si celui du produit variable n'est pas renseigné et regroupe les catégories, les images et les méta-données et produit parent avec le produit variable
- Compatibilité de la recupération de repertoire des images des produits avec les versions 13+ de Dolibarr
- Amelioration du nom de l'image du produit enregistré dans Dolibarr lors d'une synchro WooCommerce vers Dolibarr
- Corrections et ajout compatibilité avec Dolibarr v13

## 4.0.73.0
- Correction des données sur le stock lors de la synchronisation d'un produit
- Ajoute une option pour afficher les meta-données des produits de la commandes Woocommerce dans la description des lignes de produits de la commande sur Dolibarr
  Et une autre pour spécifier les meta-données a ne pas inclure dans la description des lignes de produits

## 4.0.72.0
- Correction de la synchronisation d'un produit variable considéré comme un produit unique
- Desactivation de la connection a l'ancienne API et correction de la creation d'un produit sur WooCommerce avec la nouvelle api
- N'ajoute pas le lien vers le produit Woocommerce dans Dolibarr si l'adresse fait plus de 255 charactères
- Ajout de la possibilité de change le type de factures créées (standard ou d'acompte)
- Timeout pour le telechargement des images par defaut a 30s
- Correction de la création de la facture (renseignement du tiers client manquant lors de la creation de la facture)
- Correction ajout oldcopy lors de la mise a jour sur Dolibarr d'un produit, tiers, commande, ... lors d'une synchro

## 4.0.71.0
- Correction de la synchro des clients / produits depuis la synchro d'une commande
- Correction de la synchro d'un client dans le cas ou il était en tant que contact dans le client anonyme Dolibarr préalablement

## 4.0.70.0
- Corrige le cas ou plusieurs produits sont liés au même produit d'un site
- Correction break manquant

## 4.0.69.0
- Case à cocher pour ne pas synchroniser les stocks sur un produit
- Multi-entrepôt (Dolibarr vers ECommerce)
- Synchronisation du produit parent et non ses variations (commandes inclus)
- Ajout d'une variable globale ECOMMERCE_DOWNLOAD_TIMEOUT pour definir le timeout lors du téléchargement des images (par défaut: 5s)
- Ne synchronise que les produits publiés ou présents dans une commande
- Reecriture du script de mise a jour des stocks de Dolibarr vers Ecommerce

## 4.0.68.0
- Correction de l'execution des mises a jour des stock de Dolibarr vers Ecommerce
- Correction mineure

## 4.0.67.0
- Correction mineure et compatibilité dolibarr v7

## 4.0.66.0
- Correction mineure

## 4.0.65.0
- Ajout d'une nouveau champ supplémentaire sur le produit pour gérer le stock (synchronisé avec l'option 'Gérer le stock' sur WooCommerce)
- Ajout du support des webhooks pour les produits (Creation et actualisation des produits)
- Ajout d'une nouvelle option pour determiner l'unité de poids d'un site

## 4.0.64.0
- Ajout de l'option "Ne pas synchroniser les produits lors de la synchronisation des commandes" lors de la synchronisation manuelle des commandes
- Correction $this dans la page de configuration a la suppression d'un site

## 4.0.63.0
- Ajout d'une date de relance pour la synchronisation des commandes de ECommerce vers dolibarr (Les commandes dont la date de creation est entérieur seront ignorées).

## 4.0.62.0
- Ajout d'un filtre sur les ids des clients a synchroniser spécifiquements lors de la synchro manuel des clients.
- Rattache les contacts au tiers possedant l'email du contact lors de la synchro des contact du client.
- Correction d'une erreur de creation de liens lorsque la commande/facture est ignoré car créer à partir d'un client dont le type n'est pas authorisé.
- Peut remettre un webhook aillant le statut 'traité' dans statut 'en attente de traitement'
- Ajout d'une tache planifiée pour vérifié que les webhooks sont actifs sur WooCommerce et previent par email si il y a des erreurs si la variable global ECOMMERCE_NOTIFY_EMAIL_ERRORS_CHECK_WEBHOOKS_STATUS contenant les adresses emails séparées par des virgules est définie.

## 4.0.61.0
- Ajout message d'erreur lors de la convertion des données des produits et commandes vers Dolibarr.
- Correction de l'enregistrement de la derniere date servant de date de début pour la prochaine syncho des clients, produits et commandes.
- Ajout d'un test pour ne pas traité les triggers dont l'object passé n'est pas celui attendu.

## 4.0.60.0
- Ajout de la mise à jour du prix promo (avec les dates) de Dolibarr vers Woocommerce avec le prix regulier.
- Correction de la mise à jour des dates promos de Woocommerce vers Dolibarr.
- Correction de la récupération de la TVA du produit lors de la synchro WooCommerce vers Dolibarr.

## 4.0.59.0
- Correction messages d'erreurs.

## 4.0.58.0
- Ajout d'un widget pour voir l'etat des webhooks.
- Ajout d'une page pour voir la liste des webhooks en cours (et pouvoir remettre en traitement les erreurs ou warnings et pouvoir mettre en traité les warnings)
- Suppression de la colonne datee dans la table llx_ecommerce_pending_webhooks.
- Archive maintenant les lignes de webhooks traités dont la date de traitement est inférieur à 7 (ou ECOMMERCE_PROCESSING_WEBHOOK_LOGS_BEFORE_X_DAYS) jours dans un fichier de log situé au même endroits que celui de dolibarr.
  Les colonnes de l'archive sont maintenant "Date de traitement", "Date de création", "Id du site", "Id de livraison", "Id du webhook", "Sujet du webhook", "Resource du webhook", "Événement du webhook", "Source", "Données"

## 4.0.57.0
- Ajout d'un try/catch si une erreur 500 se produit lors de la tache planifié de syncho des webhooks.
- La variable globale ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION_WITHOUT_ERRORS à été retiré.
- Seul les webhooks au statut non traités seront traités.
- Les lignes de webhooks dont ont a recu un update mais que l'ont ne trouve pas la commande est mise au statut warning.
- La synchronisation depuis le site des produits lorsque la référence n'est pas renseignée ne produit une erreur que si le module de generation automatique des références produits n'est pas activé.
- Archive les lignes de webhooks traités dont la date de création est inférieur à 7 (ou ECOMMERCE_PROCESSING_WEBHOOK_LOGS_BEFORE_X_DAYS) jours dans un fichier de log situé au même endroits que celui de dolibarr.

## 4.0.56.0
- Ajout du support avec WPML avec la variable globale ECOMMERCENG_WOOCOMMERCE_WPML_SUPPORT (met aussi à jour les information sur les traductions des produits).
- Correction ajout du tag racine boutique sur le produit.
- Correction images synchronisés (quand x images portent les mêmes noms mais situées dans des répertoires differents dans WooCommerce)
- Correction warnings

## 4.0.55.0
- Regarde si la commande existe lors d'un update depuis la synchro par webhook et genere une erreur si elle n'est pas trouvé.
- Ajout d'un variable global ECOMMERCENG_UPPERCASE_LASTNAME pour forcer les majuscule sur le nom et la premiere lettre du prenom en majuscule.

## 4.0.54.0
- Correction de la synchronisation des stocks sur les produits de WooCommerce vers Dolibarr.

## 4.0.53.0
- Le tiers aura l'addresse de facturation lors de sa creation.
- Ajout de la variable globale ECOMMERCENG_UPPERCASE_LASTNAME pour forcer le nom en majuscule.

## 4.0.52.0
- Correction de la tache planifié de syncho des webhooks.

## 4.0.51.0
- Correction lors de la synchronisation d'image de produits comportant des caractères spéciaux dans leur nom.

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
