# SchoolPay

Application Symfony 6.1 de gestion des paiements scolaires : back-office administratif, caisse, recus, impayes, rapports et espace parent.

## Prerequis

- PHP 8.1 ou superieur
- Composer
- Docker Compose pour PostgreSQL
- Symfony CLI optionnel

## Installation

```bash
composer install
docker compose up -d
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:load-demo-data
symfony serve
```

Sans Symfony CLI :

```bash
php -S 127.0.0.1:8000 -t public
```

## Comptes de demonstration

Mot de passe commun : `password123`

- Administrateur : `admin@schoolpay.test`
- Comptable : `comptable@schoolpay.test`
- Directeur : `directeur@schoolpay.test`
- Parent : `parent@schoolpay.test`

## Roles

- `ROLE_ADMIN` : acces complet, utilisateurs, audit et parametres.
- `ROLE_COMPTABLE` : caisse, paiements, recus, eleves, impayes et rapports.
- `ROLE_DIRECTEUR` : consultation des tableaux de bord et rapports.
- `ROLE_PARENT` : espace parent limite aux enfants associes au profil parent.

## URLs principales

- Accueil : `/`
- Connexion : `/login`
- Back-office : `/backoffice`
- Eleves : `/backoffice/students`
- Paiements : `/backoffice/payments`
- Nouveau paiement : `/backoffice/payments/new`
- Recus : `/backoffice/receipts`
- Impayes : `/backoffice/outstanding`
- Rapports : `/backoffice/reports`
- Espace parent : `/parent`
- Verification recu : `/receipt/verify/{code}`

## Validation

Commandes utiles :

```bash
php bin/console cache:clear
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
php bin/phpunit
```

Les integrations MVola, Orange Money, Airtel Money, SMS, WhatsApp et PDF avance ne sont pas activees sans API officielle ou bibliotheque dediee. L'application garde des champs de reference et une architecture de services pour les raccorder plus tard.
