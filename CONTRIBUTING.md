# Contribuer / Contributing

**Français** - [English](#english)

## Français

Merci de votre aide ! Toute modification passe par une **Pull Request** : la branche `main` est protégée et la CI doit être verte pour fusionner.

### 1. Préparer le projet

```bash
git clone https://github.com/kaveraa/data-lifecycle.git
cd data-lifecycle
composer install
```

Il faut PHP 8.2 ou plus, avec les extensions `mbstring` et `pdo_sqlite`.

### 2. Créer une branche

```bash
git checkout -b fix/nom-court-du-changement
```

Préfixes conseillés : `feat/` (nouveauté), `fix/` (correction), `docs/` (documentation).

### 3. Lancer les tests

```bash
vendor/bin/phpunit                      # tout
vendor/bin/phpunit --testsuite unit     # le coeur, sans framework
vendor/bin/phpunit --testsuite laravel  # l'integration Laravel
vendor/bin/phpunit --testsuite symfony  # l'integration Symfony et Doctrine
```

Les tests tournent sur SQLite en mémoire : il n'y a aucune base de données à lancer. Si votre PHP n'a pas `pdo_sqlite`, passez par Docker :

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" php:8.4-cli vendor/bin/phpunit
```

### 4. Règles du projet

- **Tests** : toute correction ou nouveauté est accompagnée d'un test. Le temps se maîtrise avec `FrozenClock`, jamais avec `sleep()`.
- **Rien n'est écrit en mode observation** : si vous touchez au parcours, ajoutez un test qui vérifie que la base n'a pas bougé.
- **Aucun schéma imposé** : une règle ne doit lire que les colonnes dont elle a besoin. Une application sans rappel et sans désactivation doit fonctionner avec une seule colonne de date.
- **Le coeur ne connaît aucun framework** : `src/` à la racine ne dépend que de PHP et des interfaces PSR. Laravel vit dans `src/Laravel/`, Symfony dans `src/Symfony/`, Doctrine dans `src/Doctrine/`.
- **Dépendances** : le paquet ne dépend que de `psr/clock` et `psr/event-dispatcher`. Pas de nouvelle dépendance sans discussion.
- **Documentation** : mettez à jour `README.md` (français) **et** `README.en.md` (anglais simple), ainsi que le `CHANGELOG.md` (section en haut, en français et en anglais).
- **Commits** : en anglais simple, compréhensible par un débutant. Phrases courtes, pas de jargon.
- **Caractères** : uniquement des caractères du clavier dans les fichiers et les commits : `-` (pas de tiret long), `"` (pas de guillemets français), `->` (pas de flèche), pas d'emoji ni d'icône. Les lettres accentuées du français sont acceptées.

### 5. Ouvrir la Pull Request

Poussez votre branche, ouvrez une PR vers `main` et remplissez la checklist proposée. La PR peut être fusionnée quand le contrôle **All tests passed** est vert.

### Publier une version (mainteneur)

Après la fusion : mettre à jour le `CHANGELOG.md` (via une PR), créer un tag `vX.Y.Z` sur `main` et le pousser. Packagist met le paquet à jour tout seul grâce au crochet GitHub.

---

## English

Thank you for your help! Every change goes through a **Pull Request**: the `main` branch is protected, and the CI must be green before merge.

### 1. Set up the project

```bash
git clone https://github.com/kaveraa/data-lifecycle.git
cd data-lifecycle
composer install
```

You need PHP 8.2 or more, with the `mbstring` and `pdo_sqlite` extensions.

### 2. Create a branch

```bash
git checkout -b fix/short-name-of-the-change
```

Suggested prefixes: `feat/` (new feature), `fix/` (bug fix), `docs/` (documentation).

### 3. Run the tests

```bash
vendor/bin/phpunit                      # everything
vendor/bin/phpunit --testsuite unit     # the core, without any framework
vendor/bin/phpunit --testsuite laravel  # the Laravel side
vendor/bin/phpunit --testsuite symfony  # the Symfony and Doctrine side
```

Tests run on in-memory SQLite: there is no database to start. If your PHP has no `pdo_sqlite`, use Docker:

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" php:8.4-cli vendor/bin/phpunit
```

### 4. Project rules

- **Tests**: every fix or new feature comes with a test. Time is controlled with `FrozenClock`, never with `sleep()`.
- **Observe mode writes nothing**: if you touch the journey, add a test that checks the database did not move.
- **No schema is forced**: a policy only reads the columns it needs. An application with no reminder and no disable step must work with a single date column.
- **The core knows no framework**: `src/` at the root only depends on PHP and the PSR interfaces. Laravel lives in `src/Laravel/`, Symfony in `src/Symfony/`, Doctrine in `src/Doctrine/`.
- **Dependencies**: the package only depends on `psr/clock` and `psr/event-dispatcher`. No new dependency without a discussion.
- **Documentation**: update `README.md` (French) **and** `README.en.md` (simple English), and the `CHANGELOG.md` (section at the top, in French and English).
- **Commits**: in simple English, easy to read for a beginner. Short sentences, no jargon.
- **Characters**: only keyboard characters in files and commits: `-` (no long dash), `"` (no French quotes), `->` (no arrow), no emoji or icon. French accented letters are fine.

### 5. Open the Pull Request

Push your branch, open a PR to `main` and fill in the checklist. The PR can be merged when the **All tests passed** check is green.

### Release a version (maintainer)

After the merge: update the `CHANGELOG.md` (with a PR), create a `vX.Y.Z` tag on `main` and push it. Packagist updates the package on its own thanks to the GitHub hook.
