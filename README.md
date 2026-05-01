# Let's Smile

Application web PHP/MySQL pour la gestion d'un cabinet dentaire à Madagascar.

## Environnement prévu

- PHP 8.3.30
- MySQL ou MariaDB via Laragon
- Fuseau horaire : `Indian/Antananarivo`
- Devise : `MGA / Ar`
- Interface : français, avec préparation pour ajouter le malgache plus tard

## Installation locale avec Laragon

1. Placez ce dossier dans le répertoire `www` de Laragon, ou configurez un Virtual Host dont le document root pointe vers `public/`.
2. Importez le fichier SQL : `database/lets_smile_schema.sql`.
   Attention : ce script est prévu pour une installation propre et réinitialise les tables de la base `lets_smile`.
3. Vérifiez les accès MySQL dans `.env`.
4. Ouvrez l'application :
   - avec Virtual Host : `http://lets-smile.test`
   - ou directement : `http://localhost/lets-smile/public`

## Compte de démonstration

- Cabinet : `lets-smile`
- Email : `admin@letssmile.mg`
- Mot de passe : `admin123`

## Module Patients

Le module Patients est disponible sur `/patients` après connexion. Il inclut :

- liste paginée avec recherche ;
- panneau de détails patient ;
- création et modification ;
- dossier médical de base ;
- archivage ;
- données de démonstration dans `database/seeders/patients_demo.sql`.

## Module Rendez-vous

Le module Rendez-vous est disponible sur `/appointments` ou `/rendez-vous` après connexion. Il inclut :

- statistiques du jour et par statut ;
- liste paginée avec onglets ;
- recherche patient, praticien, salle ou type de soin ;
- panneau de détail ;
- création et modification ;
- annulation ;
- données de démonstration dans `database/seeders/appointments_demo.sql`.

## Module Traitements

Le module Traitements est disponible sur `/treatments` ou `/traitements` après connexion. Il inclut :

- liste des plans de traitement ;
- recherche et onglets par statut ;
- détail patient à droite ;
- étapes du traitement ;
- progression calculée depuis les étapes ;
- montant total, montant payé et reste à payer ;
- emplacements Devis et Facture pour le futur module de facturation ;
- création, modification, suspension, reprise et terminaison ;
- données de démonstration dans `database/seeders/treatments_demo.sql`.

## Module Facturation

Le module Facturation est disponible sur `/billing` ou `/facturation` après connexion. Il inclut :

- indicateurs financiers : chiffre d'affaires, encaissements, factures en attente et impayées ;
- liste paginée des factures avec onglets par statut ;
- recherche par patient, référence de facture ou traitement ;
- panneau de détail de facture à droite ;
- détail des actes facturés ;
- création et modification d'une facture ;
- marquage comme payée et annulation ;
- montants en Ariary malgache via le format `MGA / Ar` ;
- données de démonstration dans `database/seeders/billing_demo.sql`.

## Module Paiements

Le module Paiements est disponible sur `/payments` ou `/paiements` après connexion. Il inclut :

- indicateurs d'encaissements, paiements reçus, en attente, échoués et annulés ;
- liste paginée avec onglets par statut ;
- recherche par patient, facture, méthode ou référence de paiement ;
- panneau de détail de paiement à droite ;
- lien direct vers le patient et la facture ;
- création et modification d'un paiement ;
- statuts : reçu, en attente, échoué, remboursé, annulé ;
- synchronisation automatique du montant payé et du statut de la facture ;
- données de démonstration dans `database/seeders/payments_demo.sql`.

## Exports PDF

Les exports PDF sont disponibles après connexion et respectent les permissions des modules :

- facture : `/billing/pdf?id=ID_FACTURE` ;
- reçu de paiement : `/payments/receipt?id=ID_PAIEMENT` ;
- devis depuis un plan de traitement : `/treatments/quote-pdf?treatment=ID_TRAITEMENT` ;
- devis enregistré : `/quotes/pdf?id=ID_DEVIS` ;
- dossier patient : `/patients/pdf?id=ID_PATIENT`.

