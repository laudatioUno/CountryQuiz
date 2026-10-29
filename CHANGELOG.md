# Changelog

All notable changes to CountryQuiz. Versions follow [semantic versioning](https://semver.org/).
The section of a version is used as the release notes on GitHub.

## [1.0.0] – 2026-10

First version: an online exercise for the 46 countries of Europe – flags, capitals and outlines.

### Added
- **Flags**: see a flag, type the country and its capital. One question at a time with immediate feedback and the correction.
- **Outlines** in three levels:
  - *Leicht*: choose the country from 4 names
  - *Mittel*: choose the matching flag from 4 flags
  - *Schwer*: type the country
- Small states (Andorra, Liechtenstein, Monaco, San Marino, Vatican City) are shown as a map excerpt with the position marked, because their outline is too small to recognise.
- Answer options are drawn at random from neighbouring countries or similar-looking flags, in a new order every time; the same set is never shown twice in a row for a country.
- Tolerant answer checking: case, umlauts, accents, spaces and hyphens are ignored; common alternative names count (Weissrussland, Grossbritannien, Kyjiw …); typos and words that sound the same count, with the correct spelling shown (Lichtenstein, Warshau …). The name of another country never counts.
- Choice of *Kurz* (15 random countries) or *Komplett* (all 46).
- Results are stored in SQLite with time, points and duration. Evaluation at the end with every answer and the place in the ranking.
- Rankings without password, one per combination of game type, level and length; top 5 on the start page; search by name and CSV export.
- Database update page: an existing database from an earlier installation is updated with one click («Update nötig» › GO), with an automatic backup copy first.
- Version shown in the page footer.
- GitHub Actions: CI with lint, self test and a test ZIP for every pull request; release workflow that builds the ZIP and publishes it with these notes.

### Database
- Table `resultate` with the columns `spielart` (flaggen/umrisse) and `stufe` (leicht/mittel/schwer); schema version 2 (`PRAGMA user_version`).
