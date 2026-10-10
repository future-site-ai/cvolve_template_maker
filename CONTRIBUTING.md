# Contributing to CVolve Template Maker

Thank you for your interest in CVolve Template Maker! 💚 Contributions of every size are welcome: bug reports, ideas, documentation, starter templates and code. Designers are just as welcome as developers.

You do not need to ask for permission to help. If something here is unclear, open an issue and we will improve this guide.

## Ways to help

- 🐛 **Report a bug**: open an [issue](https://github.com/future-site-ai/cvolve_template_maker/issues) with the steps to reproduce it, what you expected, and what happened instead. For import problems, attach the file (with no personal data) if you can.
- 💡 **Suggest a feature**: open an issue that describes the problem you want to solve, not only the solution.
- 📝 **Improve the docs**: fix typos, clarify the README, or add examples for designers.
- 🎨 **Share designs**: test the studio with your own templates and tell us what is missing.
- 🧑‍💻 **Write code**: pick an issue labelled `good first issue` or `help wanted`.

## Before you start coding

1. **Look for an existing issue** first. If there is none, open one so we can agree on the approach before you spend time on it. Small fixes (typos, obvious bugs) can go straight to a pull request.
2. **Comment on the issue** to say you are working on it, so two people do not do the same work.

## Development setup

The maker uses CVolve's shared template kit, so **you need both repositories side by side**:

```
some-folder/
├── cvolve/                  # git clone https://github.com/future-site-ai/cvolve.git
└── cvolve_template_maker/   # your fork of this repository
```

Then, as described in the [README](README.md#development):

```bash
cd cvolve_template_maker
composer install          # links ../cvolve/packages/template-kit
make local-deps           # PostgreSQL :5433, Gotenberg :3001
# create .env.local as shown in the README, then:
php bin/console doctrine:migrations:migrate -n
php bin/console app:create-user you@example.com
php -S 127.0.0.1:8090 -t public
```

## Project rules

- **Template-engine changes belong in CVolve, not here.** The sandbox, validator, renderer, importers and AI prompts come from `Cvolve\TemplateKit` in the [CVolve repository](https://github.com/future-site-ai/cvolve) (`packages/template-kit`). If your change touches them, open the pull request there. This keeps templates working the same in both apps.
- **The AI only proposes, the designer approves.** Every AI change is a diff the designer applies or discards, with a revision saved first.
- **API keys stay secret.** Designers' keys are encrypted at rest (`SecretBox`, libsodium) and are never shown or logged. Do not weaken this.
- **Designers only see their own work** (`OwnerVoter`).
- **Never commit secrets.** Keep keys in `.env.local` or `.env.docker`, which are not tracked.

## Code style

- PHP 8.3, [Symfony coding standards](https://symfony.com/doc/current/contributing/code/standards.html), 4-space indentation (see `.editorconfig`).
- Keep pull requests focused: one fix or feature per PR.
- Add or update tests for the behaviour you change.

## Checks before you open a pull request

The same checks run in GitHub Actions on every pull request:

```bash
make qa        # PHPUnit + PHPStan level 5
php bin/console lint:twig templates
php bin/console lint:container
```

## Pull request process

1. **Fork** the repository and create a branch from `main`, for example `fix/font-upload` or `feat/rtl-preview`.
2. Make your changes and commit them with clear messages, for example `Fix font declaration on asset upload`.
3. **Push** your branch and open a **pull request** against `main`.
4. In the description, explain **what** you changed and **why**, link the issue (`Closes #123`), and add screenshots for UI changes.
5. Make sure CI is green. A maintainer will review your PR, may ask for changes, and will merge it when it is ready.

Please be patient: this project is maintained in our spare time, and we try to reply within a week.

## Reporting security issues

**Do not open a public issue for security vulnerabilities**, especially anything involving stored API keys. Use GitHub's [private vulnerability reporting](https://github.com/future-site-ai/cvolve_template_maker/security/advisories/new) or email **contact@futuresite.it**.

## Code of conduct

Be kind and respectful. We want everyone to feel welcome, whatever their experience level. Harassment or abusive behaviour is not tolerated, and maintainers may remove comments or block users who break this rule.

## License

By contributing, you agree that your contributions are licensed under the project's [MIT License](LICENSE).

Thank you for helping make CVolve Template Maker better! ⭐