Les documents sont générés par le service interne `app/Services/Pdf` sans dépendance Composer externe.

## Module Stock

Le module Stock est disponible sur `/stock` après connexion. Il inclut :

- indicateurs de valeur de stock, nombre de produits, stock faible et rupture ;
- liste paginée des produits avec filtres par catégorie et statut ;
- recherche par nom, référence, code-barres, catégorie ou fournisseur ;
- panneau de détail produit à droite ;
- fournisseur, référence, code-barres, prix unitaire, stock actuel et stock minimum ;
- statuts calculés : en stock, stock faible, rupture ;
- création et modification d'un produit ;
- mouvements de stock : entrée, sortie, ajustement ;
- historique des mouvements ;
- données de démonstration dans `database/seeders/stock_demo.sql`.

## Module Documents

Le module Documents est disponible sur `/documents` après connexion. Il inclut :

- statistiques : total des documents, documents patients, documents cabinet et espace utilisé ;
- dossiers/catégories à gauche avec compteurs ;
- stockage local dans `storage/documents` ;
- liste paginée avec recherche ;
- panneau de détail du document à droite ;
- rattachement à un patient, une facture ou un traitement ;
- upload sécurisé avec contrôle extension, taille maximale et stockage local ;
- téléchargement du fichier ;
- suppression du document et du fichier associé ;
- données de démonstration dans `database/seeders/documents_demo.sql`.

## Module Messages

Le module Messages est disponible sur `/messages` après connexion. Il inclut :

- statistiques de boîte de réception, messages patients, messages équipe et archives ;
- liste des conversations avec recherche et filtres ;
- fil de discussion central avec bulles entrantes/sortantes ;
- panneau de détail du contact à droite ;
- liens rapides vers le patient, les rendez-vous et les documents ;
- création d'une conversation patient ou équipe ;
- réponse à une conversation existante ;
- archivage et réouverture des conversations ;
- données de démonstration dans `database/seeders/messages_demo.sql`.

## Module Rapports

Le module Rapports est disponible sur `/reports` ou `/rapports` après connexion. Il inclut :

- indicateurs : chiffre d'affaires, rendez-vous honorés, nouveaux patients et taux d'occupation ;
- filtres par période, catégorie de traitement, praticien et source ;
- graphique d'évolution du chiffre d'affaires ;
- répartition du chiffre d'affaires par catégorie de traitement ;
- activité par praticien ;
- répartition des rendez-vous par statut ;
- top traitements facturés ;
- comparaison de performance avec la période précédente ;
- export CSV du rapport avec journalisation dans `reports_exports`.

## Module Paramètres

Le module Paramètres est disponible sur `/settings` ou `/parametres` après connexion. Il inclut :

- informations du cabinet avec modification ;
- logo et identité visuelle ;
- préférences générales : langue, fuseau horaire, format, devise et thème ;
- notifications email, SMS, patients, stock, paiements et système ;
- sauvegarde manuelle JSON dans `storage/backups` ;
- signature électronique ;
- préfixes de numérotation des factures, reçus, devis et avoirs ;
- liste des utilisateurs du cabinet ;
- creation, modification, activation et blocage des comptes ;
- changement du mot de passe de l'utilisateur connecte ;
- roles par cabinet avec compteur de permissions ;
- matrice de permissions par role et par module ;
- journal recent des actions utilisateurs dans `audit_logs` ;
- liens de paramètres avancés pour les prochains sous-modules.

## Structure

```txt
app/          Code applicatif MVC
config/       Configuration
database/     SQL, migrations et seeders
public/       Point d'entrée web et assets
routes/       Définition des routes
storage/      Documents, logs et sauvegardes
```

## Prochaine étape conseillée

Apres les utilisateurs et permissions, la prochaine etape conseillee est une passe de finition globale : controle responsive, validations metier, exports PDF, sauvegardes automatisees et preparation production Laragon/MySQL.
