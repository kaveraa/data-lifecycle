# Changelog

**FR** Toutes les évolutions notables du paquet sont listées ici. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

**EN** All important changes of the package are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [semantic versioning](https://semver.org/).

## [1.0.2] - 2026-09-29

### Documentation

- **FR** La description du paquet dans `composer.json` est maintenant en anglais.
  **EN** The package description in `composer.json` is now in English.

## [1.0.1] - 2026-09-29

### Documentation

- **FR** Le README affiché par défaut est maintenant en anglais (`README.md`), le français est dans `README.fr.md`.
  **EN** The default README is now in English (`README.md`), the French version is in `README.fr.md`.

## [1.0.0] - 2026-09-28

### Ajouté / Added

- **FR** Règles de conservation par entité, en attributs (`#[KeepFor]`, `#[WarnBefore]`, `#[DisableFirst]`, `#[ThenAnonymise]`, `#[ThenDelete]`) ou en configuration.
  **EN** Retention policies per entity, as attributes (`#[KeepFor]`, `#[WarnBefore]`, `#[DisableFirst]`, `#[ThenAnonymise]`, `#[ThenDelete]`) or as configuration.
- **FR** Le parcours complet : rappels avant échéance, désactivation, période de grâce avec retour possible, puis anonymisation champ par champ ou suppression.
  **EN** The whole journey: reminders before the deadline, disabling, grace period with a way back, then field by field anonymisation or deletion.
- **FR** Mode observation (`lifecycle:report`, `--dry-run`, `DATA_LIFECYCLE_DRY_RUN`) : rien n'est écrit, aucun événement n'est émis, le rapport dit ce qui se passerait.
  **EN** Observe mode (`lifecycle:report`, `--dry-run`, `DATA_LIFECYCLE_DRY_RUN`): nothing is written, no event is sent, the report says what would happen.
- **FR** Intégration Laravel : fournisseur de services, commandes `lifecycle:run`, `lifecycle:report` et `lifecycle:install`, pilote Eloquent, trait `HasLifecycle`, middleware de signal d'activité.
  **EN** Laravel side: service provider, `lifecycle:run`, `lifecycle:report` and `lifecycle:install` commands, Eloquent driver, `HasLifecycle` trait, activity signal middleware.
- **FR** Intégration Symfony et Doctrine : bundle configurable, commandes console, pilote Doctrine ORM, abonné de signal d'activité.
  **EN** Symfony and Doctrine side: configurable bundle, console commands, Doctrine ORM driver, activity signal listener.
- **FR** Huit stratégies d'anonymisation, cinq événements (`SubjectWarned`, `SubjectDisabled`, `SubjectAnonymised`, `SubjectDeleted`, `SubjectReactivated`) et une horloge remplaçable pour les tests.
  **EN** Eight anonymisation strategies, five events (`SubjectWarned`, `SubjectDisabled`, `SubjectAnonymised`, `SubjectDeleted`, `SubjectReactivated`) and a replaceable clock for tests.

[1.0.2]: https://github.com/kaveraa/data-lifecycle/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/kaveraa/data-lifecycle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/kaveraa/data-lifecycle/releases/tag/v1.0.0
