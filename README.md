# Data Lifecycle

<p align="center"><img src="https://raw.githubusercontent.com/kaveraa/data-lifecycle/main/art/banner.svg" alt="Data Lifecycle" width="100%"></p>

[![Tests](https://github.com/kaveraa/data-lifecycle/actions/workflows/tests.yml/badge.svg)](https://github.com/kaveraa/data-lifecycle/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/kaveraa/data-lifecycle.svg)](https://packagist.org/packages/kaveraa/data-lifecycle)
[![Téléchargements](https://img.shields.io/packagist/dt/kaveraa/data-lifecycle.svg)](https://packagist.org/packages/kaveraa/data-lifecycle)
[![PHP](https://img.shields.io/packagist/dependency-v/kaveraa/data-lifecycle/php.svg)](https://packagist.org/packages/kaveraa/data-lifecycle)
[![Licence](https://img.shields.io/github/license/kaveraa/data-lifecycle.svg)](https://github.com/kaveraa/data-lifecycle/blob/main/LICENSE)

**Français** - [English](https://github.com/kaveraa/data-lifecycle/blob/main/README.en.md)

Le RGPD demande de ne pas garder les données personnelles plus longtemps que nécessaire (article 5.1.e). Dans la vraie vie, presque personne ne le fait : il faudrait repérer les comptes inactifs, prévenir les personnes, désactiver sans tout casser, laisser une chance de revenir, puis anonymiser ou supprimer. Et pouvoir le montrer.

Ce paquet fait ce parcours, en **une déclaration par entité**, pour **Laravel** et pour **Symfony / Doctrine**.

```php
#[KeepFor('3 years')]             // on garde 3 ans apres le dernier signe de vie
#[WarnBefore('30 days')]          // un e-mail 30 jours avant l'echeance
#[DisableFirst('30 days')]        // desactivation, puis 30 jours pour revenir
#[ThenAnonymise('email', 'name')] // ensuite, la ligne reste mais elle est anonyme
class User
{
}
```

```bash
php artisan lifecycle:report   # ce qui se passerait, sans rien ecrire
php artisan lifecycle:run      # pour de vrai
```

- **Le parcours entier** : prévenir, désactiver, laisser une période de grâce, puis anonymiser ou supprimer. Pas seulement supprimer.
- **Mode observation** : `lifecycle:report` dit exactement combien de lignes seraient touchées, et lesquelles, sans écrire une seule fois. C'est ce qu'on lance en production pendant des semaines avant d'oser le reste.
- **Réversible** : tant que la période de grâce dure, la personne qui revient retrouve son compte intact, et le compteur repart de zéro.
- **Aucun schéma imposé** : une règle ne lit que les colonnes dont elle a besoin. Une règle simple fonctionne avec une seule colonne de date.
- **Deux frameworks, un seul paquet** : le cycle est écrit une fois, en PHP pur ; Laravel et Doctrine ne sont que des pilotes.
- **Testable** : une horloge se remplace (`FrozenClock`), donc trois ans passent en trois lignes de test.
- **Léger** : deux interfaces PSR, rien d'autre.

---

## Sommaire

- [Le problème](#le-problème)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Déclarer une règle](#déclarer-une-règle)
- [Les colonnes à ajouter](#les-colonnes-à-ajouter)
- [Lancer le cycle](#lancer-le-cycle)
- [Le mode observation](#le-mode-observation)
- [Prévenir la personne](#prévenir-la-personne)
- [Quand la personne revient](#quand-la-personne-revient)
- [Anonymiser](#anonymiser)
- [Le signal d'activité](#le-signal-dactivité)
- [Savoir où en est une ligne](#savoir-où-en-est-une-ligne)
- [Toutes les options](#toutes-les-options)
- [Ce que ce paquet ne fait pas](#ce-que-ce-paquet-ne-fait-pas)
- [Développement](#développement)

## Le problème

Une base de données garde tout, pour toujours, par défaut. Les comptes abandonnés depuis six ans sont encore là, avec leur adresse, leur nom, leur historique. C'est un risque en cas de fuite, et c'est contraire au RGPD.

La réponse habituelle est un script maison, lancé une fois, qui supprime en masse. Il fait peur, donc personne ne le lance. Ce paquet remplace ce script par quelque chose qu'on ose exécuter :

```
dernier signe de vie                                                    aujourd'hui
        |                                                                    |
        |------------------- 3 ans (KeepFor) --------------------|           |
                                          |                      |           |
                                    rappel J-30            desactivation  anonymisation
                                    (WarnBefore)          (DisableFirst)   ou suppression
                                                          |<- 30 jours ->|
                                                           pour revenir
```

## Prérequis

- PHP 8.2 ou plus.
- Laravel 12+, ou Symfony 7.2+ avec Doctrine ORM 3+.
- Une colonne de date par entité concernée : le dernier signe de vie (`last_active_at`, `last_order_at`, `sent_at`, à vous de choisir).

## Installation

```bash
composer require kaveraa/data-lifecycle
```

### Laravel

```bash
php artisan lifecycle:install
```

La commande publie `config/data-lifecycle.php` et une migration d'exemple. Le fournisseur de services est découvert tout seul.

### Symfony

Ajoutez le bundle dans `config/bundles.php` :

```php
return [
    // ...
    Kaveraa\DataLifecycle\Symfony\DataLifecycleBundle::class => ['all' => true],
];
```

Puis créez `config/packages/data_lifecycle.yaml` :

```yaml
data_lifecycle:
    discover:
        - App\Entity\User
```

## Déclarer une règle

Deux façons, au choix. Les attributs sont plus lisibles, la configuration est plus pratique quand la règle change selon l'environnement. Si les deux existent pour une même classe, la configuration gagne.

### Avec des attributs

```php
use Kaveraa\DataLifecycle\Attribute\DisableFirst;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenAnonymise;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;
use Kaveraa\DataLifecycle\Attribute\WarnBefore;
use Kaveraa\DataLifecycle\Strategy;

#[KeepFor('3 years')]
#[WarnBefore('30 days')]
#[WarnBefore('7 days')]
#[DisableFirst('30 days')]
#[ThenAnonymise('email', 'name', ['birth_date' => Strategy::YearOnly])]
class User extends Authenticatable
{
}
```

Une entité peut n'avoir qu'un début et une fin :

```php
#[KeepFor('90 days', since: 'sent_at')]
#[ThenDelete]
class Invitation
{
}
```

Il faut ensuite dire où chercher ces classes, dans `discover` :

```php
// config/data-lifecycle.php
'discover' => [
    App\Models\User::class,
    App\Models\Invitation::class,
],
```

### Avec la configuration

```php
// config/data-lifecycle.php
'subjects' => [
    App\Models\User::class => [
        'keep_for' => '3 years',
        'warn_before' => ['30 days', '7 days'],
        'grace' => '30 days',
        'anonymise' => ['email' => 'email', 'name' => 'text'],
    ],

    App\Models\Invitation::class => [
        'keep_for' => '90 days',
        'fields' => ['since' => 'sent_at'],
        'delete' => true,
    ],
],
```

Les durées s'écrivent en toutes lettres : `3 years`, `18 months`, `30 days`, `48 hours`. La forme ISO 8601 (`P30D`) est acceptée aussi.

## Les colonnes à ajouter

Vous n'ajoutez que les colonnes dont votre règle a besoin.

| Colonne | Quand elle est nécessaire | Type |
|---|---|---|
| `last_active_at` | toujours (c'est le point de départ) | date, nullable |
| `lifecycle_warn_stage` | seulement avec `#[WarnBefore]` | petit entier, défaut 0 |
| `lifecycle_warned_at` | seulement avec `#[WarnBefore]` | date, nullable |
| `disabled_at` | seulement avec `#[DisableFirst]` | date, nullable |
| `anonymised_at` | seulement avec `#[ThenAnonymise]` | date, nullable |

Une règle `#[KeepFor] + #[ThenDelete]` n'a donc besoin **que** de la colonne de date. Les noms se changent, globalement ou règle par règle :

```php
'fields' => ['since' => 'derniere_activite', 'disabled_at' => 'desactive_le'],
```

Pensez à un index sur la colonne de date, et sur `disabled_at` : ce sont elles qui portent les requêtes.

## Lancer le cycle

```bash
php artisan lifecycle:run                       # tout, pour de vrai
php artisan lifecycle:run --dry-run             # sans rien ecrire
php artisan lifecycle:run --subject="App\Models\User"
php artisan lifecycle:run --step=warn           # seulement les rappels
php artisan lifecycle:run --limit=500           # au maximum 500 lignes par etape
```

Sous Symfony, les mêmes commandes s'appellent `bin/console lifecycle:run` et `bin/console lifecycle:report`.

Une fois par jour suffit. Laravel :

```php
// routes/console.php
Schedule::command('lifecycle:run')->dailyAt('03:30');
```

Symfony, avec cron :

```
30 3 * * * /usr/bin/php /var/www/bin/console lifecycle:run
```

L'exécution est faite pour être coupée et reprise : `--limit` borne chaque étape, et la commande suivante reprendra là où elle en était.

### La première exécution

Sur une base qui n'a jamais été nettoyée, tout le retard sort d'un coup : des milliers de lignes sont déjà au-delà de l'échéance. Elles reçoivent leur premier rappel, puis sont désactivées dans la même exécution, ce qui ne laisse à personne le temps de réagir. Deux précautions :

1. Lancez `lifecycle:report` d'abord, et regardez les nombres.
2. Rattrapez le retard en douceur : jouez `--step=warn` seul pendant la durée de votre rappel (30 jours si vous prévenez 30 jours avant), puis seulement ensuite la commande complète.

```bash
php artisan lifecycle:run --step=warn --limit=200   # pendant 30 jours
php artisan lifecycle:run                           # ensuite
```

## Le mode observation

C'est la porte d'entrée du paquet. Rien n'est écrit, aucun événement n'est émis, et le rapport dit ce qui se passerait :

```bash
php artisan lifecycle:report
```

```
Essai a blanc : rien n'a ete ecrit.

 Entite       Etape      Lignes   Exemples
 User         warn          412   18, 45, 61, 88, 90
 User         disable        73   7, 12, 30, 44, 51
 User         erase          19   3, 9, 14, 21, 25
 Invitation   erase       1 204   2, 4, 5, 6, 8
```

Une ligne très en retard peut apparaître deux fois, dans `warn` et dans `disable` : en observation rien n'est écrit entre les deux étapes, donc le rapport montre bien ce qu'une vraie exécution ferait, l'une après l'autre.

Laissez-le tourner quelques semaines dans une tâche planifiée, regardez les nombres se stabiliser, puis enlevez `--dry-run`. On peut aussi bloquer toute écriture depuis la configuration, le temps de la mise en place :

```dotenv
DATA_LIFECYCLE_DRY_RUN=true
```

Tant que ce réglage est vrai, `lifecycle:run` reste en observation et le dit.

## Prévenir la personne

Le paquet n'envoie aucun e-mail : il vous dit quand le faire, et vous écrivez le message. Cinq événements existent, écoutables comme n'importe quel événement de votre framework.

```php
use Kaveraa\DataLifecycle\Event\SubjectWarned;

Event::listen(function (SubjectWarned $event): void {
    $user = $event->entity();

    Mail::to($user)->send(new AccountExpiring(
        dueAt: $event->dueAt,      // date de la desactivation
        reminder: $event->warnIndex, // 0 pour le premier rappel, 1 pour le suivant
    ));
});
```

| Événement | Quand |
|---|---|
| `SubjectWarned` | un rappel doit partir |
| `SubjectDisabled` | la ligne vient d'être désactivée |
| `SubjectAnonymised` | les données personnelles sont parties |
| `SubjectDeleted` | la ligne a été supprimée |
| `SubjectReactivated` | la personne est revenue |

Aucun événement n'est émis en mode observation.

## Quand la personne revient

C'est tout l'intérêt de la période de grâce : la désactivation n'est pas une suppression.

```php
use Kaveraa\DataLifecycle\Lifecycle;

public function login(Request $request, Lifecycle $lifecycle)
{
    // ...
    $lifecycle->reactivate($user); // plus de rappel, plus de desactivation, compteur remis a zero
}
```

`reactivate()` renvoie `false` sur une ligne déjà anonymisée : ce qui est parti ne revient pas.

## Anonymiser

Anonymiser plutôt que supprimer garde vos compteurs justes (commandes, statistiques, factures) tout en faisant disparaître la personne.

```php
#[ThenAnonymise('email', 'name')]
```

Chaque champ reçoit une stratégie. Sans précision, `Strategy::Auto` choisit d'après le nom : un champ qui contient `mail` reçoit une adresse, tout le reste reçoit `[removed]`.

| Stratégie | Résultat |
|---|---|
| `Strategy::Email` | `anonymous-42@anonymous.invalid`, unique par ligne |
| `Strategy::Text` | `Anonymous` |
| `Strategy::Redact` | `[removed]` |
| `Strategy::EmptyText` | une chaîne vide |
| `Strategy::Nullify` | `null` (la colonne doit l'accepter) |
| `Strategy::Zero` | `0` |
| `Strategy::YearOnly` | garde l'année d'une date, met le 1er janvier |
| `Strategy::Hash` | une empreinte : la valeur ne revient pas, mais deux valeurs égales le restent |

`Strategy::Hash` sert quand vous avez besoin de savoir que deux lignes venaient de la même personne, sans savoir qui. Les textes de remplacement se changent dans la configuration.

## Le signal d'activité

Tout repose sur une date fiable. Écrire `last_active_at` à chaque requête coûte une écriture par requête : inacceptable. Le paquet fournit un garde-fou qui n'écrit qu'une fois par fenêtre (15 minutes par défaut).

Laravel, dans `bootstrap/app.php` :

```php
$middleware->web(append: [
    \Kaveraa\DataLifecycle\Laravel\Middleware\TrackActivity::class,
]);
```

Sous Symfony, l'abonné est branché tout seul par le bundle. La fenêtre se règle avec `activity.throttle` (en minutes ; `0` désactive complètement).

Attention au piège : une connexion automatique par cookie, un appel d'API de supervision ou une tâche planifiée qui touche la table remettent le compteur à zéro. Un compte "actif" parce qu'un robot passe dessus n'est pas actif. Choisissez comme point de départ une action volontaire de la personne.

## Savoir où en est une ligne

```php
$lifecycle->stageOf($user);  // Stage::Active, Warned, Disabled ou Erased
$lifecycle->dueAt($user);    // date de la desactivation a venir
```

Avec Laravel, le trait `HasLifecycle` ajoute les mêmes réponses sur le modèle, et des scopes :

```php
use Kaveraa\DataLifecycle\Laravel\Concerns\HasLifecycle;

class User extends Authenticatable
{
    use HasLifecycle;
}

User::active()->count();
User::disabled()->get();
$user->lifecycleStage();
$user->lifecycleDueAt();
$user->reactivate();
```

## Toutes les options

| Option | Défaut | Rôle |
|---|---|---|
| `dry_run` | `false` | Bloque toute écriture, partout |
| `limit` | `1000` | Lignes maximum par étape et par règle |
| `fields.since` | `last_active_at` | Colonne du dernier signe de vie |
| `fields.warn_stage` | `lifecycle_warn_stage` | Nombre de rappels déjà envoyés |
| `fields.warned_at` | `lifecycle_warned_at` | Date du dernier rappel |
| `fields.disabled_at` | `disabled_at` | Date de désactivation |
| `fields.anonymised_at` | `anonymised_at` | Date d'anonymisation |
| `anonymiser.email_domain` | `anonymous.invalid` | Domaine des adresses de remplacement |
| `anonymiser.redacted_text` | `[removed]` | Texte de remplacement |
| `anonymiser.anonymous_name` | `Anonymous` | Nom de remplacement |
| `anonymiser.pepper` | la clé de l'application | Sel de `Strategy::Hash` |
| `activity.throttle` | `15` | Minutes entre deux écritures du signal d'activité |
| `subjects` | `[]` | Les règles écrites en configuration |
| `discover` | `[]` | Les classes dont on lit les attributs |

## Ce que ce paquet ne fait pas

- **Ce n'est pas un conseil juridique.** Les durées sont les vôtres : elles dépendent de votre activité et de vos obligations (une facture se garde dix ans, un CV non retenu deux ans). Le paquet applique la durée que vous décidez.
- **Il ne tient pas votre registre des traitements** et ne répond pas aux demandes d'accès ou de portabilité.
- **Il ne touche pas à vos sauvegardes** ni à vos journaux : une ligne anonymisée en base reste lisible dans une sauvegarde d'hier. Pensez à la durée de conservation de vos sauvegardes.
- **Il ne devine pas vos relations** : anonymiser un utilisateur ne vide pas les tables liées. Déclarez une règle par entité, ou faites le ménage dans un écouteur de `SubjectAnonymised`.

## Développement

```bash
git clone https://github.com/kaveraa/data-lifecycle.git
cd data-lifecycle
composer install
vendor/bin/phpunit
```

Pour proposer une modification, lisez le guide [CONTRIBUTING.md](https://github.com/kaveraa/data-lifecycle/blob/main/CONTRIBUTING.md). Voir le [CHANGELOG](https://github.com/kaveraa/data-lifecycle/blob/main/CHANGELOG.md) pour l'historique des versions.

Pour signaler une faille, ouvrez une [alerte de sécurité privée](https://github.com/kaveraa/data-lifecycle/security/advisories/new) plutôt qu'une issue publique.

## Licence

MIT. Voir [LICENSE](https://github.com/kaveraa/data-lifecycle/blob/main/LICENSE).
